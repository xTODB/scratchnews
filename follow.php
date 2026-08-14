<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}
requireCsrf();

$targetId = (int)($_POST['user_id'] ?? 0);
$followerId = (int)$_SESSION['reader_id'];
$target = getUserById($targetId);

if ($target && $targetId !== $followerId) {
    if (isFollowing($followerId, $targetId)) {
        unfollowUser($followerId, $targetId);
    } else {
        followUser($followerId, $targetId);
    }
}

$fallback = '/@' . urlencode($target['username'] ?? '');
$redirect = $_POST['redirect'] ?? '';
// Only allow same-site relative paths, never an absolute URL, to avoid an open redirect.
if ($redirect === '' || $redirect[0] !== '/' || strpos($redirect, '//') === 0) {
    $redirect = $fallback;
}
header('Location: ' . $redirect);
exit;