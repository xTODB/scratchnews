<?php
require_once __DIR__ . '/functions.php';
startSession();

$slug = $_GET['slug'] ?? '';
$topicId = (int)($_GET['id'] ?? 0);
$topic = $topicId ? getForumTopicById($topicId) : null;
if (!$topic || $topic['subforum_slug'] !== $slug) {
    header('Location: /forums');
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
if ($page === 1) {
    incrementForumTopicViews($topicId);
}
$result = getForumPosts($topicId, $page, 20);
$posts = $result['posts'];
$totalPages = max(1, (int)ceil($result['total'] / $result['perPage']));

$loggedIn = !empty($_SESSION['reader_username']);
$myId = (int)($_SESSION['reader_id'] ?? 0);
$canModerate = forumCanModerate();
$error = $_GET['error'] ?? '';
$editPostId = (int)($_GET['edit'] ?? 0);

// Quote-prefill: ?quote=123 loads that post's raw content and wraps it in
// [quote=author]...[/quote] for the reply box below.
$replyPrefill = '';
$quoteId = (int)($_GET['quote'] ?? 0);
if ($quoteId) {
    $quotedPost = getForumPostById($quoteId);
    if ($quotedPost && (int)$quotedPost['topic_id'] === $topicId) {
        $replyPrefill = '[quote=' . $quotedPost['author_username'] . ']' . $quotedPost['content'] . "[/quote]\n\n";
    }
}

$allSubforums = $canModerate ? getAllForumSubforums() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title><?= e($topic['title']) ?> - <?= e($topic['subforum_name']) ?> - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=27">
<?php include __DIR__ . '/includes/forum-bbcode.php'; ?>
<style>
.forum-breadcrumb { font-size: 0.9rem; margin-bottom: 0.5rem; }
.forum-breadcrumb a { color: inherit; }
.forum-topic-tag { font-size: 0.75rem; padding: 0.1rem 0.5rem; border-radius: 999px; margin-right: 0.4rem; vertical-align: middle; }
.forum-topic-tag.sticky { background: var(--brand-bright); color: #1a1a1a; }
.forum-topic-tag.locked { background: #999; color: #fff; }
.forum-mod-toolbar { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin: 0.8rem 0; padding: 0.6rem; border: 1px dashed rgba(128,128,128,0.4); border-radius: 8px; }
.forum-mod-toolbar form { display: inline-flex; gap: 0.4rem; align-items: center; }
.forum-post { display: flex; gap: 1rem; border: 1px solid rgba(128,128,128,0.3); border-radius: 10px; padding: 1rem; margin-top: 1rem; }
.forum-post-author { width: 120px; flex-shrink: 0; text-align: center; }
.forum-post-avatar { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; }
.forum-post-avatar-placeholder { width: 56px; height: 56px; border-radius: 50%; background: var(--brand-bright); color: #1a1a1a; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.4rem; }
.forum-post-username { font-weight: 700; margin-top: 0.4rem; display: block; color: inherit; }
.forum-post-body { flex: 1; min-width: 0; }
.forum-post-meta { font-size: 0.8rem; color: #888; display: flex; justify-content: space-between; }
.forum-post-content { margin-top: 0.5rem; overflow-wrap: break-word; }
.forum-post-signature { margin-top: 0.8rem; padding-top: 0.6rem; border-top: 1px dashed rgba(128,128,128,0.35); font-size: 0.82rem; opacity: 0.8; overflow-wrap: break-word; }
.forum-post-actions { margin-top: 0.6rem; display: flex; gap: 0.6rem; }
.forum-post-actions a, .forum-post-actions button { font-size: 0.8rem; }
.forum-pagination { display: flex; gap: 0.6rem; justify-content: center; margin-top: 1.2rem; }
.forum-reply-box { margin-top: 1.5rem; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <div class="forum-breadcrumb"><a href="/forums/<?= e($topic['subforum_slug']) ?>">&larr; <?= e($topic['subforum_name']) ?></a></div>
    <h2>
        <?php if ($topic['is_sticky']): ?><span class="forum-topic-tag sticky">Sticky</span><?php endif; ?>
        <?php if ($topic['is_locked']): ?><span class="forum-topic-tag locked">Locked</span><?php endif; ?>
        <?= e($topic['title']) ?>
    </h2>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <?php if ($canModerate): ?>
    <div class="forum-mod-toolbar">
        <strong>Mod tools:</strong>
        <form method="post" action="/forum-action">
            <?= csrfField() ?>
            <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
            <input type="hidden" name="action" value="<?= $topic['is_sticky'] ? 'unsticky' : 'sticky' ?>">
            <button type="submit" class="btn inline secondary"><?= $topic['is_sticky'] ? 'Unsticky' : 'Sticky' ?></button>
        </form>
        <form method="post" action="/forum-action">
            <?= csrfField() ?>
            <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
            <input type="hidden" name="action" value="<?= $topic['is_locked'] ? 'unlock' : 'lock' ?>">
            <button type="submit" class="btn inline secondary"><?= $topic['is_locked'] ? 'Unlock' : 'Lock' ?></button>
        </form>
        <form method="post" action="/forum-action">
            <?= csrfField() ?>
            <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
            <input type="hidden" name="action" value="move">
            <select name="new_subforum_id">
                <?php foreach ($allSubforums as $sf): ?>
                    <option value="<?= (int)$sf['id'] ?>" <?= (int)$sf['id'] === (int)$topic['subforum_id'] ? 'selected' : '' ?>><?= e($sf['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn inline secondary">Move</button>
        </form>
        <form method="post" action="/forum-action" onsubmit="return confirm('Delete this entire topic and all its posts?');">
            <?= csrfField() ?>
            <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
            <input type="hidden" name="action" value="delete_topic">
            <button type="submit" class="btn inline danger">Delete Topic</button>
        </form>
    </div>
    <?php endif; ?>

    <?php foreach ($posts as $p): ?>
        <?php $isFirst = isFirstForumPost($topicId, (int)$p['id']); ?>
        <div class="forum-post" id="post-<?= (int)$p['id'] ?>">
            <div class="forum-post-author">
                <?php if (!empty($p['author_avatar'])): ?>
                    <img src="<?= e($p['author_avatar']) ?>" alt="" class="forum-post-avatar">
                <?php else: ?>
                    <span class="forum-post-avatar-placeholder"><?= e(mb_strtoupper(mb_substr($p['author_username'], 0, 1))) ?></span>
                <?php endif; ?>
                <a href="/@<?= e($p['author_username']) ?>" class="forum-post-username"><?= e($p['author_username']) ?></a>
            </div>
            <div class="forum-post-body">
                <div class="forum-post-meta">
                    <span><?= date('M j, Y g:i A', strtotime($p['created_at'])) ?><?= $p['edited_at'] ? ' (edited)' : '' ?></span>
                </div>
                <?php $canEditThis = $myId > 0 && $myId === (int)$p['author_id'] && (!$topic['is_locked'] || $canModerate); ?>
                <?php if ($editPostId === (int)$p['id'] && $canEditThis): ?>
                    <form method="post" action="/forum-action" class="forum-edit-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="action" value="edit_post">
                        <textarea name="content" id="edit-content-<?= (int)$p['id'] ?>" class="bbcode-textarea" rows="4" style="width:100%;"><?= e($p['content']) ?></textarea>
                        <?php renderBBCodeToolbar('edit-content-' . (int)$p['id']); ?>
                        <div style="margin-top:0.5rem; display:flex; gap:0.6rem;">
                            <button type="submit" class="btn inline">Save</button>
                            <a href="?page=<?= $page ?>#post-<?= (int)$p['id'] ?>" class="btn inline secondary">Cancel</a>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="forum-post-content"><?= renderBBCode($p['content']) ?></div>
                    <?php if (!empty($p['author_signature'])): ?>
                        <div class="forum-post-signature"><?= renderBBCode($p['author_signature']) ?></div>
                    <?php endif; ?>
                    <div class="forum-post-actions">
                        <?php if ($loggedIn): ?>
                            <a href="?quote=<?= (int)$p['id'] ?>#reply-form">Quote</a>
                        <?php endif; ?>
                        <?php if ($canEditThis): ?>
                            <a href="?page=<?= $page ?>&edit=<?= (int)$p['id'] ?>#post-<?= (int)$p['id'] ?>">Edit</a>
                        <?php endif; ?>
                        <?php if ($canModerate && !$isFirst): ?>
                            <form method="post" action="/forum-action" onsubmit="return confirm('Delete this post?');" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
                                <input type="hidden" name="action" value="delete_post">
                                <button type="submit" class="btn inline danger">Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
    <div class="forum-pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>" class="btn inline secondary">&laquo; Prev</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>" class="btn inline secondary">Next &raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="forum-reply-box" id="reply-form">
        <?php if (!$loggedIn): ?>
            <div class="alert">You must <a href="/login">log in</a> to reply.</div>
        <?php elseif ($topic['is_locked'] && !$canModerate): ?>
            <div class="alert">This topic is locked - no new replies.</div>
        <?php else: ?>
            <h3>Reply</h3>
            <form method="post" action="/forum-action">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reply">
                <input type="hidden" name="topic_id" value="<?= (int)$topic['id'] ?>">
                <?php renderBBCodeToolbar('bbcode_editor'); ?>
                <textarea id="bbcode_editor" name="content" rows="6" required><?= e($replyPrefill) ?></textarea>
                <button type="submit" class="btn">Post Reply</button>
            </form>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
