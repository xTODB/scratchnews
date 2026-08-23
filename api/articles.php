<?php
require_once __DIR__ . '/../functions.php';
requireApiAccess();
header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $article = getArticleById($id);
    if (!$article || ($article['status'] ?? 'published') === 'draft') {
        http_response_code(404);
        echo json_encode(['error' => 'Article not found']);
        exit;
    }
    echo json_encode(formatArticleForApi($article));
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));

$articles = getAllArticles();

if (isset($_GET['featured'])) {
    $wantFeatured = filter_var($_GET['featured'], FILTER_VALIDATE_BOOLEAN);
    $articles = array_values(array_filter($articles, function ($a) use ($wantFeatured) {
        return (bool)($a['is_featured'] ?? 0) === $wantFeatured;
    }));
}

$total = count($articles);
$slice = array_slice($articles, ($page - 1) * $perPage, $perPage);

echo json_encode([
    'data' => array_map('formatArticleForApi', $slice),
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
]);