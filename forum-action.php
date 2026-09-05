<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}
requireCsrf();

$myId = (int)$_SESSION['reader_id'];
$canModerate = forumCanModerate();
$action = $_POST['action'] ?? '';

function forumRedirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

if ($action === 'new_topic') {
    $subforumId = (int)($_POST['subforum_id'] ?? 0);
    $subforum = getSubforumById($subforumId);
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    if (!$subforum) forumRedirect('/forums');
    if ($title === '' || $content === '') {
        forumRedirect('/forums/' . $subforum['slug'] . '/new?error=' . urlencode('Title and message are both required.'));
    }
    $topicId = createForumTopic($subforumId, $myId, $title, $content);
    forumRedirect('/forums/' . $subforum['slug'] . '/' . $topicId);
}

if ($action === 'reply') {
    $topicId = (int)($_POST['topic_id'] ?? 0);
    $topic = getForumTopicById($topicId);
    if (!$topic) forumRedirect('/forums');
    if ($topic['is_locked'] && !$canModerate) {
        forumRedirect('/forums/' . $topic['subforum_slug'] . '/' . $topicId . '?error=' . urlencode('This topic is locked.'));
    }
    $content = trim($_POST['content'] ?? '');
    if ($content === '') {
        forumRedirect('/forums/' . $topic['subforum_slug'] . '/' . $topicId . '?error=' . urlencode('Reply cannot be empty.'));
    }
    $postId = addForumPost($topicId, $myId, $content);
    // Land on the last page, where the new reply now lives.
    $result = getForumPosts($topicId, 1, 20);
    $lastPage = max(1, (int)ceil($result['total'] / 20));
    forumRedirect('/forums/' . $topic['subforum_slug'] . '/' . $topicId . '?page=' . $lastPage . '#post-' . $postId);
}

// Everything below requires moderation power.
if (!$canModerate) {
    http_response_code(403);
    die('You do not have permission to do that.');
}

if (in_array($action, ['sticky', 'unsticky', 'lock', 'unlock', 'move', 'delete_topic'], true)) {
    $topicId = (int)($_POST['topic_id'] ?? 0);
    $topic = getForumTopicById($topicId);
    if (!$topic) forumRedirect('/forums');

    if ($action === 'sticky') setForumTopicSticky($topicId, true);
    if ($action === 'unsticky') setForumTopicSticky($topicId, false);
    if ($action === 'lock') setForumTopicLocked($topicId, true);
    if ($action === 'unlock') setForumTopicLocked($topicId, false);
    if ($action === 'move') {
        $newSubforumId = (int)($_POST['new_subforum_id'] ?? 0);
        $newSubforum = getSubforumById($newSubforumId);
        if ($newSubforum) {
            moveForumTopic($topicId, $newSubforumId);
            forumRedirect('/forums/' . $newSubforum['slug'] . '/' . $topicId);
        }
        forumRedirect('/forums/' . $topic['subforum_slug'] . '/' . $topicId);
    }
    if ($action === 'delete_topic') {
        deleteForumTopic($topicId);
        forumRedirect('/forums/' . $topic['subforum_slug']);
    }
    forumRedirect('/forums/' . $topic['subforum_slug'] . '/' . $topicId);
}

if ($action === 'delete_post') {
    $postId = (int)($_POST['post_id'] ?? 0);
    $post = getForumPostById($postId);
    if (!$post) forumRedirect('/forums');
    $topic = getForumTopicById((int)$post['topic_id']);
    if (isFirstForumPost((int)$post['topic_id'], $postId)) {
        // Deleting the topic-starting post doesn't make sense on its own -
        // delete the whole topic instead (forum-topic.php never renders a
        // Delete button for the first post, but guard against a replayed
        // form submission getting here anyway).
        deleteForumTopic((int)$post['topic_id']);
        forumRedirect('/forums/' . $topic['subforum_slug']);
    }
    deleteForumPost($postId);
    forumRedirect('/forums/' . $topic['subforum_slug'] . '/' . $topic['id']);
}

forumRedirect('/forums');
