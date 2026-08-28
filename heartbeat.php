<?php
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

$sessionKey = $_POST['session_key'] ?? '';
if (!preg_match('/^[a-f0-9]{32,64}$/i', $sessionKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid session key']);
    exit;
}

$source = trim($_POST['source'] ?? '');
$source = $source !== '' ? substr($source, 0, 50) : null;
$page = trim($_POST['page'] ?? '');
$page = $page !== '' ? substr($page, 0, 191) : null;
$userId = $_SESSION['reader_id'] ?? null;

recordHeartbeat($sessionKey, $source, $userId, $page);
echo json_encode(['ok' => true]);