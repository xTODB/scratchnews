<?php
require_once __DIR__ . '/functions.php';

$slug = $_GET['slug'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $slug)) {
    http_response_code(404);
    exit('Not found');
}

$db = getDB();
$stmt = $db->prepare("SELECT target_url FROM redirects WHERE slug = ? LIMIT 1");
$stmt->bind_param('s', $slug);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !preg_match('#^https?://#i', $row['target_url'])) {
    http_response_code(404);
    exit('Not found');
}

// HEAD requests (link checkers) are redirected but not logged
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
    $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $ref = substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255);

    $stmt = $db->prepare("INSERT INTO redirect_visits (slug, ip_address, user_agent, referer) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $slug, $ip, $ua, $ref);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare("UPDATE redirects SET hits = hits + 1 WHERE slug = ?");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $stmt->close();
}

header('Cache-Control: no-store');
header('Location: ' . $row['target_url'], true, 302);
exit;