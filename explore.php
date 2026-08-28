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
$newSinceCount = getNewSinceCount('sn_seen_articles', 'articles', "status = 'published'");
$newSinceCookie = 'sn_seen_articles';
$newSinceLabel = $newSinceCount === 1 ? 'new article' : 'new articles';

// Featured row only shows on the default, unfiltered view - once someone's
// filtering/sorting they're looking for something specific, not browsing.
$showFeatured = $activeSlug === 'all' && $sort === 'metrics' && $authorFilter === '' && $dateFrom === '' && $dateTo === '';
$featuredList = $showFeatured ? getFeaturedArticles(5) : [];

function exploreTabLink(string $cat, string $sort, string $author, string $from, string $to): string {
    $params = ['category' => $cat];
    if ($sort !== 'metrics') $params['sort'] = $sort;
    if ($author !== '') $params['author'] = $author;
    if ($from !== '') $params['from'] = $from;
    if ($to !== '') $params['to'] = $to;
    return '/explore?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Explore - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=27">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php include __DIR__ . '/includes/banner-poll-slot.php'; ?>
<?php include __DIR__ . '/includes/new-since-badge.php'; ?>
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

    <?php if (!empty($featuredList)): ?>
    <section class="row-section">
        <h3 class="row-title">Featured</h3>
        <div class="row-scroll">
            <?php foreach ($featuredList as $a): ?>
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

    <?php if (empty($articles)): ?>
        <p>No articles match these filters.</p>
    <?php else: ?>
        <?php
            $big = $articles[0] ?? null;
            $medium = array_slice($articles, 1, 2);
            $rest = array_slice($articles, 3);
        ?>
        <?php if ($big): ?>
        <div class="explore-grid">
            <a href="/article/<?= (int)$big['id'] ?>" class="explore-card explore-card-big">
                <div class="card-media">
                    <?php if (!empty($big['image_url'])): ?>
                        <img src="<?= e($big['image_url']) ?>" alt="" class="explore-card-img">
                    <?php else: ?>
                        <div class="explore-card-img explore-card-img-placeholder"></div>
                    <?php endif; ?>
                    <?= renderCardToolbar($big, getLikeCount($big['id']), hasUserLiked($big['id'], $_SESSION['reader_id'] ?? 0), getDislikeCount($big['id']), hasUserDisliked($big['id'], $_SESSION['reader_id'] ?? 0), getCommentCount($big['id'])) ?>
                </div>
                <div class="explore-card-title"><?= e(translatedTitle($big)) ?></div>
            </a>
            <?php if (!empty($medium)): ?>
            <div class="explore-medium-col">
                <?php foreach ($medium as $a): ?>
                <a href="/article/<?= (int)$a['id'] ?>" class="explore-card explore-card-medium">
                    <div class="card-media">
                        <?php if (!empty($a['image_url'])): ?>
                            <img src="<?= e($a['image_url']) ?>" alt="" class="explore-card-img">
                        <?php else: ?>
                            <div class="explore-card-img explore-card-img-placeholder"></div>
                        <?php endif; ?>
                        <?= renderCardToolbar($a, getLikeCount($a['id']), hasUserLiked($a['id'], $_SESSION['reader_id'] ?? 0), getDislikeCount($a['id']), hasUserDisliked($a['id'], $_SESSION['reader_id'] ?? 0), getCommentCount($a['id'])) ?>
                    </div>
                    <div class="explore-card-title"><?= e(translatedTitle($a)) ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="search-results-list" style="margin-top:1.5rem;">
            <?php foreach ($rest as $a):
                $likeCount = getLikeCount($a['id']);
                $dislikeCount = getDislikeCount($a['id']);
                $commentCount = getCommentCount($a['id']);
                $desc = $a['summary'] ?? '';
                if (mb_strlen($desc) > 140) $desc = mb_substr($desc, 0, 140) . '...';
            ?>
                <a href="/article/<?= (int)$a['id'] ?>" class="search-result">
                    <?php if (!empty($a['image_url'])): ?>
                        <img src="<?= e($a['image_url']) ?>" alt="" class="search-result-thumb">
                    <?php else: ?>
                        <div class="search-result-thumb search-result-thumb-placeholder"></div>
                    <?php endif; ?>
                    <div class="search-result-body">
                        <div>
                            <div class="search-result-title"><?= e(translatedTitle($a)) ?></div>
                            <div class="meta">By <?= e($a['author']) ?> &middot; <?= utcTimeTag($a['created_at']) ?></div>
                            <?php if ($desc !== ''): ?><div class="search-result-desc"><?= e($desc) ?></div><?php endif; ?>
                        </div>
                        <div class="search-result-stats">
                            <span><img src="/assets/icons/unlike.svg" class="icon-svg-sm" alt=""><?= formatCount($likeCount) ?></span>
                            <span><img src="/assets/icons/undislike.svg" class="icon-svg-sm" alt=""><?= formatCount($dislikeCount) ?></span>
                            <span><img src="/assets/icons/comment.svg" class="icon-svg-sm" alt=""><?= formatCount($commentCount) ?></span>
                            <?= renderThreeDotMenu($a, $likeCount, hasUserLiked($a['id'], $_SESSION['reader_id'] ?? 0), $dislikeCount, hasUserDisliked($a['id'], $_SESSION['reader_id'] ?? 0)) ?>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
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
</script>
</body>
</html>