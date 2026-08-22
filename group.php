<?php
require_once __DIR__ . '/functions.php';
startSession();

if (!isGroupsBetaAllowed()) {
    header('Location: /groups');
    exit;
}

$slug = $_GET['slug'] ?? '';
$group = getGroupBySlug($slug);
if (!$group) {
    http_response_code(404);
    echo 'Group not found.';
    exit;
}
logVisit('/group/' . $slug);

$myId = (int)($_SESSION['reader_id'] ?? 0);
$myRole = $myId ? getGroupMemberRole((int)$group['id'], $myId) : null;
$isSiteMod = !empty($_SESSION['is_admin']) || !empty($_SESSION['is_moderator']);
$error = $_GET['error'] ?? '';
$notice = $_GET['notice'] ?? '';

$members = getGroupMembers((int)$group['id']);
$memberCount = count($members);
$comments = getGroupComments((int)$group['id']);
$canComment = canCommentOnGroup($group, $myId ?: null);
$canPostImage = canPostImageInGroup($myRole);
$groupArticles = getGroupArticles((int)$group['id']);
$canAttachArticle = canAttachArticleToGroup($myRole);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title><?= e($group['name']) ?> - Groups - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=24">
<style>
.group-header-banner { width: 100%; max-height: 220px; object-fit: cover; border-radius: 10px; }
.group-header-top { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.6rem; margin: 0.8rem 0; }
.group-header-meta { opacity: 0.8; font-size: 0.9rem; }
.group-tabs { display: flex; gap: 0.5rem; border-bottom: 1px solid rgba(128,128,128,0.3); margin-bottom: 1rem; }
.group-tab { padding: 0.5rem 0.9rem; cursor: pointer; border-bottom: 2px solid transparent; }
.group-tab.active { border-bottom-color: #cc8829; font-weight: 700; }
.group-tab-panel { display: none; }
.group-tab-panel.active { display: block; }
.group-wall-form { max-width: 420px; margin: 0 auto 1.5rem; }
.group-wall-form textarea { width: 100%; min-height: 44px; margin-bottom: 0.5rem; }
.group-comment-image { max-width: 100%; border-radius: 6px; margin-top: 0.4rem; }
.group-member-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(128,128,128,0.15); gap: 0.5rem; flex-wrap: wrap; }
.group-role-tag { font-size: 0.75rem; opacity: 0.75; text-transform: capitalize; }
.group-member-actions form { display: inline; }
.group-invite-form { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
.group-toggle-form { margin: 0; background: none; padding: 0; border-radius: 0; box-shadow: none; max-width: none; }
.group-article-row { margin-bottom: 1rem; }
.group-article-remove-form { margin: 0.3rem 0 0; background: none; padding: 0; border-radius: 0; box-shadow: none; max-width: none; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <?php if (!empty($group['banner_url'])): ?>
        <img src="<?= e($group['banner_url']) ?>" alt="" class="group-header-banner">
    <?php endif; ?>
    <div class="group-header-top">
        <div>
            <h2 style="margin-bottom:0.2rem;"><?= e($group['name']) ?></h2>
            <div class="group-header-meta"><?= (int)$memberCount ?> member<?= $memberCount === 1 ? '' : 's' ?> · hosted by @<?= e($group['host_username']) ?></div>
        </div>
        <?php if ($myRole && ($myRole === 'host' || $isSiteMod)): ?>
        <form method="post" action="/group-action" class="group-toggle-form" onsubmit="return confirm('Toggle the comment policy for this group?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="set_comment_policy">
            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
            <input type="hidden" name="policy" value="<?= $group['comment_policy'] === 'everyone' ? 'members' : 'everyone' ?>">
            <button class="btn secondary" type="submit">Comments: <?= $group['comment_policy'] === 'everyone' ? 'Everyone' : 'Members only' ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php if ($group['description']): ?><p><?= nl2br(e($group['description'])) ?></p><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert"><?= e($notice) ?></div><?php endif; ?>

    <?php if ($myId && !$myRole): ?>
        <p>You're not a member of this group yet - you'll need an invite from a member, or an invite link, to join.</p>
    <?php endif; ?>

    <div class="group-tabs">
        <span class="group-tab active" onclick="showGroupTab('wall', this)">Wall</span>
        <span class="group-tab" onclick="showGroupTab('articles', this)">Articles</span>
        <span class="group-tab" onclick="showGroupTab('members', this)">Members</span>
        <?php if ($myRole): ?><span class="group-tab" onclick="showGroupTab('invite', this)">Invite</span><?php endif; ?>
    </div>

    <div id="group-tab-wall" class="group-tab-panel active">
        <?php if ($myId && $canComment): ?>
        <form method="post" action="/group-action" enctype="multipart/form-data" class="group-wall-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="post_comment">
            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
            <textarea name="content" rows="2" maxlength="1000" placeholder="Write something..." required></textarea>
            <?php if ($canPostImage): ?>
                <input type="file" name="image" accept="image/*">
            <?php endif; ?>
            <button class="btn btn-comment" type="submit">
                <img src="/assets/icons/comment.svg" alt="" class="icon-svg-sm btn-icon">
                Comment
            </button>
        </form>
        <?php elseif (!$myId): ?>
            <p><a href="/login">Log in</a> to comment.</p>
        <?php else: ?>
            <p>Only members can comment in this group.</p>
        <?php endif; ?>

        <?php foreach ($comments as $c): ?>
            <div class="comment comment-top">
                <div class="comment-header">
                    <?= renderCommentAvatar($c['avatar_url'] ?? null, $c['username']) ?>
                    <strong><a href="/@<?= e($c['username']) ?>"><?= e($c['username']) ?></a></strong>
                    <?= renderRankBadges((int)$c['user_id']) ?>
                    <span class="meta"><?= utcTimeTag($c['created_at'], 'datetime') ?></span>
                </div>
                <p><?= nl2br(e($c['content'])) ?></p>
                <?php if (!empty($c['image_url'])): ?><img src="<?= e($c['image_url']) ?>" alt="" class="group-comment-image"><?php endif; ?>
                <?php if ($isSiteMod || (int)$c['user_id'] === $myId || $myRole === 'host'): ?>
                <form method="post" action="/group-action" class="report-form" onsubmit="return confirm('Delete this comment?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_comment">
                    <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                    <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                    <button type="submit" class="reply-toggle" title="Delete"><img src="/assets/icons/comment_delete.svg" class="icon-svg-sm" alt=""> Delete</button>
                </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (empty($comments)): ?><p>No comments yet.</p><?php endif; ?>
    </div>

    <div id="group-tab-articles" class="group-tab-panel">
        <?php if ($canAttachArticle): ?>
        <form method="post" action="/group-action" class="group-invite-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="attach_article">
            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
            <input type="text" name="article_ref" placeholder="Article link or ID" required>
            <button class="btn inline" type="submit">Attach</button>
        </form>
        <?php endif; ?>
        <div class="search-results-list">
            <?php foreach ($groupArticles as $ga): ?>
                <?php
                    $likeCount = getLikeCount($ga['id']);
                    $dislikeCount = getDislikeCount($ga['id']);
                    $commentCount = getCommentCount($ga['id']);
                    $desc = $ga['summary'] ?? '';
                    if (mb_strlen($desc) > 140) $desc = mb_substr($desc, 0, 140) . '...';
                    $canRemoveArticle = $isSiteMod || $myRole === 'host' || $myRole === 'manager' || (int)$ga['added_by'] === $myId;
                ?>
                <div class="group-article-row">
                    <a href="/article/<?= (int)$ga['id'] ?>" class="search-result">
                        <?php if (!empty($ga['image_url'])): ?>
                            <img src="<?= e($ga['image_url']) ?>" alt="" class="search-result-thumb">
                        <?php else: ?>
                            <div class="search-result-thumb search-result-thumb-placeholder"></div>
                        <?php endif; ?>
                        <div class="search-result-body">
                            <div>
                                <div class="search-result-title"><?= e(translatedTitle($ga)) ?></div>
                                <div class="meta">By <?= e($ga['author']) ?> &middot; <?= utcTimeTag($ga['created_at']) ?></div>
                                <?php if ($desc !== ''): ?><div class="search-result-desc"><?= e($desc) ?></div><?php endif; ?>
                            </div>
                            <div class="search-result-stats">
                                <span><img src="/assets/icons/unlike.svg" class="icon-svg-sm" alt=""><?= formatCount($likeCount) ?></span>
                                <span><img src="/assets/icons/undislike.svg" class="icon-svg-sm" alt=""><?= formatCount($dislikeCount) ?></span>
                                <span><img src="/assets/icons/comment.svg" class="icon-svg-sm" alt=""><?= formatCount($commentCount) ?></span>
                                <span><img src="/assets/icons/views.svg" class="icon-svg-sm" alt=""><?= formatCount((int)($ga['views'] ?? 0)) ?></span>
                            </div>
                        </div>
                    </a>
                    <?php if ($canRemoveArticle): ?>
                    <form method="post" action="/group-action" class="group-article-remove-form" onsubmit="return confirm('Remove this article from the group?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="detach_article">
                        <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                        <input type="hidden" name="article_id" value="<?= (int)$ga['id'] ?>">
                        <button type="submit" class="btn secondary inline">Remove from group</button>
                    </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            <?php if (empty($groupArticles)): ?><p>No articles attached yet.</p><?php endif; ?>
        </div>
    </div>

    <div id="group-tab-members" class="group-tab-panel">
        <?php foreach ($members as $m): ?>
            <div class="group-member-row">
                <span>@<?= e($m['username']) ?> <span class="group-role-tag">(<?= e($m['role']) ?>)</span><?php if (!empty($m['timeout_until']) && strtotime($m['timeout_until']) > time()): ?> <span class="group-role-tag">timed out</span><?php endif; ?></span>
                <?php if (($isSiteMod || $myRole === 'host' || ($myRole === 'manager' && $m['role'] === 'member')) && $m['role'] !== 'host'): ?>
                <span class="group-member-actions">
                    <form method="post" action="/group-action">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="timeout_member">
                        <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                        <input type="hidden" name="group_slug" value="<?= e($group['slug']) ?>">
                        <select name="minutes" onchange="this.form.submit()">
                            <option value="">Timeout...</option>
                            <option value="10">10 min</option>
                            <option value="60">1 hour</option>
                            <option value="1440">1 day</option>
                            <option value="10080">7 days</option>
                        </select>
                    </form>
                    <form method="post" action="/group-action" onsubmit="return confirm('Remove this member?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="kick_member">
                        <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                        <input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>">
                        <input type="hidden" name="group_slug" value="<?= e($group['slug']) ?>">
                        <button class="btn secondary inline" type="submit">Remove</button>
                    </form>
                </span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($myRole): ?>
    <div id="group-tab-invite" class="group-tab-panel">
        <p>Invite a ScratchNews user you follow, or who follows you<?= $isSiteMod ? ' (moderators can invite anyone)' : '' ?>.</p>
        <form method="post" action="/group-action" class="group-invite-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="invite_user">
            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
            <input type="text" name="username" placeholder="Username" required>
            <button class="btn inline" type="submit">Invite</button>
        </form>
        <?php if ($myRole === 'host' || $myRole === 'manager' || $isSiteMod): ?>
        <form method="post" action="/group-action">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="generate_invite_link">
            <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
            <button class="btn secondary" type="submit">Generate Invite Link</button>
        </form>
        <?php if (!empty($_SESSION['group_invite_link_' . $group['id']])): ?>
            <p>Share this link: <code><?= e($_SESSION['group_invite_link_' . $group['id']]) ?></code></p>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
function showGroupTab(name, el) {
    document.querySelectorAll('.group-tab-panel').forEach(function(p) { p.classList.remove('active'); });
    document.querySelectorAll('.group-tab').forEach(function(t) { t.classList.remove('active'); });
    document.getElementById('group-tab-' + name).classList.add('active');
    el.classList.add('active');
}
</script>
</body>
</html>