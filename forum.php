<?php
require_once __DIR__ . '/functions.php';
startSession();

$slug = $_GET['slug'] ?? '';
$subforum = $slug ? getSubforumBySlug($slug) : null;
if (!$subforum) {
    header('Location: /forums');
    exit;
}
logVisit('/forums/' . $slug);

$page = max(1, (int)($_GET['page'] ?? 1));
$result = getForumTopics((int)$subforum['id'], $page, 20);
$topics = $result['topics'];
$totalPages = max(1, (int)ceil($result['total'] / $result['perPage']));
$loggedIn = !empty($_SESSION['reader_username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/favicon.php'; ?>
<title><?= e($subforum['name']) ?> - Forums - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=27">
<style>
.forums-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.forum-breadcrumb { font-size: 0.9rem; margin-bottom: 0.5rem; }
.forum-breadcrumb a { color: inherit; }
.forum-desc { color: #888; margin-bottom: 1rem; }
.forum-table { width: 100%; border-collapse: collapse; }
.forum-table th { text-align: left; padding: 0.5rem 0.8rem; font-size: 0.85rem; color: #888; border-bottom: 2px solid rgba(128,128,128,0.3); }
.forum-table td { padding: 0.8rem; border-bottom: 1px solid rgba(128,128,128,0.2); vertical-align: top; }
.forum-table tr:last-child td { border-bottom: none; }
.forum-topic-title { font-weight: 700; }
.forum-topic-tag { font-size: 0.75rem; padding: 0.1rem 0.5rem; border-radius: 999px; margin-right: 0.4rem; vertical-align: middle; }
.forum-topic-tag.sticky { background: var(--brand-bright); color: #1a1a1a; }
.forum-topic-tag.locked { background: #999; color: #fff; }
.forum-topic-meta { font-size: 0.85rem; color: #888; margin-top: 0.2rem; }
.forum-count-col { text-align: center; width: 80px; }
.forum-lastpost-col { width: 220px; font-size: 0.9rem; }
.forum-pagination { display: flex; gap: 0.6rem; justify-content: center; margin-top: 1.2rem; }
@media (max-width: 700px) {
    .forum-count-col, .forum-lastpost-col { display: none; }
}
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <div class="forum-breadcrumb"><a href="/forums">&larr; Forums</a></div>
    <div class="forums-header">
        <h2><?= e($subforum['name']) ?></h2>
        <?php if ($loggedIn): ?>
            <a href="/forums/<?= e($subforum['slug']) ?>/new" class="btn inline">New Topic</a>
        <?php endif; ?>
    </div>
    <?php if ($subforum['description']): ?>
        <div class="forum-desc"><?= e($subforum['description']) ?></div>
    <?php endif; ?>

    <?php if (!$topics): ?>
        <div class="alert">No topics yet - be the first to post!</div>
    <?php else: ?>
    <table class="forum-table">
        <thead>
            <tr>
                <th></th>
                <th class="forum-count-col">Replies</th>
                <th class="forum-lastpost-col">Last Post</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($topics as $t): ?>
            <tr>
                <td>
                    <?php if ($t['is_sticky']): ?><span class="forum-topic-tag sticky">Sticky</span><?php endif; ?>
                    <?php if ($t['is_locked']): ?><span class="forum-topic-tag locked">Locked</span><?php endif; ?>
                    <a href="/forums/<?= e($subforum['slug']) ?>/<?= (int)$t['id'] ?>" class="forum-topic-title"><?= e($t['title']) ?></a>
                    <div class="forum-topic-meta">by <a href="/@<?= e($t['author_username']) ?>"><?= e($t['author_username']) ?></a></div>
                </td>
                <td class="forum-count-col"><?= max(0, (int)$t['post_count'] - 1) ?></td>
                <td class="forum-lastpost-col">
                    <?= date('M j, Y g:i A', strtotime($t['last_post_at'])) ?><br>
                    by <a href="/@<?= e($t['last_post_username']) ?>"><?= e($t['last_post_username']) ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($totalPages > 1): ?>
    <div class="forum-pagination">
        <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>" class="btn inline secondary">&laquo; Prev</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>" class="btn inline secondary">Next &raquo;</a><?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
