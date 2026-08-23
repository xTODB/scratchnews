<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/functions.php';
startSession();

$query = trim($_GET['q'] ?? '');
$type = in_array($_GET['type'] ?? '', ['articles', 'profiles', 'groups'], true) ? $_GET['type'] : 'articles';
$myId = $_SESSION['reader_id'] ?? 0;

$results = [];
$myGroupIds = [];
if ($query !== '') {
    if ($type === 'profiles') {
        $results = searchProfiles($query);
    } elseif ($type === 'groups') {
        $results = searchGroups($query);
        $myGroupIds = $myId ? array_column(getUserGroups((int)$myId), 'id') : [];
    } else {
        $results = searchArticles($query);
    }
}

$typeQS = function (string $t) use ($query) {
    return '/search?type=' . $t . ($query !== '' ? '&q=' . urlencode($query) : '');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Search - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=23">
<style>
.search-type-tabs {
    display: flex; justify-content: center; gap: 1.1rem;
    margin: 0 auto 1.6rem;
}
.search-type-card {
    display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
    padding: 0.7rem 1.2rem;
    border-radius: 12px;
    text-decoration: none; color: var(--ink);
    opacity: 0.55;
    transition: opacity 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
}
.search-type-card:hover { opacity: 0.85; }
.search-type-card.active {
    opacity: 1;
    background: rgba(255,170,51,0.15);
    box-shadow: 0 0 0 2px var(--brand) inset;
}
.search-type-icon { width: 42px; height: 42px; object-fit: contain; }
.search-type-card span { font-size: 0.85rem; font-weight: 700; }
body.dark .search-type-card.active { background: rgba(255,170,51,0.22); }
@media (max-width: 700px) {
    .search-type-tabs { gap: 0.6rem; }
    .search-type-card { padding: 0.5rem 0.8rem; }
    .search-type-icon { width: 34px; height: 34px; }
}
/* group-card styling (mirrors groups.php, not yet in the shared stylesheet) */
.search-groups-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; margin-top: 1rem; }
.search-groups-grid .group-card { border: 1px solid rgba(128,128,128,0.3); border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; }
.search-groups-grid .group-card-banner { width: 100%; height: 90px; object-fit: cover; background: rgba(128,128,128,0.15); }
.search-groups-grid .group-card-body { padding: 0.8rem; display: flex; flex-direction: column; gap: 0.3rem; }
.search-groups-grid .group-card-name { font-weight: 700; }
.search-groups-grid .group-card-meta { font-size: 0.8rem; opacity: 0.75; }
.search-profiles-grid { margin-top: 1rem; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main<?= $query === '' ? ' search-empty' : '' ?>">
    <form method="get" action="/search" class="page-search-form">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="Search articles..." autofocus>
        <button type="submit" aria-label="Search">
            <img src="/assets/icons/nav-search.svg" alt="" class="icon-svg-sm">
        </button>
    </form>
    <div class="search-type-tabs">
        <a href="<?= e($typeQS('articles')) ?>" class="search-type-card <?= $type === 'articles' ? 'active' : '' ?>">
            <img src="/assets/icons/new_article.svg" alt="" class="search-type-icon">
            <span>Articles</span>
        </a>
        <a href="<?= e($typeQS('profiles')) ?>" class="search-type-card <?= $type === 'profiles' ? 'active' : '' ?>">
            <img src="/assets/icons/nav-profiles.svg" alt="" class="search-type-icon">
            <span>Profiles</span>
        </a>
        <a href="<?= e($typeQS('groups')) ?>" class="search-type-card <?= $type === 'groups' ? 'active' : '' ?>">
            <img src="/assets/icons/nav-groups.svg" alt="" class="search-type-icon">
            <span>Groups</span>
        </a>
    </div>
    <?php if ($query === ''): ?>
    <?php elseif (empty($results)): ?>
        <p>No <?= e($type) ?> matched your search.</p>
    <?php elseif ($type === 'profiles'): ?>
        <div class="profiles-grid search-profiles-grid">
            <?php foreach ($results as $u):
                $bioFirstLine = trim(explode("\n", (string)($u['bio'] ?? ''))[0]);
            ?>
            <div class="profile-card">
                <a href="/@<?= urlencode($u['username']) ?>" class="profile-card-top">
                    <?php if (!empty($u['avatar_url'])): ?>
                        <img src="<?= e($u['avatar_url']) ?>" alt="" class="profile-card-avatar">
                    <?php else: ?>
                        <span class="profile-card-avatar profile-card-avatar-placeholder"><?= e(mb_strtoupper(mb_substr($u['username'], 0, 1))) ?></span>
                    <?php endif; ?>
                    <div class="profile-card-names">
                        <div class="profile-card-username">@<?= e($u['username']) ?><?= renderRankBadges($u) ?></div>
                        <div class="profile-card-tagline"><?= $bioFirstLine !== '' ? e($bioFirstLine) : '&nbsp;' ?></div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    <?php elseif ($type === 'groups'): ?>
        <div class="search-groups-grid">
            <?php foreach ($results as $g): ?>
            <a href="/group/<?= e($g['slug']) ?>" class="group-card">
                <?php if (!empty($g['banner_url'])): ?>
                    <img src="<?= e($g['banner_url']) ?>" alt="" class="group-card-banner">
                <?php else: ?>
                    <div class="group-card-banner"></div>
                <?php endif; ?>
                <div class="group-card-body">
                    <span class="group-card-name"><?= e($g['name']) ?><?= (!empty($myGroupIds) && in_array((int)$g['id'], $myGroupIds, true)) ? ' ✓' : '' ?></span>
                    <span class="group-card-meta"><?= (int)$g['member_count'] ?> member<?= (int)$g['member_count'] === 1 ? '' : 's' ?> &middot; hosted by @<?= e($g['host_username']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="search-results-list">
            <?php foreach ($results as $i => $a): ?>
                <?php
                    $likeCount = getLikeCount($a['id']);
                    $dislikeCount = getDislikeCount($a['id']);
                    $commentCount = getCommentCount($a['id']);
                    $desc = $a['summary'] ?? '';
                    if (mb_strlen($desc) > 140) $desc = mb_substr($desc, 0, 140) . '...';
                ?>
                <a href="/article/<?= (int)$a['id'] ?>" class="search-result <?= $i === 0 ? 'search-result-first' : '' ?>">
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
                            <span><img src="/assets/icons/views.svg" class="icon-svg-sm" alt=""><?= formatCount((int)($a['views'] ?? 0)) ?></span>
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
document.querySelectorAll('time.local-date, time.local-datetime').forEach(function(el) {
    var d = new Date(el.getAttribute('datetime'));
    if (isNaN(d.getTime())) return;
    if (el.classList.contains('local-datetime')) {
        el.textContent = d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    } else {
        el.textContent = d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    }
});
</script>
<script>
document.addEventListener('click', function(e) {
    var menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-nav')) menu.classList.remove('open');
});
</script>
</body>
</html>