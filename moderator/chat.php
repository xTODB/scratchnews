<?php
require_once __DIR__ . '/auth.php';

$myId = (int)($_SESSION['reader_id'] ?? 0);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $source = $_POST['source'] ?? '';
    $action = $_POST['action'] ?? '';

    if ($commentId > 0 && $action === 'delete_comment' && in_array($source, ['article', 'profile', 'group'], true)) {
        adminDeleteChatComment($source, $commentId);
        $message = 'Comment deleted.';
    }
}

$feed = getChatFeed(50);
// Mark read AFTER computing this page's own view - the nav badge (rendered by
// nav.php right below) should still show what was unread walking in, not 0.
$unreadWalkingIn = getChatUnreadCountForUser($myId);
if ($myId > 0) markChatViewed($myId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Chat - Moderator - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.chat-feed-item { border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 0.9rem 1rem; margin-bottom: 0.8rem; }
.chat-feed-meta { font-size: 0.85rem; opacity: 0.75; margin: 0 0 0.4rem; display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
.chat-feed-source { text-transform: uppercase; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.03em; padding: 0.1rem 0.5rem; border-radius: 999px; }
.chat-feed-source.article { background: #dde8ff; color: #2b4fa3; }
.chat-feed-source.profile { background: #ffe1f0; color: #a32b6f; }
.chat-feed-source.group { background: #e1ffe4; color: #0a7d1f; }
body.dark .chat-feed-source.article { background: #1f2c4a; color: #9fb6ff; }
body.dark .chat-feed-source.profile { background: #4a1f38; color: #ff9fce; }
body.dark .chat-feed-source.group { background: #1f4a26; color: #7fdb8f; }
.chat-feed-content { margin: 0; white-space: pre-wrap; word-break: break-word; }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Chat</h2>
    <p class="meta">Every comment from articles, profiles, and groups (replies included), newest first.<?= $unreadWalkingIn > 0 ? ' ' . $unreadWalkingIn . ' new since your last visit.' : '' ?></p>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>

    <?php if (empty($feed)): ?>
        <p>No comments yet.</p>
    <?php else: ?>
        <?php foreach ($feed as $item): ?>
            <div class="chat-feed-item" id="chat-<?= e($item['source']) ?>-<?= (int)$item['id'] ?>">
                <p class="chat-feed-meta">
                    <span class="chat-feed-source <?= e($item['source']) ?>"><?= e(ucfirst($item['source'])) ?></span>
                    <?= renderCommentAvatar($item['avatar_url'] ?? null, $item['username']) ?>
                    <a href="/@<?= e($item['username']) ?>">@<?= e($item['username']) ?></a>
                    on
                    <?php if ($item['source_link']): ?>
                        <a href="<?= e($item['source_link']) ?>"><?= e($item['source_label'] ?? 'deleted') ?></a>
                    <?php else: ?>
                        <em>deleted <?= e($item['source']) ?></em>
                    <?php endif; ?>
                    &middot; <?= utcTimeTag($item['created_at']) ?>
                </p>
                <p class="chat-feed-content"><?= linkifyUrls(linkifyMentions(e($item['content']))) ?></p>
                <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="comment_id" value="<?= (int)$item['id'] ?>">
                    <input type="hidden" name="source" value="<?= e($item['source']) ?>">
                    <input type="hidden" name="action" value="delete_comment">
                    <button class="btn" type="submit" style="background:#a33;" onclick="return confirm('Delete this comment? This also deletes its replies.');">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
<script>
document.querySelectorAll('time.local-date, time.local-datetime').forEach(function(el) {
    var d = new Date(el.getAttribute('datetime'));
    if (isNaN(d.getTime())) return;
    if (el.classList.contains('local-datetime')) {
        el.textContent = d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    } else {
        el.textContent = d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    }
});
</script>
</body>
</html>
