<?php
require_once __DIR__ . '/../functions.php';
requireApiAccess();
header('Content-Type: application/json');

$articleId = (int)($_GET['article_id'] ?? 0);
if ($articleId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'article_id is required']);
    exit;
}

$article = getArticleById($articleId);
if (!$article || ($article['status'] ?? 'published') === 'draft') {
    http_response_code(404);
    echo json_encode(['error' => 'Article not found']);
    exit;
}

$comments = getCommentsForArticle($articleId);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
$total = count($comments);
$slice = array_slice($comments, ($page - 1) * $perPage, $perPage);

echo json_encode([
    'data' => array_map('formatCommentForApi', $slice),
    'page' => $page,
    'per_page' => $perPage,
    'total' => $total,
]);
