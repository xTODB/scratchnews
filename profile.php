<?php
require_once __DIR__ . '/functions.php';
startSession();

$username = $_GET['username'] ?? '';
$user = $username !== '' ? getUserByUsername($username) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['reader_id']) && $user && $_SESSION['reader_id'] == $user['id']) {
    requireCsrf();
    $enabled = !empty($_POST['dark_mode']);
    setDarkModePreference($user['id'], $enabled);
    $_SESSION['dark_mode'] = $enabled;
    header('Location: /@' . urlencode($user['username']));
    exit;
}

if (!$user) {
    http_response_code(404);
}

$view = $_GET['view'] ?? 'comments';
$comments = $user ? getCommentsByUser($user['id']) : [];
$articleCount = $user ? getArticleCountByUser($user['id']) : 0;
$userArticles = ($user && $view === 'articles') ? getArticlesByUser($user['id']) : [];
$profileComments = ($user && $view === 'profile_comments') ? getProfileComments($user['id']) : [];
$profileCommentCount = $user ? getProfileCommentCount($user['id']) : 0;
$followerCount = $user ? getFollowerCount($user['id']) : 0;

$isOwnProfile = $user && !empty($_SESSION['reader_id']) && $_SESSION['reader_id'] == $user['id'];
$viewerFollowing = ($user && !$isOwnProfile && !empty($_SESSION['reader_id']))
    ? isFollowing((int)$_SESSION['reader_id'], $user['id'])
    : false;

$bioRaw = $user['bio'] ?? '';
$bioFirstLine = $bioRaw !== '' ? explode("\n", $bioRaw, 2)[0] : '';
$bioHasMore = $bioRaw !== '' && strpos($bioRaw, "\n") !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title><?= $user ? e($user['username']) : 'User Not Found' ?> - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=23">
<style>
/* Page-scoped stopgap — fold into style.css once shared */
.profile-banner { width:100%; height:200px; border-radius:10px; background:linear-gradient(135deg,#e8a33d,#d97b1f); margin:0.75rem 0; }
.profile-banner-img { width:100%; max-height:280px; height:auto; object-fit:contain; object-position:center; border-radius:10px; margin:0.75rem 0; background:linear-gradient(135deg,#e8a33d,#d97b1f); display:block; }
.profile-header-row { display:flex; align-items:flex-start; gap:1rem; }
.profile-avatar { width:88px; height:88px; border-radius:50%; object-fit:cover; background:#ccc; flex-shrink:0; }
.profile-avatar-fallback { width:88px; height:88px; border-radius:50%; background:#d97b1f; color:#fff; display:flex; align-items:center; justify-content:center; font-size:2rem; font-weight:bold; flex-shrink:0; }
.profile-follow-form { margin:0; padding:0; background:none; box-shadow:none; max-width:none; display:inline-block; }
.profile-follow-btn { padding:0.35rem 1.1rem; border-radius:20px; border:none; font-weight:bold; cursor:pointer; margin:0; }
.profile-follow-btn.not-following { background:#1da1f2; color:#fff; }
.profile-follow-btn.following { background:#e2e2e2; color:#333; }
.profile-bio { margin:0.5rem 0; }
.bio-readmore-btn { background:none; border:none; color:#1da1f2; font:inherit; cursor:pointer; padding:0; margin:0; text-decoration:underline; }
.bio-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.55); display:flex; align-items:center; justify-content:center; z-index:1000; }
.bio-modal-backdrop[hidden] { display:none; }
.bio-modal-card { background:#fff; color:#111; border-radius:10px; padding:1.5rem; max-width:600px; width:92%; max-height:80vh; overflow-y:auto; position:relative; box-shadow:0 10px 30px rgba(0,0,0,0.3); }
.bio-modal-close { position:absolute; top:0.6rem; right:0.9rem; background:none; border:none; font-size:1.4rem; line-height:1; cursor:pointer; color:#333; }
.profile-stat-link { background:none; border:none; padding:0; font:inherit; cursor:pointer; }
.profile-controls-row { display:flex; justify-content:space-between; align-items:center; margin:0.5rem 0; }
.profile-icon-controls { display:flex; gap:0.5rem; align-items:center; }
.profile-icon-btn { width:40px; height:40px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; border:1px solid #666; background:transparent; cursor:pointer; padding:0; color:inherit; }
.profile-icon-btn svg { width:20px; height:20px; fill:currentColor; }
.customize-panel label { display:block; margin-top:0.6rem; font-size:0.85rem; }
.customize-panel textarea { width:100%; box-sizing:border-box; }
.profile-comment-box { display:flex; gap:0.5rem; margin:0.75rem 0; }
.profile-comment-box textarea { flex:1; min-height:80px; resize:vertical; }
.stat-link.active { color: var(--brand-bright); font-weight: 700; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
<?php if (!$user): ?>
    <h2>User Not Found</h2>
    <p>No account exists under that username.</p>
<?php else: ?>
    <?php if (!empty($user['banner_url'])): ?>
        <img src="<?= e($user['banner_url']) ?>" alt="" class="profile-banner-img">
    <?php elseif ($isOwnProfile): ?>
        <div class="profile-banner"></div>
    <?php endif; ?>
    <div class="profile-header-row">
        <?php if (!empty($user['avatar_url'])): ?>
            <img src="<?= e($user['avatar_url']) ?>" alt="" class="profile-avatar">
        <?php else: ?>
            <div class="profile-avatar-fallback"><?= e(mb_strtoupper(mb_substr($user['username'], 0, 1))) ?></div>
        <?php endif; ?>
        <div>
            <h2 style="margin-bottom:0;">@<?= e($user['username']) ?><?= renderRankBadges($user) ?></h2>
            <p class="meta" style="margin:0.2rem 0;">
                Member since <?= date('M j, Y', strtotime($user['created_at'])) ?>
                &middot; <?= formatCount((int)$followerCount) ?> follower<?= $followerCount === 1 ? '' : 's' ?>
            </p>
            <?php if (!$isOwnProfile && !empty($_SESSION['reader_id'])): ?>
                <form method="post" action="/follow" class="profile-follow-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                    <button type="submit" class="profile-follow-btn <?= $viewerFollowing ? 'following' : 'not-following' ?>">
                        <?= $viewerFollowing ? 'Following' : 'Follow' ?>
                    </button>
                </form>
            <?php elseif (!$isOwnProfile): ?>
                <a href="/login" class="profile-follow-btn not-following" style="display:inline-block; text-decoration:none;">Follow</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($bioRaw !== ''): ?>
        <p class="profile-bio"><?= nl2br(e($bioFirstLine)) ?><?php if ($bioHasMore): ?> <button type="button" class="bio-readmore-btn" data-modal-target="bio-modal-<?= (int)$user['id'] ?>">Read more</button><?php endif; ?></p>
        <?php if ($bioHasMore): ?>
        <div class="bio-modal-backdrop" id="bio-modal-<?= (int)$user['id'] ?>" hidden>
            <div class="bio-modal-card">
                <button type="button" class="bio-modal-close" aria-label="Close">&times;</button>
                <h3 style="margin-top:0;">@<?= e($user['username']) ?></h3>
                <p style="margin-bottom:0;"><?= nl2br(e($bioRaw)) ?></p>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($isOwnProfile): ?>
    <div class="profile-controls-row">
        <a href="/delete-account" class="btn secondary" style="padding:0.3rem 0.7rem; font-size:0.8rem;">Delete my account</a>
        <div class="profile-icon-controls">
            <form method="post" class="profile-actions-form">
                <?= csrfField() ?>
                <input type="hidden" name="dark_mode" value="<?= !empty($_SESSION['dark_mode']) ? '0' : '1' ?>">
                <button type="submit" class="profile-icon-btn" title="<?= !empty($_SESSION['dark_mode']) ? 'Switch to light mode' : 'Switch to dark mode' ?>" aria-label="Toggle dark mode">
                    <?php if (!empty($_SESSION['dark_mode'])): ?>
                        <!-- currently dark -> show sun (click to go light) -->
                        <svg viewBox="0 0 24 24"><path d="M12 7a5 5 0 100 10 5 5 0 000-10zm0-5a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm0 17a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm9-7a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5 12a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zm12.66-6.66a1 1 0 010 1.42l-.71.7a1 1 0 11-1.41-1.41l.7-.71a1 1 0 011.42 0zM6.46 17.54a1 1 0 010 1.42l-.7.7a1 1 0 11-1.42-1.41l.71-.71a1 1 0 011.41 0zm11.2 0a1 1 0 011.41 0l.71.71a1 1 0 11-1.42 1.41l-.7-.7a1 1 0 010-1.42zM6.46 6.46a1 1 0 01-1.41 0l-.71-.7a1 1 0 111.42-1.42l.7.71a1 1 0 010 1.41z"/></svg>
                    <?php else: ?>
                        <!-- currently light -> show moon (click to go dark) -->
                        <svg viewBox="0 0 24 24"><path d="M20.7 14.9A8.5 8.5 0 019.1 3.3a1 1 0 00-1.2-1.3 10 10 0 1013.9 13.9 1 1 0 00-1.1-1z"/></svg>
                    <?php endif; ?>
                </button>
            </form>
            <button type="button" class="profile-icon-btn" title="Customize profile" aria-label="Customize profile" data-modal-target="customize-modal-<?= (int)$user['id'] ?>">
                <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 100 20c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.3-.5-.8-.5-1.2 0-1.1.9-2 2-2h2.3A5.2 5.2 0 0022 10.5C22 5.8 17.5 2 12 2zM6.5 12a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm3-4a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm5 0a1.5 1.5 0 110-3 1.5 1.5 0 010 3zm3 4a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/></svg>
            </button>
        </div>
    </div>
    <div class="bio-modal-backdrop" id="customize-modal-<?= (int)$user['id'] ?>" hidden>
        <div class="bio-modal-card">
            <button type="button" class="bio-modal-close" aria-label="Close">&times;</button>
            <h3 style="margin-top:0;">Customize Profile</h3>
            <div class="customize-panel">
                <form method="post" action="/update-profile" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <label>Profile picture
                        <input type="file" name="avatar" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
                    </label>
                    <label>Banner
                        <input type="file" name="banner" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml">
                    </label>
                    <label>Bio
                        <textarea name="bio" rows="3" maxlength="500"><?= e($user['bio'] ?? '') ?></textarea>
                    </label>
                    <button type="submit" class="btn" style="margin-top:0.6rem;">Save changes</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="profile-stats-row">
        <h3><a href="/@<?= urlencode($user['username']) ?>?view=articles" class="stat-link <?= $view === 'articles' ? 'active' : '' ?>"><?= (int)$articleCount ?> Articles</a></h3>
        <h3><a href="/@<?= urlencode($user['username']) ?>" class="stat-link <?= $view === 'comments' ? 'active' : '' ?>">Comments (<?= count($comments) ?>)</a></h3>
        <h3><a href="/@<?= urlencode($user['username']) ?>?view=profile_comments" class="stat-link <?= $view === 'profile_comments' ? 'active' : '' ?>">Profile Comments (<?= formatCount((int)$profileCommentCount) ?>)</a></h3>
    </div>
    <?php if ($view === 'articles'): ?>
        <?php if (empty($userArticles)): ?>
            <p>No articles published yet.</p>
        <?php else: ?>
            <div class="search-results-list">
                <?php foreach ($userArticles as $i => $a): ?>
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
                                <div class="search-result-title"><?= e($a['title']) ?></div>
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
    <?php elseif ($view === 'profile_comments'): ?>
        <?php if (!empty($_GET['modError'])): ?>
            <div class="alert error moderation-banner"><?= e($_GET['modError']) ?></div>
        <?php endif; ?>
        <?php if (!empty($_SESSION['reader_id'])): ?>
        <form method="post" action="/profile-comment" class="profile-comment-box">
            <?= csrfField() ?>
            <input type="hidden" name="profile_user_id" value="<?= (int)$user['id'] ?>">
            <textarea name="content" rows="2" maxlength="1000" placeholder="Write something..." required></textarea>
            <button type="submit" class="btn btn-comment"><img src="/assets/icons/comment.svg" alt="" class="icon-svg-sm btn-icon"> Comment</button>
        </form>
        <?php endif; ?>
        <?php if (empty($profileComments)): ?>
            <p>No profile comments yet.</p>
        <?php else: ?>
            <?php $profileCommentTree = buildCommentTree($profileComments); ?>
            <?php foreach ($profileCommentTree as $pc): ?>
                <?= renderProfileCommentThread($pc, !empty($_SESSION['reader_id']), $user['id']) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php else: ?>
        <?php foreach ($comments as $c): ?>
            <div class="comment">
                <a href="/article/<?= (int)$c['article_id'] ?>"><strong><?= e($c['article_title']) ?></strong></a>
                <span class="meta"><?= date('M j, Y g:i A', strtotime($c['created_at'])) ?></span>
                <p><?= e($c['content']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
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
document.querySelectorAll('[data-modal-target]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var modal = document.getElementById(btn.getAttribute('data-modal-target'));
        if (modal) modal.hidden = false;
    });
});
document.querySelectorAll('.bio-modal-backdrop').forEach(function(backdrop) {
    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) backdrop.hidden = true;
    });
    var closeBtn = backdrop.querySelector('.bio-modal-close');
    if (closeBtn) closeBtn.addEventListener('click', function() { backdrop.hidden = true; });
});
</script>
</body>
</html>