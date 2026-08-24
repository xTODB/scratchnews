<?php
require_once __DIR__ . '/../functions.php';
requireApiAccess();
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$slug = trim($_GET['slug'] ?? '');

if ($id > 0 || $slug !== '') {
    $group = $id > 0 ? getGroupById($id) : getGroupBySlug($slug);
    if (!$group || ($group['status'] ?? 'active') !== 'active') {
        http_response_code(404);
        echo json_encode(['error' => 'Group not found']);
        exit;
    }
    if (!isset($group['host_username'])) {
        $host = getUserById((int)$group['host_user_id']);
        $group['host_username'] = $host['username'] ?? '';
    }
    $group['member_count'] = getGroupMemberCount((int)$group['id']);
    echo json_encode(formatGroupForApi($group));
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));

$groups = getActiveGroups();
$total = count($groups);
$slice = array_slice($groups, ($page - 1) * $perPage, $perPage);

echo json_encode([
    'data' => array_map('formatGroupForApi', $slice),
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
]);
