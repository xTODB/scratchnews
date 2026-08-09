<?php
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

$idToken = $_POST['credential'] ?? '';
if ($idToken === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing credential.']);
    exit;
}

$payload = verifyGoogleIdToken($idToken);
if (!$payload) {
    http_response_code(401);
    echo json_encode(['error' => 'Could not verify Google sign-in. Please try again.']);
    exit;
}

$googleId = $payload['sub'];
$email = $payload['email'] ?? '';
$name = $payload['name'] ?? '';

$user = findOrCreateGoogleUser($googleId, $email, $name);
if (!$user) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not create your account. Please try again.']);
    exit;
}

if (isUserBanned($user['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'This account is restricted.']);
    exit;
}

updateUserIp($user['id'], $_SERVER['REMOTE_ADDR'] ?? '');

$_SESSION['reader_id'] = $user['id'];
$_SESSION['reader_username'] = $user['username'];
$_SESSION['is_admin'] = !empty($user['is_admin']);
$_SESSION['is_moderator'] = !empty($user['is_moderator']);
$_SESSION['dark_mode'] = $user['dark_mode'];

$token = setRememberToken($user['id']);
setcookie('remember_me', $user['id'] . ':' . $token, [
    'expires' => time() + 60 * 60 * 24 * 30,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

echo json_encode(['redirect' => !empty($user['is_admin']) ? '/admin/' : '/']);