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
.group-wall-form textarea { width: 100%; margin-bottom: 0.5rem; }
.group-comment { border: 1px solid rgba(128,128,128,0.25); border-radius: 8px; padding: 0.7rem 0.9rem; margin-bottom: 0.6rem; }
.group-comment-head { display: flex; justify-content: space-between; font-size: 0.85rem; opacity: 0.85; margin-bottom: 0.3rem; }
.group-comment img.group-comment-image { max-width: 100%; border-radius: 6px; margin-top: 0.4rem; }
.group-member-row { display: flex; justify-content: space-between; align-items: center; padding: 0.5rem 0; border-bottom: 1px solid rgba(128,128,128,0.15); gap: 0.5rem; flex-wrap: wrap; }
.group-role-tag { font-size: 0.75rem; opacity: 0.75; text-transform: capitalize; }
.group-member-actions form { display: inline; }
.group-invite-form { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
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
        <form method="post" action="/group-action" onsubmit="return confirm('Toggle the comment policy for this group?');">
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
            <button class="btn" type="submit">Post</button>
        </form>
        <?php elseif (!$myId): ?>
            <p><a href="/login">Log in</a> to comment.</p>
        <?php else: ?>
            <p>Only members can comment in this group.</p>
        <?php endif; ?>

        <?php foreach ($comments as $c): ?>
            <div class="group-comment">
                <div class="group-comment-head">
                    <span>@<?= e($c['username']) ?></span>
                    <?php if ($isSiteMod || (int)$c['user_id'] === $myId || $myRole === 'host'): ?>
                    <form method="post" action="/group-action" onsubmit="return confirm('Delete this comment?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_comment">
                        <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                        <input type="hidden" name="group_slug" value="<?= e($group['slug']) ?>">
                        <button type="submit" style="background:none;border:none;opacity:0.6;cursor:pointer;">✕</button>
                    </form>
                    <?php endif; ?>
                </div>
                <div><?= nl2br(e($c['content'])) ?></div>
                <?php if (!empty($c['image_url'])): ?><img src="<?= e($c['image_url']) ?>" alt="" class="group-comment-image"><?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (empty($comments)): ?><p>No posts yet.</p><?php endif; ?>
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
