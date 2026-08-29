<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}
requireCsrf();

if (($_POST['action'] ?? '') === 'admin_delete') {
    if (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator'])) {
        adminDeleteProfileComment((int)($_POST['comment_id'] ?? 0));
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
    exit;
}

$profileUserId = (int)($_POST['profile_user_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$profileUser = getUserById($profileUserId);

$modError = '';
$blocked = isUserBanned((int)$_SESSION['reader_id']) || isPhoneVerificationPending((int)$_SESSION['reader_id']);
if ($blocked) {
    $modError = 'Your account is currently restricted from commenting.';
} elseif ($profileUser && $content !== '' && mb_strlen($content) <= 1000) {
    $modCheck = checkAndModerateComment((int)$_SESSION['reader_id'], $content);
    if ($modCheck['allowed']) {
        addProfileComment($profileUserId, (int)$_SESSION['reader_id'], $content, $parentId);
    } else {
        $modError = $modCheck['reason'];
    }
}

$redirect = '/@' . urlencode($profileUser['username'] ?? '') . '?view=profile_comments';
if ($modError !== '') $redirect .= '&modError=' . urlencode($modError);
header('Location: ' . $redirect);
exit;
