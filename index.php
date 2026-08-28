<?php
require_once __DIR__ . '/functions.php';
startSession();
logVisit('/');
$articles = getAllArticles();
$popular = getPopularArticles(20);
$featuredList = getFeaturedArticles(20);
$groupsList = getActiveGroups();
$profilesList = getPublicUserList('recent');
$newSinceCount = getNewSinceCount('sn_seen_articles', 'articles', "status = 'published'");
$newSinceCookie = 'sn_seen_articles';
$newSinceLabel = $newSinceCount === 1 ? 'new article' : 'new articles';

// v0.25: row arrows page through additional articles pulled from SQL above
// (5 per page) instead of horizontally scrolling the same 5.
function renderNewsRow(string $title, array $items): void {
    if (empty($items)) return;
    $pages = array_chunk($items, 5);
    $pageCount = count($pages);
    ?>
    <section class="row-section">
        <h3 class="row-title"><?= e($title) ?></h3>
        <div class="row-scroll-wrap">
            <button type="button" class="row-scroll-arrow prev" aria-label="Previous <?= e($title) ?>" onclick="scrollRow(this,-1)" disabled>&#8249;</button>
            <div class="row-scroll">
                <?php foreach ($pages as $i => $page): ?>
                <div class="row-page<?= $i === 0 ? ' active' : '' ?>">
                    <?php foreach ($page as $a): ?>
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
                <?php endforeach; ?>
            </div>
            <button type="button" class="row-scroll-arrow next" aria-label="Next <?= e($title) ?>" onclick="scrollRow(this,1)" <?= $pageCount <= 1 ? 'disabled' : '' ?>>&#8250;</button>
        </div>
    </section>
    <?php
}

// v0.25: Groups row - 2 wide group-row-cards per page (image + title +
// description + member count), same paging pattern as renderNewsRow above.
function renderGroupsRow(array $groups): void {
    if (empty($groups)) return;
    $pages = array_chunk($groups, 2);
    $pageCount = count($pages);
    ?>
    <section class="row-section">
        <h3 class="row-title">Groups</h3>
        <div class="row-scroll-wrap">
            <button type="button" class="row-scroll-arrow prev" aria-label="Previous Groups" onclick="scrollRow(this,-1)" disabled>&#8249;</button>
            <div class="row-scroll">
                <?php foreach ($pages as $i => $page): ?>
                <div class="row-page<?= $i === 0 ? ' active' : '' ?>">
                    <?php foreach ($page as $g):
                        $desc = $g['description'] ?? '';
                        if (mb_strlen($desc) > 90) $desc = mb_substr($desc, 0, 90) . '...';
                    ?>
                        <a href="/group/<?= e($g['slug']) ?>" class="group-row-card">
                            <?php if (!empty($g['banner_url'])): ?>
                                <img src="<?= e($g['banner_url']) ?>" alt="" class="group-row-media">
                            <?php else: ?>
                                <div class="group-row-media group-row-media-placeholder"></div>
                            <?php endif; ?>
                            <div class="group-row-body">
                                <div class="group-row-title"><?= e($g['name']) ?></div>
                                <?php if ($desc !== ''): ?><div class="group-row-desc"><?= e($desc) ?></div><?php endif; ?>
                                <div class="group-row-meta"><?= (int)$g['member_count'] ?> member<?= (int)$g['member_count'] === 1 ? '' : 's' ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="row-scroll-arrow next" aria-label="Next Groups" onclick="scrollRow(this,1)" <?= $pageCount <= 1 ? 'disabled' : '' ?>>&#8250;</button>
        </div>
    </section>
    <?php
}

// v0.25: Profiles section - 3x2 grid of avatar cards per page (arrows page
// through additional profiles), reuses row-scroll-wrap/arrow mechanics but
// with a grid page instead of a flex row.
function renderProfilesRow(array $users): void {
    if (empty($users)) return;
    $pages = array_chunk($users, 6);
    $pageCount = count($pages);
    ?>
    <section class="row-section">
        <h3 class="row-title">Profiles</h3>
        <div class="row-scroll-wrap">
            <button type="button" class="row-scroll-arrow prev" aria-label="Previous Profiles" onclick="scrollRow(this,-1)" disabled>&#8249;</button>
            <div class="row-scroll">
                <?php foreach ($pages as $i => $page): ?>
                <div class="row-page-grid<?= $i === 0 ? ' active' : '' ?>">
                    <?php foreach ($page as $u):
                        $bioFirstLine = trim(explode("\n", (string)($u['bio'] ?? ''))[0]);
                    ?>
                        <a href="/@<?= urlencode($u['username']) ?>" class="profile-row-card">
                            <?php if (!empty($u['avatar_url'])): ?>
                                <img src="<?= e($u['avatar_url']) ?>" alt="" class="profile-row-avatar">
                            <?php else: ?>
                                <span class="profile-row-avatar profile-row-avatar-placeholder"><?= e(mb_strtoupper(mb_substr($u['username'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <div class="profile-row-username">@<?= e($u['username']) ?></div>
                            <?php if ($bioFirstLine !== ''): ?><div class="profile-row-desc"><?= e($bioFirstLine) ?></div><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="row-scroll-arrow next" aria-label="Next Profiles" onclick="scrollRow(this,1)" <?= $pageCount <= 1 ? 'disabled' : '' ?>>&#8250;</button>
        </div>
    </section>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title><?= e(SITE_NAME) ?></title>
<meta name="description" content="ScratchNews is a community-run news site covering updates, features, and stories from the Scratch programming community.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@400;700;900&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/style.css?v=27">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/welcome-banner.php'; ?>
<?php if (!empty($_SESSION['impersonator_admin_username'])): ?>
<div class="impersonation-banner">
    Viewing as <strong><?= e($_SESSION['reader_username']) ?></strong> (impersonating)
    <form method="post" action="/stop-impersonating" class="impersonation-form">
        <?= csrfField() ?>
        <button type="submit" class="text-action">Return to Admin</button>
    </form>
</div>
<?php endif; ?>
<?php include __DIR__ . '/includes/banner-poll-slot.php'; ?>
<?php include __DIR__ . '/includes/new-since-badge.php'; ?>
<main class="home-main">
    <?php if (empty($articles)): ?>
        <p>No articles yet. Log in to the <a href="/admin/">login panel</a> to publish the first one.</p>
    <?php else: ?>
        <?php
            $heroBig = $articles[0];
            $heroSmall = array_slice($articles, 1, 2);
            $heroList = array_slice($articles, 3, 2);
            $latestRow = array_slice($articles, 0, 20);
            $heroTag = function (array $a): ?string {
                $cats = getArticleCategories((int)$a['id']);
                return $cats[0]['name'] ?? null;
            };
        ?>
        <div class="hero">
            <?php if (!empty($heroSmall)): ?>
            <div class="hero-stack">
                <?php foreach ($heroSmall as $a): ?>
                    <a href="/article/<?= (int)$a['id'] ?>" class="hero-side-card">
                        <div class="card-media">
                            <?php if (!empty($a['image_url'])): ?>
                                <img src="<?= e($a['image_url']) ?>" alt="" class="hero-side-img">
                            <?php else: ?>
                                <div class="hero-side-img hero-side-img-placeholder"></div>
                            <?php endif; ?>
                            <?= renderCardToolbar($a, getLikeCount($a['id']), hasUserLiked($a['id'], $_SESSION['reader_id'] ?? 0), getDislikeCount($a['id']), hasUserDisliked($a['id'], $_SESSION['reader_id'] ?? 0), getCommentCount($a['id'])) ?>
                        </div>
                        <div class="hero-side-title"><?= e(translatedTitle($a)) ?><?php $t = $heroTag($a); if ($t): ?> <span class="hero-caption-tag">| <?= e($t) ?></span><?php endif; ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <a href="/article/<?= (int)$heroBig['id'] ?>" class="hero-featured">
                <div class="card-media">
                    <?php if (!empty($heroBig['image_url'])): ?>
                        <img src="<?= e($heroBig['image_url']) ?>" alt="" class="hero-featured-img">
                    <?php else: ?>
                        <div class="hero-featured-img hero-featured-img-placeholder"></div>
                    <?php endif; ?>
                    <?= renderCardToolbar($heroBig, getLikeCount($heroBig['id']), hasUserLiked($heroBig['id'], $_SESSION['reader_id'] ?? 0), getDislikeCount($heroBig['id']), hasUserDisliked($heroBig['id'], $_SESSION['reader_id'] ?? 0), getCommentCount($heroBig['id'])) ?>
                </div>
                <div class="hero-featured-title"><?= e(translatedTitle($heroBig)) ?><?php $t = $heroTag($heroBig); if ($t): ?> <span class="hero-caption-tag">| <?= e($t) ?></span><?php endif; ?></div>
            </a>
            <?php if (!empty($heroList)): ?>
            <div class="hero-list">
                <?php foreach ($heroList as $a): ?>
                    <a href="/article/<?= (int)$a['id'] ?>" class="hero-list-item">
                        <div class="hero-list-title"><?= e(translatedTitle($a)) ?></div>
                        <?php if (!empty($a['summary'])): ?><div class="hero-list-summary"><?= e($a['summary']) ?></div><?php endif; ?>
                        <div class="hero-list-meta"><?php $t = $heroTag($a); if ($t): ?><?= e($t) ?> &middot; <?php endif; ?>by <?= e($a['author']) ?> &middot; <?= utcTimeTag($a['created_at']) ?></div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <hr class="hero-divider">

        <?php renderNewsRow('Featured', $featuredList); ?>
        <?php renderNewsRow('Latest', $latestRow); ?>
        <?php renderNewsRow('Popular', $popular); ?>
        <?php renderGroupsRow($groupsList); ?>
        <?php renderProfilesRow($profilesList); ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('click', function(e) {
    var menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-nav')) menu.classList.remove('open');
});
function scrollRow(btn, dir) {
    var wrap = btn.closest('.row-scroll-wrap');
    var pages = wrap.querySelectorAll('.row-page, .row-page-grid');
    if (!pages.length) return;
    var idx = 0;
    pages.forEach(function (p, i) { if (p.classList.contains('active')) idx = i; });
    var next = idx + dir;
    if (next < 0 || next >= pages.length) return;
    pages[idx].classList.remove('active');
    pages[next].classList.add('active');
    var prevBtn = wrap.querySelector('.row-scroll-arrow.prev');
    var nextBtn = wrap.querySelector('.row-scroll-arrow.next');
    if (prevBtn) prevBtn.disabled = (next === 0);
    if (nextBtn) nextBtn.disabled = (next === pages.length - 1);
}
</script>
</body>
</html>