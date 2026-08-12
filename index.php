<?php
require_once __DIR__ . '/functions.php';
startSession();
logVisit('/');
$articles = getAllArticles();
$popular = getPopularArticles(4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title><?= e(SITE_NAME) ?></title>
<meta name="description" content="ScratchNews is a community-run news site covering updates, features, and stories from the Scratch programming community.">
<link rel="stylesheet" href="/assets/style.css?v=21">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php if (!empty($_SESSION['impersonator_admin_username'])): ?>
<div class="impersonation-banner">
    Viewing as <strong><?= e($_SESSION['reader_username']) ?></strong> (impersonating)
    <form method="post" action="/stop-impersonating" class="impersonation-form">
        <?= csrfField() ?>
        <button type="submit" class="text-action">Return to Admin</button>
    </form>
</div>
<?php endif; ?>
<?php $banners = ($randomBanner = getRandomActiveBanner()) ? [$randomBanner] : []; if (!empty($banners)): ?>
<div id="promoBanners">
    <?php foreach ($banners as $b): ?>
        <div class="promo-banner" data-banner-id="<?= (int)$b['id'] ?>" data-banner-key="<?= (int)$b['id'] ?>|<?= e($b['image_url']) ?>">
            <button type="button" class="promo-banner-close" aria-label="Close">&times;</button>
            <a href="<?= e($b['link']) ?>" class="promo-banner-link">
                <img src="<?= e($b['image_url']) ?>" alt="" class="promo-banner-img">
            </a>
        </div>
    <?php endforeach; ?>
</div>
<script>
(function() {
    // Dismissal is keyed by id+image_url, not just id: InfinityFree's MySQL can
    // recalculate AUTO_INCREMENT as MAX(id)+1 (MyISAM behavior), so deleting the
    // highest-numbered banner and creating a new one can silently reuse that numeric
    // id. Keying on id alone meant a brand new banner could inherit an old, already-
    // dismissed one's id and never show up. image_url is unique per upload, so pairing
    // it with the id tells a genuinely new banner apart from a reused id.
    var dismissed = [];
    try { dismissed = JSON.parse(localStorage.getItem('dismissedBanners') || '[]'); } catch (e) {}
    document.querySelectorAll('.promo-banner').forEach(function(el) {
        var key = el.getAttribute('data-banner-key');
        if (dismissed.indexOf(key) !== -1) { el.remove(); return; }
        el.querySelector('.promo-banner-close').addEventListener('click', function() {
            dismissed.push(key);
            try { localStorage.setItem('dismissedBanners', JSON.stringify(dismissed)); } catch (e) {}
            el.remove();
        });
    });
})();
</script>
<?php endif; ?>
<main class="home-main">
    <?php if (empty($articles)): ?>
        <p>No articles yet. Log in to the <a href="/admin/">login panel</a> to publish the first one.</p>
    <?php else: ?>
        <?php
            $featured = $articles[0];
            $side = array_slice($articles, 1, 2);
            $latestRow = array_slice($articles, 0, 4);
        ?>
        <div class="hero">
            <a href="/article/<?= (int)$featured['id'] ?>" class="hero-featured">
                <?php if (!empty($featured['image_url'])): ?>
                    <img src="<?= e($featured['image_url']) ?>" alt="" class="hero-featured-img">
                <?php endif; ?>
                <div class="hero-featured-body">
                    <h2><?= e(translatedTitle($featured)) ?></h2>
                    <div class="meta">By <?= e($featured['author']) ?> &middot; <?= utcTimeTag($featured['created_at']) ?></div>
                </div>
            </a>
            <?php if (!empty($side)): ?>
            <div class="hero-side">
                <?php foreach ($side as $a): ?>
                    <a href="/article/<?= (int)$a['id'] ?>" class="hero-side-card">
                        <?php if (!empty($a['image_url'])): ?>
                            <img src="<?= e($a['image_url']) ?>" alt="" class="hero-side-img">
                        <?php endif; ?>
                        <div class="hero-side-title"><?= e(translatedTitle($a)) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($latestRow)): ?>
        <section class="row-section">
            <h3 class="row-title">Latest</h3>
            <div class="row-scroll">
                <?php foreach ($latestRow as $a): ?>
                    <a href="/article/<?= (int)$a['id'] ?>" class="row-card">
                        <?php if (!empty($a['image_url'])): ?>
                            <img src="<?= e($a['image_url']) ?>" alt="" class="row-card-img">
                        <?php else: ?>
                            <div class="row-card-img row-card-img-placeholder"></div>
                        <?php endif; ?>
                        <div class="row-card-title"><?= e(translatedTitle($a)) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($popular)): ?>
        <section class="row-section">
            <h3 class="row-title">Popular</h3>
            <div class="row-scroll">
                <?php foreach ($popular as $a): ?>
                    <a href="/article/<?= (int)$a['id'] ?>" class="row-card">
                        <?php if (!empty($a['image_url'])): ?>
                            <img src="<?= e($a['image_url']) ?>" alt="" class="row-card-img">
                        <?php else: ?>
                            <div class="row-card-img row-card-img-placeholder"></div>
                        <?php endif; ?>
                        <div class="row-card-title"><?= e(translatedTitle($a)) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('click', function(e) {
    var menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-nav')) menu.classList.remove('open');
});
</script>
</body>
</html>