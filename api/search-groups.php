<?php
require_once __DIR__ . '/../functions.php';
requireApiAccess();
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'q is required']);
    exit;
}

$groups = searchGroups($q);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
$total = count($groups);
$slice = array_slice($groups, ($page - 1) * $perPage, $perPage);

echo json_encode([
    'data' => array_map('formatGroupForApi', $slice),
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
]);
