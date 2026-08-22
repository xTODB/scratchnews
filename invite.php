<?php
require_once __DIR__ . '/functions.php';
startSession();

if (!isGroupsBetaAllowed()) {
    header('Location: /groups');
    exit;
}

$code = $_GET['code'] ?? '';
$group = getGroupByInviteCode($code);
if (!$group) {
    http_response_code(404);
    echo 'This invite link is invalid or has expired.';
    exit;
}

if (empty($_SESSION['reader_id'])) {
    // No return-redirect wired into login yet - send them to log in, then they can
    // click the invite link again to actually join.
    header('Location: /login');
    exit;
}

$myId = (int)$_SESSION['reader_id'];
if (getGroupMemberRole((int)$group['id'], $myId) !== null) {
    header('Location: /group/' . $group['slug']);
    exit;
}

$result = addGroupMember((int)$group['id'], $myId);
if ($result['ok']) {
    header('Location: /group/' . $group['slug'] . '?notice=' . urlencode('Welcome to ' . $group['name'] . '!'));
} else {
    header('Location: /groups?error=' . urlencode($result['reason']));
}
exit;
