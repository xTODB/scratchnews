<?php
require_once __DIR__ . '/functions.php';
sendNoCacheHeaders();
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unsave') {
    requireCsrf();
    $articleId = (int)($_POST['article_id'] ?? 0);
    if ($articleId > 0) unsaveArticleForUser($articleId, (int)$_SESSION['reader_id']);
    header('Location: /my-articles?view=saved');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unpublish') {
    requireCsrf();
    $articleId = (int)($_POST['article_id'] ?? 0);
    if ($articleId > 0) unpublishArticle($articleId, (int)$_SESSION['reader_id']);
    header('Location: /my-articles?view=mine');
    exit;
}

$view = $_GET['view'] ?? 'saved';
if (!in_array($view, ['saved', 'mine', 'drafts'], true)) $view = 'saved';

$drafts = [];
$articles = [];
if ($view === 'drafts') {
    $drafts = getDraftsByUser($_SESSION['reader_id']);
} elseif ($view === 'saved') {
    $articles = getSavedArticlesByUser($_SESSION['reader_id']);
} else {
    $articles = getArticlesByUser($_SESSION['reader_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>My Articles - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.my-articles-tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.my-articles-tab { padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; color: inherit; border: 1px solid rgba(128,128,128,0.3); font-weight: 600; }
.my-articles-tab.active { background: #e8a33d; color: #2a2a2a; border-color: #e8a33d; }
.unsave-form { margin-top: 0.4rem; }
.unsave-btn { background: transparent; border: 1px solid currentColor; color: inherit; opacity: 0.75; padding: 0.25rem 0.8rem; border-radius: 4px; font-size: 0.85rem; cursor: pointer; }
.unsave-btn:hover { opacity: 1; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>My Articles</h2>
    <div class="my-articles-tabs">
        <a href="/my-articles?view=saved" class="my-articles-tab <?= $view === 'saved' ? 'active' : '' ?>">Saved Articles</a>
        <a href="/my-articles?view=mine" class="my-articles-tab <?= $view === 'mine' ? 'active' : '' ?>">My Articles</a>
        <a href="/my-articles?view=drafts" class="my-articles-tab <?= $view === 'drafts' ? 'active' : '' ?>">Drafts</a>
    </div>
    <?php if ($view === 'drafts'): ?>
        <?php if (empty($drafts)): ?>
            <p>No drafts yet. <a href="/submit">Start a new article</a> and save it as a draft any time.</p>
        <?php else: ?>
            <div class="search-results-list">
                <?php foreach ($drafts as $i => $d): ?>
                    <?php
                        $desc = $d['summary'] ?? '';
                        if (mb_strlen($desc) > 140) $desc = mb_substr($desc, 0, 140) . '...';
                    ?>
                    <div class="search-result <?= $i === 0 ? 'search-result-first' : '' ?>" style="flex-direction:column;">
                        <a href="/submit?draft_id=<?= (int)$d['id'] ?>" style="display:flex; gap:1rem; text-decoration:none; color:inherit;">
                            <?php if (!empty($d['image_url'])): ?>
                                <img src="<?= e($d['image_url']) ?>" alt="" class="search-result-thumb">
                            <?php else: ?>
                                <div class="search-result-thumb search-result-thumb-placeholder"></div>
                            <?php endif; ?>
                            <div class="search-result-body">
                                <div>
                                    <div class="search-result-title"><?= e($d['title']) ?: '(untitled draft)' ?></div>
                                    <div class="meta">Last saved <?= utcTimeTag($d['created_at']) ?></div>
                                    <?php if ($desc !== ''): ?><div class="search-result-desc"><?= e($desc) ?></div><?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif (empty($articles)): ?>
        <p><?= $view === 'saved' ? 'No saved articles yet.' : 'No articles published yet.' ?></p>
    <?php else: ?>
        <div class="search-results-list">
            <?php foreach ($articles as $i => $a): ?>
                <?php
                    $likeCount = getLikeCount($a['id']);
                    $dislikeCount = getDislikeCount($a['id']);
                    $commentCount = getCommentCount($a['id']);
                    $desc = $a['summary'] ?? '';
                    if (mb_strlen($desc) > 140) $desc = mb_substr($desc, 0, 140) . '...';
                ?>
                <div class="search-result <?= $i === 0 ? 'search-result-first' : '' ?>" style="flex-direction:column;">
                    <a href="/article/<?= (int)$a['id'] ?>" style="display:flex; gap:1rem; text-decoration:none; color:inherit;">
                        <?php if (!empty($a['image_url'])): ?>
                            <img src="<?= e($a['image_url']) ?>" alt="" class="search-result-thumb">
                        <?php else: ?>
                            <div class="search-result-thumb search-result-thumb-placeholder"></div>
                        <?php endif; ?>
                        <div class="search-result-body">
                            <div>
                                <div class="search-result-title"><?= e($a['title']) ?></div>
                                <div class="meta">By <?= e($a['author']) ?> &middot; <?= utcTimeTag($a['created_at']) ?></div>
                                <?php if ($desc !== ''): ?><div class="search-result-desc"><?= e($desc) ?></div><?php endif; ?>
                            </div>
                            <div class="search-result-stats">
                                <span><img src="/assets/icons/unlike.svg" class="icon-svg-sm" alt=""><?= $likeCount ?></span>
                                <span><img src="/assets/icons/undislike.svg" class="icon-svg-sm" alt=""><?= $dislikeCount ?></span>
                                <span><img src="/assets/icons/comment.svg" class="icon-svg-sm" alt=""><?= $commentCount ?></span>
                            </div>
                        </div>
                    </a>
                    <?php if ($view === 'saved'): ?>
                        <form method="post" class="unsave-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="unsave">
                            <input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
                            <button type="submit" class="unsave-btn">Remove from Saved</button>
                        </form>
                    <?php elseif ($view === 'mine'): ?>
                        <div class="unsave-form" style="display:flex; align-items:center; gap:0.6rem;">
                            <?php if (($a['status'] ?? 'published') === 'unpublished'): ?>
                                <span style="font-size:0.85rem; opacity:0.75; font-style:italic;">Unpublished — only visible to you</span>
                            <?php endif; ?>
                            <a href="/submit?edit_article=<?= (int)$a['id'] ?>" class="unsave-btn" style="text-decoration:none; display:inline-block;">Edit / Resubmit</a>
                            <?php if (($a['status'] ?? 'published') === 'published'): ?>
                            <form method="post" style="margin:0;">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="unpublish">
                                <input type="hidden" name="article_id" value="<?= (int)$a['id'] ?>">
                                <button type="submit" class="unsave-btn" style="border-color:#d9392a; color:#d9392a;" onclick="return confirm('Unpublish this article? It will be hidden from the public. You can still see it here, and an admin can republish it later.');">Unpublish</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
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