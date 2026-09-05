<?php
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

// No login required - a push subscription is tied to the browser, not an account,
// so logged-out visitors (the whole point of this feature) can use it. No CSRF token
// either, same reasoning as heartbeat.php: there's no session/account state to hijack
// here, just a browser-local subscription the visitor explicitly opted into.
// If the visitor happens to be logged in, we link the subscription to their account
// too (see savePushSubscription()) - this is what lets a Contest Scratcher get a
// targeted "someone wrote about you" push, on top of the normal category broadcasts.

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid request body']);
    exit;
}

$action = $data['action'] ?? '';

if ($action === 'subscribe') {
    $endpoint = trim($data['endpoint'] ?? '');
    $p256dh = trim($data['keys']['p256dh'] ?? '');
    $auth = trim($data['keys']['auth'] ?? '');
    $categoryIds = array_map('intval', $data['categoryIds'] ?? []);

    if ($endpoint === '' || $p256dh === '' || $auth === '' || strlen($endpoint) > 512) {
        http_response_code(400);
        echo json_encode(['error' => 'missing or invalid subscription fields']);
        exit;
    }

    savePushSubscription($endpoint, $p256dh, $auth, $categoryIds, !empty($_SESSION['reader_id']) ? (int)$_SESSION['reader_id'] : null);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'unsubscribe') {
    $endpoint = trim($data['endpoint'] ?? '');
    if ($endpoint === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing endpoint']);
        exit;
    }
    deletePushSubscriptionByEndpoint($endpoint);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'update_categories') {
    $endpoint = trim($data['endpoint'] ?? '');
    $categoryIds = array_map('intval', $data['categoryIds'] ?? []);
    if ($endpoint === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing endpoint']);
        exit;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM push_subscriptions WHERE endpoint = ?");
    $stmt->bind_param('s', $endpoint);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['error' => 'subscription not found - resubscribe first']);
        exit;
    }
    setPushSubscriptionCategories((int)$row['id'], $categoryIds);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'get_categories') {
    $endpoint = trim($data['endpoint'] ?? '');
    if ($endpoint === '') {
        http_response_code(400);
        echo json_encode(['error' => 'missing endpoint']);
        exit;
    }
    echo json_encode(['ok' => true, 'categoryIds' => getPushSubscriptionCategoryIds($endpoint)]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
