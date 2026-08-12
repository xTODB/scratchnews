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
<main class="home-main">
    <h2>Search results<?= $query !== '' ? ' for "' . e($query) . '"' : '' ?></h2>
    <?php if ($query === ''): ?>
        <p>Type something in the search box above.</p>
    <?php elseif (empty($results)): ?>
        <p>No articles matched your search.</p>
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
                            <span><img src="/assets/icons/unlike.svg" class="icon-svg-sm" alt=""><?= $likeCount ?></span>
                            <span><img src="/assets/icons/undislike.svg" class="icon-svg-sm" alt=""><?= $dislikeCount ?></span>
                            <span><img src="/assets/icons/comment.svg" class="icon-svg-sm" alt=""><?= $commentCount ?></span>
                            <span><img src="/assets/icons/views.svg" class="icon-svg-sm" alt=""><?= (int)($a['views'] ?? 0) ?></span>
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