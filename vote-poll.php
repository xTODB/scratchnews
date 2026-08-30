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
    $pollId = (int)($_POST['poll_id'] ?? 0);
    $optionIds = $_POST['option_ids'] ?? [];
    if (!is_array($optionIds)) $optionIds = [$optionIds];

    if ($pollId <= 0 || empty($optionIds)) {
        echo json_encode(['success' => false, 'error' => 'Pick an option first.']);
        exit;
    }

    $voterSid = ensureShareId();
    if (hasVotedOnPoll($pollId, $voterSid)) {
        echo json_encode(['success' => false, 'error' => 'You already voted on this poll.']);
        exit;
    }

    $poll = getPollById($pollId);
    if ($poll && isPollExpired($poll)) {
        echo json_encode(['success' => false, 'error' => 'This poll has ended.']);
        exit;
    }

    if (!submitPollVote($pollId, $optionIds, $voterSid)) {
        echo json_encode(['success' => false, 'error' => 'Vote failed. Please refresh and try again.']);
        exit;
    }

    echo json_encode(['success' => true]);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Something went wrong. Please try again.'
    ]);
}