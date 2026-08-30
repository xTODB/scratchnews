<?php
require_once __DIR__ . '/functions.php';
startSession();

$text = buildPublicStatsExportText();
$filename = 'scratchnews-stats-' . gmdate('Y-m-d') . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($text));
echo $text;
