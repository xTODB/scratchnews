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
    $code = $_SESSION['scratch_verify_code'] ?? '';
    if ($code === '') {
        echo json_encode(['success' => false, 'error' => 'No verification code found for this session. Please refresh the page.']);
        exit;
    }

    $targetUser = getApiSetting('scratch_verify_target_user', '');
    $projectId = getApiSetting('scratch_verify_project_id', '');
    if ($targetUser === '' || $projectId === '') {
        echo json_encode(['success' => false, 'error' => 'Scratch verification is not configured yet. Please contact an admin.']);
        exit;
    }

    $author = findScratchCommentAuthor($targetUser, $projectId, $code);
    if ($author === null) {
        echo json_encode(['success' => false, 'error' => "We couldn't find your comment yet. Make sure you posted your exact code on the verification project, then try again."]);
        exit;
    }

    if (!scratchUserFollows($author, $targetUser)) {
        echo json_encode(['success' => false, 'error' => "We found your comment, but @$author isn't following @$targetUser yet. Follow them, then try again."]);
        exit;
    }

    if (isScratchUsernameLinked($author)) {
        echo json_encode(['success' => false, 'error' => "The Scratch account @$author is already linked to another ScratchNews account."]);
        exit;
    }

    $_SESSION['scratch_verified_username'] = $author;
    echo json_encode(['success' => true, 'username' => $author]);
} catch (\Throwable $e) {
    // TEMP DIAGNOSTIC: InfinityFree gives us no error logs, so surface the real crash
    // reason directly in the response instead of letting it die as a blank/HTML 500
    // that the frontend just shows as "Something went wrong." Remove the 'debug' field
    // once the actual bug behind this is found and fixed.
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Something went wrong. Please try again.',
        'debug' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
    ]);
}