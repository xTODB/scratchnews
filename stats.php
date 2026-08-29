<?php
require_once __DIR__ . '/functions.php';
startSession();

$days = 30;
$userCounts = getCumulativeUserCounts($days);
$topByViews = getTopArticlesByViews(5);
$topByLikes = getTopArticlesByLikes(5);
$topUsersByArticles = getTopUsersByArticleCount(5);
$topUsersByFollowers = getTopUsersByFollowerCount(5);

$db = getDB();
$totalUsers = (int)($db->query("SELECT COUNT(*) AS c FROM users WHERE is_banned = 0")->fetch_assoc()['c'] ?? 0);
$totalArticles = (int)($db->query("SELECT COUNT(*) AS c FROM articles WHERE status = 'published'")->fetch_assoc()['c'] ?? 0);
$totalComments = getTotalCommentCount();
$totalLikes = (int)($db->query("SELECT COUNT(*) AS c FROM likes")->fetch_assoc()['c'] ?? 0);
$totalGroups = (int)($db->query("SELECT COUNT(*) AS c FROM `groups` WHERE status = 'active'")->fetch_assoc()['c'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Stats - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=22">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Stats</h2>
    <p>A look at how <?= e(SITE_NAME) ?> is growing.</p>

    <div class="stat-cards">
        <div class="stat-card"><p class="stat-card-label">Users</p><p class="stat-card-value"><?= number_format($totalUsers) ?></p></div>
        <div class="stat-card"><p class="stat-card-label">Articles</p><p class="stat-card-value"><?= number_format($totalArticles) ?></p></div>
        <div class="stat-card"><p class="stat-card-label">Comments</p><p class="stat-card-value"><?= number_format($totalComments) ?></p></div>
        <div class="stat-card"><p class="stat-card-label">Likes</p><p class="stat-card-value"><?= number_format($totalLikes) ?></p></div>
        <div class="stat-card"><p class="stat-card-label">Groups</p><p class="stat-card-value"><?= number_format($totalGroups) ?></p></div>
    </div>

    <div class="chart-block">
        <h4>User Count (last <?= $days ?> days)</h4>
        <p class="chart-caption">Total registered users over time.</p>
        <?= renderLineChartSvg($userCounts, ['color' => '#0084ff']) ?>
    </div>

    <h3 style="margin-top:2rem;">Most Popular Articles</h3>
    <p class="chart-caption">By views</p>
    <ul class="stat-list">
        <?php if (empty($topByViews)): ?>
            <li>No articles yet.</li>
        <?php else: foreach ($topByViews as $a): ?>
            <li><a href="/article/<?= (int)$a['id'] ?>"><?= e($a['title']) ?></a> <span class="stat-list-value"><?= number_format((int)$a['views']) ?> views</span></li>
        <?php endforeach; endif; ?>
    </ul>
    <p class="chart-caption">By likes</p>
    <ul class="stat-list">
        <?php if (empty($topByLikes)): ?>
            <li>No liked articles yet.</li>
        <?php else: foreach ($topByLikes as $a): ?>
            <li><a href="/article/<?= (int)$a['id'] ?>"><?= e($a['title']) ?></a> <span class="stat-list-value"><?= number_format((int)$a['like_count']) ?> likes</span></li>
        <?php endforeach; endif; ?>
    </ul>

    <h3 style="margin-top:2rem;">Most Popular Users</h3>
    <p class="chart-caption">By articles published</p>
    <ul class="stat-list">
        <?php if (empty($topUsersByArticles)): ?>
            <li>No published articles yet.</li>
        <?php else: foreach ($topUsersByArticles as $u): ?>
            <li><a href="/@<?= e($u['username']) ?>">@<?= e($u['username']) ?></a> <span class="stat-list-value"><?= formatCount((int)$u['article_count']) ?> articles</span></li>
        <?php endforeach; endif; ?>
    </ul>
    <p class="chart-caption">By followers</p>
    <ul class="stat-list">
        <?php if (empty($topUsersByFollowers)): ?>
            <li>No follows yet.</li>
        <?php else: foreach ($topUsersByFollowers as $u): ?>
            <li><a href="/@<?= e($u['username']) ?>">@<?= e($u['username']) ?></a> <span class="stat-list-value"><?= formatCount((int)$u['follower_count']) ?> followers</span></li>
        <?php endforeach; endif; ?>
    </ul>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>