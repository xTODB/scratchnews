<?php
require_once __DIR__ . '/../functions.php';
requireApiAccess();
header('Content-Type: application/json');

$username = trim($_GET['username'] ?? '');
$id = (int)($_GET['id'] ?? 0);

$user = null;
if ($username !== '') {
    $user = getUserByUsername($username);
} elseif ($id > 0) {
    $user = getUserById($id);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'username or id is required']);
    exit;
}

if (!$user || strpos($user['username'], 'deleted_user_') === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

$articles = array_values(array_filter(
    getArticlesByUser((int)$user['id']),
    fn($a) => ($a['status'] ?? 'published') === 'published'
));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
$total = count($articles);
$slice = array_slice($articles, ($page - 1) * $perPage, $perPage);

echo json_encode([
    'data' => array_map('formatArticleForApi', $slice),
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
]);
