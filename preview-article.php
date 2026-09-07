<?php
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

// Reused by both the reader submit form and the admin create/edit pages - anyone
// already logged in either way can preview, since this never touches the DB or
// any other user's data, it's a pure render of whatever text was POSTed.
if (empty($_SESSION['reader_id']) && empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not logged in.']);
    exit;
}

// Reader forms carry a CSRF token (submit.php); admin forms currently don't use
// CSRF tokens anywhere in the app, so only enforce it on the reader path - an
// admin-only check here would just break the admin preview button outright.
if (!empty($_SESSION['reader_id']) && !verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request.']);
    exit;
}

$title = trim($_POST['title'] ?? '');
$content = trim($_POST['content'] ?? '');
$imageUrl = trim($_POST['image_url'] ?? '');

// Same pipeline a real published article goes through: sanitize, then render
// scratchblocks markup - so the preview is exactly what readers would see, not
// an approximation.
$cleanContent = $content !== '' ? sanitizeArticleHtml($content) : '';
$cleanContent = renderScratchblocksMarkup($cleanContent);

echo json_encode([
    'title' => $title !== '' ? $title : '(Untitled)',
    'content_html' => $cleanContent,
    'image_url' => $imageUrl,
    'has_scratchblocks' => strpos($cleanContent, 'class="blocks"') !== false,
]);
