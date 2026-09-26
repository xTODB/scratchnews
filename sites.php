<?php
require_once __DIR__ . '/functions.php';
startSession();
logVisit('/s');

$isAdmin = !empty($_SESSION['is_admin']);
$sites = $isAdmin ? getAllSites() : getActiveSites();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/favicon.php'; ?>
<title>Sites - <?= e(SITE_NAME) ?></title>
<meta name="description" content="ScratchNews Sites - independent tools and sites for the Scratch community.">
<link rel="stylesheet" href="/assets/style.css?v=27">
<style>
.forums-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.site-card { display: block; border: 1px solid rgba(128,128,128,0.3); border-radius: 10px; padding: 1rem; margin-top: 1rem; color: inherit; }
.site-card:hover { border-color: var(--brand-bright); }
.site-card-name { font-size: 1.15rem; font-weight: 700; }
.site-card-desc { color: #888; margin-top: 0.3rem; }
.site-card-tag { font-size: 0.75rem; padding: 0.1rem 0.5rem; border-radius: 999px; margin-left: 0.5rem; vertical-align: middle; background: #999; color: #fff; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <div class="forums-header">
        <h2>ScratchNews Sites</h2>
        <?php if ($isAdmin): ?>
            <a href="/admin/sites" class="btn inline secondary">Manage Sites</a>
        <?php endif; ?>
    </div>
    <p style="color:#888;">Independent tools and sites for the Scratch community, built by ScratchNews.</p>

    <?php if (!$sites): ?>
        <div class="alert">No sites yet - check back soon.</div>
    <?php endif; ?>

    <?php foreach ($sites as $s): ?>
        <a href="/s/<?= e($s['slug']) ?>/" class="site-card">
            <span class="site-card-name"><?= e($s['name']) ?></span>
            <?php if ($isAdmin && empty($s['is_active'])): ?><span class="site-card-tag">Hidden</span><?php endif; ?>
            <?php if ($s['description']): ?><div class="site-card-desc"><?= e($s['description']) ?></div><?php endif; ?>
        </a>
    <?php endforeach; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>