<?php
require_once __DIR__ . '/../functions.php';
startSession();

// Admin-only, same gate as admin/share-clicks.php.
if (empty($_SESSION['is_admin'])) {
    header('Location: /login');
    exit;
}

$sidFilter = trim($_GET['sid'] ?? '');
$ownerFilter = trim($_GET['owner'] ?? '');

$text = buildShareClicksExportText($sidFilter ?: null, $ownerFilter ?: null);
$filename = 'scratchnews-share-clicks-' . gmdate('Y-m-d-Hi') . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($text));
echo $text;
