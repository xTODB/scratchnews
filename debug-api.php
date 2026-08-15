<?php
// TEMPORARY DIAGNOSTIC — delete this file once the real error is found.
require_once __DIR__ . '/functions.php';
header('Content-Type: text/plain');

function step(string $label, callable $fn): void {
    echo "--- $label ---\n";
    try {
        $result = $fn();
        echo "OK: " . json_encode($result) . "\n\n";
    } catch (\Throwable $e) {
        echo "FAILED: " . get_class($e) . ": " . $e->getMessage() . "\n";
        echo $e->getTraceAsString() . "\n\n";
    }
}

step('getDB() connects', function () {
    $db = getDB();
    return ['connected' => $db ? true : false];
});

step("getApiSetting('rate_limiting_enabled')", function () {
    return getApiSetting('rate_limiting_enabled', '1');
});

step("api_settings table structure", function () {
    $db = getDB();
    $res = $db->query("DESCRIBE api_settings");
    $cols = [];
    while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
    return $cols;
});

step("api_keys table structure", function () {
    $db = getDB();
    $res = $db->query("DESCRIBE api_keys");
    $cols = [];
    while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
    return $cols;
});

step("api_requests table structure", function () {
    $db = getDB();
    $res = $db->query("DESCRIBE api_requests");
    $cols = [];
    while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
    return $cols;
});

step("getApiKeyByToken() with a bogus token", function () {
    return getApiKeyByToken('this-is-not-a-real-key');
});

step("checkAndLogApiRequest() with a fake identifier", function () {
    return checkAndLogApiRequest('debug:test', 30);
});

step("full requireApiAccess() (no Authorization header sent)", function () {
    requireApiAccess();
    return 'passed without exiting (unexpected here - it should 401/rate-limit path)';
});

echo "=== done ===\n";
