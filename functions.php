<?php
// === CANONICAL HOST REDIRECT (date-gated) ===
// Before CUTOVER_AT: freedev.app -> scratchnews.net (the old behaviour).
// From CUTOVER_AT on: scratchnews.net -> scratchnews.freedev.app, because the
// .net registration expires on 2027-09-07 and auto-renew is off.
// 301, preserves path + query string. Runs before anything else so it
// applies to every route, including clean-URL rewrites.
// Keep CUTOVER_AT well before the expiry: once the domain lapses, requests
// never reach this server and no code can redirect them.
define('CUTOVER_AT', '2027-08-24 00:00:00'); // UTC, two weeks before expiry
$reqHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$reqHost = preg_replace('/^www\./', '', $reqHost);
$cutoverDone = time() >= strtotime(CUTOVER_AT . ' UTC');
if ($reqHost !== '') {
    if ($cutoverDone && $reqHost === 'scratchnews.net') {
        header('Location: https://scratchnews.freedev.app' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
    if (!$cutoverDone && stripos($reqHost, 'freedev.app') !== false) {
        header('Location: https://scratchnews.net' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }
}

// === MAINTENANCE MODE (git-tracked, no File Manager needed) ===
date_default_timezone_set('Etc/GMT-3'); // GMT+3
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_UNTIL', null); // null = stays on until you flip this back to false
define('MAINTENANCE_MESSAGE', 'There was a social attack on the site and multiple people accused me (the developer) of some stuff. Don\'t worry, when this situation fades ScratchNews will be back in full swing!');
$maintenance_active = MAINTENANCE_MODE && (MAINTENANCE_UNTIL === null || time() < strtotime(MAINTENANCE_UNTIL));

// === WRITERS' CONTEST MODE (git-tracked, no File Manager needed) ===
// While true, the homepage welcome banner is replaced by the contest banner for
// EVERYONE (logged in or not) - see includes/welcome-banner.php. Flip back to
// false whenever the contest round ends.
define('CONTEST_MODE', false);

if ($maintenance_active) {
    http_response_code(503);
    header('Retry-After: 3600');
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ScratchNews - Back soon</title>
    <style>body{font-family:sans-serif;background:#1a1a1a;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;text-align:center;padding:20px}.box{max-width:480px}h1{color:#f7931e}</style>
    </head><body><div class="box">
    <h1>ScratchNews is taking a quick break</h1>
    <p><?php echo MAINTENANCE_MESSAGE; ?></p>
    </div></body></html>
    <?php
    exit;
}
// === rest of functions.php continues below ===
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/webpush.php';
require_once __DIR__ . '/version.php';

function getAllArticles(bool $includeDrafts = false): array {
    $db = getDB();
    $sql = "SELECT * FROM articles";
    if (!$includeDrafts) $sql .= " WHERE status = 'published'";
    $sql .= " ORDER BY created_at DESC";
    $result = $db->query($sql);
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get a single article by ID
function getArticleById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $article = $result->fetch_assoc();
    $stmt->close();
    return $article ?: null;
}

// A pure white or pure black inline text color, in any of the forms Quill's
// color picker writes (keyword / 3- or 6-digit hex / rgb() / rgba()).
function isWhiteOrBlackColorValue(string $value): bool {
    $v = strtolower(trim($value));
    if ($v === 'white' || $v === '#fff' || $v === '#ffffff') return true;
    if ($v === 'black' || $v === '#000' || $v === '#000000') return true;
    if (preg_match('/^rgba?\(\s*255\s*,\s*255\s*,\s*255\s*(,.*)?\)$/', $v)) return true;
    if (preg_match('/^rgba?\(\s*0\s*,\s*0\s*,\s*0\s*(,.*)?\)$/', $v)) return true;
    return false;
}

// Drops a hardcoded white/black `color:` declaration out of a style="" attribute
// (leaving any other declarations - e.g. background-color for a highlight box -
// untouched) so the theme's own text color always applies instead. Quill's Text
// Color picker lets writers pick literal white or black, which then rendered fine
// by coincidence in whichever theme it was authored in and went invisible in the
// other - this removes that specific footgun without touching other colors.
function stripWhiteBlackTextColor(string $html): string {
    return preg_replace_callback('/style\s*=\s*"([^"]*)"/i', function ($m) {
        $decls = array_filter(array_map('trim', explode(';', $m[1])), fn($d) => $d !== '');
        $kept = [];
        foreach ($decls as $decl) {
            if (preg_match('/^color\s*:\s*(.+)$/i', $decl, $cm) && isWhiteOrBlackColorValue($cm[1])) {
                continue; // drop this declaration only
            }
            $kept[] = $decl;
        }
        if (!$kept) return '';
        return 'style="' . implode('; ', $kept) . '"';
    }, $html);
}

// Allow only safe formatting tags in article content (bold, italic, headers, colors via span, etc.)
function sanitizeArticleHtml(string $html): string {
    $allowed = '<p><br><strong><b><em><i><s><strike><u><h1><h2><h3><span><ul><ol><li><blockquote><a><img>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')/i', '$1="#"', $html);
    $html = stripWhiteBlackTextColor($html);
    return $html;
}

// Scratchblocks support (0.27.1): writers wrap Scratch pseudo-code in
// [scratchblocks]...[/scratchblocks] directly in the Quill editor (same convention
// as the Scratch Wiki/Forums). Deliberately NOT converted to <pre> at save time -
// the raw bracket text stays in the DB (sanitizeArticleHtml never touches it, since
// it's plain text, not a tag) so the renderer/library version can change later with
// no migration - same reasoning as forums' renderBBCode(). Rendering to <pre
// class="blocks"> only happens at display time, in article.php, right before the
// scratchblocks.js library (loaded client-side) picks it up via renderMatching().
function renderScratchblocksMarkup(string $html): string {
    return preg_replace_callback('/\[scratchblocks\](.*?)\[\/scratchblocks\]/is', function ($m) {
        $code = $m[1];
        // Quill stores separate lines as <br> or as sibling <p>s depending on
        // Shift+Enter vs Enter - both need to become a real newline, since
        // scratchblocks' parser splits script lines on "\n".
        $code = preg_replace('/<br\s*\/?>/i', "\n", $code);
        $code = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $code);
        $code = strip_tags($code); // scratch code should be plain - drop any stray formatting
        $code = html_entity_decode($code, ENT_QUOTES, 'UTF-8'); // restore literal < > & etc.
        $code = trim($code, "\n");
        return '<pre class="blocks">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</pre>';
    }, $html);
}

// Machine translation (translateHtmlContent(), below) walks article content as plain
// text nodes and would otherwise send scratchblocks code straight through MyMemory,
// mangling the pseudo-code syntax. Swap each [scratchblocks]...[/scratchblocks]
// segment for an inert placeholder token before translation, then restore the
// original afterwards - translateHtmlContent() calls both halves of this pair itself.
function extractScratchblocksSegments(string $html): array {
    $segments = [];
    $protected = preg_replace_callback('/\[scratchblocks\](.*?)\[\/scratchblocks\]/is', function ($m) use (&$segments) {
        $token = '{{SB_' . count($segments) . '}}';
        $segments[] = $m[0];
        return $token;
    }, $html);
    return [$protected, $segments];
}
function restoreScratchblocksSegments(string $html, array $segments): string {
    foreach ($segments as $i => $original) {
        $html = str_replace('{{SB_' . $i . '}}', $original, $html);
    }
    return $html;
}

// Word count used for the v0.25 250-word minimum on reader submissions -
// counts plain text only, HTML markup stripped first so tags don't inflate it.
function countContentWords(string $html): int {
    $text = trim(strip_tags($html));
    if ($text === '') return 0;
    return count(preg_split('/\s+/u', $text));
}

function createArticle(string $title, string $summary, string $content, string $author, ?string $imageUrl = null, string $status = 'published', ?int $userId = null): int {
    $db = getDB();
    $content = sanitizeArticleHtml($content);
    $id = getNextArticleId();
    $stmt = $db->prepare("INSERT INTO articles (id, title, summary, content, author, image_url, status, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('issssssi', $id, $title, $summary, $content, $author, $imageUrl, $status, $userId);
    $stmt->execute();
    $stmt->close();
    if ($status === 'published' && $userId !== null) {
        foreach (getFollowerIds($userId) as $followerId) {
            createNotification($followerId, 'followed_user_article', $userId, '/article/' . $id, $title);
        }
    }
    return $id;
}

// Update an existing article
function updateArticle(int $id, string $title, string $summary, string $content, string $author, ?string $imageUrl = null, string $status = 'published', ?int $userId = null): bool {
    $db = getDB();
    $content = sanitizeArticleHtml($content);
    $stmt = $db->prepare("UPDATE articles SET title = ?, summary = ?, content = ?, author = ?, image_url = ?, status = ?, user_id = ? WHERE id = ?");
    $stmt->bind_param('ssssssii', $title, $summary, $content, $author, $imageUrl, $status, $userId, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// Delete an article
function deleteArticle(int $id): bool {
    $db = getDB();
    $article = getArticleById($id);
    if ($article && !empty($article['image_url'])) {
        deleteUploadedImage($article['image_url']);
    }
    $stmt = $db->prepare("DELETE FROM articles WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// autosaveArticle() creates a real articles row (status='draft') the moment an admin
// starts a new article, before Publish/Save is ever clicked. If the tab is closed
// without publishing, that row - and the ID it took from getNextArticleId() - is
// orphaned forever, which is why admin's all-statuses article count can run ahead of
// the public "published" count. Called opportunistically from admin/index.php on
// dashboard load rather than via cron, so no server-side scheduling is needed.
// updated_at is bumped automatically (ON UPDATE current_timestamp) by autosaveArticle()'s
// own UPDATE, so "untouched for $daysOld days" is a reliable staleness signal. Returns
// the number of rows deleted.
function cleanupStaleDraftArticles(int $daysOld = 14): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM articles WHERE status = 'draft' AND updated_at < (NOW() - INTERVAL ? DAY)");
    $stmt->bind_param('i', $daysOld);
    $stmt->execute();
    $ids = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
    $stmt->close();
    foreach ($ids as $staleId) {
        deleteArticle((int)$staleId);
    }
    return count($ids);
}

// "New since you were away" counters (retention feature). Each cookie holds the epoch
// timestamp of the last time the visitor DISMISSED that section's badge (see
// includes/new-since-badge.php + the click handler in includes/footer.php) - it is
// deliberately NEVER touched on ordinary page load/reload, only via that explicit
// dismiss click, so the count doesn't evaporate the instant the page is viewed
// (TODB's explicit requirement). First-ever visit (no cookie yet) just plants the
// cookie at "now" and returns 0, so nobody sees a huge fake number on their first load.
// $table is always one of our own fixed table names (never request input).
function getNewSinceCount(string $cookieName, string $table, string $statusClause = ''): int {
    if (empty($_COOKIE[$cookieName])) {
        setcookie($cookieName, (string)time(), time() + 31536000, '/');
        return 0;
    }
    $since = (int)$_COOKIE[$cookieName];
    $db = getDB();
    $sql = "SELECT COUNT(*) AS c FROM `$table` WHERE created_at > FROM_UNIXTIME(?)";
    if ($statusClause !== '') $sql .= " AND $statusClause";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $since);
    $stmt->execute();
    $count = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
}

// Soft-delete: a reader unpublishing their own article. The row (and its likes/
// comments/views/share history) is kept, just hidden from every public listing/query
// that filters on status = 'published'. An admin can restore it via admin/edit.php's
// Publish button. Requires the caller to own the article - returns false otherwise.
function unpublishArticle(int $articleId, int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE articles SET status = 'unpublished' WHERE id = ? AND user_id = ? AND status = 'published'");
    $stmt->bind_param('ii', $articleId, $userId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

// Dev-only: toggle the "featured" tag exposed via api/articles.php (v0.24.1,
// built for the MaterArc/ScratchStats partnership). Purely an API-facing flag -
// doesn't change how the article looks/behaves anywhere on-site.
function setArticleFeatured(int $articleId, bool $featured): bool {
    $db = getDB();
    $val = $featured ? 1 : 0;
    $stmt = $db->prepare("UPDATE articles SET is_featured = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $articleId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected > 0;
}

// Small helper to safely print user content
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function logVisit(string $page): void {
    $db = getDB();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $stmt = $db->prepare("INSERT INTO visits (ip_address, page, user_agent) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $ip, $page, $ua);
    $stmt->execute();
    $stmt->close();
    $db->query("DELETE FROM visits WHERE visited_at < DATE_SUB(NOW(), INTERVAL 3 DAY)");

    $stmt = $db->prepare("INSERT IGNORE INTO daily_unique_visitors (visit_date, ip_address) VALUES (CURDATE(), ?)");
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $stmt->close();
    $db->query("DELETE FROM daily_unique_visitors WHERE visit_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)");
}

function getRecentVisits(int $limit = 200, ?string $includeIp = null, ?string $excludeIp = null): array {
    $db = getDB();
    $sql = "SELECT * FROM visits WHERE 1=1";
    $params = [];
    $types = '';

    if ($includeIp !== null && $includeIp !== '') {
        $sql .= " AND ip_address = ?";
        $params[] = $includeIp;
        $types .= 's';
    }
    if ($excludeIp !== null && $excludeIp !== '') {
        $sql .= " AND ip_address != ?";
        $params[] = $excludeIp;
        $types .= 's';
    }

    $sql .= " ORDER BY id DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Plain-text export of the raw Visitor Log (admin/visits.php) - same include/exclude
// IP filters as that page. The `visits` table now keeps a rolling 3-day window
// (see logVisit()) instead of a fixed row count, so a busy day survives until
// the next morning; this export shows up to the most recent 2000 of those rows.
// Admin-only, not Head Mod (raw IPs) - matches admin/visits.php's own gate.
function buildVisitsExportText(?string $includeIp = null, ?string $excludeIp = null): string {
    $visits = getRecentVisits(2000, $includeIp, $excludeIp);

    $out = "ScratchNews Visitor Log Export\n";
    $out .= "Generated: " . gmdate('Y-m-d H:i') . " UTC\n";
    if ($includeIp) $out .= "Filter: only IP $includeIp\n";
    if ($excludeIp) $out .= "Filter: excluding IP $excludeIp\n";
    $out .= "Showing up to the most recent 2000 visits from the last 3 days (the `visits` table keeps a rolling 3-day window, not a fixed row count)\n";
    $out .= "==========================================\n\n";

    foreach ($visits as $v) {
        $out .= $v['visited_at'] . " UTC | " . $v['ip_address'] . " | " . $v['page'] . " | " . $v['user_agent'] . "\n";
    }

    return $out;
}

// ---- Time on Site ----
define('HEARTBEAT_INTERVAL_SECONDS', 15);

function recordHeartbeat(string $sessionKey, ?string $source = null, ?int $userId = null, ?string $page = null): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT last_seen FROM site_sessions WHERE session_key = ?");
    $stmt->bind_param('s', $sessionKey);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $add = HEARTBEAT_INTERVAL_SECONDS;

    if ($row) {
        $secondsSinceLast = time() - strtotime($row['last_seen']);
        if ($secondsSinceLast < HEARTBEAT_INTERVAL_SECONDS - 5) return;
        $stmt = $db->prepare("UPDATE site_sessions SET seconds_active = seconds_active + ?, last_seen = NOW(), user_id = ? WHERE session_key = ?");
        $stmt->bind_param('iis', $add, $userId, $sessionKey);
        $stmt->execute();
        $stmt->close();
    } else {
        $stmt = $db->prepare("INSERT INTO site_sessions (session_key, seconds_active, first_seen, last_seen, source, landing_page, user_id) VALUES (?, ?, NOW(), NOW(), ?, ?, ?)");
        $stmt->bind_param('sissi', $sessionKey, $add, $source, $page, $userId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $db->prepare("INSERT INTO time_totals_daily (visit_date, total_seconds) VALUES (CURDATE(), ?) ON DUPLICATE KEY UPDATE total_seconds = total_seconds + ?");
    $stmt->bind_param('ii', $add, $add);
    $stmt->execute();
    $stmt->close();

    if ($page !== null && $page !== '') {
        $stmt = $db->prepare("INSERT INTO page_time_totals (visit_date, page, total_seconds) VALUES (CURDATE(), ?, ?) ON DUPLICATE KEY UPDATE total_seconds = total_seconds + ?");
        $stmt->bind_param('sii', $page, $add, $add);
        $stmt->execute();
        $stmt->close();
    }

    $db->query("DELETE FROM site_sessions WHERE last_seen < DATE_SUB(NOW(), INTERVAL 7 DAY)");
}

function getCollectiveTimeStats(): array {
    $db = getDB();
    $allTime = $db->query("SELECT COALESCE(SUM(total_seconds), 0) AS s FROM time_totals_daily")->fetch_assoc()['s'];
    $today = $db->query("SELECT COALESCE(total_seconds, 0) AS s FROM time_totals_daily WHERE visit_date = CURDATE()")->fetch_assoc()['s'] ?? 0;
    return [
        'all_time_hours' => round($allTime / 3600, 1),
        'today_hours' => round($today / 3600, 1),
    ];
}

function getTimeOnSiteStats(int $days = 7): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT seconds_active FROM site_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? DAY) AND seconds_active > 0");
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $values = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'seconds_active');
    $stmt->close();

    sort($values);
    $count = count($values);
    $avg = $count ? array_sum($values) / $count : 0;
    $median = $count ? ($count % 2 ? $values[intdiv($count, 2)] : ($values[$count / 2 - 1] + $values[$count / 2]) / 2) : 0;
    return ['count' => $count, 'avg_seconds' => round($avg), 'median_seconds' => round($median)];
}

// Bounce = a session that only ever registered a single heartbeat (left within about
// one HEARTBEAT_INTERVAL_SECONDS window of arriving). Not a perfect measure - a visitor
// who leaves before the FIRST heartbeat fires is invisible to this entirely - but it's
// the best proxy the existing heartbeat infra can give without new client-side tracking.
function getBounceRate(int $days = 7): array {
    $db = getDB();
    $threshold = HEARTBEAT_INTERVAL_SECONDS;
    $stmt = $db->prepare("SELECT COUNT(*) AS total, SUM(seconds_active <= ?) AS bounced FROM site_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? DAY) AND seconds_active > 0");
    $stmt->bind_param('ii', $threshold, $days);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $total = (int)($row['total'] ?? 0);
    $bounced = (int)($row['bounced'] ?? 0);
    return ['total' => $total, 'bounced' => $bounced, 'rate' => $total ? round($bounced / $total * 100, 1) : 0.0];
}

// Total time-on-page across ALL visits to that URL (not just landings) - shows which
// pages people actually spend time on, independent of where their session started.
function getTimePerPageStats(int $days = 7, int $limit = 15): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT page, SUM(total_seconds) AS total_seconds FROM page_time_totals WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY page ORDER BY total_seconds DESC LIMIT ?");
    $stmt->bind_param('ii', $days, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Bounce rate broken down by the page a session actually LANDED on (site_sessions.landing_page,
// set once when that session's first heartbeat fires). Only populates going forward from the
// migration - sessions recorded before it have landing_page = NULL and are excluded.
function getLandingPageBreakdown(int $days = 7, int $limit = 15): array {
    $db = getDB();
    $threshold = HEARTBEAT_INTERVAL_SECONDS;
    $stmt = $db->prepare("SELECT landing_page, COUNT(*) AS sessions, SUM(seconds_active <= ?) AS bounced FROM site_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? DAY) AND seconds_active > 0 AND landing_page IS NOT NULL GROUP BY landing_page ORDER BY sessions DESC LIMIT ?");
    $stmt->bind_param('iii', $threshold, $days, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$r) {
        $r['sessions'] = (int)$r['sessions'];
        $r['bounced'] = (int)$r['bounced'];
        $r['bounce_rate'] = $r['sessions'] ? round($r['bounced'] / $r['sessions'] * 100, 1) : 0.0;
    }
    unset($r);
    return $rows;
}

// Bounce rate broken down by site_sessions.source (the `?src=` campaign/referral param
// already captured on every session) - reveals which promo channels (Discord, YouTube,
// Scratch, etc.) send higher- or lower-quality traffic. Sessions with no ?src= param
// group under 'direct'.
function getBounceRateBySource(int $days = 7, int $limit = 15): array {
    $db = getDB();
    $threshold = HEARTBEAT_INTERVAL_SECONDS;
    $stmt = $db->prepare("SELECT COALESCE(NULLIF(source, ''), 'direct') AS source, COUNT(*) AS sessions, SUM(seconds_active <= ?) AS bounced FROM site_sessions WHERE last_seen >= DATE_SUB(NOW(), INTERVAL ? DAY) AND seconds_active > 0 GROUP BY source ORDER BY sessions DESC LIMIT ?");
    $stmt->bind_param('iii', $threshold, $days, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$r) {
        $r['sessions'] = (int)$r['sessions'];
        $r['bounced'] = (int)$r['bounced'];
        $r['bounce_rate'] = $r['sessions'] ? round($r['bounced'] / $r['sessions'] * 100, 1) : 0.0;
    }
    unset($r);
    return $rows;
}

// ---- Stats pages: daily time-series helpers + tiny inline-SVG charts (no JS/CDN dependency) ----

// Generic "count per day for the last N days" helper, zero-filled for days with no rows.
function getDailyCounts(string $table, string $dateExpr, int $days, string $extraWhere = ''): array {
    $db = getDB();
    $rangeDays = max(0, $days - 1);
    $sql = "SELECT $dateExpr AS d, COUNT(*) AS c FROM $table WHERE $dateExpr >= DATE_SUB(CURDATE(), INTERVAL ? DAY)"
         . ($extraWhere !== '' ? " AND $extraWhere" : '') . " GROUP BY d ORDER BY d";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $rangeDays);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $out[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    foreach ($rows as $r) {
        $out[$r['d']] = (int)$r['c'];
    }
    return $out;
}

function getDailyCollectiveTimeHours(int $days): array {
    $db = getDB();
    $rangeDays = max(0, $days - 1);
    $stmt = $db->prepare("SELECT visit_date, total_seconds FROM time_totals_daily WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)");
    $stmt->bind_param('i', $rangeDays);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $out[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    foreach ($rows as $r) {
        $out[$r['visit_date']] = round($r['total_seconds'] / 3600, 1);
    }
    return $out;
}

function getDailyArticleViewCounts(int $days): array {
    $db = getDB();
    $rangeDays = max(0, $days - 1);
    $stmt = $db->prepare("SELECT view_date, view_count FROM daily_article_views WHERE view_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)");
    $stmt->bind_param('i', $rangeDays);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $out[date('Y-m-d', strtotime("-$i days"))] = 0;
    }
    foreach ($rows as $r) {
        $out[$r['view_date']] = (int)$r['view_count'];
    }
    return $out;
}

function getDailyConversionRate(int $days): array {
    $visitors = getDailyCounts('daily_unique_visitors', 'visit_date', $days);
    $signups = getDailyCounts('signup_attempts', 'DATE(created_at)', $days, 'successful = 1');
    $out = [];
    foreach ($visitors as $date => $v) {
        $s = $signups[$date] ?? 0;
        $out[$date] = $v > 0 ? round(($s / $v) * 100, 1) : 0;
    }
    return $out;
}

// Cumulative signup count by day, for the public "user count over time" graph.
function getCumulativeUserCounts(int $days): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE created_at < DATE_SUB(CURDATE(), INTERVAL ? DAY)");
    $stmt->bind_param('i', $days);
    $stmt->execute();
    $baseline = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $out = [];
    $running = $baseline;
    foreach (getDailyCounts('users', 'DATE(created_at)', $days) as $date => $count) {
        $running += $count;
        $out[$date] = $running;
    }
    return $out;
}

function getTopArticlesByViews(int $limit = 5): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, title, views FROM articles WHERE status = 'published' ORDER BY views DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getTopArticlesByLikes(int $limit = 5): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT a.id, a.title, COUNT(l.id) AS like_count FROM articles a JOIN likes l ON l.article_id = a.id WHERE a.status = 'published' GROUP BY a.id ORDER BY like_count DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getTopUsersByArticleCount(int $limit = 5): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.id, u.username, COUNT(a.id) AS article_count FROM users u JOIN articles a ON a.user_id = u.id AND a.status = 'published' WHERE u.is_banned = 0 GROUP BY u.id ORDER BY article_count DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getTopUsersByFollowerCount(int $limit = 5): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT u.id, u.username, COUNT(f.follower_id) AS follower_count FROM users u JOIN follows f ON f.followed_id = u.id WHERE u.is_banned = 0 GROUP BY u.id ORDER BY follower_count DESC LIMIT ?");
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// v0.26: Stats export. Plain-text dump of the same numbers already shown on stats.php
// (and admin/stats.php for the admin-only version) - built so TODB can download it and
// hand the whole thing to Claude in one paste instead of re-typing figures into a
// handoff by hand. Public version mirrors stats.php's public "Stats:" figures; admin
// version appends everything admin/stats.php shows (same access gate as that page -
// admin or head moderator - since head mods already see all of it on-page today).
function formatSecondsAsMinSec(int $seconds): string {
    return (int)floor($seconds / 60) . 'm ' . (int)($seconds % 60) . 's';
}

function buildPublicStatsExportText(): string {
    $days = 30;
    $userCounts = getCumulativeUserCounts($days);
    $topByViews = getTopArticlesByViews(10);
    $topByLikes = getTopArticlesByLikes(10);
    $topUsersByArticles = getTopUsersByArticleCount(10);
    $topUsersByFollowers = getTopUsersByFollowerCount(10);

    $db = getDB();
    $totalUsers = (int)($db->query("SELECT COUNT(*) AS c FROM users WHERE is_banned = 0")->fetch_assoc()['c'] ?? 0);
    $totalArticles = (int)($db->query("SELECT COUNT(*) AS c FROM articles WHERE status = 'published'")->fetch_assoc()['c'] ?? 0);
    $totalComments = getTotalCommentCount();
    $totalLikes = (int)($db->query("SELECT COUNT(*) AS c FROM likes")->fetch_assoc()['c'] ?? 0);
    $totalGroups = (int)($db->query("SELECT COUNT(*) AS c FROM `groups` WHERE status = 'active'")->fetch_assoc()['c'] ?? 0);

    $out = "ScratchNews Stats Export\n";
    $out .= "Generated: " . gmdate('Y-m-d H:i') . " UTC\n";
    $out .= "==========================================\n\n";

    $out .= "Site Totals\n-----------\n";
    $out .= "Users: " . number_format($totalUsers) . "\n";
    $out .= "Articles: " . number_format($totalArticles) . "\n";
    $out .= "Comments: " . number_format($totalComments) . "\n";
    $out .= "Likes: " . number_format($totalLikes) . "\n";
    $out .= "Groups: " . number_format($totalGroups) . "\n\n";

    $out .= "User Count, last $days days (cumulative)\n------------------------------------------\n";
    foreach ($userCounts as $date => $count) {
        $out .= "$date: " . number_format($count) . "\n";
    }
    $out .= "\n";

    $out .= "Most Popular Articles - by views\n---------------------------------\n";
    if (empty($topByViews)) { $out .= "(none yet)\n"; }
    foreach ($topByViews as $a) {
        $out .= "- " . $a['title'] . " (id " . $a['id'] . "): " . number_format((int)$a['views']) . " views\n";
    }
    $out .= "\n";

    $out .= "Most Popular Articles - by likes\n---------------------------------\n";
    if (empty($topByLikes)) { $out .= "(none yet)\n"; }
    foreach ($topByLikes as $a) {
        $out .= "- " . $a['title'] . " (id " . $a['id'] . "): " . number_format((int)$a['like_count']) . " likes\n";
    }
    $out .= "\n";

    $out .= "Most Popular Users - by articles published\n-------------------------------------------\n";
    if (empty($topUsersByArticles)) { $out .= "(none yet)\n"; }
    foreach ($topUsersByArticles as $u) {
        $out .= "- @" . $u['username'] . ": " . number_format((int)$u['article_count']) . " articles\n";
    }
    $out .= "\n";

    $out .= "Most Popular Users - by followers\n----------------------------------\n";
    if (empty($topUsersByFollowers)) { $out .= "(none yet)\n"; }
    foreach ($topUsersByFollowers as $u) {
        $out .= "- @" . $u['username'] . ": " . number_format((int)$u['follower_count']) . " followers\n";
    }

    return $out;
}

function buildAdminStatsExportText(): string {
    $days = 30;
    $db = getDB();

    $daily = $db->query("SELECT visit_date, COUNT(*) AS unique_visitors FROM daily_unique_visitors GROUP BY visit_date ORDER BY visit_date DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
    $daily = array_reverse($daily);
    $totalUniqueIps = (int)($db->query("SELECT COUNT(DISTINCT ip_address) AS c FROM daily_unique_visitors")->fetch_assoc()['c'] ?? 0);
    $totalSignups = (int)($db->query("SELECT COUNT(DISTINCT ip) AS c FROM signup_attempts WHERE successful = 1")->fetch_assoc()['c'] ?? 0);
    $conversionRate = $totalUniqueIps > 0 ? round(($totalSignups / $totalUniqueIps) * 100, 2) : 0;
    $ct = getCollectiveTimeStats();
    $tos = getTimeOnSiteStats(7);
    $bounce = getBounceRate(7);
    $pageTime = getTimePerPageStats(7);
    $landingPages = getLandingPageBreakdown(7);
    $sourceBounce = getBounceRateBySource(7);
    $engagementSeries = [
        'Article views' => getDailyArticleViewCounts($days),
        'Likes' => getDailyCounts('likes', 'DATE(created_at)', $days),
        'Comments' => getDailyCountsMulti(['comments', 'group_comments', 'profile_comments'], 'DATE(created_at)', $days),
        'Shared-link clicks' => getDailyCounts('share_clicks', 'DATE(created_at)', $days),
    ];

    $out = buildPublicStatsExportText();
    $out .= "\n\n==========================================\n";
    $out .= "ADMIN STATS (not shown on the public stats page)\n";
    $out .= "==========================================\n\n";

    $out .= "Conversion rate (all-time): $conversionRate%\n";
    $out .= "Signups / unique visitor IPs (all-time, within retention window): $totalSignups / $totalUniqueIps\n";
    $out .= "Collective time on site (all-time): " . number_format($ct['all_time_hours'], 1) . "h\n";
    $out .= "Collective time on site (today): " . number_format($ct['today_hours'], 1) . "h\n\n";

    $out .= "Time on Site, last 7 days\n-------------------------\n";
    if ($tos['count'] > 0) {
        $out .= "Average: " . formatSecondsAsMinSec($tos['avg_seconds']) . " | Median: " . formatSecondsAsMinSec($tos['median_seconds']) . " | Sessions counted: " . $tos['count'] . "\n\n";
    } else {
        $out .= "(no session data yet)\n\n";
    }

    $out .= "Bounce Rate, last 7 days\n-------------------------\n";
    if ($bounce['total'] > 0) {
        $out .= $bounce['rate'] . "% bounced (" . $bounce['bounced'] . " of " . $bounce['total'] . " sessions)\n\n";
    } else {
        $out .= "(no session data yet)\n\n";
    }

    $out .= "Time per Page, last 7 days\n---------------------------\n";
    if ($pageTime) {
        foreach ($pageTime as $p) {
            $out .= "- " . $p['page'] . ": " . formatSecondsAsMinSec((int)$p['total_seconds']) . "\n";
        }
    } else {
        $out .= "(no data yet)\n";
    }
    $out .= "\n";

    $out .= "Bounce Rate by Landing Page, last 7 days\n-----------------------------------------\n";
    if ($landingPages) {
        foreach ($landingPages as $lp) {
            $out .= "- " . $lp['landing_page'] . ": " . $lp['sessions'] . " sessions, " . $lp['bounce_rate'] . "% bounce\n";
        }
    } else {
        $out .= "(no data yet)\n";
    }
    $out .= "\n";

    $out .= "Bounce Rate by Traffic Source, last 7 days\n-------------------------------------------\n";
    if ($sourceBounce) {
        foreach ($sourceBounce as $sb) {
            $out .= "- " . $sb['source'] . ": " . $sb['sessions'] . " sessions, " . $sb['bounce_rate'] . "% bounce\n";
        }
    } else {
        $out .= "(no data yet)\n";
    }
    $out .= "\n";

    $out .= "Engagement per day, last $days days\n------------------------------------\n";
    foreach ($engagementSeries as $label => $series) {
        $out .= "$label: " . implode(', ', array_map(fn($d, $v) => "$d=$v", array_keys($series), $series)) . "\n";
    }
    $out .= "\n";

    $out .= "Daily Unique Visitors, last 30 days\n------------------------------------\n";
    foreach ($daily as $d) {
        $out .= "- " . $d['visit_date'] . ": " . (int)$d['unique_visitors'] . "\n";
    }

    return $out;
}

// Renders a small bar chart from a 'label' => value array. $opts['highlight_last']
// draws the final bar in a lighter color (used for "today", since it's always a
// partial day of data).
function renderBarChartSvg(array $data, array $opts = []): string {
    $width = $opts['width'] ?? 700;
    $height = $opts['height'] ?? 160;
    $color = $opts['color'] ?? '#ff8c1a';
    $highlightColor = $opts['highlight_color'] ?? '#ffcf8a';
    $highlightLast = $opts['highlight_last'] ?? false;

    $values = array_values($data);
    $labels = array_keys($data);
    $n = count($values);
    if ($n === 0) return '<p style="opacity:0.6;">No data yet.</p>';
    $labelEvery = $opts['label_every'] ?? max(1, (int)ceil($n / 10));
    $max = max(max($values), 1);
    $padBottom = 22;
    $padTop = 8;
    $padX = 20;
    $drawWidth = $width - $padX * 2;
    $barGap = 3;
    $barWidth = max(1, ($drawWidth - ($n - 1) * $barGap) / $n);

    $bars = '';
    foreach ($values as $i => $v) {
        $barHeight = ($height - $padBottom - $padTop) * ($v / $max);
        $x = $padX + $i * ($barWidth + $barGap);
        $y = $height - $padBottom - $barHeight;
        $fill = ($highlightLast && $i === $n - 1) ? $highlightColor : $color;
        $bars .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($barWidth, 1) . '" height="' . round($barHeight, 1) . '" fill="' . $fill . '"><title>' . e($labels[$i]) . ': ' . e($v) . '</title></rect>';
    }
    $ticks = '';
    foreach ($labels as $i => $label) {
        if ($i % $labelEvery !== 0 && $i !== $n - 1) continue;
        $x = $padX + $i * ($barWidth + $barGap) + $barWidth / 2;
        $ticks .= '<text x="' . round($x, 1) . '" y="' . ($height - 6) . '" font-size="9" fill="currentColor" text-anchor="middle" opacity="0.65">' . e(substr($label, 5)) . '</text>';
    }
    return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg" preserveAspectRatio="none" style="width:100%;height:' . $height . 'px;">' . $bars . $ticks . '</svg>';
}

// Renders a small line chart from a 'label' => value array. $opts['highlight_last']
// marks the final point in red (used for "today", since it's always partial).
function renderLineChartSvg(array $data, array $opts = []): string {
    $width = $opts['width'] ?? 700;
    $height = $opts['height'] ?? 160;
    $color = $opts['color'] ?? '#ff8c1a';
    $highlightLast = $opts['highlight_last'] ?? false;

    $values = array_values($data);
    $labels = array_keys($data);
    $n = count($values);
    if ($n === 0) return '<p style="opacity:0.6;">No data yet.</p>';
    $labelEvery = $opts['label_every'] ?? max(1, (int)ceil($n / 10));
    $max = max(max($values), 1);
    $min = min(0, min($values));
    $range = max($max - $min, 1);
    $padBottom = 22;
    $padTop = 8;
    $padX = 20;
    $drawWidth = $width - $padX * 2;
    $stepX = $n > 1 ? $drawWidth / ($n - 1) : 0;

    $coords = [];
    foreach ($values as $i => $v) {
        $x = $padX + $stepX * $i;
        $y = $padTop + ($height - $padBottom - $padTop) * (1 - (($v - $min) / $range));
        $coords[] = [round($x, 1), round($y, 1)];
    }
    $polyline = '<polyline points="' . implode(' ', array_map(fn($c) => $c[0] . ',' . $c[1], $coords)) . '" fill="none" stroke="' . $color . '" stroke-width="2"/>';

    $dots = '';
    foreach ($coords as $i => [$x, $y]) {
        $isLast = $i === $n - 1;
        $r = ($isLast && $highlightLast) ? 4 : 2.5;
        $fill = ($isLast && $highlightLast) ? '#ff3a3a' : $color;
        $dots .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="' . $fill . '"><title>' . e($labels[$i]) . ': ' . e($values[$i]) . '</title></circle>';
    }
    $ticks = '';
    foreach ($labels as $i => $label) {
        if ($i % $labelEvery !== 0 && $i !== $n - 1) continue;
        $x = $padX + $stepX * $i;
        $ticks .= '<text x="' . round($x, 1) . '" y="' . ($height - 6) . '" font-size="9" fill="currentColor" text-anchor="middle" opacity="0.65">' . e(substr($label, 5)) . '</text>';
    }
    return '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg" preserveAspectRatio="none" style="width:100%;height:' . $height . 'px;">' . $polyline . $dots . $ticks . '</svg>';
}

// Renders several lines on one chart with a legend. $series: 'name' => ['label' => value, ...],
// all series must share the same set of labels/order (build them from the same $days window).
function renderMultiLineChartSvg(array $series, array $opts = []): string {
    $width = $opts['width'] ?? 700;
    $height = $opts['height'] ?? 220;
    $colors = $opts['colors'] ?? ['#ff8c1a', '#0084ff', '#8000ff', '#00b368'];

    $first = reset($series);
    $labels = $first ? array_keys($first) : [];
    $n = count($labels);
    if ($n === 0) return '<p style="opacity:0.6;">No data yet.</p>';
    $labelEvery = $opts['label_every'] ?? max(1, (int)ceil($n / 10));
    $allValues = [];
    foreach ($series as $vals) $allValues = array_merge($allValues, array_values($vals));
    $max = max(max($allValues), 1);
    $padBottom = 22;
    $padTop = 8;
    $padX = 20;
    $drawWidth = $width - $padX * 2;
    $stepX = $n > 1 ? $drawWidth / ($n - 1) : 0;

    $polylines = '';
    $legend = '';
    $i = 0;
    foreach ($series as $name => $vals) {
        $color = $colors[$i % count($colors)];
        $points = [];
        foreach (array_values($vals) as $idx => $v) {
            $x = $padX + $stepX * $idx;
            $y = $padTop + ($height - $padBottom - $padTop) * (1 - ($v / $max));
            $points[] = round($x, 1) . ',' . round($y, 1);
        }
        $polylines .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $color . '" stroke-width="2"/>';
        $legend .= '<span class="chart-legend-item"><span class="chart-legend-swatch" style="background:' . $color . '"></span>' . e($name) . '</span>';
        $i++;
    }
    $ticks = '';
    foreach ($labels as $idx => $label) {
        if ($idx % $labelEvery !== 0 && $idx !== $n - 1) continue;
        $x = $padX + $stepX * $idx;
        $ticks .= '<text x="' . round($x, 1) . '" y="' . ($height - 6) . '" font-size="9" fill="currentColor" text-anchor="middle" opacity="0.65">' . e(substr($label, 5)) . '</text>';
    }
    $svg = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" class="chart-svg" preserveAspectRatio="none" style="width:100%;height:' . $height . 'px;">' . $polylines . $ticks . '</svg>';
    return '<div class="chart-legend">' . $legend . '</div>' . $svg;
}

// ---- Reader accounts ----
function createUser(string $username, ?string $email, string $password, ?string $scratchUsername = null, ?string $phoneNumber = null) {
    $db = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $emailValue = ($email === '' ? null : $email);
    $verifiedAt = $scratchUsername !== null ? date('Y-m-d H:i:s') : null;
    // Phone numbers verify instantly on signup (no admin review) - see roadmap history for
    // why this changed from the original manual-approval design.
    $phoneVerifiedAt = $phoneNumber !== null ? date('Y-m-d H:i:s') : null;
    try {
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, scratch_username, scratch_verified_at, phone_number, phone_verified_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssss', $username, $emailValue, $hash, $scratchUsername, $verifiedAt, $phoneNumber, $phoneVerifiedAt);
        $stmt->execute();
        $id = $db->insert_id;
        $stmt->close();
        notifyAdmins('admin_new_account', $id, '/@' . $username);
        return $id;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) return 'duplicate';
        throw $e;
    }
}

function getUserByUsername(string $username): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function usernameExists(string $username): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM users WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $exists;
}

function changePassword(int $userId, string $newPassword): void {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $userId);
    $stmt->execute();
    $stmt->close();
}

// ---- Word list (backup login / recovery) ----
const WORD_LIST_DICTIONARY = [
    'apple','anchor','arrow','autumn','banjo','basket','beacon','beetle','birch','blanket',
    'blossom','boulder','bramble','breeze','bridge','bristle','bronze','brook','bucket','cabin',
    'candle','canyon','cedar','chalk','cherry','chimney','cinder','clover','coast','comet',
    'compass','copper','coral','cotton','cradle','crater','cricket','crimson','crystal','current',
    'daisy','delta','desert','dewdrop','dolphin','dragon','driftwood','ember','emerald','falcon',
    'feather','fern','fjord','forest','fossil','fountain','garnet','ginger','glacier','glimmer',
    'granite','grove','harbor','hazel','heather','hollow','honey','hornet','hummingbird','ivory',
    'ivy','jasper','juniper','kestrel','lagoon','lantern','larch','lavender','leaf','lichen',
    'lighthouse','lilac','linen','lotus','lumber','lynx','magma','mallow','maple','marble',
    'marigold','marsh','meadow','meridian','mesa','mineral','mint','mirror','moss','mountain',
    'nectar','nettle','nutmeg','oak','oasis','obsidian','opal','orbit','orchard','osprey',
    'otter','paddle','palm','pebble','pepper','pigeon','pine','plateau','plum','poppy',
    'prairie','pumpkin','quail','quartz','quiver','raven','reed','ridge','river','robin',
    'rocket','rosemary','rust','saffron','sage','sail','sandstone','sapling','sequoia','shadow',
    'shale','shamrock','shell','shoreline','sierra','silver','sparrow','sprout','spruce','starling',
    'stone','stream','summit','sunrise','swallow','sycamore','tangerine','thistle','thunder','timber',
    'tundra','turquoise','umber','valley','velvet','violet','walnut','warbler','waterfall','willow',
    'wisteria','wren','yarrow','zephyr',
];

function normalizeWordListInput(string $input): string {
    $words = preg_split('/[\s,]+/', strtolower(trim($input)), -1, PREG_SPLIT_NO_EMPTY);
    return implode(' ', $words);
}

function generateWordList(int $userId, int $wordCount = 8): string {
    if ($wordCount < 4 || $wordCount > count(WORD_LIST_DICTIONARY)) $wordCount = 8;
    $keys = array_rand(WORD_LIST_DICTIONARY, $wordCount);
    if (!is_array($keys)) $keys = [$keys];
    shuffle($keys);
    $words = array_map(fn($k) => WORD_LIST_DICTIONARY[$k], $keys);
    $plain = implode(' ', $words);
    $hash = password_hash($plain, PASSWORD_DEFAULT);

    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET word_list_hash = ?, word_list_generated_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $hash, $userId);
    $stmt->execute();
    $stmt->close();

    return $plain;
}

function verifyWordList(string $username, string $input): ?array {
    $user = getUserByUsername($username);
    if (!$user || empty($user['word_list_hash'])) return null;
    $normalized = normalizeWordListInput($input);
    if ($normalized === '' || !password_verify($normalized, $user['word_list_hash'])) return null;
    return $user;
}

function updateUserIp(int $userId, string $ip): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET ip_address = ? WHERE id = ?");
    $stmt->bind_param('si', $ip, $userId);
    $stmt->execute();
    $stmt->close();
    logUserIp($userId, $ip);
}

// ---- IP login history (Related Accounts network detection) ----
function logUserIp(int $userId, string $ip): void {
    if ($ip === '' || $ip === '0.0.0.0' || $ip === 'unknown') return;
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO ip_login_history (user_id, ip_address) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE last_seen_at = NOW()");
    $stmt->bind_param('is', $userId, $ip);
    $stmt->execute();
    $stmt->close();
}

// Given a username, walks the shared-IP graph transitively (any account that
// ever logged in from an IP any other account in the network ever used) and
// returns the connected accounts plus which IP(s) link each one back in.
function getRelatedAccounts(string $username): ?array {
    $root = getUserByUsername($username);
    if (!$root) return null;

    $db = getDB();
    $visitedUserIds = [(int)$root['id'] => true];
    $visitedIps = [];
    $linkedByIp = []; // user_id => [ip => true]
    $queue = [(int)$root['id']];

    while ($queue) {
        $uid = array_shift($queue);
        $stmt = $db->prepare("SELECT ip_address FROM ip_login_history WHERE user_id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $ips = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'ip_address');
        $stmt->close();

        foreach ($ips as $ip) {
            if (isset($visitedIps[$ip])) continue;
            $visitedIps[$ip] = true;

            $stmt2 = $db->prepare("SELECT DISTINCT user_id FROM ip_login_history WHERE ip_address = ?");
            $stmt2->bind_param('s', $ip);
            $stmt2->execute();
            $otherIds = array_column($stmt2->get_result()->fetch_all(MYSQLI_ASSOC), 'user_id');
            $stmt2->close();

            foreach ($otherIds as $otherId) {
                $otherId = (int)$otherId;
                $linkedByIp[$otherId][$ip] = true;
                if (!isset($visitedUserIds[$otherId])) {
                    $visitedUserIds[$otherId] = true;
                    $queue[] = $otherId;
                }
            }
        }
    }

    unset($visitedUserIds[(int)$root['id']]);
    $networkIds = array_keys($visitedUserIds);
    $network = [];
    if ($networkIds) {
        $placeholders = implode(',', array_fill(0, count($networkIds), '?'));
        $types = str_repeat('i', count($networkIds));
        $stmt3 = $db->prepare("SELECT id, username, is_banned, is_admin, is_moderator, created_at FROM users WHERE id IN ($placeholders)");
        $stmt3->bind_param($types, ...$networkIds);
        $stmt3->execute();
        $rows = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt3->close();
        foreach ($rows as $row) {
            $row['shared_ips'] = array_keys($linkedByIp[(int)$row['id']] ?? []);
            $network[] = $row;
        }
        usort($network, fn($a, $b) => strcasecmp($a['username'], $b['username']));
    }

    return ['root' => $root, 'network' => $network];
}

// ---- Suspicious IP flagging (phone verification fallback) ----
function isSuspiciousIp(string $ip): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM suspicious_ips WHERE ip_address = ?");
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row !== null;
}

function getSuspiciousIps(): array {
    $db = getDB();
    return $db->query("SELECT * FROM suspicious_ips ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
}

function addSuspiciousIp(string $ip, string $note = ''): bool {
    $db = getDB();
    try {
        $stmt = $db->prepare("INSERT INTO suspicious_ips (ip_address, note) VALUES (?, ?)");
        $stmt->bind_param('ss', $ip, $note);
        $stmt->execute();
        $stmt->close();
        return true;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) return false;
        throw $e;
    }
}

function removeSuspiciousIp(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM suspicious_ips WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

// ---- Public form rate limiting (contact.php / feedback.php - added after the
// Sep 2026 spam floods, where one anonymous IP posted ~50x in a row on each
// form with nothing to stop it). Per-IP, per-form, sliding window - independent
// of the suspicious_ips flagging above, which is for the phone-verification
// fallback, not form spam. $form is a short fixed tag ('contact' / 'feedback'),
// not user input.
const FORM_RATE_LIMIT_MAX = 5;         // max submissions per window
const FORM_RATE_LIMIT_WINDOW_MIN = 60; // window length, minutes

function isFormRateLimited(string $form, string $ip): bool {
    $db = getDB();
    $window = FORM_RATE_LIMIT_WINDOW_MIN;
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM form_submissions WHERE form = ? AND ip_address = ? AND submitted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->bind_param('ssi', $form, $ip, $window);
    $stmt->execute();
    $cnt = (int)($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();
    return $cnt >= FORM_RATE_LIMIT_MAX;
}

// Call only after a submission has actually been accepted/recorded (i.e. after
// submitContactMessage()/submitFeedback() succeed) - not on validation failures,
// so a genuine typo-and-retry doesn't eat into the same IP's budget.
function recordFormSubmission(string $form, string $ip): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO form_submissions (form, ip_address) VALUES (?, ?)");
    $stmt->bind_param('ss', $form, $ip);
    $stmt->execute();
    $stmt->close();
    // Table only needs to hold one window's worth of rows - keep it trimmed.
    $db->query("DELETE FROM form_submissions WHERE submitted_at < DATE_SUB(NOW(), INTERVAL " . FORM_RATE_LIMIT_WINDOW_MIN . " MINUTE)");
}

// ---- Country calling code detection (prefills the phone verification field) ----
const COUNTRY_CALLING_CODES = [
    'US'=>'1','CA'=>'1','GB'=>'44','IE'=>'353','FR'=>'33','DE'=>'49','ES'=>'34','PT'=>'351','IT'=>'39',
    'NL'=>'31','BE'=>'32','LU'=>'352','CH'=>'41','AT'=>'43','SE'=>'46','NO'=>'47','DK'=>'45','FI'=>'358',
    'IS'=>'354','PL'=>'48','CZ'=>'420','SK'=>'421','HU'=>'36','RO'=>'40','BG'=>'359','GR'=>'30','TR'=>'90',
    'RU'=>'7','UA'=>'380','BY'=>'375','MD'=>'373','LT'=>'370','LV'=>'371','EE'=>'372','HR'=>'385','SI'=>'386',
    'RS'=>'381','BA'=>'387','MK'=>'389','AL'=>'355','ME'=>'382','XK'=>'383','MT'=>'356','CY'=>'357',
    'AU'=>'61','NZ'=>'64','JP'=>'81','KR'=>'82','CN'=>'86','HK'=>'852','TW'=>'886','SG'=>'65','MY'=>'60',
    'TH'=>'66','VN'=>'84','PH'=>'63','ID'=>'62','IN'=>'91','PK'=>'92','BD'=>'880','LK'=>'94','NP'=>'977',
    'AE'=>'971','SA'=>'966','IL'=>'972','EG'=>'20','ZA'=>'27','NG'=>'234','KE'=>'254','GH'=>'233','MA'=>'212',
    'DZ'=>'213','TN'=>'216','BR'=>'55','MX'=>'52','AR'=>'54','CL'=>'56','CO'=>'57','PE'=>'51','VE'=>'58',
    'EC'=>'593','UY'=>'598','PY'=>'595','BO'=>'591','CR'=>'506','PA'=>'507','DO'=>'1','JM'=>'1','TT'=>'1',
];

function getCountryCallingCode(string $ip): string {
    $ch = curl_init("http://ip-api.com/json/" . urlencode($ip) . "?fields=countryCode");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) return '+';
    $data = json_decode($response, true);
    $countryCode = $data['countryCode'] ?? null;
    if ($countryCode === null || !isset(COUNTRY_CALLING_CODES[$countryCode])) return '+';
    return '+' . COUNTRY_CALLING_CODES[$countryCode];
}

// ---- Phone verification (manual admin approval, only for flagged IPs) ----
function isPhoneNumberLinked(string $phoneNumber): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE phone_number = ?");
    $stmt->bind_param('s', $phoneNumber);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row !== null;
}

function isPhoneVerificationPending(int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT phone_number, phone_verified_at FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row && $row['phone_number'] !== null && $row['phone_verified_at'] === null;
}

function getPendingPhoneVerifications(): array {
    $db = getDB();
    return $db->query("SELECT id, username, phone_number, created_at FROM users WHERE phone_number IS NOT NULL AND phone_verified_at IS NULL ORDER BY created_at ASC")->fetch_all(MYSQLI_ASSOC);
}

function approvePhoneVerification(int $userId): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET phone_verified_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

// ---- Scratch follower verification ----
function generateScratchVerifyCode(): string {
    return 'SN-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

function scratchApiGet(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ScratchNews-Verification/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) return null;
    $data = json_decode($response, true);
    return is_array($data) ? $data : null;
}

// Like scratchApiGet() but returns the raw response body instead of JSON-decoding it.
// Needed for endpoints like the profile-comments site-api, which returns HTML, not JSON.
function scratchApiGetRaw(string $url): ?string {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) return null;
    return $response;
}

// Scans the verification project's comments for one containing $code and
// returns the commenter's Scratch username, or null if not found yet.
function findScratchCommentAuthor(string $ownerUsername, string $projectId, string $code): ?string {
    $offset = 0;
    do {
        $page = scratchApiGet("https://api.scratch.mit.edu/users/" . rawurlencode($ownerUsername) . "/projects/" . rawurlencode($projectId) . "/comments/?limit=40&offset=$offset");
        if ($page === null) return null;
        foreach ($page as $comment) {
            if (!empty($comment['content']) && stripos($comment['content'], $code) !== false) {
                return $comment['author']['username'] ?? null;
            }
        }
        $offset += 40;
    } while (count($page) === 40 && $offset < 400);
    return null;
}

// Builds the exact text a user must comment on their OWN Scratch profile to verify via
// Comment Auth, with their chosen ScratchNews username filled in. Deliberately avoids
// the literal site name and any "follow me"/self-promo phrasing - Scratch's automated
// comment filter mutes accounts for posting the site's name, so the old wording
// (which spelled it out twice) made verification itself trigger a mute. The username
// alone still gives this enough uniqueness to match reliably.
function buildCommentAuthText(string $scratchNewsUsername): string {
    return "Account verification - ref ($scratchNewsUsername). More info: https://scratch.mit.edu/projects/1368284445/";
}

// Comment Auth: scans $scratchUsername's OWN profile comments (unofficial site-api,
// paginated via ?page=N, unauthenticated) for one containing $expectedText. Unlike
// findScratchCommentAuthor() above, this doesn't use a shared verification project and
// doesn't require a follow - following is only suggested inside the comment text itself.
//
// NOTE: this endpoint returns raw HTML (a <body> full of <li class="top-level-reply">
// / <li class="reply"> nodes, comment text inside <div class="content">), NOT JSON -
// unlike the api.scratch.mit.edu endpoints used elsewhere in this file. Must use
// scratchApiGetRaw() + DOMDocument/XPath here, not scratchApiGet().
function findScratchProfileComment(string $scratchUsername, string $expectedText): bool {
    $normalizedExpected = preg_replace('/\s+/', ' ', trim($expectedText));
    $page = 1;
    do {
        $html = scratchApiGetRaw("https://scratch.mit.edu/site-api/comments/user/" . rawurlencode($scratchUsername) . "/?page=$page");
        if ($html === null) return false;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query("//div[@class='content']");

        if ($nodes === false || $nodes->length === 0) return false; // no comments left / end of pages

        foreach ($nodes as $node) {
            $text = preg_replace('/\s+/', ' ', trim($node->textContent));
            if (stripos($text, $normalizedExpected) !== false) return true;
        }
        $page++;
    } while ($page <= 10);
    return false;
}

// Checks whether $username is in $targetUsername's follower list.
function scratchUserFollows(string $username, string $targetUsername): bool {
    $offset = 0;
    do {
        $page = scratchApiGet("https://api.scratch.mit.edu/users/" . rawurlencode($targetUsername) . "/followers/?limit=40&offset=$offset");
        if ($page === null) return false;
        foreach ($page as $follower) {
            if (isset($follower['username']) && strcasecmp($follower['username'], $username) === 0) return true;
        }
        $offset += 40;
    } while (count($page) === 40 && $offset < 2000);
    return false;
}

function isScratchUsernameLinked(string $scratchUsername): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE scratch_username = ?");
    $stmt->bind_param('s', $scratchUsername);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

function getScratchLinkedUsers(): array {
    $db = getDB();
    $result = $db->query("SELECT id, username, scratch_username, scratch_verified_at FROM users WHERE scratch_username IS NOT NULL ORDER BY scratch_verified_at DESC");
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Clears scratch_username/scratch_verified_at for a user so that Scratch username can be
// re-verified (by them or anyone else). Does not delete or ban the account. Returns the
// Scratch username that was unlinked, or null if the user had none linked.
function unlinkScratchUsername(int $userId): ?string {
    $db = getDB();
    $stmt = $db->prepare("SELECT scratch_username FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || $row['scratch_username'] === null) return null;

    $stmt = $db->prepare("UPDATE users SET scratch_username = NULL, scratch_verified_at = NULL WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
    return $row['scratch_username'];
}

// Links a Scratch account to an already-registered, logged-in user (as opposed to the
// registration-time flow in verify-scratch.php, which stores the pending username in
// session until the account is created).
function linkScratchToUser(int $userId, string $scratchUsername): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET scratch_username = ?, scratch_verified_at = NOW() WHERE id = ?");
    $stmt->bind_param('si', $scratchUsername, $userId);
    $stmt->execute();
    $stmt->close();
}

// True if the user has verified through any real method: Scratch follow, phone, or a
// grandfathered email verification from before that flow was removed.
function isUserVerified(array $user): bool {
    return !empty($user['scratch_verified_at'])
        || !empty($user['phone_verified_at'])
        || (int)($user['email_verified'] ?? 0) === 1;
}

// ---- Ranks ----
// Writer/Featured Writer are computed live from article count (no stored flag, so they
// can never go stale relative to actual article authorship). Fan and Moderator are
// stored flags granted manually by an admin (no Ko-fi webhook exists to automate Fan;
// Moderator is a trust-based promotion, donation optional).
function isModerator(array $user): bool {
    return !empty($user['is_admin']) || !empty($user['is_moderator']);
}

function setUserFan(int $userId, bool $isFan): void {
    $db = getDB();
    $val = $isFan ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET is_fan = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
}

function setUserModerator(int $userId, bool $isModerator): void {
    $db = getDB();
    $val = $isModerator ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET is_moderator = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
}

// v0.25.2: Head Moderator - a promotion layered on top of standard Moderator.
// Granting it also grants the base is_moderator flag (a Head Mod is always a
// Moderator too); revoking it leaves the base Moderator flag untouched.
// Gets its own rank badge/color and extra panel access (see moderator/auth.php).
function setUserHeadModerator(int $userId, bool $isHeadModerator): void {
    $db = getDB();
    $val = $isHeadModerator ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET is_head_moderator = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
    if ($isHeadModerator) {
        setUserModerator($userId, true);
    }
}

// Dev/Admin power. Only callable from admin/users.php (already admin-gated).
// Refuses to remove the last remaining admin so the site can't be locked out.
// Returns 'ok' or 'last_admin'.
function setUserAdmin(int $userId, bool $isAdmin): string {
    $db = getDB();
    if (!$isAdmin) {
        $res = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 1 AND id <> " . (int)$userId);
        $others = $res ? (int)$res->fetch_row()[0] : 0;
        if ($others < 1) return 'last_admin';
    }
    $val = $isAdmin ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
    if ($isAdmin) {
        setUserModerator($userId, true);
    }
    return 'ok';
}

// v0.25.2: Featured Users - admin-granted flag (reuses the same star-toggle
// pattern as setArticleFeatured()/admin/index.php). All of a featured user's
// published articles automatically count as Featured (see getFeaturedArticles()
// and getTrendingHeroArticles() below) - no per-article toggling needed for them.
function setUserFeaturedUser(int $userId, bool $isFeatured): void {
    $db = getDB();
    $val = $isFeatured ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET is_featured_user = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
}

// v0.27: Writers' Contest badges - manually toggled for now (mirrors the Featured
// User star-toggle pattern) until the Writers' Contest feature itself defines how
// entrants get flagged; these two setters are the placeholder mechanism.
function setUserContestWriter(int $userId, bool $isContestWriter): void {
    $db = getDB();
    $val = $isContestWriter ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET is_contest_writer = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
}

// Writers' Contest: the fixed list of 15 Scratchers writers can choose to write
// about this round (popular + animators + underrated/new). Edit this array directly
// for future contest rounds - nothing else needs to change.
const CONTEST_SCRATCHERS = [
    'griffpatch', 'TimMcCool', '-Katana_Gaming-', 'Official_DarkFlame', 'Coltroc',
    '1BellaPup', 'BenjaminWins11', 'TheMaskedEclipse', 'IncognitoOrange', 'Draiveri',
    'Vryell', 'SkyCedar', 'Zolk2', 'TheEggyHippo', 'TheNotableMan',
];

// The ScratchNews account (if any) a given Scratcher from CONTEST_SCRATCHERS has
// made - their scratch_username is exactly the CONTEST_SCRATCHERS slug, verified via
// Comment Auth at registration (see register-contest.php). Used both to link the
// mention banner to their profile and to know where to send a mention notification.
function getContestScratcherAccount(string $scratcher): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, username FROM users WHERE scratch_username = ? AND is_contest_scratcher = 1 LIMIT 1");
    $stmt->bind_param('s', $scratcher);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Admin-only (not Head Mods) view of every Contest account (Writer or Scratcher) -
// so TODB can personally reach someone by phone number if they lose their account.
// Phone numbers are collected, not SMS-verified (same as the existing suspicious-IP
// signup flow) - this is a recovery contact channel, not an identity check.
function getContestAccounts(): array {
    $db = getDB();
    return $db->query("SELECT id, username, scratch_username, phone_number, is_contest_writer, is_contest_scratcher, created_at FROM users WHERE is_contest_writer = 1 OR is_contest_scratcher = 1 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
}

// v0.27: Badge Redesign. All rank badges now render as clickable icons (see
// assets/ranks/) instead of colored text chips. Central registry below -
// slug => definition. 'icon' is a full path under /assets/. 'group'/'order' link
// tiered badges together (the 5 Writer tiers, the 4 Share tiers) so the
// click-through modal can draw a full tier-progression row. 'description' is
// TODB's copy to write per-badge; PLACEHOLDER text below until he supplies real
// copy - just swap the strings, nothing else needs to change.
function getBadgeDefinitions(): array {
    return [
        'user'               => ['label' => 'User',               'icon' => '/assets/ranks/rank-user.svg',               'group' => null,    'order' => 0, 'description' => 'Oh, look who made a ScratchNews account! Everyone starts here.'],
        'writer'             => ['label' => 'Writer',              'icon' => '/assets/ranks/rank-writer.svg',              'group' => 'writer', 'order' => 1, 'description' => 'Write 1 article and get it approved. Damn this guy knows how to write!'],
        'dedicated-writer'   => ['label' => 'Dedicated Writer',    'icon' => '/assets/ranks/rank-dedicated-writer.svg',    'group' => 'writer', 'order' => 2, 'description' => 'Write 3 articles and get them approved. Okay looks like you actually like ScratchNews...'],
        'featured-writer'    => ['label' => 'Featured Writer',     'icon' => '/assets/ranks/rank-featured-writer.svg',     'group' => 'writer', 'order' => 3, 'description' => 'Write 5 articles and get them approved. Now that\'s some good work! You considered writing a book?'],
        'master-writer'      => ['label' => 'Master Writer',       'icon' => '/assets/ranks/rank-master-writer.svg',       'group' => 'writer', 'order' => 4, 'description' => 'Write 10 articles and get them approved. If you see this, you are one of 3 users in the entirety of ScratchNews to get this rank. Congrats.'],
        'legendary-writer'   => ['label' => 'Legendary Writer',    'icon' => '/assets/ranks/rank-legendary-writer.svg',    'group' => 'writer', 'order' => 5, 'description' => 'Write 15 or more articles and get them approved. ...Some people have insane dedication. You are one special Scratcher. You truly are one of the best writers.'],
        'fan'                => ['label' => 'Fan',                 'icon' => '/assets/ranks/rank-fan.svg',                 'group' => null,    'order' => 0, 'description' => 'You contributed or donated to ScratchNews? Well here\'s your rank!'],
        'moderator'          => ['label' => 'Moderator',           'icon' => '/assets/ranks/rank-moderator.svg',           'group' => null,    'order' => 0, 'description' => 'Moderators are people who made good moderator applications, and actually use their moderator powers for good. Fun fact: 1 in 10 ScratchNews users are moderators or higher. You\'re part of the ScratchNews team!'],
        'head-moderator'     => ['label' => 'Head Moderator',      'icon' => '/assets/ranks/rank-head-moderator.svg',      'group' => null,    'order' => 0, 'description' => 'Oh look, a head moderator. A head moderator is a moderator who has more experience. More powers and stuff! That includes responding to contact us messages, seeing the admin stats etc. Head Moderators are the foundation of this site.'],
        'founder'            => ['label' => 'Founder',             'icon' => '/assets/ranks/rank-founder.svg',             'group' => null,    'order' => 0, 'description' => 'Created ScratchNews. None other than TODB himself.'],
        'contest-writer'     => ['label' => "Writers' Contest Writer",    'icon' => '/assets/ranks/rank-contest-writer.svg',    'group' => null, 'order' => 0, 'description' => "Participate in the Writers' Contest."],
        'contest-scratcher'  => ['label' => "Writers' Contest Scratcher", 'icon' => '/assets/ranks/rank-contest-scratcher.svg', 'group' => null, 'order' => 0, 'description' => "Create a ScratchNews account while you're in the list of people to write to in the Writers' Contest. Hello hello!"],
        'sharer'             => ['label' => 'Sharer',              'icon' => '/assets/badges/sn-sharer.svg',   'group' => 'share', 'order' => 1, 'description' => "Get 1 of your shared article links clicked. People are actually reading what you send them!"],
        'shower'             => ['label' => 'Shower',              'icon' => '/assets/badges/sn-shower.svg',   'group' => 'share', 'order' => 2, 'description' => "Get 5 of your shared article links clicked. Okay, you're showing ScratchNews to people now."],
        'teller'             => ['label' => 'Teller',              'icon' => '/assets/badges/sn-teller.svg',   'group' => 'share', 'order' => 3, 'description' => "Get 10 of your shared article links clicked. You're telling everyone about this site, huh?"],
        'spreader'           => ['label' => 'Spreader',            'icon' => '/assets/badges/sn-spreader.svg', 'group' => 'share', 'order' => 4, 'description' => "Get 25 or more of your shared article links clicked. You're basically a ScratchNews walking billboard at this point."],
    ];
}

// Returns an ordered list of badge slugs (keys into getBadgeDefinitions()) for a user.
// Accepts either a full user row (preferred, avoids an extra query for is_fan/is_moderator)
// or just a user id. Only the user's HIGHEST Writer tier is included (tiers don't stack).
// Falls back to ['user'] when nothing else applies, so every user shows at least one badge.
function getUserRankBadges($user): array {
    if (is_int($user)) {
        $user = getUserById($user);
        if (!$user) return [];
    }
    $slugs = [];
    if (!empty($user['is_admin'])) {
        $slugs[] = 'founder';
    }
    $articleCount = getArticleCountByUser((int)$user['id']);
    if ($articleCount >= 15) {
        $slugs[] = 'legendary-writer';
    } elseif ($articleCount >= 10) {
        $slugs[] = 'master-writer';
    } elseif ($articleCount >= 5) {
        $slugs[] = 'featured-writer';
    } elseif ($articleCount >= 3) {
        $slugs[] = 'dedicated-writer';
    } elseif ($articleCount >= 1) {
        $slugs[] = 'writer';
    }
    if (!empty($user['is_fan'])) {
        $slugs[] = 'fan';
    }
    if (empty($user['is_admin']) && !empty($user['is_head_moderator'])) {
        $slugs[] = 'head-moderator';
    } elseif (empty($user['is_admin']) && !empty($user['is_moderator'])) {
        $slugs[] = 'moderator';
    }
    if (!empty($user['is_contest_writer'])) {
        $slugs[] = 'contest-writer';
    }
    if (!empty($user['is_contest_scratcher'])) {
        $slugs[] = 'contest-scratcher';
    }
    if (empty($slugs)) {
        $slugs[] = 'user';
    }
    return $slugs;
}

// Renders badges as clickable icons. Pass a full user row when you already have one
// (comment lists, profile) to avoid an extra getUserById() lookup per call.
// Each icon opens the shared badge-details modal (markup/JS lives once in
// includes/footer.php) via a data-badges/data-groups JSON payload on the wrapper -
// no per-click network request needed.
function renderRankBadges($user): string {
    $defs = getBadgeDefinitions();
    $slugs = getUserRankBadges($user);
    $userId = is_int($user) ? $user : (int)($user['id'] ?? 0);
    // Share tier (Sharer/Shower/Teller/Spreader) folds into the same badge list/modal
    // as every rank badge below - it's just another 'group' in getBadgeDefinitions().
    if ($userId > 0) {
        $shareBadge = getShareRankBadge(getUserShareClickCount($userId));
        if ($shareBadge) $slugs[] = $shareBadge['slug'];
    }
    if (empty($slugs)) return '';

    $badgesPayload = [];
    $groupsNeeded = [];
    foreach ($slugs as $slug) {
        if (!isset($defs[$slug])) continue;
        $d = $defs[$slug];
        $badgesPayload[] = [
            'slug' => $slug,
            'label' => $d['label'],
            'icon' => $d['icon'],
            'description' => $d['description'],
            'group' => $d['group'],
        ];
        if ($d['group']) $groupsNeeded[$d['group']] = true;
    }
    $groupsPayload = [];
    foreach (array_keys($groupsNeeded) as $group) {
        $tiers = [];
        foreach ($defs as $slug => $d) {
            if ($d['group'] === $group) {
                $tiers[$d['order']] = ['slug' => $slug, 'label' => $d['label'], 'icon' => $d['icon']];
            }
        }
        ksort($tiers);
        $groupsPayload[$group] = array_values($tiers);
    }

    $html = '<span class="rank-badges" data-badges="' . e(json_encode($badgesPayload)) . '" data-groups="' . e(json_encode($groupsPayload)) . '">';
    foreach ($badgesPayload as $b) {
        $html .= '<button type="button" class="rank-badge-icon" data-slug="' . e($b['slug']) . '" title="' . e($b['label']) . '"><img src="' . e($b['icon']) . '" alt="' . e($b['label']) . '"></button>';
    }
    $html .= '</span>';
    return $html;
}

// Renders the article byline author name, linked to their profile when the article is
// attributed to an existing user account. Falls back to plain text for guest bylines
// (no user_id) or if the attributed account was since deleted.
function renderArticleByline(array $article): string {
    if (!empty($article['user_id'])) {
        $authorUser = getUserById((int)$article['user_id']);
        if ($authorUser) {
            return '<a href="/@' . e($authorUser['username']) . '">' . e($article['author']) . '</a>';
        }
    }
    return e($article['author']);
}

// Admin override: marks a user verified directly (sets the same email_verified flag that
// submit.php already accepts as one of three valid verification paths), bypassing Scratch
// or phone verification entirely. Returns false if no user with that username exists.
function verifyUserManually(string $username): bool {
    $user = getUserByUsername($username);
    if (!$user) return false;
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET email_verified = 1 WHERE id = ?");
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $stmt->close();
    return true;
}

// ---- Remember Me ----
function setRememberToken(int $userId): string {
    $db = getDB();
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
    $stmt = $db->prepare("INSERT INTO user_remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $userId, $hash, $expires);
    $stmt->execute();
    $stmt->close();
    // prune expired tokens, and keep only the 10 newest devices
    $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE user_id = ? AND (expires_at < NOW() OR id NOT IN (SELECT id FROM (SELECT id FROM user_remember_tokens WHERE user_id = ? ORDER BY id DESC LIMIT 10) t))");
    $stmt->bind_param('ii', $userId, $userId);
    $stmt->execute();
    $stmt->close();
    return $token;
}

function touchRememberToken(string $token): void {
    $db = getDB();
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
    $stmt = $db->prepare("UPDATE user_remember_tokens SET expires_at = ? WHERE token_hash = ?");
    $stmt->bind_param('ss', $expires, $hash);
    $stmt->execute();
    $stmt->close();
}

function clearRememberToken(int $userId, ?string $token = null): void {
    $db = getDB();
    if ($token !== null && $token !== '') {
        $hash = hash('sha256', $token);
        $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE user_id = ? AND token_hash = ?");
        $stmt->bind_param('is', $userId, $hash);
    } else {
        $stmt = $db->prepare("DELETE FROM user_remember_tokens WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
    }
    $stmt->execute();
    $stmt->close();
}

function getUserByValidRememberToken(int $userId, string $token): ?array {
    $db = getDB();
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT u.* FROM users u JOIN user_remember_tokens t ON t.user_id = u.id WHERE u.id = ? AND t.token_hash = ? AND t.expires_at > NOW()");
    $stmt->bind_param('is', $userId, $hash);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

// ---- DB-backed sessions ----
// InfinityFree's free tier can route requests to different backend app servers with no
// shared/sticky session storage, so PHP's default file-based sessions can silently vanish
// between requests (e.g. between the Scratch-verify AJAX call and the final Finish submit,
// causing a false "session expired" CSRF failure). Storing sessions in MySQL instead makes
// them consistent regardless of which backend server handles a given request.
class DbSessionHandler implements SessionHandlerInterface {
    private mysqli $db;
    private string $readData = '';
    private int $readActivity = 0; // 0 = no stored row existed when this request read it

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function open($savePath, $sessionName): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string {
        $stmt = $this->db->prepare("SELECT data, last_activity FROM sessions WHERE id = ? AND last_activity > ?");
        $cutoff = time() - (int)ini_get('session.gc_maxlifetime');
        $stmt->bind_param('si', $id, $cutoff);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $this->readData = $row ? (string)$row['data'] : '';
        $this->readActivity = $row ? (int)$row['last_activity'] : 0;
        return $this->readData;
    }

    public function write($id, $data): bool {
        $now = time();
        // Speed: no row and nothing to store (bots, first-time guests) = no insert; unchanged data
        // seen within the last 5 minutes = no write. Sessions still never outlive gc_maxlifetime.
        if ($this->readActivity === 0 && $data === '') return true;
        if ($this->readActivity > 0 && $data === $this->readData && ($now - $this->readActivity) < 300) return true;
        $stmt = $this->db->prepare("INSERT INTO sessions (id, data, last_activity) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE data = VALUES(data), last_activity = VALUES(last_activity)");
        $stmt->bind_param('ssi', $id, $data, $now);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function destroy($id): bool {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->bind_param('s', $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function gc($max_lifetime): int|false {
        $cutoff = time() - $max_lifetime;
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE last_activity <= ?");
        $stmt->bind_param('i', $cutoff);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();
        return $deleted;
    }
}

// Validates a user-supplied redirect target is an internal, same-site path (used for
// post-login "return to where you were" redirects, e.g. invite links). Rejects anything
// that isn't a root-relative path (blocks protocol-relative //evil.com, absolute URLs,
// and scheme tricks like /\evil.com that some browsers still treat as protocol-relative).
function safeInternalRedirect(?string $path, string $default = '/'): string {
    if ($path === null || $path === '') return $default;
    if ($path[0] !== '/' || (isset($path[1]) && ($path[1] === '/' || $path[1] === '\\'))) return $default;
    if (preg_match('#^https?://#i', $path)) return $default;
    return $path;
}

function startSession(): void {
    static $handlerSet = false;
    if (!$handlerSet) {
        session_set_save_handler(new DbSessionHandler(getDB()), true);
        $handlerSet = true;
    }
    session_start();

    if (empty($_SESSION['reader_id']) && !empty($_COOKIE['remember_me'])) {
        [$uid, $token] = array_pad(explode(':', $_COOKIE['remember_me'], 2), 2, '');
        $uid = (int)$uid;
        if ($uid > 0 && $token !== '') {
            $user = getUserByValidRememberToken($uid, $token);
            if ($user) {
                $_SESSION['reader_id'] = $user['id'];
                $_SESSION['reader_username'] = $user['username'];
                $_SESSION['is_admin'] = !empty($user['is_admin']);
                $_SESSION['is_moderator'] = !empty($user['is_moderator']);
                $_SESSION['is_head_moderator'] = !empty($user['is_head_moderator']);
                $_SESSION['dark_mode'] = $user['dark_mode'];
                $_SESSION['color_theme'] = $user['color_theme'] ?? 'default';
                $_SESSION['translate_lang'] = $user['translate_lang'] ?? '';
                logUserIp((int)$user['id'], $_SERVER['REMOTE_ADDR'] ?? '');
                touchRememberToken($token);
                setcookie('remember_me', $user['id'] . ':' . $token, [
                    'expires' => time() + 60 * 60 * 24 * 30,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('remember_me', '', time() - 3600, '/');
            }
        }
    }

    ensureShareId();
}

// ---- Share ID (SID) system: every visitor gets a 6-char SID cookie the moment
// they first load any page (no account required). If they later log in, an
// unclaimed SID gets linked to their account. Shared links append the SID plus
// a 7th binary flag (1 = shared while logged in, 0 = shared as a guest), e.g.
// scratchnews.net/article/27?sid=W6_fnw0, so clicks can be attributed
// back to whoever shared the link. ----

const SHARE_ID_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

function generateShareId(): string {
    $db = getDB();
    do {
        $sid = '';
        for ($i = 0; $i < 6; $i++) {
            $sid .= SHARE_ID_ALPHABET[random_int(0, 63)];
        }
        $stmt = $db->prepare("SELECT 1 FROM share_ids WHERE sid = ?");
        $stmt->bind_param('s', $sid);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row();
        $stmt->close();
    } while ($exists);
    return $sid;
}

// Reads/creates the visitor's SID cookie and links it to the logged-in user
// (if any) the first time it sees them. Cached per-request since startSession()
// already calls this once - callers that need the sid can call it again for free.
function ensureShareId(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $sid = $_COOKIE['sn_sid'] ?? '';
    $valid = preg_match('/^[A-Za-z0-9_-]{6}$/', $sid) === 1;

    if ($valid) {
        if (!empty($_SESSION['reader_id'])) {
            $db = getDB();
            $stmt = $db->prepare("UPDATE share_ids SET user_id = ? WHERE sid = ? AND user_id IS NULL");
            $stmt->bind_param('is', $_SESSION['reader_id'], $sid);
            $stmt->execute();
            $stmt->close();
        }
        $cached = $sid;
        return $sid;
    }

    $sid = generateShareId();
    $userId = !empty($_SESSION['reader_id']) ? (int)$_SESSION['reader_id'] : null;
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO share_ids (sid, user_id) VALUES (?, ?)");
    $stmt->bind_param('si', $sid, $userId);
    $stmt->execute();
    $stmt->close();

    setcookie('sn_sid', $sid, [
        'expires' => time() + 60 * 60 * 24 * 365 * 5,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $cached = $sid;
    return $sid;
}

// Builds the sid+flag suffix (7 chars) to append to a share link generated on
// the current request.
function currentShareSuffix(): string {
    return ensureShareId() . (empty($_SESSION['reader_id']) ? '0' : '1');
}

// Records a click against a shared link's SID, deduped per-session per-article
// the same way incrementArticleView() dedupes view counts.
function recordShareClick(int $articleId, string $rawSid): void {
    if (!preg_match('/^([A-Za-z0-9_-]{6})([01])$/', $rawSid, $m)) return;
    [, $sid, $fromAccount] = $m;

    // The share URL is only ever written into the page as JS text (for clipboard
    // copy), never a real <a href> - but it's still URL-shaped text sitting in
    // the raw page source, and some crawler/link-checker is scraping it and
    // re-fetching it. Because currentShareSuffix() always embeds the CURRENT
    // visitor's own SID, that bot ends up "clicking" its own share link on every
    // single article it crawls. A real click always comes from someone whose own
    // SID cookie differs from the SID they're crediting - skip it otherwise.
    if (($_COOKIE['sn_sid'] ?? '') === $sid) return;

    $dedupeKey = $articleId . ':' . $sid;
    if (!empty($_SESSION['clicked_shares'][$dedupeKey])) return;
    $_SESSION['clicked_shares'][$dedupeKey] = true;

    $db = getDB();
    $stmt = $db->prepare("SELECT created_at FROM share_ids WHERE sid = ?");
    $stmt->bind_param('s', $sid);
    $stmt->execute();
    $sidRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$sidRow) return;

    // A real share needs time to reach someone else. A SID "clicked" within a
    // minute of its own creation - even with no matching cookie (see above) -
    // is the crawler self-discovery pattern, not a genuine referral.
    if (strtotime($sidRow['created_at']) >= time() - 60) return;
    if (!empty($_SESSION['clicked_shares'][$dedupeKey])) return;
    $_SESSION['clicked_shares'][$dedupeKey] = true;

    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM share_ids WHERE sid = ?");
    $stmt->bind_param('s', $sid);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row();
    $stmt->close();
    if (!$exists) return;

    $fromAccountInt = (int)$fromAccount;
    $stmt = $db->prepare("INSERT INTO share_clicks (sid, article_id, from_account) VALUES (?, ?, ?)");
    $stmt->bind_param('sii', $sid, $articleId, $fromAccountInt);
    $stmt->execute();
    $stmt->close();
}

// Total clicks ever generated by a user's linked SID(s) - powers the share
// count badge and the share rank badges below.
function getUserShareClickCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM share_clicks sc JOIN share_ids si ON sc.sid = si.sid WHERE si.user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $count;
}

// Every recorded share-link click (most recent first), joined to the SID's
// owning account (if that SID has ever been claimed by a logged-in visitor)
// and the article it pointed at. Powers the admin Share Clicks page - lets
// TODB see exactly who clicked a given shared link and who shared it.
function getShareClicksDetail(int $limit = 500, ?string $sid = null, ?string $ownerUsername = null): array {
    $db = getDB();
    $sql = "SELECT sc.id, sc.article_id, a.title AS article_title, sc.sid, sc.from_account, sc.created_at,
                   si.user_id AS owner_id, u.username AS owner_username
            FROM share_clicks sc
            JOIN share_ids si ON sc.sid = si.sid
            LEFT JOIN users u ON si.user_id = u.id
            LEFT JOIN articles a ON sc.article_id = a.id
            WHERE 1=1";
    $params = [];
    $types = '';

    if ($sid !== null && $sid !== '') {
        $sql .= " AND sc.sid = ?";
        $params[] = $sid;
        $types .= 's';
    }
    if ($ownerUsername !== null && $ownerUsername !== '') {
        $sql .= " AND u.username = ?";
        $params[] = $ownerUsername;
        $types .= 's';
    }

    $sql .= " ORDER BY sc.created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';

    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
        $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Plain-text export of getShareClicksDetail(), mirroring buildVisitsExportText()'s
// format so TODB can paste/share it the same way.
function buildShareClicksExportText(?string $sid = null, ?string $ownerUsername = null): string {
    $clicks = getShareClicksDetail(2000, $sid, $ownerUsername);

    $out = "ScratchNews Share Clicks Export\n";
    $out .= "Generated: " . gmdate('Y-m-d H:i') . " UTC\n";
    if ($sid) $out .= "Filter: only Share ID $sid\n";
    if ($ownerUsername) $out .= "Filter: only shared by @$ownerUsername\n";
    $out .= "Showing up to the most recent 2000 share-link clicks\n";
    $out .= "==========================================\n\n";

    foreach ($clicks as $c) {
        $article = $c['article_id'] ? ('#' . $c['article_id'] . ' ' . $c['article_title']) : '(unknown article)';
        $owner = $c['owner_username'] ? '@' . $c['owner_username'] : 'unclaimed/guest SID';
        $via = $c['from_account'] ? 'logged in' : 'guest';
        $out .= $c['created_at'] . " UTC | " . $article . " | SID " . $c['sid'] . " (shared while " . $via . ") | shared by " . $owner . "\n";
    }

    return $out;
}

// Share rank tiers, highest first. Returns the matching slug into
// getBadgeDefinitions() (group 'share') so renderRankBadges() can fold it into
// the same click-through badge list/modal as every other rank badge.
function getShareRankBadge(int $clicks): ?array {
    $tiers = [
        ['min' => 25, 'slug' => 'spreader'],
        ['min' => 10, 'slug' => 'teller'],
        ['min' => 5,  'slug' => 'shower'],
        ['min' => 1,  'slug' => 'sharer'],
    ];
    foreach ($tiers as $tier) {
        if ($clicks >= $tier['min']) return $tier;
    }
    return null;
}

function setDarkModePreference(int $userId, bool $enabled): void {
    $db = getDB();
    $val = $enabled ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET dark_mode = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
}

// v0.26: Color Themes. Each preset just overrides --brand/--brand-bright/--accent-blue
// (the same CSS variables the site already builds gradients and accent colors from, e.g.
// row-card-img-placeholder's `linear-gradient(135deg, var(--brand-bright), var(--brand))`),
// so picking a theme re-colors buttons/links/gradients everywhere without touching
// dark mode's background/text colors - the two combine freely. 'default' is the
// site's original orange and is never persisted as a body class (no theme-default
// CSS rule needed since :root already has those values).
function colorThemeOptions(): array {
    return [
        'default' => ['label' => 'Default (Orange)', 'brand' => '#cc8829', 'bright' => '#ffaa33'],
        'ocean'   => ['label' => 'Ocean',             'brand' => '#1c6ea4', 'bright' => '#29b6f6'],
        'sunset'  => ['label' => 'Sunset',             'brand' => '#c2410c', 'bright' => '#fb923c'],
        'forest'  => ['label' => 'Forest',             'brand' => '#2f6b3a', 'bright' => '#4ade80'],
        'grape'   => ['label' => 'Grape',              'brand' => '#6d28d9', 'bright' => '#c084fc'],
        'rose'    => ['label' => 'Rose',               'brand' => '#be123c', 'bright' => '#fb7185'],
        'mono'    => ['label' => 'Mono',               'brand' => '#4b5563', 'bright' => '#9ca3af'],
    ];
}

function getColorTheme(): string {
    $theme = $_SESSION['color_theme'] ?? 'default';
    return array_key_exists($theme, colorThemeOptions()) ? $theme : 'default';
}

function setColorThemePreference(int $userId, string $theme): void {
    if (!array_key_exists($theme, colorThemeOptions())) $theme = 'default';
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET color_theme = ? WHERE id = ?");
    $stmt->bind_param('si', $theme, $userId);
    $stmt->execute();
    $stmt->close();
}

function setAutosavePreference(int $userId, bool $enabled, int $intervalSeconds): void {
    $db = getDB();
    $val = $enabled ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET autosave_enabled = ?, autosave_interval = ? WHERE id = ?");
    $stmt->bind_param('iii', $val, $intervalSeconds, $userId);
    $stmt->execute();
    $stmt->close();
}

function setAutocolorLinksPreference(int $userId, bool $enabled): void {
    $db = getDB();
    $val = $enabled ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET autocolor_links = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
}

// Forum post signature: short BBCode blurb appended under a user's forum posts.
// Stored on users.forum_signature, capped at 300 chars (kept short on purpose -
// a signature is a byline, not a second post).
function setForumSignature(int $userId, string $signature): void {
    $db = getDB();
    $signature = trim(mb_substr($signature, 0, 300));
    $stmt = $db->prepare("UPDATE users SET forum_signature = ? WHERE id = ?");
    $stmt->bind_param('si', $signature, $userId);
    $stmt->execute();
    $stmt->close();
}

// Gates group_member_joined / group_member_promoted / group_new_comment notifications
// only (the three types fanned out by notifyGroupMembers()) - does not affect
// group_invite, which is a direct one-to-one notification, not group activity.
function setGroupActivityNotifsPreference(int $userId, bool $enabled): void {
    $db = getDB();
    $val = $enabled ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET group_activity_notifs = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
}

// ---- Article translation (MyMemory, no signup/billing required - chosen because
// TODB is a minor and can't use anything needing a credit card). Free t
