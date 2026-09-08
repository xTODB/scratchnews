<?php
require_once __DIR__ . '/../functions.php';
startSession();

// Admin-only (not Head Mod) - same gate as admin/visits.php, since this exposes raw IPs.
if (empty($_SESSION['is_admin'])) {
    header('Location: /login');
    exit;
}

$includeIp = trim($_GET['include_ip'] ?? '');
$excludeIp = trim($_GET['exclude_ip'] ?? '');

$text = buildVisitsExportText($includeIp ?: null, $excludeIp ?: null);
$filename = 'scratchnews-visits-' . gmdate('Y-m-d-Hi') . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($text));
echo $text;
