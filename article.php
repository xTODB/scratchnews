<?php
require_once __DIR__ . '/functions.php';
sendNoCacheHeaders();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
logVisit('/article/' . $id);

$article = $id > 0 ? getArticleById($id) : null;
startSession();

if ($article && ($article['status'] ?? 'published') === 'draft' && empty($_SESSION['is_admin'])) {
    $article = null;
}

if ($article) {
    if (empty($_SESSION['viewed_articles'][$article['id']])) {
        incrementArticleView($article['id']);
        $_SESSION['viewed_articles'][$article['id']] = true;
    }
    if (isset($_GET['sid'])) {
        recordShareClick($article['id'], (string)$_GET['sid']);
    }
}

$isBanned = !empty($_SESSION['reader_id']) && (isUserBanned($_SESSION['reader_id']) || isPhoneVerificationPending($_SESSION['reader_id']));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $article && !empty($_SESSION['reader_id'])) {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'like' && !$isBanned) {
        toggleLike($article['id'], $_SESSION['reader_id']);
        header('Location: /article/' . $article['id']);
        exit;
    }
    if (($_POST['action'] ?? '') === 'dislike' && !$isBanned) {
        toggleDislike($article['id'], $_SESSION['reader_id']);
        header('Location: /article/' . $article['id']);
        exit;
    }
    if (($_POST['action'] ?? '') === 'toggle_save') {
        if (isArticleSaved($article['id'], $_SESSION['reader_id'])) {
            unsaveArticleForUser($article['id'], $_SESSION['reader_id']);
        } else {
            saveArticleForUser($article['id'], $_SESSION['reader_id']);
        }
        header('Location: /article/' . $article['id']);
        exit;
    }
    if (($_POST['action'] ?? '') === 'comment' && !$isBanned) {
        $content = trim($_POST['content'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if ($content !== '') addComment($article['id'], $_SESSION['reader_id'], $content, $parentId);
        header('Location: /article/' . $article['id']);
        exit;
    }
    if (($_POST['action'] ?? '') === 'report') {
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if ($commentId > 0) reportComment($commentId, $_SESSION['reader_id']);
        header('Location: /article/' . $article['id']);
        exit;
    }
    if (($_POST['action'] ?? '') === 'admin_delete' && (!empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator']))) {
        // Pre-existing bug fixed here: this button rendered in renderCommentThread() but had
        // no server-side handler on article.php, so admin comment deletion silently did nothing.
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if ($commentId > 0) adminDeleteComment($commentId);
        header('Location: /article/' . $article['id']);
        exit;
    }
}

$comments = $article ? getCommentsForArticle($article['id']) : [];
$commentTree = $article ? buildCommentTree($comments) : [];
$likeCount = $article ? getLikeCount($article['id']) : 0;
$liked = ($article && !empty($_SESSION['reader_id'])) ? hasUserLiked($article['id'], $_SESSION['reader_id']) : false;
$dislikeCount = $article ? getDislikeCount($article['id']) : 0;
$disliked = ($article && !empty($_SESSION['reader_id'])) ? hasUserDisliked($article['id'], $_SESSION['reader_id']) : false;
$isSaved = ($article && !empty($_SESSION['reader_id'])) ? isArticleSaved($article['id'], $_SESSION['reader_id']) : false;

if (!$article) {
    http_response_code(404);
}

[$displayTitle, $displayContent] = $article ? translatedArticleFields($article) : ['', ''];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title><?= $article ? e($displayTitle) . ' - ' . e(SITE_NAME) : 'Article not found' ?></title>
<?php if ($article): ?>
<meta name="description" content="<?= e(mb_strimwidth($article['summary'], 0, 160, '...')) ?>">
<script type="application/ld+json">
<?= json_encode([
    "@context" => "https://schema.org",
    "@type" => "NewsArticle",
    "headline" => $displayTitle,
    "datePublished" => date('c', strtotime($article['created_at'])),
    "dateModified" => date('c', strtotime($article['updated_at'])),
    "author" => ["@type" => "Person", "name" => $article['author']],
]) ?>
</script>
<?php endif; ?>
<link rel="stylesheet" href="/assets/style.css?v=21">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<?php if (!empty($_SESSION['impersonator_admin_username'])): ?>
<div class="impersonation-banner">
    Viewing as <strong><?= e($_SESSION['reader_username']) ?></strong> (impersonating)
    <form method="post" action="/stop-impersonating.php" class="impersonation-form">
        <?= csrfField() ?>
        <button type="submit" class="text-action">Return to Admin</button>
    </form>
</div>
<?php endif; ?>
<main>
    <a class="back-link" href="/">&larr; Back to all articles</a>
    <?php if ($article): ?>
        <div class="full-article">
            <h1><?= e($displayTitle) ?></h1>
            <?php if (!empty($article['image_url'])): ?>
                <img src="<?= e($article['image_url']) ?>" alt="" class="article-cover-image" style="max-width:100%;height:auto;">
            <?php endif; ?>
            <div class="meta">
                By <?= renderArticleByline($article) ?> ·
                Published <?= utcTimeTag($article['created_at']) ?>
                <?php if ($article['updated_at'] !== $article['created_at']): ?>
                    · Updated <?= utcTimeTag($article['updated_at']) ?>
                <?php endif; ?>
            </div>
            <?php $articleCats = getArticleCategories($article['id']); if (!empty($articleCats)): ?>
            <div class="article-categories">
                <?php foreach ($articleCats as $cat): ?><span class="category-badge"><?= e($cat['name']) ?></span><?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div class="engage-bar">
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="like">
                    <button class="icon-btn <?= $liked ? 'active' : '' ?>" type="submit" <?= (empty($_SESSION['reader_id']) || $isBanned) ? 'disabled' : '' ?> title="<?= $liked ? 'Unlike' : 'Like' ?>">
                        <img src="/assets/icons/<?= $liked ? 'like' : 'unlike' ?>.svg" alt="Like" class="icon-svg">
                        <span class="icon-count"><?= $likeCount ?></span>
                    </button>
                </form>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="dislike">
                    <button class="icon-btn <?= $disliked ? 'active' : '' ?>" type="submit" <?= (empty($_SESSION['reader_id']) || $isBanned) ? 'disabled' : '' ?> title="<?= $disliked ? 'Remove dislike' : 'Dislike' ?>">
                        <img src="/assets/icons/<?= $disliked ? 'dislike' : 'undislike' ?>.svg" alt="Dislike" class="icon-svg">
                        <span class="icon-count"><?= $dislikeCount ?></span>
                    </button>
    </form>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_save">
                    <button class="icon-btn save-btn <?= $isSaved ? 'active' : '' ?>" type="submit" <?= empty($_SESSION['reader_id']) ? 'disabled' : '' ?> title="<?= $isSaved ? 'Remove from Saved' : 'Save for later' ?>">
                        <img src="/assets/icons/save.svg" alt="Save" class="icon-svg">
                    </button>
                </form>
                <a href="#comments" class="icon-btn" title="Jump to comments" style="text-decoration:none;">
                    <img src="/assets/icons/comment.svg" alt="Comments" class="icon-svg">
                    <span class="icon-count"><?= count($comments) ?></span>
                </a>
                <span class="icon-btn" title="Views">
                    <img src="/assets/icons/views.svg" alt="Views" class="icon-svg">
                    <span class="icon-count"><?= (int)$article['views'] ?></span>
                </span>
                <div class="share-wrap">
                    <button type="button" class="icon-btn" id="shareBtn" title="Share">
                        <img src="/assets/icons/share.svg" alt="Share" class="icon-svg">
                    </button>
                    <?php if (!empty($_SESSION['reader_id'])):
                        $__shareClicks = getUserShareClickCount((int)$_SESSION['reader_id']);
                        if ($__shareClicks > 0): ?>
                    <span class="share-click-badge" title="Clicks generated by links you've shared"><?= $__shareClicks > 99 ? '99+' : $__shareClicks ?></span>
                    <?php endif; endif; ?>
                    <div class="share-menu" id="shareMenu">
                        <button type="button" class="share-option" data-share="copy">Copy Link</button>
                        <button type="button" class="share-option" data-share="text">Copy Article Text</button>
                        <button type="button" class="share-option" data-share="discord">Share to Scratch/Discord</button>
                        <button type="button" class="share-option" data-share="more" id="shareMoreBtn" style="display:none;">More...</button>
                    </div>
                </div>
            </div>
            <?php if ($isBanned): ?>
                <p class="meta">Your account is restricted from liking and commenting.</p>
            <?php endif; ?>
            <button type="button" class="share-banner">
                <img src="/assets/banners/sn-banner-4.svg" alt="Share this article" class="share-banner-img">
                <span class="share-banner-label">Copy link to share</span>
            </button>
            <div class="content"><?= $displayContent ?></div>
            <button type="button" class="share-banner">
                <img src="/assets/banners/sn-banner-4.svg" alt="Share this article" class="share-banner-img">
                <span class="share-banner-label">Enjoyed it? Share the link</span>
            </button>
            <div class="comments-section" id="comments">
    <h3>Comments (<?= count($comments) ?>)</h3>
    <?php if (!empty($_SESSION['reader_id']) && !$isBanned): ?>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="comment">
            <textarea name="content" placeholder="Add a comment..." required></textarea>
            <button class="btn btn-comment" type="submit">
                <img src="/assets/icons/comment.svg" alt="" class="icon-svg-sm btn-icon">
                Comment
            </button>
        </form>
    <?php elseif (!$isBanned): ?>
        <p><a href="/signin">Log in</a> or <a href="/register">sign up</a> to comment.</p>
    <?php endif; ?>
    <?php foreach ($commentTree as $c): ?>
        <?= renderCommentThread($c, !empty($_SESSION['reader_id']) && !$isBanned, 0, !empty($_SESSION['reader_id'])) ?>
    <?php endforeach; ?>
</div>
        </div>
    <?php else: ?>
        <div class="alert error">Article #<?= (int)$id ?> was not found. It may have been removed.</div>
    <?php endif; ?>
</main>
<div class="lightbox-overlay" id="lightboxOverlay">
    <button type="button" class="lightbox-close" id="lightboxClose">&times;</button>
    <img src="" alt="" id="lightboxImg">
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('click', function(e) {
    var menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-nav')) menu.classList.remove('open');
});
</script>
<script>
(function() {
    var overlay = document.getElementById('lightboxOverlay');
    var lbImg = document.getElementById('lightboxImg');
    var closeBtn = document.getElementById('lightboxClose');

    document.addEventListener('click', function(e) {
        var img = e.target.closest('.content img, .article-cover-image');
        if (!img) return;
        lbImg.src = img.src;
        lbImg.classList.remove('zoomed');
        lbImg.style.transformOrigin = 'center center';
        overlay.classList.add('open');
        document.body.classList.add('no-scroll');
    });

    lbImg.addEventListener('click', function(e) {
        e.stopPropagation();
        if (!lbImg.classList.contains('zoomed')) {
            var rect = lbImg.getBoundingClientRect();
            var xPct = ((e.clientX - rect.left) / rect.width) * 100;
            var yPct = ((e.clientY - rect.top) / rect.height) * 100;
            lbImg.style.transformOrigin = xPct + '% ' + yPct + '%';
            lbImg.classList.add('zoomed');
        } else {
            lbImg.classList.remove('zoomed');
        }
    });

    function closeLightbox() {
        overlay.classList.remove('open');
        lbImg.src = '';
        lbImg.classList.remove('zoomed');
        document.body.classList.remove('no-scroll');
    }
    closeBtn.addEventListener('click', closeLightbox);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeLightbox();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeLightbox();
    });
})();
</script>
<script>
(function() {
    var shareBtn = document.getElementById('shareBtn');
    var shareMenu = document.getElementById('shareMenu');
    if (!shareBtn) return;
    shareBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        shareMenu.classList.toggle('open');
    });
    document.addEventListener('click', function() {
        shareMenu.classList.remove('open');
    });
    shareMenu.addEventListener('click', function(e) { e.stopPropagation(); });
    var pageUrl = window.location.origin + '/article/<?= (int)($article['id'] ?? 0) ?>?sid=<?= e(currentShareSuffix()) ?>';
    var pageTitle = <?= json_encode($displayTitle ?? '') ?>;
    var shareMoreBtn = document.getElementById('shareMoreBtn');
    if (navigator.share) shareMoreBtn.style.display = 'block';
    shareMenu.querySelectorAll('.share-option').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = btn.getAttribute('data-share');
            if (type === 'more') {
                shareMenu.classList.remove('open');
                navigator.share({ title: pageTitle, url: pageUrl }).catch(function() {});
                return;
            }
            var text;
            if (type === 'discord') {
                text = '("' + pageTitle + '") on ScratchNews: ' + pageUrl;
            } else if (type === 'text') {
                var contentEl = document.querySelector('.content');
                text = pageTitle + '\n\n' + (contentEl ? contentEl.innerText.trim() : '') + '\n\n' + pageUrl;
            } else {
                text = pageUrl;
            }
            navigator.clipboard.writeText(text).then(function() {
                var original = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() {
                    btn.textContent = original;
                    shareMenu.classList.remove('open');
                }, 900);
            });
        });
    });

    document.querySelectorAll('.share-banner').forEach(function(banner) {
        banner.addEventListener('click', function() {
            navigator.clipboard.writeText(pageUrl).then(function() {
                var label = banner.querySelector('.share-banner-label');
                if (!label) return;
                var original = label.textContent;
                label.textContent = 'Copied!';
                setTimeout(function() { label.textContent = original; }, 900);
            });
        });
    });
})();
</script>
</body>
</html>