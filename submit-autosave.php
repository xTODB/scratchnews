<?php
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

if (empty($_SESSION['reader_id'])) {
    http_response_code(403);
    echo json_encode(['saved' => false]);
    exit;
}

if (!verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['saved' => false]);
    exit;
}

$readerId = (int)$_SESSION['reader_id'];
$draftId = (int)($_POST['draft_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$summary = trim($_POST['summary'] ?? '');
$content = trim($_POST['content'] ?? '');
$categoryIds = $_POST['categories'] ?? [];

// Nothing worth saving yet.
if ($title === '' && $content === '') {
    echo json_encode(['saved' => false]);
    exit;
}

$cleanContent = $content !== '' ? sanitizeArticleHtml($content) : '';

if ($draftId > 0) {
    $existing = getUserSubmissionById($draftId, $readerId);
    if (!$existing || $existing['status'] !== 'draft') {
        // Not this user's draft, or it's already been submitted for review / decided -
        // autosave never touches that.
        echo json_encode(['saved' => false]);
        exit;
    }
    updateSubmission($draftId, $title, $summary, $cleanContent, $existing['image_url'] ?? null, $categoryIds, 'draft');
    echo json_encode(['saved' => true, 'draft_id' => $draftId]);
    exit;
}

$newId = createSubmission($readerId, $title, $summary, $cleanContent, null, $categoryIds, 'draft');
echo json_encode(['saved' => true, 'draft_id' => $newId]);
