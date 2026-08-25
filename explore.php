<?php
require_once __DIR__ . '/functions.php';
startSession();
logVisit('/explore');

$categories = getAllCategories();
$activeSlug = $_GET['category'] ?? 'all';
$sort = $_GET['sort'] ?? 'metrics';
$authorFilter = trim($_GET['author'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');

$articles = getExploreArticles($activeSlug, $sort, $authorFilter, $dateFrom, $dateTo);
$groups = getActiveGroups();

function exploreTabLink(string $cat, string $sort, string $author, string $from, string $to): string {
    $params = ['category' => $cat];
    if ($sort !== 'metrics') $params['sort'] = $sort;
    if ($author !== '') $params['author'] = $author;
    if ($from !== '') $params['from'] = $from;
    if ($to !== '') $params['to'] = $to;
    return '/explore?' . http_build_query($params);
}

// v0.25: Recent/Groups rows page through pulled items (renderExploreRow's own
// helper, kept local/distinct from index.php's renderNewsRow to avoid
// touching that just-confirmed-live file for this page's rework).
function renderExploreRow(string $title, array $items, int $perPage, callable $renderCard): void {
    if (empty($items)) return;
    $pages = array_chunk($items, $perPage);
    $pageCount = count($pages);
    ?>
    <section class="row-section">
        <h3 class="row-title"><?= e($title) ?></h3>
        <div class="row-scroll-wrap">
            <button type="button" class="row-scroll-arrow prev" aria-label="Previous <?= e($title) ?>" onclick="scrollRow(this,-1)" disabled>&#8249;</button>
            <div class="row-scroll">
                <?php foreach ($pages as $i => $page): ?>
                <div class="row-page<?= $i === 0 ? ' active' : '' ?>">
                    <?php foreach ($page as $item) $renderCard($item); ?>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="row-scroll-arrow next" aria-label="Next <?= e($title) ?>" onclick="scrollRow(this,1)" <?= $pageCount <= 1 ? 'disabled' : '' ?>>&#8250;</button>
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
<title>Explore - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=26">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/banner-poll-slot.php'; ?>
<main class="home-main">
    <h2 class="explore-title">Explore</h2>
    <div class="explore-tabs">
        <a href="<?= exploreTabLink('all', $sort, $authorFilter, $dateFrom, $dateTo) ?>" class="explore-tab <?= $activeSlug === 'all' ? 'active' : '' ?>">All</a>
        <?php foreach ($categories as $cat): ?>
            <a href="<?= exploreTabLink($cat['slug'], $sort, $authorFilter, $dateFrom, $dateTo) ?>" class="explore-tab <?= $activeSlug === $cat['slug'] ? 'active' : '' ?>"><?= e($cat['name']) ?></a>
        <?php endforeach; ?>
        <div class="explore-filter-wrap">
            <button type="button" class="explore-filter-btn explore-tab" onclick="document.getElementById('filterMenu').classList.toggle('open')">
                <svg viewBox="0 0 24 24" width="14" height="14" xmlns="http://www.w3.org/2000/svg"><path d="M3 4h18l-7 8v6l-4 2v-8z" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg>
                Filter
            </button>
            <div id="filterMenu" class="explore-filter-menu">
                <form method="get" class="explore-filter-form">
                    <input type="hidden" name="category" value="<?= e($activeSlug) ?>">
                    <label for="filterSort">Sort by</label>
                    <select name="sort" id="filterSort">
                        <option value="metrics" <?= $sort === 'metrics' ? 'selected' : '' ?>>Metrics (default)</option>
                        <option value="recent" <?= $sort === 'recent' ? 'selected' : '' ?>>Recent</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Popular (views)</option>
                        <option value="most_liked" <?= $sort === 'most_liked' ? 'selected' : '' ?>>Most Liked</option>
                        <option value="most_disliked" <?= $sort === 'most_disliked' ? 'selected' : '' ?>>Most Disliked</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                    <label for="filterAuthor">Author contains</label>
                    <input type="text" name="author" id="filterAuthor" value="<?= e($authorFilter) ?>" placeholder="e.g. TODB">
                    <label for="filterFrom">From date</label>
                    <input type="date" name="from" id="filterFrom" value="<?= e($dateFrom) ?>">
                    <label for="filterTo">To date</label>
                    <input type="date" name="to" id="filterTo" value="<?= e($dateTo) ?>">
                    <button type="submit" class="btn" style="margin-top:0.5rem;">Apply</button>
                    <a href="/explore?category=<?= e($activeSlug) ?>" class="reset-filter-link">Reset Filter</a>
                </form>
            </div>
        </div>
        </div>

    <?php if (empty($articles)): ?>
        <p>No articles match these filters.</p>
    <?php else: ?>
        <?php renderExploreRow('Recent', $articles, 3, function (array $a): void { ?>
            <a href="/article/<?= (int)$a['id'] ?>" class="row-card">
                <?php if (!empty($a['image_url'])): ?>
                    <img src="<?= e($a['image_url']) ?>" alt="" class="row-card-img">
                <?php else: ?>
                    <div class="row-card-img row-card-img-placeholder"></div>
                <?php endif; ?>
                <div class="row-card-title"><?= e(translatedTitle($a)) ?></div>
            </a>
        <?php }); ?>
    <?php endif; ?>

    <?php if (empty($groups)): ?>
        <p>No groups yet - be the first to request one via <a href="/groups">Groups</a>.</p>
    <?php else: ?>
        <?php renderExploreRow('Groups', $groups, 2, function (array $g): void { ?>
            <a href="/group/<?= e($g['slug']) ?>" class="group-row-card">
                <?php if (!empty($g['banner_url'])): ?>
                    <img src="<?= e($g['banner_url']) ?>" alt="" class="group-row-media">
                <?php else: ?>
                    <div class="group-row-media group-row-media-placeholder"></div>
                <?php endif; ?>
                <div class="group-row-body">
                    <div class="group-row-title"><?= e($g['name']) ?></div>
                    <?php if (!empty($g['description'])):
                        $desc = $g['description'];
                        if (mb_strlen($desc) > 90) $desc = mb_substr($desc, 0, 90) . '...';
                    ?>
                        <div class="group-row-desc"><?= e($desc) ?></div>
                    <?php endif; ?>
                    <div class="group-row-meta"><?= (int)$g['member_count'] ?> member<?= (int)$g['member_count'] === 1 ? '' : 's' ?></div>
                </div>
            </a>
        <?php }); ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('click', function(e) {
    var menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-nav')) menu.classList.remove('open');
    var filterMenu = document.getElementById('filterMenu');
    if (filterMenu && !e.target.closest('.explore-filter-wrap')) filterMenu.classList.remove('open');
});
function scrollRow(btn, dir) {
    var wrap = btn.closest('.row-scroll-wrap');
    var pages = wrap.querySelectorAll('.row-page');
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