<?php
require_once __DIR__ . '/config.php';

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

// ---- Reader accounts ----
function createUser(string $username, string $email, string $password) {
    $db = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $username, $email, $hash);
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

function startSession(): void {
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
                $_SESSION['dark_mode'] = $user['dark_mode'];
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
}

function setDarkModePreference(int $userId, bool $enabled): void {
    $db = getDB();
    $val = $enabled ? 1 : 0;
    $stmt = $db->prepare("UPDATE users SET dark_mode = ? WHERE id = ?");
    $stmt->bind_param('ii', $val, $userId);
    $stmt->execute();
    $stmt->close();
}

// ---- Comments ----
function getCommentsForArticle(int $articleId): array {
    $db = getDB();
    $stmt = $db->prepare("SELECT comments.*, users.username FROM comments JOIN users ON comments.user_id = users.id WHERE article_id = ? ORDER BY comments.created_at ASC");
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

function renderCommentThread(array $comment, bool $canReply, int $depth = 0, bool $canReport = false): string {
    $indent = min($depth * 24, 96); // cap indentation so deep threads don't run off-screen
    $html = '<div class="comment" style="margin-left: ' . $indent . 'px;">';
    $html .= '<strong><a href="/@' . e($comment['username']) . '">' . e($comment['username']) . '</a></strong>';
    $html .= ' <span class="meta">' . utcTimeTag($comment['created_at'], 'datetime') . '</span>';
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

    if (!empty($_SESSION['is_admin'])) {
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
        $stmt = $db->prepare("DELETE FROM comments WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM likes WHERE user_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();

        $db->commit();
        return true;
    } catch (Throwable $e) {
        $db->rollback();
        return false;
    }
}

function issueVerificationToken($userId) {
    $db = getDB();
    $token = bin2hex(random_bytes(32));

    $stmt = $db->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
    $stmt->bind_param("si", $token, $userId);
    $stmt->execute();
    $stmt->close();

    return $token;
}

function getUserByVerificationToken($token) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function markEmailVerified($userId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->close();
}

function sendVerificationEmail($toEmail, $toUsername, $token) {
    $verifyLink = "https://scratchnews.freedev.app/verify?token=" . urlencode($token);

    $payload = json_encode([
        "sender" => [
            "name" => "ScratchNews",
            "email" => BREVO_SENDER_EMAIL
        ],
        "to" => [
            ["email" => $toEmail, "name" => $toUsername]
        ],
        "subject" => "Verify your ScratchNews account",
        "htmlContent" => "<p>Hi " . htmlspecialchars($toUsername) . ",</p>"
            . "<p>Click the link below to verify your email address and unlock likes and comments on ScratchNews:</p>"
            . "<p><a href=\"" . htmlspecialchars($verifyLink) . "\">" . htmlspecialchars($verifyLink) . "</a></p>"
            . "<p>If you didn't create this account, you can ignore this email.</p>"
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

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // TEMPORARY DEBUG LOGGING — remove once email sending is confirmed working
    file_put_contents(__DIR__ . '/brevo_debug.log',
        date('Y-m-d H:i:s') . " | HTTP $httpCode | curl_error: $curlError | response: $response\n",
        FILE_APPEND
    );

    return $httpCode >= 200 && $httpCode < 300;
}

function createSubmission($userId, $title, $summary, $content, ?string $imageUrl = null, array $categoryIds = [], string $status = 'pending') {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO submissions (user_id, title, summary, content, image_url, status) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $userId, $title, $summary, $content, $imageUrl, $status);
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

    $articleId = getNextArticleId();
    $stmt = $db->prepare("INSERT INTO articles (id, title, summary, content, author, image_url, user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssi", $articleId, $submission['title'], $submission['summary'], $submission['content'], $submission['username'], $submission['image_url'], $submission['user_id']);
    $stmt->execute();
    $stmt->close();

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

function submitFeedback($userId, $message) {
    $db = getDB();
    $stmt = $db->prepare("INSERT INTO feedback (user_id, message) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $message);
    $stmt->execute();
    $stmt->close();
    notifyAdmins('admin_new_feedback', $userId, '/admin/feedback', $message);
}

function getAllFeedback() {
    $db = getDB();
    $result = $db->query("
        SELECT feedback.*, users.username
        FROM feedback
        LEFT JOIN users ON feedback.user_id = users.id
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
    $result = $db->query("SELECT id, username, email, is_admin, is_banned, email_verified, created_at, ip_address FROM users ORDER BY created_at DESC");
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

    $allowedTypes = ['articles', 'avatars', 'banners'];
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
    $_SESSION['dark_mode'] = (bool)$target['dark_mode'];
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
    $_SESSION['dark_mode'] = (bool)$admin['dark_mode'];
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

// ── Views & Trending ────────────────────────────────────

function incrementArticleView(int $articleId): void {
    $db = getDB();
    $stmt = $db->prepare("UPDATE articles SET views = views + 1 WHERE id = ?");
    $stmt->bind_param('i', $articleId);
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

// ── Subscribers ─────────────────────────────────────────

function getSubscriberIdByEmail(string $email): ?int {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM subscribers WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}

// Returns the confirm token to email out. Re-subscribing resets preferences to whatever was just picked.
function createSubscriber(string $email, array $categoryIds): string {
    $db = getDB();
    $confirmToken = bin2hex(random_bytes(24));
    $unsubToken = bin2hex(random_bytes(24));

    $stmt = $db->prepare("INSERT INTO subscribers (email, confirm_token, unsubscribe_token) VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE confirm_token = VALUES(confirm_token)");
    $stmt->bind_param('sss', $email, $confirmToken, $unsubToken);
    $stmt->execute();
    $stmt->close();

    $subscriberId = getSubscriberIdByEmail($email);

    $stmt = $db->prepare("DELETE FROM subscriber_categories WHERE subscriber_id = ?");
    $stmt->bind_param('i', $subscriberId);
    $stmt->execute();
    $stmt->close();

    if (!empty($categoryIds)) {
        $stmt = $db->prepare("INSERT INTO subscriber_categories (subscriber_id, category_id) VALUES (?, ?)");
        foreach (array_map('intval', $categoryIds) as $catId) {
            $stmt->bind_param('ii', $subscriberId, $catId);
            $stmt->execute();
        }
        $stmt->close();
    }

    return $confirmToken;
}

function confirmSubscriber(string $token): bool {
    $db = getDB();
    $stmt = $db->prepare("UPDATE subscribers SET confirmed = 1, confirm_token = NULL, confirmed_at = NOW() WHERE confirm_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

function unsubscribeByToken(string $token): bool {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM subscribers WHERE unsubscribe_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();
    return $ok;
}

function getSubscribersForCategories(array $categoryIds): array {
    if (empty($categoryIds)) return [];
    $db = getDB();
    $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
    $types = str_repeat('i', count($categoryIds));
    $stmt = $db->prepare("SELECT DISTINCT s.* FROM subscribers s
        JOIN subscriber_categories sc ON sc.subscriber_id = s.id
        WHERE s.confirmed = 1 AND sc.category_id IN ($placeholders)");
    $stmt->bind_param($types, ...array_map('intval', $categoryIds));
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// ── Subscriber emails (shares the Brevo pattern from sendVerificationEmail) ──

function sendBrevoEmail(string $payload): bool {
    $ch = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "accept: application/json",
        "api-key: " . BREVO_API_KEY,
        "content-type: application/json"
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode >= 200 && $httpCode < 300;
}

function sendSubscriptionConfirmEmail(string $toEmail, string $token): bool {
    $confirmLink = "https://scratchnews.freedev.app/confirm-subscription.php?token=" . urlencode($token);
    $payload = json_encode([
        "sender" => ["name" => "ScratchNews", "email" => BREVO_SENDER_EMAIL],
        "to" => [["email" => $toEmail]],
        "subject" => "Confirm your ScratchNews subscription",
        "htmlContent" => "<p><a href=\"" . htmlspecialchars($confirmLink) . "\"><strong>Click here to confirm your subscription</strong></a></p>"
            . "<p>Thanks for subscribing to ScratchNews! Confirming gets you Scratch news in your inbox.</p>"
            . "<p>If you didn't request this, you can ignore this email.</p>"
    ]);
    return sendBrevoEmail($payload);
}

function sendNewArticleNotification(string $toEmail, string $unsubToken, string $articleTitle, int $articleId): bool {
    $articleLink = "https://scratchnews.freedev.app/article/" . $articleId;
    $unsubLink = "https://scratchnews.freedev.app/unsubscribe.php?token=" . urlencode($unsubToken);
    $payload = json_encode([
        "sender" => ["name" => "ScratchNews", "email" => BREVO_SENDER_EMAIL],
        "to" => [["email" => $toEmail]],
        "subject" => "New article on ScratchNews: " . $articleTitle,
        "htmlContent" => "<p>A new article just went up that matches your interests:</p>"
            . "<p><a href=\"" . htmlspecialchars($articleLink) . "\"><strong>" . htmlspecialchars($articleTitle) . "</strong></a></p>"
            . "<p style=\"margin-top:2rem;font-size:0.85em;color:#888;\"><a href=\"" . htmlspecialchars($unsubLink) . "\">Unsubscribe</a> from these emails.</p>"
    ]);
    return sendBrevoEmail($payload);
}

// Call this right after setArticleCategories(), only when status is 'published'
function notifySubscribersOfNewArticle(int $articleId, string $articleTitle): void {
    $categoryIds = getArticleCategoryIds($articleId);
    if (empty($categoryIds)) return;
    $subscribers = getSubscribersForCategories($categoryIds);
    foreach ($subscribers as $sub) {
        sendNewArticleNotification($sub['email'], $sub['unsubscribe_token'], $articleTitle, $articleId);
    }
}

function getSubscriberIdByConfirmToken(string $token): ?int {
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM subscribers WHERE confirm_token = ?");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int)$row['id'] : null;
}

function getSubscriberCategoryCount(int $subscriberId): int {
    $db = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) AS c FROM subscriber_categories WHERE subscriber_id = ?");
    $stmt->bind_param('i', $subscriberId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['c'] ?? 0);
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
        'views' => (int)$article['views'],
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
    $indent = min($depth * 24, 96);
    $avatar = $comment['author_avatar'] ?? null;
    $html = '<div class="comment" style="margin-left: ' . $indent . 'px;">';
    $html .= '<a href="/@' . e($comment['author_username']) . '"><strong>@' . e($comment['author_username']) . '</strong></a>';
    $html .= ' <span class="meta">' . date('M j, Y g:i A', strtotime($comment['created_at'])) . '</span>';
    $html .= '<p>' . linkifyMentions(e($comment['content'])) . '</p>';

    if (!empty($_SESSION['is_admin'])) {
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
    'followed_user_article'  => '/assets/icons/new_article.svg',
    'account_banned'         => '/assets/icons/ban.svg',
    'comment_deleted'        => '/assets/icons/comment_delete.svg',
    'admin_new_account'      => '/assets/icons/message.svg',
    'admin_new_comment'      => '/assets/icons/reply.svg',
    'admin_new_report'       => '/assets/icons/report.svg',
    'admin_new_submission'   => '/assets/icons/message.svg',
    'admin_new_feedback'     => '/assets/icons/message.svg',
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
    $actor = !empty($n['actor_username']) ? '<a href="/@' . e($n['actor_username']) . '"><strong>' . e($n['actor_username']) . '</strong></a>' : 'ScratchNews';
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