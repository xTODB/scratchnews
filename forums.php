<?php
require_once __DIR__ . '/functions.php';
startSession();
logVisit('/forums');

$categories = getForumCategoriesWithSubforums();
$isAdmin = !empty($_SESSION['is_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/favicon.php'; ?>
<title>Forums - <?= e(SITE_NAME) ?></title>
<meta name="description" content="ScratchNews Forums - topics, discussion, and suggestions.">
<link rel="stylesheet" href="/assets/style.css?v=27">
<style>
.forums-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.forum-category { margin-top: 1.5rem; }
.forum-category h3 { margin-bottom: 0.5rem; }
.forum-table { width: 100%; border-collapse: collapse; }
.forum-table th { text-align: left; padding: 0.5rem 0.8rem; font-size: 0.85rem; color: #888; border-bottom: 2px solid rgba(128,128,128,0.3); }
.forum-table td { padding: 0.8rem; border-bottom: 1px solid rgba(128,128,128,0.2); vertical-align: top; }
.forum-table tr:last-child td { border-bottom: none; }
.forum-subforum-name { font-size: 1.1rem; font-weight: 700; }
.forum-subforum-desc { font-size: 0.9rem; color: #888; margin-top: 0.2rem; }
.forum-count-col { text-align: center; width: 80px; white-space: nowrap; }
.forum-lastpost-col { width: 220px; font-size: 0.9rem; }
.forum-lastpost-empty { color: #999; }
@media (max-width: 700px) {
    .forum-count-col, .forum-lastpost-col { display: none; }
}
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <div class="forums-header">
        <h2>Forums</h2>
        <?php if ($isAdmin): ?>
            <a href="/admin/forums" class="btn inline secondary">Manage Categories</a>
        <?php endif; ?>
    </div>

    <?php if (!$categories): ?>
        <div class="alert">No forum categories yet.</div>
    <?php endif; ?>

    <?php foreach ($categories as $cat): ?>
        <div class="forum-category">
            <h3><?= e($cat['name']) ?></h3>
            <?php if (!$cat['subforums']): ?>
                <div class="alert">No subforums in this category yet.</div>
            <?php else: ?>
            <table class="forum-table">
                <thead>
                    <tr>
                        <th></th>
                        <th class="forum-count-col">Topics</th>
                        <th class="forum-count-col">Posts</th>
                        <th class="forum-lastpost-col">Last Post</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cat['subforums'] as $sf): ?>
                    <tr>
                        <td>
                            <a href="/forums/<?= e($sf['slug']) ?>" class="forum-subforum-name"><?= e($sf['name']) ?></a>
                            <?php if ($sf['description']): ?>
                                <div class="forum-subforum-desc"><?= e($sf['description']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="forum-count-col"><?= (int)$sf['topic_count'] ?></td>
                        <td class="forum-count-col"><?= (int)$sf['post_count'] ?></td>
                        <td class="forum-lastpost-col">
                            <?php if ($sf['last_post_id']): ?>
                                <a href="/forums/<?= e($sf['slug']) ?>/<?= (int)$sf['last_post_topic_id'] ?>"><?= e($sf['last_post_topic_title']) ?></a><br>
                                <?= date('M j, Y g:i A', strtotime($sf['last_post_at'])) ?> by
                                <a href="/@<?= e($sf['last_post_username']) ?>"><?= e($sf['last_post_username']) ?></a>
                            <?php else: ?>
                                <span class="forum-lastpost-empty">No posts yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
