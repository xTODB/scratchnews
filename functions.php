<?php
// === MAINTENANCE MODE (git-tracked, no File Manager needed) ===
date_default_timezone_set('Etc/GMT-3'); // GMT+3
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_UNTIL', null); // null = stays on until you flip this back to false
define('MAINTENANCE_MESSAGE', 'guess what we\'re maintenaincing our siting');

$maintenance_active = MAINTENANCE_MODE && (MAINTENANCE_UNTIL === null || time() < strtotime(MAINTENANCE_UNTIL));

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

// Allow only safe formatting tags in article content (bold, italic, headers, colors via span, etc.)
function sanitizeArticleHtml(string $html): string {
    $allowed = '<p><br><strong><b><em><i><s><strike><u><h1><h2><h3><span><ul><ol><li><blockquote><a><img>';
    $html = strip_tags($html, $allowed);
    $html = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')/i', '$1="#"', $html);
    return $html;
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
    syncToGithub();
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
    syncToGithub();
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
    syncToGithub();
    return $ok;
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
    if ($affected > 0) syncToGithub();
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
    if ($affected > 0) syncToGithub();
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
    $db->query("DELETE FROM visits WHERE id NOT IN (SELECT id FROM (SELECT id FROM visits ORDER BY id DESC LIMIT 200) AS keep)");

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

// ---- Time on Site ----
define('HEARTBEAT_INTERVAL_SECONDS', 15);

function recordHeartbeat(string $sessionKey, ?string $source = null, ?int $userId = null): void {
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
        $stmt = $db->prepare("INSERT INTO site_sessions (session_key, seconds_active, first_seen, last_seen, source, user_id) VALUES (?, ?, NOW(), NOW(), ?, ?)");
        $stmt->bind_param('sisi', $sessionKey, $add, $source, $userId);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $db->prepare("INSERT INTO time_totals_daily (visit_date, total_seconds) VALUES (CURDATE(), ?) ON DUPLICATE KEY UPDATE total_seconds = total_seconds + ?");
    $stmt->bind_param('ii', $add, $add);
    $stmt->execute();
    $stmt->close();

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

function updateUserIp(int $userId, string $ip): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET ip_address = ? WHERE id = ?");
    $stmt->bind_param('si', $ip, $userId);
    $stmt->execute();
    $stmt->close();
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
// Comment Auth, with their chosen ScratchNews username filled in.
function buildCommentAuthText(string $scratchNewsUsername): string {
    return "I've made my ScratchNews profile ($scratchNewsUsername)! I'd suggest you'd follow me there. If you're curious about what ScratchNews is, learn more here: https://scratch.mit.edu/projects/1368284445/";
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

// Returns an ordered list of ['label' => string, 'class' => string] badges for a user.
// Accepts either a full user row (preferred, avoids an extra query for is_fan/is_moderator)
// or just a user id.
function getUserRankBadges($user): array {
    if (is_int($user)) {
        $user = getUserById($user);
        if (!$user) return [];
    }
    $badges = [];
    if (!empty($user['is_admin'])) {
        $badges[] = ['label' => 'Dev', 'class' => 'rank-dev'];
    }
    $articleCount = getArticleCountByUser((int)$user['id']);
    if ($articleCount >= 3) {
        $badges[] = ['label' => 'Featured Writer', 'class' => 'rank-featured-writer'];
    } elseif ($articleCount >= 1) {
        $badges[] = ['label' => 'Writer', 'class' => 'rank-writer'];
    }
    if (!empty($user['is_fan'])) {
        $badges[] = ['label' => 'Fan', 'class' => 'rank-fan'];
    }
    if (empty($user['is_admin']) && !empty($user['is_moderator'])) {
        $badges[] = ['label' => 'Moderator', 'class' => 'rank-moderator'];
    }
    return $badges;
}

// Renders badges as inline HTML spans. Pass a full user row when you already have one
// (comment lists, profile) to avoid an extra getUserById() lookup per call.
function renderRankBadges($user): string {
    $badges = getUserRankBadges($user);
    $userId = is_int($user) ? $user : (int)($user['id'] ?? 0);
    $shareBadge = $userId > 0 ? getShareRankBadge(getUserShareClickCount($userId)) : null;
    if (empty($badges) && !$shareBadge) return '';
    $html = '<span class="rank-badges">';
    foreach ($badges as $b) {
        $html .= '<span class="rank-badge ' . e($b['class']) . '">' . e($b['label']) . '</span>';
    }
    if ($shareBadge) {
        $html .= '<span class="rank-badge rank-badge-share" title="' . e($shareBadge['label']) . ' - shared links clicked ' . e($shareBadge['min']) . '+ times"><img src="/assets/badges/' . e($shareBadge['icon']) . '" alt="' . e($shareBadge['label']) . '"></span>';
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

function verifyGoogleIdToken(string $idToken): ?array {
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200 || !$response) return null;
    $payload = json_decode($response, true);
    if (!$payload || ($payload['aud'] ?? '') !== GOOGLE_CLIENT_ID) return null;
    if (($payload['email_verified'] ?? '') !== 'true') return null;
    return $payload;
}

// Suggests an available username derived from a display name (e.g. a Google account's
// name), for prefilling the signup form. Does not reserve or create anything.
function suggestUsernameFromName(string $name): string {
    $base = $name !== '' ? preg_replace('/[^A-Za-z0-9_]/', '', $name) : 'user';
    if ($base === '') $base = 'user';
    $base = mb_substr($base, 0, 15);
    $username = $base;
    $suffix = 0;
    while (getUserByUsername($username)) {
        $suffix++;
        $username = mb_substr($base, 0, 15 - strlen((string)$suffix)) . $suffix;
    }
    return $username;
}

// SUPERSEDED as of v0.2.1: Google sign-up used to call this to create-and-log-in an
// account immediately, which let it skip follower/phone verification entirely. That
// path now goes through google-auth.php (login-only for existing accounts) + the
// register.php wizard (verification-gated account creation for new ones). Left in
// place in case it's useful again, but nothing currently calls it.
function findOrCreateGoogleUser(string $googleId, string $email, string $name): ?array {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->bind_param('s', $googleId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($user) return $user;

    if ($email !== '') {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user) {
            $stmt = $db->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $stmt->bind_param('si', $googleId, $user['id']);
            $stmt->execute();
            $stmt->close();
            $user['google_id'] = $googleId;
            return $user;
        }
    }

    $base = $name !== '' ? preg_replace('/[^A-Za-z0-9_]/', '', $name) : 'user';
    if ($base === '') $base = 'user';
    $base = mb_substr($base, 0, 15);
    $username = $base;
    $suffix = 0;
    while (getUserByUsername($username)) {
        $suffix++;
        $username = mb_substr($base, 0, 15 - strlen((string)$suffix)) . $suffix;
    }

    $fallbackEmail = $username . '+google@scratchnews.local';
    $newId = createUser($username, $email !== '' ? $email : $fallbackEmail, bin2hex(random_bytes(16)));
    if (!is_int($newId)) return null;

    $stmt = $db->prepare("UPDATE users SET google_id = ?, email_verified = 1 WHERE id = ?");
    $stmt->bind_param('si', $googleId, $newId);
    $stmt->execute();
    $stmt->close();

    return getUserById($newId);
}

// Looks up an existing account by Google ID or, failing that, by the email Google
// returned - without creating anything. Used to tell a returning Google sign-in
// (log straight in) apart from a brand new one (must complete the same follower/phone
// verification a manual signup requires - see google-auth.php and register.php).
function getUserByGoogleIdOrEmail(string $googleId, string $email): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE google_id = ?");
    $stmt->bind_param('s', $googleId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($user) return $user;

    if ($email !== '') {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($user) return $user;
    }
    return null;
}

// Links a verified Google identity to a user after they've completed the same
// verification a manual signup requires. Also marks email_verified since Google
// already confirmed the address.
function linkGoogleIdToUser(int $userId, string $googleId): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET google_id = ?, email_verified = 1 WHERE id = ?");
    $stmt->bind_param('si', $googleId, $userId);
    $stmt->execute();
    $stmt->close();
}

// ---- Remember Me ----
function setRememberToken(int $userId): string {
    $db = getDB();
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + 60 * 60 * 24 * 30);
    $stmt = $db->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE id = ?");
    $stmt->bind_param('ssi', $hash, $expires, $userId);
    $stmt->execute();
    $stmt->close();
    return $token;
}

function clearRememberToken(int $userId): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function getUserByValidRememberToken(int $userId, string $token): ?array {
    $db = getDB();
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND remember_token = ? AND remember_token_expires > NOW()");
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

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    public function open($savePath, $sessionName): bool { return true; }
    public function close(): bool { return true; }

    public function read($id): string {
        $stmt = $this->db->prepare("SELECT data FROM sessions WHERE id = ? AND last_activity > ?");
        $cutoff = time() - (int)ini_get('session.gc_maxlifetime');
        $stmt->bind_param('si', $id, $cutoff);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? $row['data'] : '';
    }

    public function write($id, $data): bool {
        $now = time();
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
                $_SESSION['dark_mode'] = $user['dark_mode'];
                $_SESSION['translate_lang'] = $user['translate_lang'] ?? '';
                $newToken = setRememberToken($user['id']);
                setcookie('remember_me', $user['id'] . ':' . $newToken, [
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
// scratchnews.freedev.app/article/27?sid=W6_fnw0, so clicks can be attributed
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

    $dedupeKey = $articleId . ':' . $sid;
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

// Share rank tiers, highest first. Badge icon files live in assets/badges/.
function getShareRankBadge(int $clicks): ?array {
    $tiers = [
        ['min' => 25, 'label' => 'Spreader', 'icon' => 'sn-spreader.svg'],
        ['min' => 10, 'label' => 'Teller', 'icon' => 'sn-teller.svg'],
        ['min' => 5, 'label' => 'Shower', 'icon' => 'sn-shower.svg'],
        ['min' => 1, 'label' => 'Sharer', 'icon' => 'sn-sharer.svg'],
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

// ---- Article translation (MyMemory, no signup/billing required - chosen because
// TODB is a minor and can't use anything needing a credit card). Free tier is
// rate-limited (5000 words/day tracked by IP, or 50000/day if a contact email is
// passed via &de=), so every translated title+content is cached in
// article_translations keyed by (article_id, lang) and only re-requested if the
// article's title/content actually changed (source_hash). ----

function translateLanguageOptions(): array {
    return [
        'es' => '🇪🇸 Español',
        'fr' => '🇫🇷 Français',
        'de' => '🇩🇪 Deutsch',
        'pt' => '🇵🇹 Português',
        'ru' => '🇷🇺 Русский',
        'it' => '🇮🇹 Italiano',
        'pl' => '🇵🇱 Polski',
    ];
}

// '' means off / show the original English. Logged-in preference lives on the
// users row (hydrated into the session at login, same pattern as dark_mode).
// Guests get a cookie set by set-language.php.
function getTranslateTarget(): string {
    if (!empty($_SESSION['reader_id'])) {
        $lang = $_SESSION['translate_lang'] ?? '';
        return array_key_exists($lang, translateLanguageOptions()) ? $lang : '';
    }
    $lang = $_COOKIE['sn_translate_lang'] ?? '';
    return array_key_exists($lang, translateLanguageOptions()) ? $lang : '';
}

function setTranslatePreference(int $userId, string $lang): void {
    if (!array_key_exists($lang, translateLanguageOptions())) $lang = '';
    $db = getDB();
    $langOrNull = $lang !== '' ? $lang : null;
    $stmt = $db->prepare("UPDATE users SET translate_lang = ? WHERE id = ?");
    $stmt->bind_param('si', $langOrNull, $userId);
    $stmt->execute();
    $stmt->close();
}

// Single MyMemory call for one chunk of plain text. Returns null on any failure
// (network, non-200, or MyMemory's quota-exceeded message riding along inside a
// 200 response) so callers can fall back to showing the original English.
function translateTextViaMyMemory(string $text, string $targetLang): ?string {
    if (trim($text) === '') return $text;
    // Preserve leading/trailing whitespace ourselves - MyMemory trims the query
    // and doesn't hand it back, so without this, adjacent text nodes/chunks that
    // relied on a boundary space get silently glued together after translation
    // (e.g. "xelna has released" + "Prerendered Clockwork..." -> "...releasedPrerendered...").
    preg_match('/^(\s*)(.*?)(\s*)$/s', $text, $m);
    [, $lead, $core, $trail] = $m;
    if ($core === '') return $text;
    $email = defined('MYMEMORY_CONTACT_EMAIL') && MYMEMORY_CONTACT_EMAIL !== ''
        ? MYMEMORY_CONTACT_EMAIL
        : (defined('BREVO_SENDER_EMAIL') ? BREVO_SENDER_EMAIL : '');
    $url = 'https://api.mymemory.translated.net/get?q=' . rawurlencode($core)
        . '&langpair=en|' . rawurlencode($targetLang)
        . ($email !== '' ? '&de=' . rawurlencode($email) : '');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);
    curl_setopt($ch, CURLOPT_USERAGENT, 'ScratchNews-Translate/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) return null;
    $data = json_decode($response, true);
    $translated = $data['responseData']['translatedText'] ?? null;
    if (!is_string($translated) || $translated === '') return null;
    // MyMemory sometimes returns a 200 with a warning sentence AS the translated
    // text instead of a clean error (e.g. quota exceeded) - catch that here.
    if (stripos($translated, 'QUERY LENGTH LIMIT') !== false || stripos($translated, 'MYMEMORY WARNING') !== false) {
        return null;
    }
    return $lead . html_entity_decode($translated, ENT_QUOTES, 'UTF-8') . $trail;
}

// Splits plain text into <=$maxLen chunks (MyMemory caps around 500 chars per
// request), preferring to cut on sentence boundaries so chunks translate cleanly.
function splitTextIntoChunks(string $text, int $maxLen = 480): array {
    if (mb_strlen($text) <= $maxLen) return [$text];
    $chunks = [];
    $remaining = $text;
    while (mb_strlen($remaining) > $maxLen) {
        $slice = mb_substr($remaining, 0, $maxLen);
        $candidates = [];
        foreach (['. ', '! ', '? ', "\n"] as $sep) {
            $pos = mb_strrpos($slice, $sep);
            if ($pos !== false) $candidates[] = $pos + mb_strlen($sep) - 1;
        }
        $cut = $candidates ? max($candidates) : false;
        if ($cut === false || $cut < $maxLen * 0.3) {
            $spacePos = mb_strrpos($slice, ' ');
            $cut = $spacePos !== false ? $spacePos : $maxLen - 1;
        }
        $chunks[] = mb_substr($remaining, 0, $cut + 1);
        $remaining = mb_substr($remaining, $cut + 1);
    }
    if ($remaining !== '') $chunks[] = $remaining;
    return $chunks;
}

function translateTextChunked(string $text, string $targetLang): string {
    $out = '';
    foreach (splitTextIntoChunks($text) as $chunk) {
        $out .= translateTextViaMyMemory($chunk, $targetLang) ?? $chunk;
    }
    return $out;
}

// Collects text nodes eligible for translation from a DOM subtree, in document
// order, skipping whitespace-only nodes and anything inside <script>/<style>/
// <code>/<pre>.
function collectTranslatableTextNodes(DOMNode $node, array &$out): void {
    foreach (iterator_to_array($node->childNodes) as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
            if (trim($child->data) !== '') $out[] = $child;
        } elseif ($child->nodeType === XML_ELEMENT_NODE) {
            if (in_array(strtolower($child->nodeName), ['script', 'style', 'code', 'pre'], true)) continue;
            collectTranslatableTextNodes($child, $out);
        }
    }
}

// Time budget (seconds) for how long ONE request is willing to keep calling
// MyMemory for a single article's content. Once the deadline passes we stop
// starting new batches and return what's been translated so far instead of
// risking InfinityFree's execution time limit killing the request outright
// (a hard 500 with nothing saved). getTranslatedArticle() persists whatever
// got done so the NEXT request continues from there instead of starting over -
// so a long article converges over a couple of page loads instead of 500ing
// forever. Tune down if InfinityFree's actual limit turns out tighter than this.
if (!defined('TRANSLATE_TIME_BUDGET_SECONDS')) {
    define('TRANSLATE_TIME_BUDGET_SECONDS', 12);
}

// Translates a keyed list of DOMText nodes (key = stable position index from
// collectTranslatableTextNodes order), batching several short ones into a
// single MyMemory call (joined by a rare delimiter, then split back apart)
// instead of firing one HTTP request per paragraph. Stops starting new batches
// once $deadline (a microtime(true) timestamp) passes, leaving any remaining
// nodes untouched (still English) for the caller to retry on a later request.
// Falls back to translating a batch's items individually if the delimiter
// didn't survive translation intact. Returns the list of keys that were
// actually attempted this run (translated successfully OR fell back to
// English after a real MyMemory call) - NOT keys skipped purely due to the
// time budget, so the caller knows exactly what still needs retrying.
function translateTextNodesBatched(array $nodes, string $targetLang, ?float $deadline = null): array {
    $delimiter = " \u{2016} ";
    $maxBatchLen = 420;
    $batch = [];
    $batchLen = 0;
    $attempted = [];

    $flush = function () use (&$batch, &$batchLen, $targetLang, $delimiter, &$attempted) {
        if (empty($batch)) return;
        if (count($batch) === 1) {
            $item = $batch[0];
            $translated = translateTextViaMyMemory($item['core'], $targetLang);
            $item['node']->data = $item['lead'] . ($translated ?? $item['core']) . $item['trail'];
            $attempted[] = $item['key'];
        } else {
            $joined = implode($delimiter, array_column($batch, 'core'));
            $translatedJoined = translateTextViaMyMemory($joined, $targetLang);
            $parts = $translatedJoined !== null ? explode(trim($delimiter), $translatedJoined) : null;
            if ($parts !== null && count($parts) === count($batch)) {
                foreach ($batch as $i => $item) {
                    $item['node']->data = $item['lead'] . trim($parts[$i]) . $item['trail'];
                    $attempted[] = $item['key'];
                }
            } else {
                // Delimiter got mangled in translation - fall back to one call per item.
                foreach ($batch as $item) {
                    $translated = translateTextViaMyMemory($item['core'], $targetLang);
                    $item['node']->data = $item['lead'] . ($translated ?? $item['core']) . $item['trail'];
                    $attempted[] = $item['key'];
                }
            }
        }
        $batch = [];
        $batchLen = 0;
    };

    foreach ($nodes as $key => $node) {
        if ($deadline !== null && microtime(true) >= $deadline) {
            break; // out of time budget - leave the rest for a later request
        }
        preg_match('/^(\s*)(.*?)(\s*)$/s', $node->data, $m);
        [, $lead, $core, $trail] = $m;
        if ($core === '') continue;
        if (mb_strlen($core) > $maxBatchLen) {
            $flush();
            $node->data = $lead . translateTextChunked($core, $targetLang) . $trail;
            $attempted[] = $key;
            continue;
        }
        if ($batch && $batchLen + mb_strlen($core) + 3 > $maxBatchLen) {
            $flush();
        }
        $batch[] = ['key' => $key, 'node' => $node, 'lead' => $lead, 'core' => $core, 'trail' => $trail];
        $batchLen += mb_strlen($core) + 3;
    }
    $flush();
    return $attempted;
}

// Translates article body HTML (as authored via Quill) without disturbing
// markup. $progress is a [nodeIndex => translatedText] map from a prior
// partial run (see getTranslatedArticle) - those nodes are restored for free
// (no API call) before translating whatever's left, up to
// TRANSLATE_TIME_BUDGET_SECONDS. Returns:
//   'html'     => the article HTML with everything translated so far applied
//   'progress' => updated [nodeIndex => translatedText] map to persist
//   'complete' => true only if every text node has now been translated
// Node indices come from collectTranslatableTextNodes's document-order walk,
// which is deterministic for a given $html - safe to reuse across requests as
// long as the source content (and therefore the hash) hasn't changed.
function translateHtmlContent(string $html, string $targetLang, array $progress = []): array {
    if (trim($html) === '') return ['html' => $html, 'progress' => [], 'complete' => true];
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $root = $dom->getElementsByTagName('div')->item(0);
    if (!$root) return ['html' => $html, 'progress' => [], 'complete' => true];
    $textNodes = [];
    collectTranslatableTextNodes($root, $textNodes);

    $remaining = [];
    foreach ($textNodes as $i => $node) {
        if (array_key_exists($i, $progress)) {
            $node->data = $progress[$i]; // already translated in a prior run - free
        } else {
            $remaining[$i] = $node;
        }
    }

    $deadline = microtime(true) + TRANSLATE_TIME_BUDGET_SECONDS;
    $attempted = translateTextNodesBatched($remaining, $targetLang, $deadline);
    foreach ($attempted as $i) {
        $progress[$i] = $textNodes[$i]->data;
    }

    $complete = count($progress) >= count($textNodes);

    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return ['html' => $out, 'progress' => $progress, 'complete' => $complete];
}

function computeArticleSourceHash(array $article): string {
    return md5(($article['title'] ?? '') . '|' . ($article['content'] ?? ''));
}

function getCachedTranslation(int $articleId, string $lang): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM article_translations WHERE article_id = ? AND lang = ?");
    $stmt->bind_param('is', $articleId, $lang);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function upsertArticleTranslation(int $articleId, string $lang, ?string $title, ?string $content, string $hash, array $progress = [], bool $complete = true): void {
    $db = getDB();
    $progressJson = empty($progress) ? null : json_encode($progress);
    $isComplete = $complete ? 1 : 0;
    $stmt = $db->prepare("INSERT INTO article_translations (article_id, lang, title, content, source_hash, progress_json, is_complete, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE title = VALUES(title), content = VALUES(content), source_hash = VALUES(source_hash), progress_json = VALUES(progress_json), is_complete = VALUES(is_complete), updated_at = NOW()");
    $stmt->bind_param('isssssi', $articleId, $lang, $title, $content, $hash, $progressJson, $isComplete);
    $stmt->execute();
    $stmt->close();
}

// Cheap path for listings: translates/caches just the title. Reuses a cached
// content field if one already exists and is still fresh, so a later full-article
// view doesn't get its cached content clobbered back to null by this function.
function getTranslatedTitle(array $article, string $lang): string {
    if ($lang === '') return $article['title'];
    $hash = computeArticleSourceHash($article);
    $cached = getCachedTranslation((int)$article['id'], $lang);
    $sameHash = $cached && $cached['source_hash'] === $hash;
    if ($sameHash && $cached['title'] !== null) {
        return $cached['title'];
    }
    $translated = translateTextViaMyMemory($article['title'], $lang);
    if ($translated === null) return $article['title'];
    $freshContent = $sameHash ? $cached['content'] : null;
    $freshProgress = [];
    if ($sameHash && !empty($cached['progress_json'])) {
        $decoded = json_decode($cached['progress_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) $freshProgress[(int)$k] = $v;
        }
    }
    $freshComplete = $sameHash && (int)($cached['is_complete'] ?? 0) === 1;
    upsertArticleTranslation((int)$article['id'], $lang, $translated, $freshContent, $hash, $freshProgress, $freshComplete);
    return $translated;
}

// Full path for the article page: translates/caches both title and content.
// Returns [title, content].
function getTranslatedArticle(array $article, string $lang): array {
    if ($lang === '') return [$article['title'], $article['content']];
    $hash = computeArticleSourceHash($article);
    $cached = getCachedTranslation((int)$article['id'], $lang);
    $sameHash = $cached && $cached['source_hash'] === $hash;

    // Fast path: fully translated and cached already - no MyMemory calls at all.
    if ($sameHash && (int)($cached['is_complete'] ?? 0) === 1 && $cached['title'] !== null && $cached['content'] !== null) {
        return [$cached['title'], $cached['content']];
    }

    $title = ($sameHash && $cached['title'] !== null)
        ? $cached['title']
        : (translateTextViaMyMemory($article['title'], $lang) ?? $article['title']);

    // Resume from whatever a prior (possibly time-budget-cut-short) run already
    // translated, instead of re-translating the whole article from scratch.
    $progress = [];
    if ($sameHash && !empty($cached['progress_json'])) {
        $decoded = json_decode($cached['progress_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $k => $v) $progress[(int)$k] = $v;
        }
    }

    $result = translateHtmlContent($article['content'], $lang, $progress);
    upsertArticleTranslation((int)$article['id'], $lang, $title, $result['html'], $hash, $result['progress'], $result['complete']);
    // Even mid-translation, return the best available mix (translated so far +
    // English fallback for the rest) rather than nothing - never a 500 for this.
    return [$title, $result['html']];
}

// Convenience wrappers so page code doesn't need to resolve the preference itself.
function translatedTitle(array $article): string {
    $lang = getTranslateTarget();
    return $lang === '' ? $article['title'] : getTranslatedTitle($article, $lang);
}

function translatedArticleFields(array $article): array {
    $lang = getTranslateTarget();
    return $lang === '' ? [$article['title'], $article['content']] : getTranslatedArticle($article, $lang);
}

// Lightweight draft-only autosave for admin-authored articles (admin/create.php and
// admin/edit.php). Deliberately NOT createArticle()/updateArticle(): those call
// syncToGithub() on every save, which pushes a GitHub commit - fine for a deliberate
// Publish/Save click, not something to fire every few seconds. This also refuses to
// touch a row that isn't currently status='draft', so it can never silently overwrite
// or partially-publish a live article mid-edit. Returns the article id (existing or
// newly created) on success, or null if there was nothing eligible to save to.
function autosaveArticle(?int $id, string $title, string $summary, string $content, string $author, ?int $userId = null): ?int {
    $db = getDB();
    $content = sanitizeArticleHtml($content);
    if ($id) {
        $existing = getArticleById($id);
        if (!$existing || $existing['status'] !== 'draft') return null;
        $stmt = $db->prepare("UPDATE articles SET title = ?, summary = ?, content = ?, author = ? WHERE id = ?");
        $stmt->bind_param('ssssi', $title, $summary, $content, $author, $id);
        $stmt->execute();
        $stmt->close();
        return $id;
    }
    $newId = getNextArticleId();
    $stmt = $db->prepare("INSERT INTO articles (id, title, summary, content, author, status, user_id) VALUES (?, ?, ?, ?, ?, 'draft', ?)");
    $stmt->bind_param('issssi', $newId, $title, $summary, $content, $author, $userId);
    $stmt->execute();
    $stmt->close();
    return $newId;
}

// ---- Comments ----
function getCommentsForArticle(int $articleId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT comments.*, users.username, users.avatar_url FROM comments JOIN users ON comments.user_id = users.id WHERE article_id = ? ORDER BY comments.created_at ASC");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function addComment(int $articleId, int $userId, string $content, ?int $parentId = null): bool {
    $db = getDB();
    if ($parentId === null) {
        $stmt = $db->prepare("INSERT INTO comments (article_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $articleId, $userId, $content);
    } else {
        $stmt = $db->prepare("INSERT INTO comments (article_id, user_id, content, parent_comment_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iisi', $articleId, $userId, $content, $parentId);
    }
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $article = getArticleById($articleId);
        $link = '/article/' . $articleId;
        $excludeFromMentions = [];
        if ($parentId !== null) {
            $pstmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
            $pstmt->bind_param('i', $parentId);
            $pstmt->execute();
            $parentOwner = $pstmt->get_result()->fetch_assoc();
            $pstmt->close();
            if ($parentOwner && (int)$parentOwner['user_id'] !== $userId) {
                createNotification((int)$parentOwner['user_id'], 'comment_reply', $userId, $link, $content);
                $excludeFromMentions[] = (int)$parentOwner['user_id'];
            }
        } elseif ($article && !empty($article['user_id']) && (int)$article['user_id'] !== $userId) {
            createNotification((int)$article['user_id'], 'article_comment', $userId, $link, $content);
            $excludeFromMentions[] = (int)$article['user_id'];
        }
        notifyMentions($content, $userId, $link, $excludeFromMentions);
        notifyAdmins('admin_new_comment', $userId, $link, $content);
    }

    return $ok;
}

function buildCommentTree(array $comments): array {
    $byId = [];
    foreach ($comments as $c) {
        $c['replies'] = [];
        $byId[$c['id']] = $c;
    }

    $tree = [];
    foreach ($byId as $id => $c) {
        if ($c['parent_comment_id'] !== null && isset($byId[$c['parent_comment_id']])) {
            $byId[$c['parent_comment_id']]['replies'][] = &$byId[$id];
        } else {
            $tree[] = &$byId[$id];
        }
    }

    // Only return top-level comments; replies are nested inside via reference
    $topLevel = [];
    foreach ($tree as $c) {
        $topLevel[] = $c;
    }
    return $topLevel;
}

function linkifyMentions(string $escapedText): string {
    return preg_replace('/@([A-Za-z0-9_]{3,20})\b/', '<a href="/@$1">@$1</a>', $escapedText);
}

// Finds @username mentions in comment content and notifies each mentioned user,
// skipping the actor themselves and anyone in $excludeUserIds (e.g. the article/profile
// owner or parent comment author, who already got their own notification for this comment).
function notifyMentions(string $content, int $actorId, string $link, array $excludeUserIds = []): void {
    if (!preg_match_all('/@([A-Za-z0-9_]{3,20})\b/', $content, $matches)) {
        return;
    }
    $notified = [];
    foreach (array_unique($matches[1]) as $username) {
        $mentioned = getUserByUsername($username);
        if (!$mentioned) continue;
        $mentionedId = (int)$mentioned['id'];
        if ($mentionedId === $actorId) continue;
        if (in_array($mentionedId, $excludeUserIds, true)) continue;
        if (in_array($mentionedId, $notified, true)) continue;
        createNotification($mentionedId, 'mention', $actorId, $link, $content);
        $notified[] = $mentionedId;
    }
}

// Renders a small round avatar for comment authors, falling back to a
// first-letter placeholder (matching the pattern used in header.php/profile.php)
// when no avatar_url is set.
function renderCommentAvatar(?string $avatarUrl, string $username): string {
    if ($avatarUrl) {
        return '<img src="' . e($avatarUrl) . '" alt="" class="comment-avatar">';
    }
    return '<span class="comment-avatar comment-avatar-placeholder">' . e(mb_strtoupper(mb_substr($username, 0, 1))) . '</span>';
}

function renderCommentThread(array $comment, bool $canReply, int $depth = 0, bool $canReport = false): string {
    $indent = min($depth * 14, 56); // cap indentation so deep threads don't run off-screen
    $topClass = $depth === 0 ? ' comment-top' : '';
    $html = '<div class="comment' . $topClass . '" style="margin-left: ' . $indent . 'px;">';
    $html .= '<div class="comment-header">';
    $html .= renderCommentAvatar($comment['avatar_url'] ?? null, $comment['username']);
    $html .= '<strong><a href="/@' . e($comment['username']) . '">' . e($comment['username']) . '</a></strong>';
    $html .= renderRankBadges((int)$comment['user_id']);
    $html .= ' <span class="meta">' . utcTimeTag($comment['created_at'], 'datetime') . '</span>';
    $html .= '</div>';
    $html .= '<p>' . linkifyMentions(e($comment['content'])) . '</p>';

    if ($canReply) {
        $formId = 'reply-form-' . (int)$comment['id'];
        $html .= '<button type="button" class="reply-toggle" title="Reply" onclick="document.getElementById(\'' . $formId . '\').classList.toggle(\'open\')"><img src="/assets/icons/reply.svg" class="icon-svg-sm" alt=""> Reply</button>';
        $html .= '<form method="post" class="reply-form" id="' . $formId . '">';
        $html .= csrfField();
        $html .= '<input type="hidden" name="action" value="comment">';
        $html .= '<input type="hidden" name="parent_id" value="' . (int)$comment['id'] . '">';
        $html .= '<textarea name="content" placeholder="Write a reply..." required></textarea>';
        $html .= '<button class="btn" type="submit">Post Reply</button>';
        $html .= '</form>';
    }

    if (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator'])) {
        $html .= ' <form method="post" class="report-form" onsubmit="return confirm(\'Delete this comment?\');">';
        $html .= csrfField();
        $html .= '<input type="hidden" name="action" value="admin_delete">';
        $html .= '<input type="hidden" name="comment_id" value="' . (int)$comment['id'] . '">';
        $html .= '<button type="submit" class="reply-toggle" title="Delete"><img src="/assets/icons/comment_delete.svg" class="icon-svg-sm" alt=""> Delete</button>';
        $html .= '</form>';
    }

    if ($canReport) {
        $html .= ' <form method="post" class="report-form" onsubmit="return confirm(\'Report this comment for review?\');">';
        $html .= csrfField();
        $html .= '<input type="hidden" name="action" value="report">';
        $html .= '<input type="hidden" name="comment_id" value="' . (int)$comment['id'] . '">';
        $html .= '<button type="submit" class="reply-toggle" title="Report"><img src="/assets/icons/report.svg" class="icon-svg-sm" alt=""> Report</button>';
        $html .= '</form>';
    }

    foreach ($comment['replies'] as $reply) {
        $html .= renderCommentThread($reply, $canReply, $depth + 1, $canReport);
    }

    $html .= '</div>';
    return $html;
}

// ---- Formatting ----
// Comma-formats any statistic display (likes, dislikes, comments, views,
// shares, followers, etc.) - e.g. 1234 -> "1,234".
function formatCount(int $n): string {
    return number_format($n);
}

// ---- Likes ----
function getCommentCount(int $articleId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM comments WHERE article_id = ?");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['cnt'];
}

function getLikeCount(int $articleId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM likes WHERE article_id = ?");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['cnt'];
}

function hasUserLiked(int $articleId, int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM likes WHERE article_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $articleId, $userId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$found;
}

function toggleLike(int $articleId, int $userId): bool {
    $db = getDB();
    if (hasUserLiked($articleId, $userId)) {
        $stmt = $db->prepare("DELETE FROM likes WHERE article_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $articleId, $userId);
        $stmt->execute();
        $stmt->close();
        return false;
    } else {
        $stmt = $db->prepare("INSERT INTO likes (article_id, user_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $articleId, $userId);
        $stmt->execute();
        $stmt->close();
        $del = $db->prepare("DELETE FROM dislikes WHERE article_id = ? AND user_id = ?");
        $del->bind_param('ii', $articleId, $userId);
        $del->execute();
        $del->close();

        $article = getArticleById($articleId);
        if ($article && !empty($article['user_id']) && (int)$article['user_id'] !== $userId) {
            createNotification((int)$article['user_id'], 'article_liked', $userId, '/article/' . $articleId);
        }
        return true;
    }
}

// ---- Dislikes ----
function getDislikeCount(int $articleId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM dislikes WHERE article_id = ?");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['cnt'];
}

function hasUserDisliked(int $articleId, int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM dislikes WHERE article_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $articleId, $userId);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (bool)$found;
}

function toggleDislike(int $articleId, int $userId): bool {
    $db = getDB();
    if (hasUserDisliked($articleId, $userId)) {
        $stmt = $db->prepare("DELETE FROM dislikes WHERE article_id = ? AND user_id = ?");
        $stmt->bind_param('ii', $articleId, $userId);
        $stmt->execute();
        $stmt->close();
        return false;
    } else {
        $stmt = $db->prepare("INSERT INTO dislikes (article_id, user_id) VALUES (?, ?)");
        $stmt->bind_param('ii', $articleId, $userId);
        $stmt->execute();
        $stmt->close();
        $del = $db->prepare("DELETE FROM likes WHERE article_id = ? AND user_id = ?");
        $del->bind_param('ii', $articleId, $userId);
        $del->execute();
        $del->close();

        $article = getArticleById($articleId);
        if ($article && !empty($article['user_id']) && (int)$article['user_id'] !== $userId) {
            createNotification((int)$article['user_id'], 'article_disliked', $userId, '/article/' . $articleId);
        }
        return true;
    }
}

// ---- Profile / account deletion helpers ----
function getUserById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function getCommentsByUser(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT comments.*, articles.title AS article_title FROM comments JOIN articles ON comments.article_id = articles.id WHERE comments.user_id = ? ORDER BY comments.created_at DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getApprovedArticleCountByUser(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM submissions WHERE user_id = ? AND status = 'approved'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return (int)$count;
}

function getArticleCountByUser(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM articles WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return (int)$count;
}

// ---- About page: Contributions section ----
function getFanUsers(): array {
    $db = getDB();
    $result = $db->query("SELECT id, username, avatar_url, bio FROM users WHERE is_fan = 1 AND is_banned = 0 ORDER BY username ASC");
    if (!$result) return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function getFeaturedWriterUsers(): array {
    $db = getDB();
    // GROUP BY lists every non-aggregated selected column explicitly, since some MySQL
    // configs (ONLY_FULL_GROUP_BY, common on shared hosts) reject grouping by u.id alone
    // even though the other columns are functionally dependent on it. A rejected query
    // makes $db->query() return false, which would otherwise fatal on fetch_assoc().
    $result = $db->query(
        "SELECT u.id, u.username, u.avatar_url, u.bio, COUNT(a.id) AS article_count
         FROM users u
         JOIN articles a ON a.user_id = u.id
         WHERE u.is_banned = 0
         GROUP BY u.id, u.username, u.avatar_url, u.bio
         HAVING article_count >= 3
         ORDER BY article_count DESC, u.username ASC"
    );
    if (!$result) return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    return $rows;
}

function getArticlesByUser(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM articles WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function deleteUserAccount(int $userId): bool {
    $db = getDB();
    $db->begin_transaction();
    try {
        $db->query("SET FOREIGN_KEY_CHECKS=0");

        // Preserve articles they wrote; just detach authorship instead of
        // deleting the content.
        $stmt = $db->prepare("UPDATE articles SET user_id = NULL WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        // Tables with no enforced FK cascade — must clean these ourselves or
        // they'd be left dangling on a user id that no longer exists.
        // (comments/likes already cascade via real FK constraints, but
        // deleting them explicitly here is harmless and keeps this function
        // correct even if those constraints ever change.)
        $refs = [
            ['comments', 'user_id'],
            ['likes', 'user_id'],
            ['dislikes', 'user_id'],
            ['submissions', 'user_id'],
            ['follows', 'follower_id'],
            ['follows', 'followed_id'],
            ['profile_comments', 'profile_user_id'],
            ['profile_comments', 'author_id'],
        ];
        foreach ($refs as [$table, $col]) {
            $stmt = $db->prepare("DELETE FROM $table WHERE $col = ?");
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }

        // comment_reports.reporter_id and impersonation_log are audit trails
        // — deliberately left alone. Their user id references may go stale,
        // which is fine and expected for a historical log.

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $db->query("SET FOREIGN_KEY_CHECKS=1");
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $db->query("SET FOREIGN_KEY_CHECKS=1");
        return false;
    }

    // Self-heal AUTO_INCREMENT the same way moveUserId() does — a hard
    // delete of a huge 9-digit-id account should not leave the counter
    // stuck up there for the next real signup.
    $result = $db->query("SELECT MAX(id) AS max_id FROM users WHERE id < 1000000");
    $maxId = (int)($result->fetch_assoc()['max_id'] ?? 0);
    $db->query("ALTER TABLE users AUTO_INCREMENT = " . ($maxId + 1));

    return true;
}

function bulkDeleteAnonymizedUsers(): int {
    $db = getDB();
    $ids = $db->query("SELECT id FROM users WHERE username LIKE 'deleted\\_user\\_%'")->fetch_all(MYSQLI_ASSOC);
    $count = 0;
    foreach ($ids as $row) {
        if (deleteUserAccount((int)$row['id'])) $count++;
    }
    return $count;
}

function createSubmission($userId, $title, $summary, $content, ?string $imageUrl = null, array $categoryIds = [], string $status = 'pending', ?int $articleId = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO submissions (user_id, title, summary, content, image_url, status, article_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssi", $userId, $title, $summary, $content, $imageUrl, $status, $articleId);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();
    setSubmissionCategories($id, $categoryIds);
    if ($status === 'pending') {
        notifyAdmins('admin_new_submission', $userId, '/admin/submissions', $title);
    }
    return $id;
}

function updateSubmission(int $id, string $title, string $summary, string $content, ?string $imageUrl, array $categoryIds, string $status = 'pending'): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE submissions SET title = ?, summary = ?, content = ?, image_url = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $title, $summary, $content, $imageUrl, $status, $id);
    $ok = $stmt->execute();
    $stmt->close();
    setSubmissionCategories($id, $categoryIds);
    if ($status === 'pending') {
        $submission = getSubmissionById($id);
        if ($submission) {
            notifyAdmins('admin_new_submission', (int)$submission['user_id'], '/admin/submissions', $title);
        }
    }
    return $ok;
}

function getSubmissionCategoryIds(int $submissionId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT category_id FROM submission_categories WHERE submission_id = ?");
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map('intval', array_column($rows, 'category_id'));
}

function setSubmissionCategories(int $submissionId, array $categoryIds): void {
    $categoryIds = array_slice(array_unique(array_map('intval', $categoryIds)), 0, 3);
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM submission_categories WHERE submission_id = ?");
    $stmt->bind_param('i', $submissionId);
    $stmt->execute();
    $stmt->close();
    if (empty($categoryIds)) return;
    $stmt = $db->prepare("INSERT INTO submission_categories (submission_id, category_id) VALUES (?, ?)");
    foreach ($categoryIds as $catId) {
        $stmt->bind_param('ii', $submissionId, $catId);
        $stmt->execute();
    }
    $stmt->close();
}

function getDraftsByUser(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM submissions WHERE user_id = ? AND status = 'draft' ORDER BY created_at DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getUserSubmissionById(int $id, int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM submissions WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $id, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Finds an in-progress resubmission (draft or already pending review) for a reader
// editing one of their own published articles, so submit.php can resume it instead
// of starting a duplicate. Returns the most recent one if somehow more than one exists.
function getResubmissionForArticle(int $articleId, int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM submissions WHERE article_id = ? AND user_id = ? AND status IN ('draft', 'pending') ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('ii', $articleId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getPendingSubmissions() {
    $db = getDB();
    $result = $db->query("
        SELECT submissions.*, users.username, users.email
        FROM submissions
        JOIN users ON submissions.user_id = users.id
        WHERE submissions.status = 'pending'
        ORDER BY submissions.created_at ASC
    ");
    $submissions = [];
    while ($row = $result->fetch_assoc()) {
        $submissions[] = $row;
    }
    return $submissions;
}

function getSubmissionById($id) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT submissions.*, users.username, users.email
        FROM submissions
        JOIN users ON submissions.user_id = users.id
        WHERE submissions.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $submission = $result->fetch_assoc();
    $stmt->close();
    return $submission ?: null;
}

function approveSubmission($id) {
    $db = getDB();
    $submission = getSubmissionById($id);
    if (!$submission || $submission['status'] !== 'pending') {
        return false;
    }

    // A resubmission (reader editing one of their own already-published articles) has
    // article_id set - update that same article row in place (keeping its id, likes,
    // comments, views, share history) instead of inserting a brand-new article. The
    // live article stayed untouched and visible the whole time this was pending, per
    // TODB's call - this is the moment it actually changes.
    $editingArticleId = !empty($submission['article_id']) ? (int)$submission['article_id'] : null;

    if ($editingArticleId) {
        $articleId = $editingArticleId;
        $stmt = $db->prepare("UPDATE articles SET title = ?, summary = ?, content = ?, author = ?, image_url = ?, status = 'published' WHERE id = ?");
        $stmt->bind_param("sssssi", $submission['title'], $submission['summary'], $submission['content'], $submission['username'], $submission['image_url'], $articleId);
        $stmt->execute();
        $stmt->close();
    } else {
        $articleId = getNextArticleId();
        $stmt = $db->prepare("INSERT INTO articles (id, title, summary, content, author, image_url, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssi", $articleId, $submission['title'], $submission['summary'], $submission['content'], $submission['username'], $submission['image_url'], $submission['user_id']);
        $stmt->execute();
        $stmt->close();
    }

    setArticleCategories($articleId, getSubmissionCategoryIds($id));

    $stmt = $db->prepare("UPDATE submissions SET status = 'approved', reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    $articleLink = '/article/' . $articleId;
    createNotification((int)$submission['user_id'], 'article_approved', null, $articleLink, $submission['title']);
    foreach (getFollowerIds((int)$submission['user_id']) as $followerId) {
        createNotification($followerId, 'followed_user_article', (int)$submission['user_id'], $articleLink, $submission['title']);
    }

    sendSubmissionDecisionEmail($submission['email'], $submission['username'], $submission['title'], true);
    syncToGithub();
    return true;
}

function rejectSubmission($id) {
    $db = getDB();
    $submission = getSubmissionById($id);
    if (!$submission || $submission['status'] !== 'pending') {
        return false;
    }

    $stmt = $db->prepare("UPDATE submissions SET status = 'rejected', reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();

    createNotification((int)$submission['user_id'], 'article_rejected', null, null, $submission['title']);

    sendSubmissionDecisionEmail($submission['email'], $submission['username'], $submission['title'], false);
    return true;
}

function sendSubmissionDecisionEmail($toEmail, $toUsername, $articleTitle, $approved) {
    if ($approved) {
        $subject = "Your ScratchNews submission was approved!";
        $body = "<p>Hi " . htmlspecialchars($toUsername) . ",</p>"
            . "<p>Great news — your submission \"" . htmlspecialchars($articleTitle) . "\" has been approved and is now live on ScratchNews.</p>"
            . "<p><a href=\"https://scratchnews.freedev.app/\">Check it out</a></p>";
    } else {
        $subject = "Update on your ScratchNews submission";
        $body = "<p>Hi " . htmlspecialchars($toUsername) . ",</p>"
            . "<p>Thanks for submitting \"" . htmlspecialchars($articleTitle) . "\" to ScratchNews. After review, we've decided not to publish this one.</p>"
            . "<p>Feel free to submit again in the future!</p>";
    }

    $payload = json_encode([
        "sender" => [
            "name" => "ScratchNews",
            "email" => BREVO_SENDER_EMAIL
        ],
        "to" => [
            ["email" => $toEmail, "name" => $toUsername]
        ],
        "subject" => $subject,
        "htmlContent" => $body
    ]);

    $ch = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "api-key: " . BREVO_API_KEY,
        "content-type: application/json"
    ]);

    curl_exec($ch);
    curl_close($ch);
}

function submitFeedback($userId, $message, ?string $imageUrl = null) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO feedback (user_id, message, image_url) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $message, $imageUrl);
    $stmt->execute();
    $stmt->close();
    notifyAdmins('admin_new_feedback', $userId, '/admin/feedback', $message);
}

// Dev/mod reply to a feedback submission. Notifies the submitter (if they weren't
// anonymous) via the existing notification system - there's no dedicated feedback
// inbox for readers, so the notification link just points back to /feedback.
function replyToFeedback(int $id, int $adminUserId, string $replyMessage): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE feedback SET reply_message = ?, replied_at = NOW(), replied_by = ? WHERE id = ?");
    $stmt->bind_param("sii", $replyMessage, $adminUserId, $id);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok) {
        $stmt = $db->prepare("SELECT user_id FROM feedback WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row['user_id'])) {
            createNotification((int)$row['user_id'], 'feedback_reply', $adminUserId, '/feedback', $replyMessage);
        }
    }
    return $ok;
}

function getAllFeedback() {
    $db = getDB();
    $result = $db->query("
        SELECT feedback.*, users.username, replier.username AS replied_by_username
        FROM feedback
        LEFT JOIN users ON feedback.user_id = users.id
        LEFT JOIN users replier ON feedback.replied_by = replier.id
        ORDER BY feedback.created_at DESC
    ");
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function searchArticles(string $query): array {
    $db = getDB();
    $like = '%' . $query . '%';
    $stmt = $db->prepare("SELECT * FROM articles WHERE status = 'published' AND (title LIKE ? OR summary LIKE ? OR content LIKE ?) ORDER BY created_at DESC");
    $stmt->bind_param('sss', $like, $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function searchProfiles(string $query): array {
    $db = getDB();
    $like = '%' . $query . '%';
    $stmt = $db->prepare(
        "SELECT u.id, u.username, u.avatar_url, u.bio, u.is_admin, u.is_moderator, u.is_fan,
                (SELECT COUNT(*) FROM follows f WHERE f.followed_id = u.id) AS follower_count
         FROM users u
         WHERE u.is_banned = 0 AND u.username NOT LIKE 'deleted_user_%' AND (u.username LIKE ? OR u.bio LIKE ?)
         ORDER BY follower_count DESC"
    );
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function searchGroups(string $query): array {
    $db = getDB();
    $like = '%' . $query . '%';
    $stmt = $db->prepare(
        "SELECT g.*, u.username AS host_username,
                (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS member_count
         FROM `groups` g JOIN users u ON u.id = g.host_user_id
         WHERE g.status = 'active' AND (g.name LIKE ? OR g.description LIKE ?)
         ORDER BY member_count DESC"
    );
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function reportComment($commentId, $reporterId) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO comment_reports (comment_id, reporter_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $commentId, $reporterId);
    $stmt->execute();
    $stmt->close();
    notifyAdmins('admin_new_report', $reporterId, '/admin/');
}

function getPendingReports() {
    $db = getDB();
    $result = $db->query("
        SELECT comment_reports.id AS report_id, comment_reports.created_at AS reported_at,
               comments.id AS comment_id, comments.content, comments.article_id,
               commenter.username AS commenter_username,
               reporter.username AS reporter_username
        FROM comment_reports
        JOIN comments ON comment_reports.comment_id = comments.id
        JOIN users AS commenter ON comments.user_id = commenter.id
        JOIN users AS reporter ON comment_reports.reporter_id = reporter.id
        WHERE comment_reports.status = 'pending'
        ORDER BY comment_reports.created_at ASC
    ");
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function resolveReport($reportId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE comment_reports SET status = 'resolved' WHERE id = ?");
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $stmt->close();
}

function adminDeleteComment($commentId) {
    $db = getDB();

    $stmt = $db->prepare("SELECT user_id FROM comments WHERE id = ?");
    $stmt->bind_param("i", $commentId);
    $stmt->execute();
    $comment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $db->prepare("SELECT 1 FROM comment_reports WHERE comment_id = ?");
    $stmt->bind_param("i", $commentId);
    $stmt->execute();
    $wasReported = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();

    $stmt = $db->prepare("DELETE FROM comments WHERE id = ?");
    $stmt->bind_param("i", $commentId);
    $stmt->execute();
    $stmt->close();

    if ($comment && $wasReported) {
        createNotification((int)$comment['user_id'], 'comment_deleted');
    }
}

function adminDeleteProfileComment(int $commentId): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM profile_comments WHERE id = ?");
    $stmt->bind_param('i', $commentId);
    $stmt->execute();
    $stmt->close();
}

function banUser($userId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET is_banned = 1 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
    createNotification($userId, 'account_banned');
}

function unbanUser($userId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET is_banned = 0 WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

function isUserBanned($userId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT is_banned FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result && (int)$result['is_banned'] === 1;
}

function getAllUsers() {
    $db = getDB();
    $result = $db->query("SELECT id, username, email, is_admin, is_banned, email_verified, scratch_verified_at, phone_verified_at, is_fan, is_moderator, created_at, ip_address FROM users ORDER BY created_at DESC");
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function anonymizeUser($userId) {
    $db = getDB();
    $anonUsername = 'deleted_user_' . $userId;
    $anonEmail = 'deleted_' . $userId . '@deleted.local';
    $unusableHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password_hash = ?, is_banned = 1, email_verified = 0, verification_token = NULL WHERE id = ?");
    $stmt->bind_param("sssi", $anonUsername, $anonEmail, $unusableHash, $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("DELETE FROM likes WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("DELETE FROM dislikes WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

// Find the lowest unused article ID (fills gaps left by moved/deleted articles)
function getNextArticleId(): int {
    $db = getDB();
    $result = $db->query("
        SELECT MIN(t1.id + 1) AS next_id
        FROM articles t1
        LEFT JOIN articles t2 ON t1.id + 1 = t2.id
        WHERE t2.id IS NULL
    ");
    $row = $result->fetch_assoc();
    return $row['next_id'] ? (int)$row['next_id'] : 1;
}

function saveUploadedImage(array $file, string $type = 'articles', int $maxDim = 1600): ?string {
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 3 * 1024 * 1024) throw new RuntimeException('Image must be under 3MB.');

    $allowedTypes = ['articles', 'avatars', 'banners', 'feedback', 'group_banners', 'group_comments'];
    if (!in_array($type, $allowedTypes, true)) $type = 'articles';

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $dir = __DIR__ . '/assets/uploads/' . $type;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if ($ext === 'svg' || in_array($detectedType, ['image/svg+xml', 'text/xml', 'text/html', 'text/plain'], true)) {
        $content = file_get_contents($file['tmp_name']);
        if ($content === false || stripos($content, '<svg') === false) {
            throw new RuntimeException('File is not a valid SVG.');
        }
        $content = sanitizeSvg($content);
        $filename = bin2hex(random_bytes(8)) . '.svg';
        file_put_contents($dir . '/' . $filename, $content);
        return '/assets/uploads/' . $type . '/' . $filename;
    }

    $info = getimagesize($file['tmp_name']);
    if (!$info) throw new RuntimeException('File is not a valid image.');
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$info['mime']])) throw new RuntimeException('Only JPG, PNG, GIF, WEBP, or SVG images are allowed.');
    $filename = bin2hex(random_bytes(8)) . '.' . $allowed[$info['mime']];
    move_uploaded_file($file['tmp_name'], $dir . '/' . $filename);
    resizeImageIfNeeded($dir . '/' . $filename, $info['mime'], $maxDim);
    return '/assets/uploads/' . $type . '/' . $filename;
}

function sanitizeSvg(string $svg): string {
    $svg = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $svg);
    $svg = preg_replace('#<foreignObject\b[^>]*>.*?</foreignObject>#is', '', $svg);
    $svg = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $svg);
    $svg = preg_replace('/(href|xlink:href)\s*=\s*("javascript:[^"]*"|\'javascript:[^\']*\')/i', '$1="#"', $svg);
    return $svg;
}

function deleteUploadedImage(?string $url): void {
    if (!$url) return;
    $path = __DIR__ . $url;
    $uploadsRoot = realpath(__DIR__ . '/assets/uploads');
    if (is_file($path) && $uploadsRoot && strpos(realpath($path), $uploadsRoot) === 0) {
        unlink($path);
    }
}

function getPopularArticles(int $limit = 12): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT a.*, COUNT(l.article_id) AS like_count
         FROM articles a
         LEFT JOIN likes l ON l.article_id = a.id
         WHERE a.status = 'published'
         GROUP BY a.id
         ORDER BY like_count DESC, a.created_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function resizeImageIfNeeded(string $path, string $mime, int $maxDim = 1600): void {
    if (!extension_loaded('gd') || $mime === 'image/gif') return;
    $info = getimagesize($path);
    if (!$info) return;
    [$width, $height] = $info;
    if ($width <= $maxDim && $height <= $maxDim) return;

    $ratio = min($maxDim / $width, $maxDim / $height);
    $newWidth = (int) round($width * $ratio);
    $newHeight = (int) round($height * $ratio);

    switch ($mime) {
        case 'image/jpeg': $src = imagecreatefromjpeg($path); break;
        case 'image/png': $src = imagecreatefrompng($path); break;
        case 'image/webp': $src = imagecreatefromwebp($path); break;
        default: return;
    }
    if (!$src) return;

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    switch ($mime) {
        case 'image/jpeg': imagejpeg($dst, $path, 85); break;
        case 'image/png': imagepng($dst, $path, 6); break;
        case 'image/webp': imagewebp($dst, $path, 85); break;
    }
    imagedestroy($src);
    imagedestroy($dst);
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): bool {
    return !empty($_POST['csrf_token']) && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function requireCsrf(): void {
    if (!verifyCsrf()) {
        http_response_code(403);
        die('Session expired or invalid request. Please refresh the page and try again.');
    }
}

function sendNoCacheHeaders(): void {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function impersonateUser(int $adminId, int $targetUserId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $targetUserId);
    $stmt->execute();
    $target = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$target) return false;

    $stmt = $db->prepare("INSERT INTO impersonation_log (admin_id, target_user_id, started_at) VALUES (?, ?, NOW())");
    $stmt->bind_param('ii', $adminId, $targetUserId);
    $stmt->execute();
    $stmt->close();

    $_SESSION['impersonator_admin_id'] = $adminId;
    $_SESSION['impersonator_admin_username'] = $_SESSION['reader_username'];
    $_SESSION['reader_id'] = $target['id'];
    $_SESSION['reader_username'] = $target['username'];
    $_SESSION['is_admin'] = (bool)$target['is_admin'];
    $_SESSION['is_moderator'] = !empty($target['is_moderator']);
    $_SESSION['dark_mode'] = (bool)$target['dark_mode'];
    $_SESSION['translate_lang'] = $target['translate_lang'] ?? '';
    return true;
}

function stopImpersonation(): bool {
    if (empty($_SESSION['impersonator_admin_id'])) return false;
    $db = getDB();
    $adminId = $_SESSION['impersonator_admin_id'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$admin) return false;

    $_SESSION['reader_id'] = $admin['id'];
    $_SESSION['reader_username'] = $admin['username'];
    $_SESSION['is_admin'] = (bool)$admin['is_admin'];
    $_SESSION['is_moderator'] = !empty($admin['is_moderator']);
    $_SESSION['dark_mode'] = (bool)$admin['dark_mode'];
    $_SESSION['translate_lang'] = $admin['translate_lang'] ?? '';
    unset($_SESSION['impersonator_admin_id'], $_SESSION['impersonator_admin_username']);
    return true;
}

function isDisposableEmail(string $email): bool {
    $blocked = ['gicont.com', 'ezimb.com', 'mailinator.com', 'tempmail.com', '10minutemail.com', 'guerrillamail.com', 'yopmail.com', 'trashmail.com', 'discard.email', 'getnada.com'];
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    return in_array($domain, $blocked, true);
}

function tooManySignupAttempts(string $ip, int $maxAttempts = 5, int $windowMinutes = 10): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM signup_attempts WHERE ip = ? AND created_at > (NOW() - INTERVAL ? MINUTE)");
    $stmt->bind_param('si', $ip, $windowMinutes);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $count >= $maxAttempts;
}

function logSignupAttempt(string $ip, bool $successful = false): void {
    $db = getDB();
    $successInt = $successful ? 1 : 0;
    $stmt = $db->prepare("INSERT INTO signup_attempts (ip, created_at, successful) VALUES (?, NOW(), ?)");
    $stmt->bind_param('si', $ip, $successInt);
    $stmt->execute();
    $stmt->close();
}

function utcTimeTag(string $datetimeUtc, string $style = 'date'): string {
    try {
        $dt = new DateTime($datetimeUtc, new DateTimeZone('UTC'));
    } catch (Exception $e) {
        return e($datetimeUtc);
    }
    $iso = $dt->format('c');
    $class = $style === 'datetime' ? 'local-datetime' : 'local-date';
    return '<time class="' . $class . '" datetime="' . $iso . '">' . e($datetimeUtc) . '</time>';
}

// ── Categories ──────────────────────────────────────────

function getAllCategories(): array {
    $db = getDB();
    return $db->query("SELECT * FROM categories ORDER BY name ASC")->fetch_all(MYSQLI_ASSOC);
}

function getCategoryBySlug(string $slug): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getArticleCategoryIds(int $articleId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT category_id FROM article_categories WHERE article_id = ?");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map('intval', array_column($rows, 'category_id'));
}

function getArticleCategories(int $articleId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT c.* FROM categories c
        JOIN article_categories ac ON ac.category_id = c.id
        WHERE ac.article_id = ?");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Enforces max 3 categories per article regardless of what's passed in
function setArticleCategories(int $articleId, array $categoryIds): void {
    $categoryIds = array_slice(array_unique(array_map('intval', $categoryIds)), 0, 3);
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM article_categories WHERE article_id = ?");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $stmt->close();
    if (empty($categoryIds)) return;
    $stmt = $db->prepare("INSERT INTO article_categories (article_id, category_id) VALUES (?, ?)");
    foreach ($categoryIds as $catId) {
        $stmt->bind_param('ii', $articleId, $catId);
        $stmt->execute();
    }
    $stmt->close();
}

function getArticlesByCategorySlug(string $slug): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT a.* FROM articles a
        JOIN article_categories ac ON ac.article_id = a.id
        JOIN categories c ON c.id = ac.category_id
        WHERE c.slug = ? AND a.status = 'published'
        ORDER BY a.created_at DESC");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Splits a summary into lowercase significant words (len > 3, stopwords
// removed) for lightweight description-overlap scoring - no FULLTEXT index
// needed for a catalog this size.
function extractSignificantWords(string $text): array {
    static $stopwords = ['the','a','an','and','or','but','of','to','in','on','for','with','is','are','was','were',
        'it','this','that','you','your','how','what','why','from','as','at','by','be','will','can','has','have',
        'had','not','all','more','about','into','than','then','them','they','their','our','out','over','use','used'];
    $text = strtolower(strip_tags($text));
    preg_match_all('/[a-z0-9]+/', $text, $m);
    return array_values(array_unique(array_filter($m[0], function($w) use ($stopwords) {
        return strlen($w) > 3 && !in_array($w, $stopwords, true);
    })));
}

// Ranks other published articles against $article by shared categories
// (weighted highest) then shared significant summary words, tie-broken by
// recency. Used to build both the Related (top 3) and Extra Articles
// (next up to 16) sections on article.php - callers just slice the pool.
function getRelatedArticlePool(array $article, int $limit = 19): array {
    $db = getDB();
    $articleId = (int)$article['id'];
    $myCatIds = getArticleCategoryIds($articleId);
    $myWords = extractSignificantWords($article['summary'] ?? '');

    $stmt = $db->prepare("SELECT * FROM articles WHERE status = 'published' AND id != ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (empty($candidates)) return [];

    $catsByArticle = [];
    $catResult = $db->query("SELECT article_id, category_id FROM article_categories");
    while ($row = $catResult->fetch_assoc()) {
        $catsByArticle[(int)$row['article_id']][] = (int)$row['category_id'];
    }

    $scored = [];
    foreach ($candidates as $c) {
        $theirCats = $catsByArticle[(int)$c['id']] ?? [];
        $score = count(array_intersect($myCatIds, $theirCats)) * 3;
        $theirWords = extractSignificantWords($c['summary'] ?? '');
        $score += count(array_intersect($myWords, $theirWords));
        $scored[] = ['article' => $c, 'score' => $score];
    }

    usort($scored, function($a, $b) {
        if ($a['score'] !== $b['score']) return $b['score'] <=> $a['score'];
        return strtotime($b['article']['created_at']) <=> strtotime($a['article']['created_at']);
    });

    return array_slice(array_column($scored, 'article'), 0, $limit);
}

// Renders the hover toolbar (like/dislike/share/comment/profile) used on the
// three big homepage/explore cards. Buttons are <button> not <a> since the
// toolbar sits inside the card's own outer <a> - nested anchors are invalid
// HTML, so navigation/like-toggling is handled by the shared click-delegate
// script in footer.php via data-action attributes.
function renderCardToolbar(array $article, int $likeCount, bool $liked, int $dislikeCount, bool $disliked, int $commentCount): string {
    $readerId = $_SESSION['reader_id'] ?? null;
    $isBanned = $readerId ? (isUserBanned((int)$readerId) || isPhoneVerificationPending((int)$readerId)) : false;
    $disabled = empty($readerId) || $isBanned;
    $articleId = (int)$article['id'];
    $profileUrl = '';
    if (!empty($article['user_id'])) {
        $authorUser = getUserById((int)$article['user_id']);
        if ($authorUser) $profileUrl = '/@' . $authorUser['username'];
    }
    ob_start();
    ?>
    <div class="card-toolbar" data-article-id="<?= $articleId ?>" data-csrf="<?= e(csrfToken()) ?>">
        <button type="button" class="card-toolbar-btn <?= $liked ? 'active' : '' ?>" data-action="like" title="Like" <?= $disabled ? 'disabled' : '' ?>>
            <img src="/assets/icons/<?= $liked ? 'like' : 'unlike' ?>.svg" class="icon-svg-sm" alt="">
            <span class="card-toolbar-count"><?= formatCount($likeCount) ?></span>
        </button>
        <button type="button" class="card-toolbar-btn <?= $disliked ? 'active' : '' ?>" data-action="dislike" title="Dislike" <?= $disabled ? 'disabled' : '' ?>>
            <img src="/assets/icons/<?= $disliked ? 'dislike' : 'undislike' ?>.svg" class="icon-svg-sm" alt="">
            <span class="card-toolbar-count"><?= formatCount($dislikeCount) ?></span>
        </button>
        <button type="button" class="card-toolbar-btn" data-action="share" title="Copy link">
            <img src="/assets/icons/share.svg" class="icon-svg-sm" alt="">
        </button>
        <button type="button" class="card-toolbar-btn" data-action="comment" title="Comments">
            <img src="/assets/icons/comment.svg" class="icon-svg-sm" alt="">
            <span class="card-toolbar-count"><?= formatCount($commentCount) ?></span>
        </button>
        <?php if ($profileUrl !== ''): ?>
        <button type="button" class="card-toolbar-btn" data-action="profile" data-href="<?= e($profileUrl) ?>" title="Writer's profile">
            <img src="/assets/icons/nav-submit.svg" class="icon-svg-sm" alt="">
        </button>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

// Renders the "..." menu used on standard search-result cards (search.php,
// explore.php's list view). Same data-action/data-article-id contract as
// renderCardToolbar() so the shared click-delegate in footer.php handles
// both - just styled as a dropdown instead of a hover bar.
function renderThreeDotMenu(array $article, int $likeCount, bool $liked, int $dislikeCount, bool $disliked): string {
    $readerId = $_SESSION['reader_id'] ?? null;
    $isBanned = $readerId ? (isUserBanned((int)$readerId) || isPhoneVerificationPending((int)$readerId)) : false;
    $disabled = empty($readerId) || $isBanned;
    $articleId = (int)$article['id'];
    $profileUrl = '';
    if (!empty($article['user_id'])) {
        $authorUser = getUserById((int)$article['user_id']);
        if ($authorUser) $profileUrl = '/@' . $authorUser['username'];
    }
    ob_start();
    ?>
    <div class="three-dot-wrap">
        <button type="button" class="three-dot-btn" title="More options">
            <svg viewBox="0 0 24 24" width="18" height="18"><circle cx="5" cy="12" r="2" fill="currentColor"/><circle cx="12" cy="12" r="2" fill="currentColor"/><circle cx="19" cy="12" r="2" fill="currentColor"/></svg>
        </button>
        <div class="three-dot-menu" data-article-id="<?= $articleId ?>" data-csrf="<?= e(csrfToken()) ?>">
            <button type="button" class="card-toolbar-btn <?= $liked ? 'active' : '' ?>" data-action="like" <?= $disabled ? 'disabled' : '' ?>>
                <img src="/assets/icons/<?= $liked ? 'like' : 'unlike' ?>.svg" class="icon-svg-sm" alt="">Like<span class="card-toolbar-count">(<?= formatCount($likeCount) ?>)</span>
            </button>
            <button type="button" class="card-toolbar-btn <?= $disliked ? 'active' : '' ?>" data-action="dislike" <?= $disabled ? 'disabled' : '' ?>>
                <img src="/assets/icons/<?= $disliked ? 'dislike' : 'undislike' ?>.svg" class="icon-svg-sm" alt="">Dislike<span class="card-toolbar-count">(<?= formatCount($dislikeCount) ?>)</span>
            </button>
            <button type="button" class="card-toolbar-btn" data-action="share">
                <img src="/assets/icons/share.svg" class="icon-svg-sm" alt="">Share
            </button>
            <button type="button" class="card-toolbar-btn" data-action="comment">
                <img src="/assets/icons/comment.svg" class="icon-svg-sm" alt="">Comments
            </button>
            <?php if ($profileUrl !== ''): ?>
            <button type="button" class="card-toolbar-btn" data-action="profile" data-href="<?= e($profileUrl) ?>">
                <img src="/assets/icons/nav-submit.svg" class="icon-svg-sm" alt="">Writer's profile
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// ── Views & Trending ────────────────────────────────────

function incrementArticleView(int $articleId): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
    $stmt->bind_param('i', $articleId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("INSERT INTO daily_article_views (view_date, view_count) VALUES (CURDATE(), 1) ON DUPLICATE KEY UPDATE view_count = view_count + 1");
    $stmt->execute();
    $stmt->close();
}

// Score = (views + likes*3 + comments*4) / (age_in_hours + 2)^1.5
// Recency decay means a hot new article can outrank an old high-total one.
function getTrendingArticles(int $limit = 10): array {
    $db = getDB();
    $sql = "SELECT a.*,
        (a.views
            + (SELECT COUNT(*) FROM likes l WHERE l.article_id = a.id) * 3
            + (SELECT COUNT(*) FROM comments c WHERE c.article_id = a.id) * 4
        ) / POWER(TIMESTAMPDIFF(HOUR, a.created_at, NOW()) + 2, 1.5) AS trend_score
        FROM articles a
        WHERE a.status = 'published'
        ORDER BY trend_score DESC
        LIMIT ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getExploreArticles(string $categorySlug, string $sort, string $authorFilter = '', string $dateFrom = '', string $dateTo = ''): array {
    $db = getDB();
    $joins = '';
    $where = "a.status = 'published'";
    $params = [];
    $types = '';

    if ($categorySlug !== 'all') {
        $joins = "JOIN article_categories ac ON ac.article_id = a.id JOIN categories c ON c.id = ac.category_id";
        $where .= " AND c.slug = ?";
        $params[] = $categorySlug;
        $types .= 's';
    }

    if ($authorFilter !== '') {
        $where .= " AND a.author LIKE ?";
        $params[] = '%' . $authorFilter . '%';
        $types .= 's';
    }

    if ($dateFrom !== '') {
        $where .= " AND a.created_at >= ?";
        $params[] = $dateFrom . ' 00:00:00';
        $types .= 's';
    }

    if ($dateTo !== '') {
        $where .= " AND a.created_at <= ?";
        $params[] = $dateTo . ' 23:59:59';
        $types .= 's';
    }

    $likeExpr = "(SELECT COUNT(*) FROM likes l WHERE l.article_id = a.id)";
    $dislikeExpr = "(SELECT COUNT(*) FROM dislikes d WHERE d.article_id = a.id)";
    $commentExpr = "(SELECT COUNT(*) FROM comments cm WHERE cm.article_id = a.id)";
    $trendExpr = "(a.views + $likeExpr*3 + $commentExpr*4) / POWER(TIMESTAMPDIFF(HOUR, a.created_at, NOW()) + 2, 1.5)";

    switch ($sort) {
        case 'recent': $orderBy = "a.created_at DESC"; break;
        case 'popular': $orderBy = "a.views DESC, a.created_at DESC"; break;
        case 'most_liked': $orderBy = "$likeExpr DESC, a.created_at DESC"; break;
        case 'most_disliked': $orderBy = "$dislikeExpr DESC, a.created_at DESC"; break;
        case 'oldest': $orderBy = "a.created_at ASC"; break;
        default: $orderBy = "$trendExpr DESC"; break; // 'metrics'
    }

    $sql = "SELECT a.* FROM articles a $joins WHERE $where ORDER BY $orderBy";
    $stmt = $db->prepare($sql);
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ---- API keys & rate limiting ----

function generateApiKey(string $label, ?int $rateLimitPerMinute = null): string {
    $db = getDB();
    $key = bin2hex(random_bytes(24));
    $hash = hash('sha256', $key);
    $stmt = $db->prepare("INSERT INTO api_keys (key_hash, label, rate_limit_per_minute) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $hash, $label, $rateLimitPerMinute);
    $stmt->execute();
    $stmt->close();
    return $key; // only returned once — only the hash is stored
}

function revokeApiKey(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM api_keys WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

function setApiKeyRateLimit(int $id, ?int $rateLimitPerMinute): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE api_keys SET rate_limit_per_minute = ? WHERE id = ?");
    $stmt->bind_param('ii', $rateLimitPerMinute, $id);
    $stmt->execute();
    $stmt->close();
}

function getApiKeyByToken(string $token): ?array {
    $db = getDB();
    $hash = hash('sha256', $token);
    $stmt = $db->prepare("SELECT * FROM api_keys WHERE key_hash = ?");
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getApiSetting(string $key, string $default = ''): string {
    $db = getDB();
    $stmt = $db->prepare("SELECT setting_value FROM api_settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? $row['setting_value'] : $default;
}

function setApiSetting(string $key, string $value): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO api_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

// Returns true if allowed, false if this identifier should be rate-limited.
function checkAndLogApiRequest(string $identifier, ?int $limitPerMinute): bool {
    $db = getDB();

    if ($limitPerMinute !== null) {
        $stmt = $db->prepare("SELECT COUNT(*) AS c FROM api_requests WHERE identifier = ? AND requested_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
        $stmt->bind_param('s', $identifier);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        if ($count >= $limitPerMinute) return false;
    }

    $stmt = $db->prepare("INSERT INTO api_requests (identifier, requested_at) VALUES (?, NOW())");
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    $stmt->close();

    $db->query("DELETE FROM api_requests WHERE requested_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    return true;
}

function requireApiAccess(): void {
    if (getApiSetting('rate_limiting_enabled', '1') === '0') {
        return; // kill switch: fully open, no limits at all
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? '') : '');
    $token = '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        $token = trim($m[1]);
    }

    if ($token !== '') {
        $key = getApiKeyByToken($token);
        if (!$key) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }
        $identifier = 'key:' . $key['id'];
        $limit = $key['rate_limit_per_minute'] !== null ? (int)$key['rate_limit_per_minute'] : null;
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $identifier = 'ip:' . $ip;
        $default = (int)getApiSetting('anonymous_rate_limit', '30');
        $limit = $default > 0 ? $default : null;
    }

    if (!checkAndLogApiRequest($identifier, $limit)) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Rate limit exceeded, slow down']);
        exit;
    }
}

function getEngagementCounts(): array {
    $db = getDB();
    $counts = [];
    foreach (['likes' => 'like_count', 'dislikes' => 'dislike_count', 'comments' => 'comment_count'] as $table => $key) {
        $rows = $db->query("SELECT article_id, COUNT(*) AS c FROM $table GROUP BY article_id")->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $row) {
            $counts[(int)$row['article_id']][$key] = (int)$row['c'];
        }
    }
    return $counts;
}

// Best-effort: never throws, so a GitHub/network hiccup can't break article save/approve/delete.
function syncToGithub(): array {
    try {
        $engagement = getEngagementCounts();
        $articles = array_map(function ($a) use ($engagement) {
            $formatted = formatArticleForApi($a);
            $id = $formatted['id'];
            $formatted['likes'] = $engagement[$id]['like_count'] ?? 0;
            $formatted['dislikes'] = $engagement[$id]['dislike_count'] ?? 0;
            $formatted['comments'] = $engagement[$id]['comment_count'] ?? 0;
            return $formatted;
        }, getAllArticles());

        $articlesPayload = ['data' => $articles, 'total' => count($articles), 'synced_at' => gmdate('c')];
        $categoriesPayload = ['data' => getAllCategories(), 'synced_at' => gmdate('c')];

        return [
            'articles.json' => pushJsonToGithub('data/articles.json', $articlesPayload),
            'categories.json' => pushJsonToGithub('data/categories.json', $categoriesPayload),
        ];
    } catch (\Throwable $e) {
        return ['error' => $e->getMessage()];
    }
}


function formatArticleForApi(array $article): array {
    return [
        'id' => (int)$article['id'],
        'title' => $article['title'],
        'summary' => $article['summary'],
        'content' => $article['content'],
        'image_url' => $article['image_url'],
        'author' => $article['author'],
        'created_at' => $article['created_at'],
        'updated_at' => $article['updated_at'],
        'featured' => (bool)($article['is_featured'] ?? 0),
        'views' => (int)$article['views'],
        'likes' => getLikeCount((int)$article['id']),
        'dislikes' => getDislikeCount((int)$article['id']),
        'comments' => getCommentCount((int)$article['id']),
        'categories' => array_map(function ($c) {
            return ['id' => (int)$c['id'], 'name' => $c['name'], 'slug' => $c['slug']];
        }, getArticleCategories((int)$article['id'])),
    ];
}

function isFollowing(int $followerId, int $followedId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM follows WHERE follower_id = ? AND followed_id = ?");
    $stmt->bind_param('ii', $followerId, $followedId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $exists;
}

// Public-safe user listing for /profiles (v0.23). Only non-sensitive fields -
// no email/ip/is_banned like getAllUsers(), which is admin-only.
function getPublicUserList(string $sort = 'recent'): array {
    $db = getDB();
    if ($sort === 'followers') {
        $sql = "SELECT u.id, u.username, u.avatar_url, u.bio, u.created_at, u.is_admin, u.is_moderator, u.is_fan,
                       (SELECT COUNT(*) FROM follows f WHERE f.followed_id = u.id) AS follower_count
                FROM users u
                WHERE u.is_banned = 0 AND u.username NOT LIKE 'deleted_user_%'
                ORDER BY follower_count DESC, u.created_at DESC";
    } else {
        $sql = "SELECT u.id, u.username, u.avatar_url, u.bio, u.created_at, u.is_admin, u.is_moderator, u.is_fan,
                       (SELECT COUNT(*) FROM follows f WHERE f.followed_id = u.id) AS follower_count
                FROM users u
                WHERE u.is_banned = 0 AND u.username NOT LIKE 'deleted_user_%'
                ORDER BY u.created_at DESC";
    }
    $result = $db->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function followUser(int $followerId, int $followedId): void {
    if ($followerId === $followedId) return;
    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO follows (follower_id, followed_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $followerId, $followedId);
    $stmt->execute();
    $stmt->close();
    createNotification($followedId, 'follow', $followerId, '/@' . getUserById($followerId)['username']);
}

function getFollowerIds(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT follower_id FROM follows WHERE followed_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return array_map(fn($r) => (int)$r['follower_id'], $rows);
}

function unfollowUser(int $followerId, int $followedId): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM follows WHERE follower_id = ? AND followed_id = ?");
    $stmt->bind_param('ii', $followerId, $followedId);
    $stmt->execute();
    $stmt->close();
}

function getFollowerCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM follows WHERE followed_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count;
}

function getProfileCommentCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM profile_comments WHERE profile_user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count;
}

function getProfileComments(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT pc.*, u.username AS author_username, u.avatar_url AS author_avatar
         FROM profile_comments pc
         JOIN users u ON u.id = pc.author_id
         WHERE pc.profile_user_id = ?
         ORDER BY pc.created_at DESC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function addProfileComment(int $profileUserId, int $authorId, string $content, ?int $parentId = null): int {
    $db = getDB();
    if ($parentId === null) {
        $stmt = $db->prepare("INSERT INTO profile_comments (profile_user_id, author_id, content) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $profileUserId, $authorId, $content);
    } else {
        $stmt = $db->prepare("INSERT INTO profile_comments (profile_user_id, author_id, content, parent_comment_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iisi', $profileUserId, $authorId, $content, $parentId);
    }
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    $link = '/@' . getUserById($profileUserId)['username'];
    $excludeFromMentions = [];
    if ($parentId !== null) {
        $pstmt = $db->prepare("SELECT author_id FROM profile_comments WHERE id = ?");
        $pstmt->bind_param('i', $parentId);
        $pstmt->execute();
        $parentOwner = $pstmt->get_result()->fetch_assoc();
        $pstmt->close();
        if ($parentOwner && (int)$parentOwner['author_id'] !== $authorId) {
            createNotification((int)$parentOwner['author_id'], 'comment_reply', $authorId, $link, $content);
            $excludeFromMentions[] = (int)$parentOwner['author_id'];
        }
    } elseif ($profileUserId !== $authorId) {
        createNotification($profileUserId, 'profile_comment', $authorId, $link, $content);
        $excludeFromMentions[] = $profileUserId;
    }
    notifyMentions($content, $authorId, $link, $excludeFromMentions);
    notifyAdmins('admin_new_comment', $authorId, $link, $content);

    return $id;
}

function renderProfileCommentThread(array $comment, bool $canReply, int $profileUserId, int $depth = 0): string {
    $indent = min($depth * 14, 56);
    $avatar = $comment['author_avatar'] ?? null;
    $topClass = $depth === 0 ? ' comment-top' : '';
    $html = '<div class="comment' . $topClass . '" style="margin-left: ' . $indent . 'px;">';
    $html .= '<div class="comment-header">';
    $html .= renderCommentAvatar($avatar, $comment['author_username']);
    $html .= '<a href="/@' . e($comment['author_username']) . '"><strong>@' . e($comment['author_username']) . '</strong></a>';
    $html .= renderRankBadges((int)$comment['author_id']);
    $html .= ' <span class="meta">' . date('M j, Y g:i A', strtotime($comment['created_at'])) . '</span>';
    $html .= '</div>';
    $html .= '<p>' . linkifyMentions(e($comment['content'])) . '</p>';

    if (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator'])) {
        $html .= '<form method="post" action="/profile-comment" class="report-form" onsubmit="return confirm(\'Delete this comment?\');">';
        $html .= csrfField();
        $html .= '<input type="hidden" name="action" value="admin_delete">';
        $html .= '<input type="hidden" name="comment_id" value="' . (int)$comment['id'] . '">';
        $html .= '<button type="submit" class="reply-toggle" title="Delete"><img src="/assets/icons/comment_delete.svg" class="icon-svg-sm" alt=""> Delete</button>';
        $html .= '</form>';
    }

    if ($canReply) {
        $formId = 'pc-reply-form-' . (int)$comment['id'];
        $html .= '<button type="button" class="reply-toggle" title="Reply" onclick="document.getElementById(\'' . $formId . '\').classList.toggle(\'open\')"><img src="/assets/icons/reply.svg" class="icon-svg-sm" alt=""> Reply</button>';
        $html .= '<form method="post" action="/profile-comment" class="reply-form" id="' . $formId . '">';
        $html .= csrfField();
        $html .= '<input type="hidden" name="profile_user_id" value="' . (int)$profileUserId . '">';
        $html .= '<input type="hidden" name="parent_id" value="' . (int)$comment['id'] . '">';
        $html .= '<textarea name="content" maxlength="1000" placeholder="Write a reply..." required></textarea>';
        $html .= '<button class="btn" type="submit">Post Reply</button>';
        $html .= '</form>';
    }

    foreach ($comment['replies'] as $reply) {
        $html .= renderProfileCommentThread($reply, $canReply, $profileUserId, $depth + 1);
    }

    $html .= '</div>';
    return $html;
}

// ---- Username changes ----
function canChangeUsername(array $user): array {
    $last = $user['last_username_change'] ?? null;
    if (!$last) return ['allowed' => true, 'next_at' => null];
    $nextAt = strtotime($last) + 7 * 86400;
    if (time() >= $nextAt) return ['allowed' => true, 'next_at' => null];
    return ['allowed' => false, 'next_at' => date('M j, Y', $nextAt)];
}

function changeUsername(int $userId, string $newUsername): string {
    $newUsername = trim($newUsername);
    if (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $newUsername)) return 'invalid';

    $user = getUserById($userId);
    if (!$user) return 'invalid';
    if (strcasecmp($user['username'], $newUsername) === 0) return 'unchanged';

    $check = canChangeUsername($user);
    if (!$check['allowed']) return 'too_soon';

    $db = getDB();
    try {
        $stmt = $db->prepare("UPDATE users SET username = ?, last_username_change = NOW() WHERE id = ?");
        $stmt->bind_param('si', $newUsername, $userId);
        $stmt->execute();
        $stmt->close();
        return 'ok';
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) return 'duplicate';
        throw $e;
    }
}

// ---- Saved articles ----
function isArticleSaved(int $articleId, int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM saved_articles WHERE article_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $articleId, $userId);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    return $exists;
}

function saveArticleForUser(int $articleId, int $userId): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO saved_articles (user_id, article_id) VALUES (?, ?)");
    $stmt->bind_param('ii', $userId, $articleId);
    $stmt->execute();
    $stmt->close();

    $article = getArticleById($articleId);
    if ($article && !empty($article['user_id']) && (int)$article['user_id'] !== $userId) {
        createNotification((int)$article['user_id'], 'article_saved', $userId, '/article/' . $articleId);
    }
}

function unsaveArticleForUser(int $articleId, int $userId): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM saved_articles WHERE user_id = ? AND article_id = ?");
    $stmt->bind_param('ii', $userId, $articleId);
    $stmt->execute();
    $stmt->close();
}

function getSavedArticlesByUser(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT articles.* FROM saved_articles
         JOIN articles ON articles.id = saved_articles.article_id
         WHERE saved_articles.user_id = ?
         ORDER BY saved_articles.created_at DESC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function updateUserProfile(int $userId, ?string $avatarUrl, ?string $bannerUrl, ?string $bio): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET avatar_url = ?, banner_url = ?, bio = ? WHERE id = ?");
    $stmt->bind_param('sssi', $avatarUrl, $bannerUrl, $bio, $userId);
    $stmt->execute();
    $stmt->close();
}

function updateUserLocation(int $userId, ?float $lat, ?float $lng, ?string $countryCode, ?string $regionName): void {
    $db = getDB();
    $shared = ($lat !== null && $lng !== null) ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET latitude = ?, longitude = ?, country_code = ?, region_name = ?, location_shared = ? WHERE id = ?");
    $stmt->bind_param('ddssii', $lat, $lng, $countryCode, $regionName, $shared, $userId);
    $stmt->execute();
    $stmt->close();
}

// ---- Notifications ----
// NOTE: icon filenames below are placed based on the new icons provided this session
// (follow, new_article, article_approved, article_rejected, comment_delete, ban)
// plus reuse of existing icons (reply.svg, report.svg, save.svg confirmed in code;
// like.svg/dislike.svg assumed to exist from the v0.12 Dislikes feature - adjust below if wrong).
const NOTIFICATION_ICONS = [
    'follow'                 => '/assets/icons/follow.svg',
    'article_comment'        => '/assets/icons/reply.svg',
    'profile_comment'        => '/assets/icons/reply.svg',
    'comment_reply'          => '/assets/icons/reply.svg',
    'mention'                => '/assets/icons/mention.svg',
    'article_liked'          => '/assets/icons/like.svg',
    'article_disliked'       => '/assets/icons/dislike.svg',
    'article_saved'          => '/assets/icons/save.svg',
    'article_approved'       => '/assets/icons/article_approved.svg',
    'article_rejected'       => '/assets/icons/article_rejected.svg',
    'followed_user_article'  => '/assets/icons/article_inbox.svg',
    'account_banned'         => '/assets/icons/ban.svg',
    'comment_deleted'        => '/assets/icons/comment_delete.svg',
    'admin_new_account'      => '/assets/icons/message.svg',
    'admin_new_comment'      => '/assets/icons/reply.svg',
    'admin_new_report'       => '/assets/icons/report.svg',
    'admin_new_submission'   => '/assets/icons/message.svg',
    'admin_new_feedback'     => '/assets/icons/message.svg',
    'feedback_reply'         => '/assets/icons/reply.svg',
    // group_invite.svg (SN_Groups icon) is also slated to replace nav-profiles.svg
    // once Groups fully ships and Profiles folds into it - not done yet, still beta.
    'group_invite'           => '/assets/icons/group_invite.svg',
    'group_member_joined'    => '/assets/icons/group_activity.svg',
    'group_member_promoted'  => '/assets/icons/group_activity.svg',
    'group_new_comment'      => '/assets/icons/group_activity.svg',
];

function createNotification(int $userId, string $type, ?int $actorId = null, ?string $link = null, ?string $message = null): void {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, actor_id, link, message) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('isiss', $userId, $type, $actorId, $link, $message);
    $stmt->execute();
    $stmt->close();
}

function notifyAdmins(string $type, ?int $actorId = null, ?string $link = null, ?string $message = null): void {
    $db = getDB();
    $result = $db->query("SELECT id FROM users WHERE is_admin = 1");
    while ($row = $result->fetch_assoc()) {
        if ($actorId !== null && (int)$row['id'] === $actorId) continue;
        createNotification((int)$row['id'], $type, $actorId, $link, $message);
    }
}

function getNotificationsForUser(int $userId, int $limit = 50): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT n.*, u.username AS actor_username, u.avatar_url AS actor_avatar
         FROM notifications n
         LEFT JOIN users u ON u.id = n.actor_id
         WHERE n.user_id = ?
         ORDER BY n.created_at DESC
         LIMIT ?"
    );
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getUnreadNotificationCount(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
    return (int)$count;
}

function markAllNotificationsRead(int $userId): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function renderNotificationText(array $n): string {
    if (!empty($n['actor_username'])) {
        $actor = '<a href="/@' . e($n['actor_username']) . '"><strong>' . e($n['actor_username']) . '</strong></a>';
    } elseif (!empty($n['actor_id'])) {
        // actor_id is set but the join found no matching user - the account was deleted
        // since this notification was created. Don't fall back to 'ScratchNews', that's
        // misleading (implies the system did it, not a since-deleted user).
        $actor = 'a deleted account';
    } else {
        $actor = 'ScratchNews';
    }
    switch ($n['type']) {
        case 'follow': return $actor . ' followed you';
        case 'article_comment': return $actor . ' commented on your article';
        case 'profile_comment': return $actor . ' commented on your profile';
        case 'comment_reply': return $actor . ' replied to your comment';
        case 'mention': return $actor . ' mentioned you in a comment';
        case 'article_liked': return $actor . ' liked your article';
        case 'article_disliked': return $actor . ' disliked your article';
        case 'article_saved': return $actor . ' saved your article';
        case 'article_approved': return 'Your article was approved';
        case 'article_rejected': return 'Your article was rejected';
        case 'followed_user_article': return $actor . ' published a new article';
        case 'account_banned': return 'Your account was banned';
        case 'comment_deleted': return 'Your comment was removed for violating community guidelines';
        case 'admin_new_account': return 'New account ' . $actor . ' was created';
        case 'admin_new_comment': return 'New comment was made by ' . $actor;
        case 'admin_new_report': return 'New comment report submitted';
        case 'admin_new_submission': return 'New article submission from ' . $actor;
        case 'admin_new_feedback': return 'New feedback submitted';
        case 'feedback_reply': return 'ScratchNews replied to your feedback';
        case 'group_member_joined': return $actor . ' joined a group you\'re in';
        case 'group_member_promoted': return $actor . ' was promoted to manager';
        case 'group_new_comment': return $actor . ' commented in a group you\'re in';
        default: return 'New notification';
    }
}
// ---- Comment Moderation (local keyword/pattern filter) ----
// Modeled on Scratch's actual Community Guidelines: no swearing/rude language,
// no bullying/insults, no NFE content (gore/violence/sexual/self-harm), no
// personal info sharing, no spam/scam links. Words live in the
// `moderation_words` table, managed from /admin/moderation-words.
// Regex patterns stay hardcoded (too technical for a simple word-list UI).

const MODERATION_CATEGORIES = ['profanity', 'sexual', 'violence_selfharm'];

const MODERATION_PATTERNS = [
    '/\b[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}\b/i',          // email addresses
    '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/',                     // phone numbers
    '/\b(free\s?robux|free\s?nitro|steam\s?gift)\b/i',        // common scam bait
];

const MODERATION_LOCK_TIERS = [
    1 => 3600,        // 1 hour
    2 => 14400,       // 4 hours
    3 => 43200,       // 12 hours
    4 => 86400,       // 1 day
    5 => 259200,      // 3 days
];
const MODERATION_BAN_STRIKE = 6;

function getModerationWords(?string $category = null): array {
    $db = getDB();
    if ($category !== null) {
        $stmt = $db->prepare("SELECT id, category, word FROM moderation_words WHERE category = ? ORDER BY word ASC");
        $stmt->bind_param('s', $category);
    } else {
        $stmt = $db->prepare("SELECT id, category, word FROM moderation_words ORDER BY category ASC, word ASC");
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function addModerationWord(string $category, string $word): bool {
    if (!in_array($category, MODERATION_CATEGORIES, true)) return false;
    $word = strtolower(trim($word));
    if ($word === '') return false;
    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO moderation_words (category, word) VALUES (?, ?)");
    $stmt->bind_param('ss', $category, $word);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function removeModerationWord(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM moderation_words WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

function resetModerationStrikes(int $userId): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET moderation_strikes = 0, comment_locked_until = NULL WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function moderateText(string $text): array {
    static $wordsByCategory = null;
    if ($wordsByCategory === null) {
        $wordsByCategory = [];
        foreach (getModerationWords() as $row) {
            $wordsByCategory[$row['category']][] = $row['word'];
        }
    }

    $normalized = strtolower($text);
    $flaggedCategories = [];

    foreach ($wordsByCategory as $category => $words) {
        foreach ($words as $word) {
            if (str_contains($normalized, $word)) {
                $flaggedCategories[] = $category;
                break;
            }
        }
    }

    foreach (MODERATION_PATTERNS as $pattern) {
        if (preg_match($pattern, $text)) {
            $flaggedCategories[] = 'personal_info_or_spam';
            break;
        }
    }

    $flaggedCategories = array_unique($flaggedCategories);
    return ['flagged' => !empty($flaggedCategories), 'categories' => $flaggedCategories];
}

function getCommentLockStatus(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT comment_locked_until FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $until = $row['comment_locked_until'] ?? null;
    if ($until && strtotime($until) > time()) {
        return ['locked' => true, 'until' => $until];
    }
    return ['locked' => false, 'until' => null];
}

function recordModerationStrike(int $userId): string {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET moderation_strikes = moderation_strikes + 1 WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("SELECT moderation_strikes FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $strikes = (int)($stmt->get_result()->fetch_assoc()['moderation_strikes'] ?? 1);
    $stmt->close();

    if ($strikes >= MODERATION_BAN_STRIKE) {
        banUser($userId);
        return 'Your account has been banned for repeated community guideline violations.';
    }

    $seconds = MODERATION_LOCK_TIERS[$strikes] ?? end(MODERATION_LOCK_TIERS);
    $until = date('Y-m-d H:i:s', time() + $seconds);
    $stmt = $db->prepare("UPDATE users SET comment_locked_until = ? WHERE id = ?");
    $stmt->bind_param('si', $until, $userId);
    $stmt->execute();
    $stmt->close();

    return "Your comment violates ScratchNews's community guidelines. You can comment again after " . $until . '.';
}

// Call this BEFORE addComment()/addProfileComment(). If ['allowed'=>false], show
// $result['reason'] to the user instead of inserting the comment.
function checkAndModerateComment(int $userId, string $text): array {
    $lock = getCommentLockStatus($userId);
    if ($lock['locked']) {
        return ['allowed' => false, 'reason' => 'You are temporarily blocked from commenting until ' . $lock['until'] . '.'];
    }

    $mod = moderateText($text);
    if ($mod['flagged']) {
        $reason = recordModerationStrike($userId);
        return ['allowed' => false, 'reason' => $reason];
    }

    return ['allowed' => true, 'reason' => null];
}

// ---- Admin nav badge counts ----
function getPendingReportsCount(): int {
    $db = getDB();
    $result = $db->query("SELECT COUNT(*) AS cnt FROM comment_reports WHERE status = 'pending'");
    return (int)($result->fetch_assoc()['cnt'] ?? 0);
}

function getPendingSubmissionsCount(): int {
    $db = getDB();
    $result = $db->query("SELECT COUNT(*) AS cnt FROM submissions WHERE status = 'pending'");
    return (int)($result->fetch_assoc()['cnt'] ?? 0);
}

function getPendingFeedbackCount(): int {
    $db = getDB();
    $result = $db->query("SELECT COUNT(*) AS cnt FROM feedback WHERE is_read = 0");
    return (int)($result->fetch_assoc()['cnt'] ?? 0);
}

function markAllFeedbackRead(): void {
    $db = getDB();
    $db->query("UPDATE feedback SET is_read = 1 WHERE is_read = 0");
}

function deleteFeedback(int $id): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}
function getActiveBanners(): array {
    $db = getDB();
    return $db->query("SELECT * FROM banners WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
}

function getRandomActiveBanner(): ?array {
    $banners = getActiveBanners();
    if (empty($banners)) return null;
    $totalWeight = 0;
    foreach ($banners as $b) $totalWeight += max(0, (int)$b['sort_order']);
    if ($totalWeight <= 0) {
        return $banners[array_rand($banners)];
    }
    $rand = mt_rand(1, $totalWeight);
    $cumulative = 0;
    foreach ($banners as $b) {
        $cumulative += max(0, (int)$b['sort_order']);
        if ($rand <= $cumulative) return $b;
    }
    return $banners[count($banners) - 1];
}

function getAllBanners(): array {
    $db = getDB();
    return $db->query("SELECT * FROM banners ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
}

function getBannerById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM banners WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function createBanner(string $imageUrl, ?string $text, string $link, int $sortOrder = 0): int {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO banners (image_url, text, link, sort_order) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('sssi', $imageUrl, $text, $link, $sortOrder);
    $stmt->execute();
    $id = $db->insert_id;
    $stmt->close();
    return $id;
}

function updateBanner(int $id, string $imageUrl, ?string $text, string $link, int $sortOrder, bool $isActive): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE banners SET image_url = ?, text = ?, link = ?, sort_order = ?, is_active = ? WHERE id = ?");
    $active = $isActive ? 1 : 0;
    $stmt->bind_param('sssiii', $imageUrl, $text, $link, $sortOrder, $active, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function deleteBanner(int $id): bool {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM banners WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// ---- Polls ----
function getActivePolls(): array {
    $db = getDB();
    return $db->query("SELECT * FROM polls WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
}

function getAllPolls(): array {
    $db = getDB();
    return $db->query("SELECT * FROM polls ORDER BY sort_order ASC, id ASC")->fetch_all(MYSQLI_ASSOC);
}

function getPollById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM polls WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getPollOptions(int $pollId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM poll_options WHERE poll_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->bind_param('i', $pollId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function createPoll(string $question, string $pollType, array $optionTexts, int $sortOrder = 0): int {
    $pollType = $pollType === 'multi' ? 'multi' : 'single';
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO polls (question, poll_type, sort_order) VALUES (?, ?, ?)");
    $stmt->bind_param('ssi', $question, $pollType, $sortOrder);
    $stmt->execute();
    $pollId = $db->insert_id;
    $stmt->close();

    $optStmt = $db->prepare("INSERT INTO poll_options (poll_id, option_text, sort_order) VALUES (?, ?, ?)");
    $i = 0;
    foreach ($optionTexts as $text) {
        $text = trim($text);
        if ($text === '') continue;
        $optStmt->bind_param('isi', $pollId, $text, $i);
        $optStmt->execute();
        $i++;
    }
    $optStmt->close();
    return $pollId;
}

function updatePoll(int $id, string $question, string $pollType, int $sortOrder, bool $isActive): bool {
    $pollType = $pollType === 'multi' ? 'multi' : 'single';
    $db = getDB();
    $stmt = $db->prepare("UPDATE polls SET question = ?, poll_type = ?, sort_order = ?, is_active = ? WHERE id = ?");
    $active = $isActive ? 1 : 0;
    $stmt->bind_param('ssiii', $question, $pollType, $sortOrder, $active, $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function deletePoll(int $id): bool {
    // poll_options/poll_votes/poll_voter_log rows for this poll are cleaned up
    // via ON DELETE CASCADE (see poll_schema.sql) - no manual cleanup needed here.
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM polls WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// Whether $voterSid (the visitor's share-id cookie, see ensureShareId()) has
// already voted on this poll.
function hasVotedOnPoll(int $pollId, string $voterSid): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT 1 FROM poll_voter_log WHERE poll_id = ? AND voter_sid = ?");
    $stmt->bind_param('is', $pollId, $voterSid);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

// Records $voterSid's vote(s) for $pollId. For single-choice polls only the first
// valid option id in $optionIds is used. Returns false if this voter already voted,
// the poll doesn't exist/is inactive, or none of $optionIds actually belong to this
// poll - true on success.
function submitPollVote(int $pollId, array $optionIds, string $voterSid): bool {
    $poll = getPollById($pollId);
    if (!$poll || !$poll['is_active']) return false;
    if (hasVotedOnPoll($pollId, $voterSid)) return false;

    $validOptionIds = array_column(getPollOptions($pollId), 'id');
    $optionIds = array_values(array_intersect(array_map('intval', $optionIds), $validOptionIds));
    if (empty($optionIds)) return false;
    if ($poll['poll_type'] === 'single') $optionIds = [$optionIds[0]];

    $db = getDB();
    // Claims the vote first - the unique key on (poll_id, voter_sid) means a
    // double-submit race can't slip two vote sets past this insert.
    $logStmt = $db->prepare("INSERT INTO poll_voter_log (poll_id, voter_sid) VALUES (?, ?)");
    $logStmt->bind_param('is', $pollId, $voterSid);
    if (!$logStmt->execute()) { $logStmt->close(); return false; } // duplicate key = already voted
    $logStmt->close();

    $voteStmt = $db->prepare("INSERT INTO poll_votes (poll_id, option_id, voter_sid) VALUES (?, ?, ?)");
    foreach ($optionIds as $optionId) {
        $voteStmt->bind_param('iis', $pollId, $optionId, $voterSid);
        $voteStmt->execute();
    }
    $voteStmt->close();
    return true;
}

// Vote counts per option. Callers must check is_admin/is_moderator themselves
// before calling this - see moderator.php's Poll Results section.
function getPollResults(int $pollId): array {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT po.id, po.option_text, COUNT(pv.id) AS votes
        FROM poll_options po
        LEFT JOIN poll_votes pv ON pv.option_id = po.id
        WHERE po.poll_id = ?
        GROUP BY po.id, po.option_text, po.sort_order
        ORDER BY po.sort_order ASC, po.id ASC
    ");
    $stmt->bind_param('i', $pollId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getPollVoterCount(int $pollId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM poll_voter_log WHERE poll_id = ?");
    $stmt->bind_param('i', $pollId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0);
}

// Decides what shows in the banner/poll slot for the current visitor: one
// weighted-random draw across active banners AND active polls together (banner
// sort_order and poll sort_order are directly comparable weights in the same pool -
// admin/banners.php and admin/polls.php both frame it as "higher = shown more
// often"). If a poll wins the draw, this does NOT show that specific poll - it
// shows the next active poll (by sort_order) that this visitor (by share-id
// cookie) hasn't voted on yet, so a visitor works through active polls in order
// across page loads instead of bouncing between them. If every active poll has
// already been voted on by this visitor, falls back to a banner (or null).
// Returns ['type'=>'poll','poll'=>array] | ['type'=>'banner','banner'=>array] | null.
function getBannerOrPollSlot(): ?array {
    $banners = getActiveBanners();
    $polls = getActivePolls();
    if (empty($banners) && empty($polls)) return null;

    $pool = [];
    foreach ($banners as $b) $pool[] = ['type' => 'banner', 'data' => $b, 'weight' => max(0, (int)$b['sort_order'])];
    foreach ($polls as $p) $pool[] = ['type' => 'poll', 'data' => $p, 'weight' => max(0, (int)$p['sort_order'])];

    $totalWeight = array_sum(array_column($pool, 'weight'));
    if ($totalWeight <= 0) {
        $winner = $pool[array_rand($pool)];
    } else {
        $rand = mt_rand(1, $totalWeight);
        $cumulative = 0;
        $winner = $pool[count($pool) - 1];
        foreach ($pool as $entry) {
            $cumulative += $entry['weight'];
            if ($rand <= $cumulative) { $winner = $entry; break; }
        }
    }

    if ($winner['type'] === 'banner') return ['type' => 'banner', 'banner' => $winner['data']];

    $voterSid = ensureShareId();
    foreach ($polls as $p) {
        if (!hasVotedOnPoll((int)$p['id'], $voterSid)) {
            $p['options'] = getPollOptions((int)$p['id']);
            return ['type' => 'poll', 'poll' => $p];
        }
    }
    return empty($banners) ? null : ['type' => 'banner', 'banner' => $banners[array_rand($banners)]];
}
// ==================== v0.24 Groups (beta, IP-restricted) ====================

const GROUP_MAX_PER_USER = 5;

// True while Groups is in beta: only GROUPS_BETA_IPS (comma-separated in config.php,
// gitignored/server-only) or a logged-in admin can access it. Everyone else gets a
// "Work in progress" notice. Flip GROUPS_BETA_IPS to '' (or remove the check) to launch.
function isGroupsBetaAllowed(): bool {
    // Groups exited beta and is public to all users as of this change.
    // Left in place (rather than removed) so callers don't need touching if it's ever
    // useful again; GROUPS_BETA_IPS is now unused but harmless.
    return true;
}

function slugifyGroupName(string $name): string {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'group';
}

function generateUniqueGroupSlug(string $name): string {
    $db = getDB();
    $base = slugifyGroupName($name);
    $slug = $base;
    $i = 2;
    while (true) {
        $stmt = $db->prepare("SELECT 1 FROM `groups` WHERE slug = ?");
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if (!$exists) return $slug;
        $slug = $base . '-' . $i;
        $i++;
    }
}

function countUserOwnedGroups(int $userId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM group_members gm JOIN `groups` g ON g.id = gm.group_id WHERE gm.user_id = ? AND gm.role = 'host' AND g.status = 'active'");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count;
}

function canUserOwnAnotherGroup(int $userId): bool {
    return countUserOwnedGroups($userId) < GROUP_MAX_PER_USER;
}

// ---- Group requests (create/edit/delete - moderator+dev-only review for now) ----

function createGroupRequest(int $userId, string $name, string $description, ?string $bannerUrl): array {
    if (!canUserOwnAnotherGroup($userId)) {
        return ['ok' => false, 'reason' => 'You already own the maximum of ' . GROUP_MAX_PER_USER . ' groups.'];
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO group_requests (request_type, requested_by, name, description, banner_url) VALUES ('create', ?, ?, ?, ?)");
    $stmt->bind_param('isss', $userId, $name, $description, $bannerUrl);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    notifyAdmins('group_request', $userId, '/admin/group-requests', $name);
    return ['ok' => true, 'id' => $id];
}

function createGroupEditRequest(int $groupId, int $userId, string $name, string $description, ?string $bannerUrl): array {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO group_requests (request_type, group_id, requested_by, name, description, banner_url) VALUES ('edit', ?, ?, ?, ?, ?)");
    $stmt->bind_param('iisss', $groupId, $userId, $name, $description, $bannerUrl);
    $stmt->execute();
    $stmt->close();
    notifyAdmins('group_request', $userId, '/admin/group-requests', 'Edit: ' . $name);
    return ['ok' => true];
}

function createGroupDeleteRequest(int $groupId, int $userId): array {
    $db = getDB();
    $group = getGroupById($groupId);
    $stmt = $db->prepare("INSERT INTO group_requests (request_type, group_id, requested_by, name) VALUES ('delete', ?, ?, ?)");
    $name = $group['name'] ?? '';
    $stmt->bind_param('iis', $groupId, $userId, $name);
    $stmt->execute();
    $stmt->close();
    notifyAdmins('group_request', $userId, '/admin/group-requests', 'Delete: ' . $name);
    return ['ok' => true];
}

// Used to hide the Edit/Delete Group forms once a request is already pending, so the
// host doesn't queue up duplicate requests before a moderator reviews the first one.
function getPendingGroupRequestForGroup(int $groupId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM group_requests WHERE group_id = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getPendingGroupRequests(): array {
    $db = getDB();
    $result = $db->query(
        "SELECT gr.*, u.username AS requester_username
         FROM group_requests gr JOIN users u ON u.id = gr.requested_by
         WHERE gr.status = 'pending' ORDER BY gr.created_at ASC"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getPendingGroupRequestsCount(): int {
    $db = getDB();
    $result = $db->query("SELECT COUNT(*) AS cnt FROM group_requests WHERE status = 'pending'");
    return (int)($result->fetch_assoc()['cnt'] ?? 0);
}

function getGroupRequestById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM group_requests WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function approveGroupRequest(int $requestId, int $reviewerId): bool {
    $req = getGroupRequestById($requestId);
    if (!$req || $req['status'] !== 'pending') return false;
    $db = getDB();

    if ($req['request_type'] === 'create') {
        $slug = generateUniqueGroupSlug($req['name']);
        $stmt = $db->prepare("INSERT INTO `groups` (slug, name, description, banner_url, host_user_id, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param('ssssi', $slug, $req['name'], $req['description'], $req['banner_url'], $req['requested_by']);
        $stmt->execute();
        $groupId = $stmt->insert_id;
        $stmt->close();
        $stmt = $db->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'host')");
        $stmt->bind_param('ii', $groupId, $req['requested_by']);
        $stmt->execute();
        $stmt->close();
        createNotification((int)$req['requested_by'], 'group_request_approved', $reviewerId, '/group/' . $slug, $req['name']);
    } elseif ($req['request_type'] === 'edit' && $req['group_id']) {
        $stmt = $db->prepare("UPDATE `groups` SET name = ?, description = ?, banner_url = COALESCE(?, banner_url) WHERE id = ?");
        $stmt->bind_param('sssi', $req['name'], $req['description'], $req['banner_url'], $req['group_id']);
        $stmt->execute();
        $stmt->close();
        $group = getGroupById((int)$req['group_id']);
        createNotification((int)$req['requested_by'], 'group_request_approved', $reviewerId, '/group/' . ($group['slug'] ?? ''), $req['name']);
    } elseif ($req['request_type'] === 'delete' && $req['group_id']) {
        $stmt = $db->prepare("UPDATE `groups` SET status = 'deleted' WHERE id = ?");
        $stmt->bind_param('i', $req['group_id']);
        $stmt->execute();
        $stmt->close();
        createNotification((int)$req['requested_by'], 'group_request_approved', $reviewerId, '/groups', $req['name'] ?? '');
    }

    $stmt = $db->prepare("UPDATE group_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $reviewerId, $requestId);
    $stmt->execute();
    $stmt->close();
    return true;
}

function rejectGroupRequest(int $requestId, int $reviewerId): bool {
    $req = getGroupRequestById($requestId);
    if (!$req || $req['status'] !== 'pending') return false;
    $db = getDB();
    $stmt = $db->prepare("UPDATE group_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $reviewerId, $requestId);
    $stmt->execute();
    $stmt->close();
    createNotification((int)$req['requested_by'], 'group_request_rejected', $reviewerId, '/groups', $req['name'] ?? '');
    return true;
}

// ---- Groups ----

function getGroupBySlug(string $slug): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT g.*, u.username AS host_username FROM `groups` g JOIN users u ON u.id = g.host_user_id WHERE g.slug = ? AND g.status = 'active'");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getGroupById(int $id): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM `groups` WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function getActiveGroups(): array {
    $db = getDB();
    $result = $db->query(
        "SELECT g.*, u.username AS host_username, (SELECT COUNT(*) FROM group_members gm WHERE gm.group_id = g.id) AS member_count
         FROM `groups` g JOIN users u ON u.id = g.host_user_id
         WHERE g.status = 'active' ORDER BY g.created_at DESC"
    );
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getUserGroups(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT g.*, gm.role FROM group_members gm JOIN `groups` g ON g.id = gm.group_id
         WHERE gm.user_id = ? AND g.status = 'active' ORDER BY g.name ASC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
 
// Same as getUserGroups() but with the extra fields (host username, member count) the
// group-card display needs - used by the profile page's Groups tab.
function getUserGroupsForProfile(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT g.*, u.username AS host_username, gm.role,
                (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id = g.id) AS member_count
         FROM group_members gm
         JOIN `groups` g ON g.id = gm.group_id
         JOIN users u ON u.id = g.host_user_id
         WHERE gm.user_id = ? AND g.status = 'active'
         ORDER BY g.name ASC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getGroupMemberRole(int $groupId, int $userId): ?string {
    $db = getDB();
    $stmt = $db->prepare("SELECT role FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $groupId, $userId);
    $stmt->execute();
    $stmt->bind_result($role);
    $found = $stmt->fetch();
    $stmt->close();
    return $found ? $role : null;
}

function getGroupMembers(int $groupId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT gm.*, u.username, u.avatar_url FROM group_members gm JOIN users u ON u.id = gm.user_id
         WHERE gm.group_id = ?
         ORDER BY FIELD(gm.role, 'host', 'manager', 'member'), gm.joined_at ASC"
    );
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function getGroupMemberCount(int $groupId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM group_members WHERE group_id = ?");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int)$count;
}

// Fans out a 'group_activity' notification (join / promotion / new wall comment) to
// every current member of the group except $excludeUserId (normally the actor - a
// member doesn't need to be told they just did the thing themselves). $message is
// shown as the notification's supporting snippet (e.g. the group name), same pattern
// as article_comment/feedback_reply using the message field for extra context.
function notifyGroupMembers(int $groupId, string $type, int $excludeUserId, ?string $message = null, ?string $link = null): void {
    $group = getGroupById($groupId);
    if (!$group) return;
    $link = $link ?? ('/group/' . ($group['slug'] ?? ''));
    foreach (getGroupMembers($groupId) as $m) {
        $memberId = (int)$m['user_id'];
        if ($memberId === $excludeUserId) continue;
        createNotification($memberId, $type, $excludeUserId, $link, $message);
    }
}

function addGroupMember(int $groupId, int $userId, string $role = 'member'): array {
    if (getGroupMemberRole($groupId, $userId) !== null) {
        return ['ok' => false, 'reason' => 'Already a member of this group.'];
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)");
    $stmt->bind_param('iis', $groupId, $userId, $role);
    $stmt->execute();
    $stmt->close();
    $group = getGroupById($groupId);
    notifyGroupMembers($groupId, 'group_member_joined', $userId, $group['name'] ?? null);
    return ['ok' => true];
}

// $actorId must be a manager (or host, or site mod/dev) and can only remove plain members.
// Hosts can also remove managers. Nobody can remove the host.
function kickGroupMember(int $groupId, int $userId, int $actorId, bool $actorIsSiteMod): array {
    $targetRole = getGroupMemberRole($groupId, $userId);
    $actorRole = getGroupMemberRole($groupId, $actorId);
    if ($targetRole === null) return ['ok' => false, 'reason' => 'Not a member.'];
    if ($targetRole === 'host') return ['ok' => false, 'reason' => 'The host cannot be removed.'];
    $canAct = $actorIsSiteMod || $actorRole === 'host' || ($actorRole === 'manager' && $targetRole === 'member');
    if (!$canAct) return ['ok' => false, 'reason' => 'You do not have permission to remove this member.'];
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $groupId, $userId);
    $stmt->execute();
    $stmt->close();
    return ['ok' => true];
}

function setGroupMemberTimeout(int $groupId, int $userId, int $actorId, bool $actorIsSiteMod, int $minutes): array {
    $targetRole = getGroupMemberRole($groupId, $userId);
    $actorRole = getGroupMemberRole($groupId, $actorId);
    if ($targetRole === null || $targetRole === 'host') return ['ok' => false, 'reason' => 'Cannot time out this member.'];
    $canAct = $actorIsSiteMod || $actorRole === 'host' || ($actorRole === 'manager' && $targetRole === 'member');
    if (!$canAct) return ['ok' => false, 'reason' => 'You do not have permission to time out this member.'];
    $db = getDB();
    $stmt = $db->prepare("UPDATE group_members SET timeout_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param('iii', $minutes, $groupId, $userId);
    $stmt->execute();
    $stmt->close();
    return ['ok' => true];
}

// Only the host (or a site mod/dev) can change a member's rank between 'member' and 'manager'.
// The host's own rank can never be changed here.
// Critical fix (Aug 23 2026): rank changes used to accept $actorIsSiteMod, which let
// ANY site moderator promote/demote in a group they were merely a member of (not host) -
// the exact "site-mod-bypass artifact" the manager role was supposed to retire. Group
// rank is a host-ownership decision now; only the host, or a dev in an emergency
// ($actorIsAdmin - is_admin specifically, not general moderators), can change it.
function setGroupMemberRole(int $groupId, int $userId, string $newRole, int $actorId, bool $actorIsAdmin): array {
    if (!in_array($newRole, ['member', 'manager'], true)) {
        return ['ok' => false, 'reason' => 'Invalid rank.'];
    }
    $targetRole = getGroupMemberRole($groupId, $userId);
    $actorRole = getGroupMemberRole($groupId, $actorId);
    if ($targetRole === null) return ['ok' => false, 'reason' => 'Not a member.'];
    if ($targetRole === 'host') return ['ok' => false, 'reason' => "The host's rank cannot be changed."];
    $canAct = $actorIsAdmin || $actorRole === 'host';
    if (!$canAct) return ['ok' => false, 'reason' => 'Only the host can change member ranks.'];
    if ($targetRole === $newRole) return ['ok' => true];
    $db = getDB();
    $stmt = $db->prepare("UPDATE group_members SET role = ? WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param('sii', $newRole, $groupId, $userId);
    $stmt->execute();
    $stmt->close();
    if ($newRole === 'manager') {
        $group = getGroupById($groupId);
        notifyGroupMembers($groupId, 'group_member_promoted', $actorId, $group['name'] ?? null);
    }
    return ['ok' => true];
}
 
function isGroupMemberTimedOut(int $groupId, int $userId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT timeout_until FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param('ii', $groupId, $userId);
    $stmt->execute();
    $stmt->bind_result($until);
    $stmt->fetch();
    $stmt->close();
    return $until !== null && strtotime($until) > time();
}

function setGroupCommentPolicy(int $groupId, string $policy): void {
    if (!in_array($policy, ['everyone', 'members'], true)) return;
    $db = getDB();
    $stmt = $db->prepare("UPDATE `groups` SET comment_policy = ? WHERE id = ?");
    $stmt->bind_param('si', $policy, $groupId);
    $stmt->execute();
    $stmt->close();
}

// ---- Invites ----

// Any member can invite a user they follow, or who follows them. Moderators/dev can
// invite anyone (per spec: "anyone for moderators and the dev").
function canInviteUserToGroup(int $inviterId, int $targetUserId, bool $inviterIsSiteMod): bool {
    if ($inviterIsSiteMod) return true;
    return isFollowing($inviterId, $targetUserId) || isFollowing($targetUserId, $inviterId);
}

function inviteUserToGroup(int $groupId, int $inviterId, int $targetUserId): array {
    if (getGroupMemberRole($groupId, $targetUserId) !== null) {
        return ['ok' => false, 'reason' => 'That user is already a member.'];
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT IGNORE INTO group_invites (group_id, invited_user_id, invited_by) VALUES (?, ?, ?)");
    $stmt->bind_param('iii', $groupId, $targetUserId, $inviterId);
    $stmt->execute();
    $inserted = $stmt->affected_rows > 0;
    $stmt->close();
    if (!$inserted) return ['ok' => false, 'reason' => 'That user already has a pending invite to this group.'];

    $group = getGroupById($groupId);
    createNotification($targetUserId, 'group_invite', $inviterId, '/group/' . ($group['slug'] ?? ''), $group['name'] ?? '');
    return ['ok' => true];
}

function getPendingGroupInvitesForUser(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT gi.*, g.name AS group_name, g.slug AS group_slug, u.username AS inviter_username
         FROM group_invites gi JOIN `groups` g ON g.id = gi.group_id JOIN users u ON u.id = gi.invited_by
         WHERE gi.invited_user_id = ? AND gi.status = 'pending' AND g.status = 'active'
         ORDER BY gi.created_at DESC"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Used on the group page itself so someone who was invited by username sees an Accept/
// Decline button right there, instead of only on /groups (they'd otherwise land on
// "you'll need an invite to join" with no visible way to act on the invite they have).
function getPendingGroupInviteForUserInGroup(int $groupId, int $userId): ?array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT gi.*, u.username AS inviter_username
         FROM group_invites gi JOIN users u ON u.id = gi.invited_by
         WHERE gi.group_id = ? AND gi.invited_user_id = ? AND gi.status = 'pending'
         LIMIT 1"
    );
    $stmt->bind_param('ii', $groupId, $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function respondToGroupInvite(int $inviteId, int $userId, bool $accept): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM group_invites WHERE id = ? AND invited_user_id = ? AND status = 'pending'");
    $stmt->bind_param('ii', $inviteId, $userId);
    $stmt->execute();
    $invite = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$invite) return ['ok' => false, 'reason' => 'Invite not found.'];

    $result = ['ok' => true];
    if ($accept) {
        $result = addGroupMember((int)$invite['group_id'], $userId);
    }
    $status = $accept ? 'accepted' : 'declined';
    $stmt = $db->prepare("UPDATE group_invites SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $inviteId);
    $stmt->execute();
    $stmt->close();
    $result['group_id'] = (int)$invite['group_id'];
    return $result;
}

// Random invite links - anyone with the link can join directly (up to the group cap).
function createGroupInviteLink(int $groupId, int $createdBy): string {
    $db = getDB();
    do {
        $code = bin2hex(random_bytes(6));
        $stmt = $db->prepare("SELECT 1 FROM group_invite_links WHERE code = ?");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
    } while ($exists);
 
    $stmt = $db->prepare("INSERT INTO group_invite_links (group_id, code, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $groupId, $code, $createdBy);
    $stmt->execute();
    $stmt->close();
    return $code;
}
 
// The group's standing "public" invite link, if it has one - shown to anyone visiting
// the group page (members and non-members, logged in or not) as a self-serve Join button,
// as opposed to the one-off links generated above which are meant to be shared privately.
function getPublicGroupInviteLink(int $groupId): ?array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT * FROM group_invite_links WHERE group_id = ? AND is_public = 1 ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}
 
// Only the host (or a site mod/dev) can turn the public join link on or off.
// Turning it on always mints a fresh code, so a previously-shared public link can't be
// silently reactivated later. Turning it off clears the flag but leaves the row (the
// code simply stops resolving to "public" - getGroupByInviteCode() still finds it, but
// nothing surfaces it to non-members anymore).
function setGroupPublicInviteLink(int $groupId, bool $enabled, int $actorId, bool $actorIsSiteMod): array {
    $actorRole = getGroupMemberRole($groupId, $actorId);
    if (!($actorIsSiteMod || $actorRole === 'host')) {
        return ['ok' => false, 'reason' => 'Only the host can change this.'];
    }
    $db = getDB();
    $stmt = $db->prepare("UPDATE group_invite_links SET is_public = 0 WHERE group_id = ? AND is_public = 1");
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $stmt->close();
    if (!$enabled) {
        return ['ok' => true];
    }
    $code = createGroupInviteLink($groupId, $actorId);
    $stmt = $db->prepare("UPDATE group_invite_links SET is_public = 1 WHERE code = ?");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $stmt->close();
    return ['ok' => true, 'code' => $code];
}

function getGroupByInviteCode(string $code): ?array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT g.* FROM group_invite_links l JOIN `groups` g ON g.id = l.group_id
         WHERE l.code = ? AND g.status = 'active'"
    );
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// ---- Group wall (flat comments, not threaded - v1) ----

function canCommentOnGroup(array $group, ?int $userId): bool {
    if ($group['comment_policy'] === 'everyone') return true;
    return $userId !== null && getGroupMemberRole((int)$group['id'], $userId) !== null;
}

function canPostImageInGroup(?string $role): bool {
    return in_array($role, ['host', 'manager'], true);
}

function getGroupComments(int $groupId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT gc.*, u.username, u.avatar_url FROM group_comments gc JOIN users u ON u.id = gc.user_id
         WHERE gc.group_id = ? ORDER BY gc.created_at DESC"
    );
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// $parentId nests the comment under an existing group comment (reply). Notifies the
// parent comment's author, same pattern as addComment() on articles.
function addGroupComment(int $groupId, int $userId, string $content, ?string $imageUrl = null, ?int $parentId = null): int {
    $db = getDB();
    if ($parentId === null) {
        $stmt = $db->prepare("INSERT INTO group_comments (group_id, user_id, content, image_url) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('iiss', $groupId, $userId, $content, $imageUrl);
    } else {
        $stmt = $db->prepare("INSERT INTO group_comments (group_id, user_id, content, image_url, parent_comment_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('iissi', $groupId, $userId, $content, $imageUrl, $parentId);
    }
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    if ($parentId !== null) {
        $parent = getGroupCommentById($parentId);
        if ($parent && (int)$parent['user_id'] !== $userId) {
            $group = getGroupById($groupId);
            createNotification((int)$parent['user_id'], 'comment_reply', $userId, '/group/' . ($group['slug'] ?? ''), $content);
        }
    } else {
        // Top-level wall comments only - replies already notify the parent commenter
        // above via comment_reply, so fanning those out here too would double-notify them.
        $group = getGroupById($groupId);
        notifyGroupMembers($groupId, 'group_new_comment', $userId, $group['name'] ?? null);
    }

    return $id;
}

function adminDeleteGroupComment(int $commentId): void {
    $db = getDB();
    $stmt = $db->prepare("SELECT image_url FROM group_comments WHERE id = ?");
    $stmt->bind_param('i', $commentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && $row['image_url']) deleteUploadedImage($row['image_url']);
    $stmt = $db->prepare("DELETE FROM group_comments WHERE id = ?");
    $stmt->bind_param('i', $commentId);
    $stmt->execute();
    $stmt->close();
}

// Used by group-action.php's delete_comment handler to check comment ownership
// (site mods and the group host can already delete anyone's; this lets a member
// delete their own too, matching what group.php's UI already implies).
function getGroupCommentById(int $commentId): ?array {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM group_comments WHERE id = ?");
    $stmt->bind_param('i', $commentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Renders a group wall comment and its replies, reusing the same .comment/.comment-header/
// comment-avatar/rank-badge markup and reply-depth indent (14px/cap 56px) as article
// comments (renderCommentThread), so the wall keeps matching that look. $canReply gates
// the Reply button/form the same way $canComment already gates the top-level composer.
function renderGroupCommentThread(array $comment, int $groupId, int $myId, ?string $myRole, bool $isSiteMod, bool $canReply, int $depth = 0): string {
    $indent = min($depth * 14, 56);
    $topClass = $depth === 0 ? ' comment-top' : '';
    $html = '<div class="comment' . $topClass . '" style="margin-left: ' . $indent . 'px;">';
    $html .= '<div class="comment-header">';
    $html .= renderCommentAvatar($comment['avatar_url'] ?? null, $comment['username']);
    $html .= '<strong><a href="/@' . e($comment['username']) . '">' . e($comment['username']) . '</a></strong>';
    $html .= renderRankBadges((int)$comment['user_id']);
    $html .= ' <span class="meta">' . utcTimeTag($comment['created_at'], 'datetime') . '</span>';
    $html .= '</div>';
    $html .= '<p>' . nl2br(e($comment['content'])) . '</p>';
    if (!empty($comment['image_url'])) {
        $html .= '<img src="' . e($comment['image_url']) . '" alt="" class="group-comment-image">';
    }

    if ($canReply) {
        $formId = 'group-reply-form-' . (int)$comment['id'];
        $html .= '<button type="button" class="reply-toggle" title="Reply" onclick="document.getElementById(\'' . $formId . '\').classList.toggle(\'open\')"><img src="/assets/icons/reply.svg" class="icon-svg-sm" alt=""> Reply</button>';
        $html .= '<form method="post" action="/group-action" class="reply-form" id="' . $formId . '">';
        $html .= csrfField();
        $html .= '<input type="hidden" name="action" value="post_comment">';
        $html .= '<input type="hidden" name="group_id" value="' . $groupId . '">';
        $html .= '<input type="hidden" name="parent_id" value="' . (int)$comment['id'] . '">';
        $html .= '<textarea name="content" placeholder="Write a reply..." maxlength="1000" required></textarea>';
        $html .= '<button class="btn" type="submit">Post Reply</button>';
        $html .= '</form>';
    }

    if ($isSiteMod || (int)$comment['user_id'] === $myId || $myRole === 'host') {
        $html .= '<form method="post" action="/group-action" class="report-form" onsubmit="return confirm(\'Delete this comment?\');">';
        $html .= csrfField();
        $html .= '<input type="hidden" name="action" value="delete_comment">';
        $html .= '<input type="hidden" name="group_id" value="' . $groupId . '">';
        $html .= '<input type="hidden" name="comment_id" value="' . (int)$comment['id'] . '">';
        $html .= '<button type="submit" class="reply-toggle" title="Delete"><img src="/assets/icons/comment_delete.svg" class="icon-svg-sm" alt=""> Delete</button>';
        $html .= '</form>';
    }

    foreach ($comment['replies'] as $reply) {
        $html .= renderGroupCommentThread($reply, $groupId, $myId, $myRole, $isSiteMod, $canReply, $depth + 1);
    }

    $html .= '</div>';
    return $html;
}

// ---- Group Articles (v0.24) ----
// Lets members/host attach existing published articles to a group's Articles tab.
// Uses the group_articles join table - see groups-schema.sql addition, run manually
// via phpMyAdmin like the rest of the Groups schema.

function canAttachArticleToGroup(?string $role): bool {
    return $role !== null; // any group member, including host/manager
}

function isArticleAttachedToGroup(int $groupId, int $articleId): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM group_articles WHERE group_id = ? AND article_id = ?");
    $stmt->bind_param('ii', $groupId, $articleId);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $exists;
}

function attachArticleToGroup(int $groupId, int $articleId, int $userId): array {
    if (isArticleAttachedToGroup($groupId, $articleId)) {
        return ['ok' => false, 'reason' => 'That article is already attached to this group.'];
    }
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO group_articles (group_id, article_id, added_by) VALUES (?, ?, ?)");
    $stmt->bind_param('iii', $groupId, $articleId, $userId);
    $stmt->execute();
    $stmt->close();
    return ['ok' => true];
}

// Returns the id of whoever attached $articleId to $groupId, or null if it isn't attached.
function getGroupArticleAttacherId(int $groupId, int $articleId): ?int {
    $db = getDB();
    $stmt = $db->prepare("SELECT added_by FROM group_articles WHERE group_id = ? AND article_id = ?");
    $stmt->bind_param('ii', $groupId, $articleId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['added_by'] : null;
}

function detachArticleFromGroup(int $groupId, int $articleId): void {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM group_articles WHERE group_id = ? AND article_id = ?");
    $stmt->bind_param('ii', $groupId, $articleId);
    $stmt->execute();
    $stmt->close();
}

// Published articles attached to a group, newest-attached first, same shape as an
// articles row plus added_by/attached_at - rendered with the same search-result
// card markup used on search.php/explore.php/profile.php.
function getGroupArticles(int $groupId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT a.*, ga.added_by, ga.created_at AS attached_at FROM group_articles ga
         JOIN articles a ON a.id = ga.article_id
         WHERE ga.group_id = ? AND a.status = 'published' ORDER BY ga.created_at DESC"
    );
    $stmt->bind_param('i', $groupId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}