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

$redirect = safeInternalRedirect($_POST['redirect'] ?? null);
$googleId = $payload['sub'];
$email = $payload['email'] ?? '';
$name = $payload['name'] ?? '';

$user = getUserByGoogleIdOrEmail($googleId, $email);

if ($user) {
    // Returning account - log straight in, same as before.
    if ($user['google_id'] !== $googleId) {
        linkGoogleIdToUser($user['id'], $googleId);
    }

    updateUserIp($user['id'], $_SERVER['REMOTE_ADDR'] ?? '');

    $_SESSION['reader_id'] = $user['id'];
    $_SESSION['reader_username'] = $user['username'];
    $_SESSION['is_admin'] = !empty($user['is_admin']);
    $_SESSION['is_moderator'] = !empty($user['is_moderator']);
    $_SESSION['dark_mode'] = $user['dark_mode'];
    $_SESSION['translate_lang'] = $user['translate_lang'] ?? '';

    $token = setRememberToken($user['id']);
    setcookie('remember_me', $user['id'] . ':' . $token, [
        'expires' => time() + 60 * 60 * 24 * 30,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (isUserBanned($user['id'])) {
        echo json_encode(['redirect' => '/banned']);
        exit;
    }

    echo json_encode(['redirect' => !empty($user['is_admin']) ? '/admin/' : $redirect]);
    exit;
}

// No matching account: this is a brand new signup. Google sign-in no longer acts as
// a replacement for follower/phone verification - stash the verified Google identity
// in the session and send the person to /register to complete the same Scratch-follow
// or phone verification a username/password signup requires. Account creation happens
// in register.php's Finish handler once that's done (see $googlePending there).
$_SESSION['google_pending'] = ['google_id' => $googleId, 'email' => $email, 'name' => $name];
echo json_encode(['newSignup' => true]);
