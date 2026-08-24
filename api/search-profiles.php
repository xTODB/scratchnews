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

// searchProfiles() returns a lean row set (no created_at/banner_url), so re-fetch
// the full user record per hit before formatting - formatUserForApi() expects it.
$hits = searchProfiles($q);
$users = array_values(array_filter(array_map(
    fn($u) => getUserById((int)$u['id']),
    $hits
)));

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
$total = count($users);
$slice = array_slice($users, ($page - 1) * $perPage, $perPage);

echo json_encode([
    'data' => array_map('formatUserForApi', $slice),
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
]);
