<?php
require_once __DIR__ . '/functions.php';
startSession();

$featuredWriters = getFeaturedWriterUsers();
$fans = getFanUsers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>About - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=17">
<style>
.about-hero { width:100%; height:auto; max-height:500px; object-fit:contain; border-radius:10px; margin:1rem 0 1.5rem; display:block; }
.contributor-grid { display:flex; flex-wrap:wrap; gap:1rem; margin:1rem 0 2rem; }
.contributor-card {
    display:flex; align-items:center; gap:0.75rem; border:1px solid #ccc; border-radius:8px;
    padding:0.75rem 1rem; min-width:220px; flex:1 1 220px; max-width:320px;
}
body.dark .contributor-card { border-color:#444; }
.contributor-avatar { width:48px; height:48px; border-radius:50%; object-fit:cover; background:#ccc; flex-shrink:0; }
.contributor-avatar-fallback {
    width:48px; height:48px; border-radius:50%; background:#d97b1f; color:#fff;
    display:flex; align-items:center; justify-content:center; font-size:1.2rem; font-weight:bold; flex-shrink:0;
}
.contributor-name { font-weight:600; }
.contributor-bio { font-size:0.85rem; opacity:0.8; margin:0.15rem 0 0; }
.credit-card { margin-bottom:1.5rem; }
.credit-card h4 { margin-bottom:0.2rem; }
.credit-role { opacity:0.75; font-size:0.9rem; margin:0 0 0.4rem; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/empty-header.php'; ?>
<main>
    <h2>About</h2>
    <img src="/assets/scratchnewsSS.webp" alt="" class="about-hero" onerror="this.style.display='none';">

    <p>ScratchNews is a news platform about Scratch-related news, made to prevent misinformation and disinformation among the Scratch community.
        It transmits information in the form of articles, has many social features and created by Scratchers, for Scratchers.<br>
        ScratchNews is a growing platform, and features are added almost daily. We'd like if there were users, and users who submit articles to help our goal of making the Scratch community a little more informed every day.
    </p>

    <h3>Who Makes This Happen</h3>
    <div class="credit-card">
        <h4>TODB</h4>
        <p class="credit-role">Founder &amp; Developer</p>
        <p>Builds and maintains ScratchNews solo; everything from the backend to moderation tools to this page you're reading.</p>
    </div>
    <div class="credit-card">
        <h4>benpax</h4>
        <p class="credit-role">First Non-Admin User</p>
        <p>Redesigned the like icon and has been part of the community since near the start.</p>
    </div>
    <!--
    copy the block below to add someone new cuz im lazy:
    <div class="credit-card">
        <h4>Name</h4>
        <p class="credit-role">Role</p>
        <p>What they did.</p>
    </div>
    -->

    <h3>Contributions</h3>
    <p>ScratchNews runs on more than staff writing. Here's who's helped shape it.</p>

    <?php if (!empty($featuredWriters)): ?>
        <h4>Featured Writers</h4>
        <div class="contributor-grid">
            <?php foreach ($featuredWriters as $w): ?>
                <a href="/@<?= e($w['username']) ?>" style="text-decoration:none; color:inherit;">
                    <div class="contributor-card">
                        <?php if (!empty($w['avatar_url'])): ?>
                            <img src="<?= e($w['avatar_url']) ?>" alt="" class="contributor-avatar">
                        <?php else: ?>
                            <div class="contributor-avatar-fallback"><?= e(mb_strtoupper(mb_substr($w['username'], 0, 1))) ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="contributor-name">@<?= e($w['username']) ?></div>
                            <p class="contributor-bio"><?= (int)$w['article_count'] ?> articles published</p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($fans)): ?>
        <h4>Fans</h4>
        <p>These readers have supported ScratchNews with a donation.</p>
        <div class="contributor-grid">
            <?php foreach ($fans as $f): ?>
                <a href="/@<?= e($f['username']) ?>" style="text-decoration:none; color:inherit;">
                    <div class="contributor-card">
                        <?php if (!empty($f['avatar_url'])): ?>
                            <img src="<?= e($f['avatar_url']) ?>" alt="" class="contributor-avatar">
                        <?php else: ?>
                            <div class="contributor-avatar-fallback"><?= e(mb_strtoupper(mb_substr($f['username'], 0, 1))) ?></div>
                        <?php endif; ?>
                        <div>
                            <div class="contributor-name">@<?= e($f['username']) ?></div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($featuredWriters) && empty($fans)): ?>
        <p><em>No Featured Writers or Fans yet — <a href="/submission-guidelines">write 3 articles</a> or <a href="https://ko-fi.com/scratchnews">donate</a> to be the first.</em></p>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>