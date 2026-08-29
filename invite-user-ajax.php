<?php
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please refresh the page and try again.']);
    exit;
}

if (empty($_SESSION['reader_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You must be logged in to invite someone.']);
    exit;
}

try {
    $myId = (int)$_SESSION['reader_id'];
    $isSiteMod = !empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator']);
    $groupId = (int)($_POST['group_id'] ?? 0);
    $group = getGroupById($groupId);
    if (!$group || $group['status'] !== 'active') {
        echo json_encode(['success' => false, 'error' => 'Group not found.']);
        exit;
    }
    $myRole = getGroupMemberRole($groupId, $myId);
    if (!$myRole) {
        echo json_encode(['success' => false, 'error' => 'You must be a member to invite others.']);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    if ($username === '') {
        echo json_encode(['success' => false, 'error' => 'Enter a username first.']);
        exit;
    }
    $target = getUserByUsername($username);
    if (!$target) {
        echo json_encode(['success' => false, 'error' => 'User not found.']);
        exit;
    }
    if (!canInviteUserToGroup($myId, (int)$target['id'], $isSiteMod)) {
        echo json_encode(['success' => false, 'error' => 'You can only invite users you follow, or who follow you.']);
        exit;
    }

    $result = inviteUserToGroup($groupId, $myId, (int)$target['id']);
    if (!$result['ok']) {
        echo json_encode(['success' => false, 'error' => $result['reason']]);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Invite sent to @' . $target['username'] . '.']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again.']);
}
