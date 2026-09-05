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
    $contestScratcher = trim($_POST['contest_scratcher'] ?? '');
    if (!in_array($contestScratcher, CONTEST_SCRATCHERS, true)) {
        echo json_encode(['success' => false, 'error' => 'Invalid Scratcher selection.']);
        exit;
    }

    $code = $_SESSION['contest_verify_code'] ?? '';
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

    if (isScratchUsernameLinked($contestScratcher)) {
        echo json_encode(['success' => false, 'error' => "The Scratch account @$contestScratcher is already linked to another ScratchNews account."]);
        exit;
    }

    $author = findScratchCommentAuthor($targetUser, $projectId, $code);
    if ($author === null) {
        echo json_encode(['success' => false, 'error' => "We couldn't find your code in any comment on the verification project yet. Make sure you posted it, then try again."]);
        exit;
    }

    if (strcasecmp($author, $contestScratcher) !== 0) {
        echo json_encode(['success' => false, 'error' => "We found your code, but it was posted by @$author, not @$contestScratcher. Make sure you're logged into the right Scratch account."]);
        exit;
    }

    $_SESSION['contest_verified_username'] = $author;
    echo json_encode(['success' => true, 'username' => $author]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Something went wrong. Please try again.'
    ]);
}
