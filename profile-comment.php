<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}
requireCsrf();

if (($_POST['action'] ?? '') === 'admin_delete') {
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $anchor = '';
    if (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator'])) {
        // The comment itself won't exist to anchor to once deleted, so fall back
        // to its parent (if it had one).
        $target = getProfileCommentById($commentId);
        if ($target && !empty($target['parent_comment_id'])) {
            $anchor = '#comment-' . (int)$target['parent_comment_id'];
        }
        adminDeleteProfileComment($commentId);
    }
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/') . $anchor);
    exit;
}

$profileUserId = (int)($_POST['profile_user_id'] ?? 0);
$content = trim($_POST['content'] ?? '');
$parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
$profileUser = getUserById($profileUserId);

$modError = '';
$newId = null;
$blocked = isUserBanned((int)$_SESSION['reader_id']) || isPhoneVerificationPending((int)$_SESSION['reader_id']);
if ($blocked) {
    $modError = 'Your account is currently restricted from commenting.';
} elseif ($profileUser && $content !== '' && mb_strlen($content) <= 1000) {
    $modCheck = checkAndModerateComment((int)$_SESSION['reader_id'], $content);
    if ($modCheck['allowed']) {
        $newId = addProfileComment($profileUserId, (int)$_SESSION['reader_id'], $content, $parentId);
    } else {
        $modError = $modCheck['reason'];
    }
}

$redirect = '/@' . urlencode($profileUser['username'] ?? '') . '?view=profile_comments';
if ($modError !== '') $redirect .= '&modError=' . urlencode($modError);
if ($newId) $redirect .= '#comment-' . $newId;
header('Location: ' . $redirect);
exit;