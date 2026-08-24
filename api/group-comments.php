<?php
require_once __DIR__ . '/../functions.php';
requireApiAccess();
header('Content-Type: application/json');

$groupId = (int)($_GET['group_id'] ?? 0);
if ($groupId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'group_id is required']);
    exit;
}

$group = getGroupById($groupId);
if (!$group || ($group['status'] ?? 'active') !== 'active') {
    http_response_code(404);
    echo json_encode(['error' => 'Group not found']);
    exit;
}

$comments = getGroupComments($groupId);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
$total = count($comments);
$slice = array_slice($comments, ($page - 1) * $perPage, $perPage);

echo json_encode([
    'data' => array_map('formatGroupCommentForApi', $slice),
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
]);
