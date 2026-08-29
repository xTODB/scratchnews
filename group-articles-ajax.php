<?php
require_once __DIR__ . '/functions.php';
startSession();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Session expired. Please refresh the page and try again.']);
    exit;
}

if (empty($_SESSION['reader_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'You must be logged in.']);
    exit;
}

try {
    $myId = (int)$_SESSION['reader_id'];
    $isSiteMod = !empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator']);
    $groupId = (int)($_POST['group_id'] ?? 0);
    $group = getGroupById($groupId);
    if (!$group || $group['status'] !== 'active') {
        echo json_encode(['success' => false, 'error' => 'Group not found.']);
        exit;
    }
    $myRole = getGroupMemberRole($groupId, $myId);
    if (!canAttachArticleToGroup($myRole, $group['article_policy'] ?? 'members', true)) {
        echo json_encode(['success' => false, 'error' => 'Only members can add articles to this group.']);
        exit;
    }

    $op = $_POST['op'] ?? 'list';

    if ($op === 'list') {
        $offset = max(0, (int)($_POST['offset'] ?? 0));
        $limit = 10;
        $rows = getPublishedArticlesByUserPaginated($myId, $offset, $limit);
        $total = countPublishedArticlesByUser($myId);
        $articles = array_map(function ($a) use ($groupId) {
            return [
                'id' => (int)$a['id'],
                'title' => $a['title'],
                'image_url' => $a['image_url'],
                'already_attached' => isArticleAttachedToGroup($groupId, (int)$a['id']),
            ];
        }, $rows);
        echo json_encode([
            'success' => true,
            'articles' => $articles,
            'has_more' => ($offset + $limit) < $total,
        ]);
        exit;
    }

    if ($op === 'add') {
        $articleId = (int)($_POST['article_id'] ?? 0);
        $article = $articleId > 0 ? getArticleById($articleId) : null;
        // Selecting from your own published-articles list, not a pasted link -
        // the "full link required" rule (0.25.1) only applies to the paste-in box.
        if (!$article || $article['status'] !== 'published' || (int)($article['user_id'] ?? 0) !== $myId) {
            echo json_encode(['success' => false, 'error' => 'You can only add your own published articles this way.']);
            exit;
        }
        $result = attachArticleToGroup($groupId, $articleId, $myId);
        if (!$result['ok']) {
            echo json_encode(['success' => false, 'error' => $result['reason']]);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Article added.']);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown request.']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Something went wrong. Please try again.']);
}
