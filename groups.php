<?php
require_once __DIR__ . '/functions.php';
startSession();
logVisit('/groups');

if (!isGroupsBetaAllowed()):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Groups - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=24">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <h2>Groups</h2>
    <div class="alert" style="background: rgba(255,170,51,0.15); border: 1px solid #ffaa33;">
        🚧 Groups is a work in progress! We're still building it out - check back soon.
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
<?php
exit;
endif;

$reader = !empty($_SESSION['reader_id']) ? getUserById((int)$_SESSION['reader_id']) : null;
$myId = $reader['id'] ?? 0;
$error = $_GET['error'] ?? '';
$notice = $_GET['notice'] ?? '';

$groups = getActiveGroups();
$myGroups = $myId ? getUserGroups($myId) : [];
$myGroupIds = array_column($myGroups, 'id');
$pendingInvites = $myId ? getPendingGroupInvitesForUser($myId) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Groups - <?= e(SITE_NAME) ?></title>
<meta name="description" content="Browse ScratchNews community groups.">
<link rel="stylesheet" href="/assets/style.css?v=24">
<style>
.groups-beta-tag { font-size: 0.75rem; background: #ffaa33; color: #1a1a1a; padding: 0.15rem 0.5rem; border-radius: 999px; margin-left: 0.5rem; vertical-align: middle; }
.groups-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; margin-top: 1rem; }
.group-card { border: 1px solid rgba(128,128,128,0.3); border-radius: 10px; overflow: hidden; display: flex; flex-direction: column; }
.group-card-banner { width: 100%; height: 90px; object-fit: cover; background: rgba(128,128,128,0.15); }
.group-card-body { padding: 0.8rem; display: flex; flex-direction: column; gap: 0.3rem; }
.group-card-name { font-weight: 700; }
.group-card-meta { font-size: 0.8rem; opacity: 0.75; }
.group-invite-row { border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 0.7rem 1rem; display: flex; justify-content: space-between; align-items: center; gap: 0.6rem; margin-bottom: 0.6rem; }
.groups-header-top { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.6rem; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <div class="groups-header-top">
        <h2>Groups <span class="groups-beta-tag">BETA</span></h2>
        <?php if ($myId): ?>
            <a href="/create-group" class="btn inline">Create Group</a>
        <?php endif; ?>
    </div>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($notice): ?><div class="alert"><?= e($notice) ?></div><?php endif; ?>

    <?php if (!empty($pendingInvites)): ?>
    <h3>Your Invites</h3>
    <?php foreach ($pendingInvites as $inv): ?>
        <div class="group-invite-row">
            <span><strong>@<?= e($inv['inviter_username']) ?></strong> invited you to <strong><?= e($inv['group_name']) ?></strong></span>
            <span style="display:flex; gap:0.5rem;">
                <form method="post" action="/group-action">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="respond_invite">
                    <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                    <input type="hidden" name="accept" value="1">
                    <button class="btn inline" type="submit">Accept</button>
                </form>
                <form method="post" action="/group-action">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="respond_invite">
                    <input type="hidden" name="invite_id" value="<?= (int)$inv['id'] ?>">
                    <input type="hidden" name="accept" value="0">
                    <button class="btn secondary inline" type="submit">Decline</button>
                </form>
            </span>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (empty($groups)): ?>
        <p>No groups yet - be the first to request one<?= $myId ? '.' : ' (log in first).' ?></p>
    <?php else: ?>
        <div class="groups-grid">
            <?php foreach ($groups as $g): ?>
            <a href="/group/<?= e($g['slug']) ?>" class="group-card">
                <?php if (!empty($g['banner_url'])): ?>
                    <img src="<?= e($g['banner_url']) ?>" alt="" class="group-card-banner">
                <?php else: ?>
                    <div class="group-card-banner"></div>
                <?php endif; ?>
                <div class="group-card-body">
                    <span class="group-card-name"><?= e($g['name']) ?><?= in_array((int)$g['id'], $myGroupIds, true) ? ' ✓' : '' ?></span>
                    <span class="group-card-meta"><?= (int)$g['member_count'] ?> member<?= (int)$g['member_count'] === 1 ? '' : 's' ?> · hosted by @<?= e($g['host_username']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!$myId): ?>
    <p><a href="/login">Log in</a> to request a group.</p>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>