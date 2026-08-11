<?php
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');

$id = (int)($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$summary = trim($_POST['summary'] ?? '');
$content = trim($_POST['content'] ?? '');
$author = trim($_POST['author'] ?? 'ScratchNews Staff');
$userId = ($_POST['user_id'] ?? '') !== '' ? (int)$_POST['user_id'] : ($_SESSION['reader_id'] ?? null);

// Nothing worth saving yet (e.g. autosave fired before anything was typed).
if ($title === '' && $content === '') {
    echo json_encode(['saved' => false]);
    exit;
}

$savedId = autosaveArticle($id ?: null, $title, $summary, $content, $author ?: 'ScratchNews Staff', $userId);

if ($savedId === null) {
    // Either the id doesn't exist, or it's not a draft (e.g. editing an already-
    // published article) - autosave never touches that, so just report no-op.
    echo json_encode(['saved' => false]);
    exit;
}

echo json_encode(['saved' => true, 'id' => $savedId]);
