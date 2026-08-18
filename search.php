<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/functions.php';
startSession();

$query = trim($_GET['q'] ?? '');
$results = $query !== '' ? searchArticles($query) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Search - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=21">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main<?= $query === '' ? ' search-empty' : '' ?>">
    <form method="get" action="/search" class="page-search-form">
        <input type="text" name="q" value="<?= e($query) ?>" placeholder="Search articles..." autofocus>
        <button type="submit" aria-label="Search">
            <img src="/assets/icons/nav-search.svg" alt="" class="icon-svg-sm">
        </button>
    </form>
    <?php if ($query === ''): ?>
    <?php elseif (empty($results)): ?>
        <h2>Search results for "<?= e($query) ?>"</h2>
        <p>No articles matched your search.</p>
    <?php else: ?>
        <h2>Search results for "<?= e($query) ?>"</h2>
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