<?php
require_once __DIR__ . '/../functions.php';
startSession();

// v0.26: same access gate as admin/stats.php - Head Moderators get this too, since
// they already see all of this data on that page.
if (empty($_SESSION['is_admin']) && empty($_SESSION['is_head_moderator'])) {
    header('Location: /login');
    exit;
}

$text = buildAdminStatsExportText();
$filename = 'scratchnews-admin-stats-' . gmdate('Y-m-d') . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($text));
echo $text;
