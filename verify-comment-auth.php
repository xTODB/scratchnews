<?php
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please refresh the page and try again.']);
    exit;
}

try {
    $scratchUsername = trim($_POST['scratch_username'] ?? '');
    $scratchNewsUsername = trim($_POST['scratchnews_username'] ?? '');

    if ($scratchUsername === '' || !preg_match('/^[A-Za-z0-9_-]{3,20}$/', $scratchUsername)) {
        echo json_encode(['success' => false, 'error' => 'Please enter a valid Scratch username.']);
        exit;
    }
    if ($scratchNewsUsername === '' || !preg_match('/^[A-Za-z0-9_]{3,20}$/', $scratchNewsUsername)) {
        echo json_encode(['success' => false, 'error' => 'Please enter your ScratchNews username in step 1 first.']);
        exit;
    }

    if (isScratchUsernameLinked($scratchUsername)) {
        echo json_encode(['success' => false, 'error' => "The Scratch account @$scratchUsername is already linked to another ScratchNews account."]);
        exit;
    }

    $expectedText = buildCommentAuthText($scratchNewsUsername);
    if (!findScratchProfileComment($scratchUsername, $expectedText)) {
        echo json_encode(['success' => false, 'error' => "We couldn't find that comment on your profile yet. Make sure you posted the exact text, then try again."]);
        exit;
    }

    // Reuses the same session key Follower Auth uses (verify-scratch.php), so
    // register.php's final-submit check works unchanged regardless of which method
    // was used to verify.
    $_SESSION['scratch_verified_username'] = $scratchUsername;
    echo json_encode(['success' => true, 'username' => $scratchUsername]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Something went wrong. Please try again.'
    ]);
}
