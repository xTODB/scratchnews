<?php
// v0.28 - "Show preview" checkbox on forum topic/reply/edit forms.
// Read-only text transform (renderBBCode(), same function used to display
// real posts) - no DB write, so this mirrors upload-image.php's pattern of
// a session check with no CSRF token required.
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

if (empty($_SESSION['reader_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'You must be logged in to preview.']);
    exit;
}

$content = (string)($_POST['content'] ?? '');
if (mb_strlen($content) > 20000) {
    $content = mb_substr($content, 0, 20000);
}

echo json_encode(['html' => renderBBCode($content)]);