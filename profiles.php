<?php
require_once __DIR__ . '/functions.php';
startSession();
logVisit('/profiles');

$sort = ($_GET['sort'] ?? 'recent') === 'followers' ? 'followers' : 'recent';
$users = getPublicUserList($sort);
$myId = $_SESSION['reader_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Profiles - <?= e(SITE_NAME) ?></title>
<meta name="description" content="Browse ScratchNews community members.">
<link rel="stylesheet" href="/assets/style.css?v=21">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <h2>Profiles</h2>
    <div class="profiles-sort-tabs">
        <a href="/profiles?sort=recent" class="profiles-sort-tab <?= $sort === 'recent' ? 'active' : '' ?>">Recent</a>
        <a href="/profiles?sort=followers" class="profiles-sort-tab <?= $sort === 'followers' ? 'active' : '' ?>">Followers</a>
    </div>
    <?php if (empty($users)): ?>
        <p>No profiles yet.</p>
    <?php else: ?>
        <div class="profiles-grid">
            <?php foreach ($users as $u):
                $bioFirstLine = trim(explode("\n", (string)($u['bio'] ?? ''))[0]);
                $following = $myId ? isFollowing((int)$myId, (int)$u['id']) : false;
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
                <?php if ($myId && (int)$myId !== (int)$u['id']): ?>
                <form method="post" action="/follow" class="profile-card-follow-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="redirect" value="/profiles?sort=<?= e($sort) ?>">
                    <button type="submit" class="profile-card-follow-btn <?= $following ? 'following' : '' ?>"><?= $following ? 'Following' : 'Follow' ?></button>
                </form>
                <?php endif; ?>
                <div class="profile-card-hover">
                    <a href="/@<?= urlencode($u['username']) ?>" class="profile-card-visit">Visit Profile</a>
                    <?php if ($myId): ?>
                    <form method="post" action="/profile-comment" class="profile-card-comment-form">
                        <?= csrfField() ?>
                        <input type="hidden" name="profile_user_id" value="<?= (int)$u['id'] ?>">
                        <input type="text" name="content" maxlength="1000" placeholder="Comment...">
                        <button type="submit" title="Post comment">
                            <img src="/assets/icons/comment.svg" alt="" class="icon-svg-sm">
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
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
