<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}
if (!isGroupsBetaAllowed()) {
    header('Location: /groups');
    exit;
}
requireCsrf();

$myId = (int)$_SESSION['reader_id'];
$isSiteMod = !empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator']);
$action = $_POST['action'] ?? '';

function groupRedirect(string $slug, string $error = '', string $notice = ''): void {
    $url = '/group/' . $slug;
    $params = [];
    if ($error !== '') $params['error'] = $error;
    if ($notice !== '') $params['notice'] = $notice;
    if ($params) $url .= '?' . http_build_query($params);
    header('Location: ' . $url);
    exit;
}

if ($action === 'respond_invite') {
    $inviteId = (int)($_POST['invite_id'] ?? 0);
    $accept = !empty($_POST['accept']);
    $result = respondToGroupInvite($inviteId, $myId, $accept);
    $destGroup = !empty($result['group_id']) ? getGroupById((int)$result['group_id']) : null;
    $backTo = $destGroup ? '/group/' . $destGroup['slug'] : '/groups';
    header('Location: ' . $backTo . (!$result['ok'] ? '?error=' . urlencode($result['reason']) : '?notice=' . urlencode($accept ? 'Joined the group!' : 'Invite declined.')));
    exit;
}

$groupId = (int)($_POST['group_id'] ?? 0);
$group = getGroupById($groupId);
if (!$group || $group['status'] !== 'active') {
    header('Location: /groups?error=' . urlencode('Group not found.'));
    exit;
}
$slug = $group['slug'];
$myRole = getGroupMemberRole($groupId, $myId);

if ($action === 'post_comment') {
    if (!canCommentOnGroup($group, $myId)) groupRedirect($slug, 'Only members can comment in this group.');
    if (isGroupMemberTimedOut($groupId, $myId)) groupRedirect($slug, 'You are currently timed out from commenting in this group.');
    $content = trim($_POST['content'] ?? '');
    if ($content === '' || mb_strlen($content) > 1000) groupRedirect($slug, 'Comment must be 1-1000 characters.');

    $blocked = isUserBanned($myId) || isPhoneVerificationPending($myId);
    if ($blocked) groupRedirect($slug, 'Your account is currently restricted from commenting.');
    $modCheck = checkAndModerateComment($myId, $content);
    if (!$modCheck['allowed']) groupRedirect($slug, $modCheck['reason']);

    $imageUrl = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        if (!canPostImageInGroup($myRole)) groupRedirect($slug, 'Only the host and managers can post images in this group.');
        try {
            $imageUrl = saveUploadedImage($_FILES['image'], 'group_comments');
        } catch (RuntimeException $e) {
            groupRedirect($slug, $e->getMessage());
        }
    }
    addGroupComment($groupId, $myId, $content, $imageUrl);
    groupRedirect($slug);

} elseif ($action === 'delete_comment') {
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $gc = getGroupCommentById($commentId);
    if ($gc && ($isSiteMod || $myRole === 'host' || (int)$gc['user_id'] === $myId)) {
        adminDeleteGroupComment($commentId);
    }
    groupRedirect($slug);

} elseif ($action === 'attach_article') {
    if (!canAttachArticleToGroup($myRole)) groupRedirect($slug, 'Only members can attach articles to this group.');
    $raw = trim($_POST['article_ref'] ?? '');
    $articleId = preg_match('~/article/(\d+)~', $raw, $m) ? (int)$m[1] : (int)$raw;
    $article = $articleId > 0 ? getArticleById($articleId) : null;
    if (!$article || $article['status'] !== 'published') groupRedirect($slug, 'Article not found - paste the article link or its ID.');
    $result = attachArticleToGroup($groupId, $articleId, $myId);
    groupRedirect($slug, $result['ok'] ? '' : $result['reason'], $result['ok'] ? 'Article attached.' : '');

} elseif ($action === 'detach_article') {
    $articleId = (int)($_POST['article_id'] ?? 0);
    $canRemove = $isSiteMod || $myRole === 'host' || $myRole === 'manager' || getGroupArticleAttacherId($groupId, $articleId) === $myId;
    if (!$canRemove) groupRedirect($slug, 'You can only remove articles you attached yourself.');
    detachArticleFromGroup($groupId, $articleId);
    groupRedirect($slug);

} elseif ($action === 'invite_user') {
    if (!$myRole) groupRedirect($slug, 'You must be a member to invite others.');
    $username = trim($_POST['username'] ?? '');
    $target = getUserByUsername($username);
    if (!$target) groupRedirect($slug, 'User not found.');
    if (!canInviteUserToGroup($myId, (int)$target['id'], $isSiteMod)) {
        groupRedirect($slug, 'You can only invite users you follow, or who follow you.');
    }
    $result = inviteUserToGroup($groupId, $myId, (int)$target['id']);
    groupRedirect($slug, $result['ok'] ? '' : $result['reason'], $result['ok'] ? 'Invite sent to @' . $target['username'] . '.' : '');

} elseif ($action === 'generate_invite_link') {
    if (!($myRole === 'host' || $myRole === 'manager' || $isSiteMod)) groupRedirect($slug, 'Only the host or managers can generate invite links.');
    $code = createGroupInviteLink($groupId, $myId);
    $_SESSION['group_invite_link_' . $groupId] = (($_SERVER['HTTPS'] ?? '') === 'on' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/invite/' . $code;
    groupRedirect($slug);

} elseif ($action === 'kick_member') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $result = kickGroupMember($groupId, $targetUserId, $myId, $isSiteMod);
    groupRedirect($slug, $result['ok'] ? '' : $result['reason']);

} elseif ($action === 'timeout_member') {
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $minutes = (int)($_POST['minutes'] ?? 0);
    if ($minutes <= 0) groupRedirect($slug);
    $result = setGroupMemberTimeout($groupId, $targetUserId, $myId, $isSiteMod, $minutes);
    groupRedirect($slug, $result['ok'] ? '' : $result['reason']);

} elseif ($action === 'set_comment_policy') {
    if (!($myRole === 'host' || $isSiteMod)) groupRedirect($slug, 'Only the host can change this.');
    $policy = ($_POST['policy'] ?? '') === 'everyone' ? 'everyone' : 'members';
    setGroupCommentPolicy($groupId, $policy);
    groupRedirect($slug);

} else {
    groupRedirect($slug);
}