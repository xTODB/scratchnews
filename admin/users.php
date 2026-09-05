<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId > 0 && in_array($action, ['ban', 'unban', 'delete'])) {
        if ($action === 'ban') {
            banUser($userId, $_POST['ban_reason'] ?? null);
            $message = 'User banned.';
        } elseif ($action === 'unban') {
            unbanUser($userId);
            $message = 'User unbanned.';
        } else {
            anonymizeUser($userId);
            $message = 'User deleted and anonymized.';
        }
    } elseif ($userId > 0 && in_array($action, ['make_fan', 'unmake_fan'])) {
        setUserFan($userId, $action === 'make_fan');
        $message = $action === 'make_fan' ? 'Fan rank granted.' : 'Fan rank removed.';
    } elseif ($userId > 0 && in_array($action, ['make_featured_user', 'unmake_featured_user'])) {
        setUserFeaturedUser($userId, $action === 'make_featured_user');
        $message = $action === 'make_featured_user' ? 'Marked as Featured User - their articles will show on the Featured page.' : 'Featured User status removed.';
    } elseif ($userId > 0 && in_array($action, ['make_contest_writer', 'unmake_contest_writer'])) {
        setUserContestWriter($userId, $action === 'make_contest_writer');
        $message = $action === 'make_contest_writer' ? "Writers' Contest Writer badge granted." : "Writers' Contest Writer badge removed.";
    } elseif ($userId > 0 && in_array($action, ['make_contest_scratcher', 'unmake_contest_scratcher'])) {
        setUserContestScratcher($userId, $action === 'make_contest_scratcher');
        $message = $action === 'make_contest_scratcher' ? "Writers' Contest Scratcher badge granted." : "Writers' Contest Scratcher badge removed.";
    } elseif ($userId > 0 && in_array($action, ['make_moderator', 'unmake_moderator'])) {
        setUserModerator($userId, $action === 'make_moderator');
        $message = $action === 'make_moderator' ? 'Moderator rank granted.' : 'Moderator rank removed.';
    } elseif ($userId > 0 && in_array($action, ['make_head_moderator', 'unmake_head_moderator'])) {
        setUserHeadModerator($userId, $action === 'make_head_moderator');
        $message = $action === 'make_head_moderator' ? 'Head Moderator rank granted (Moderator rank included).' : 'Head Moderator rank removed.';
    } elseif ($userId > 0 && $action === 'reset_password') {
        $newPassword = $_POST['new_password'] ?? '';
        if (strlen($newPassword) < 6) {
            $message = 'New password must be at least 6 characters.';
        } else {
            changePassword($userId, $newPassword);
            $message = 'Password reset for that user.';
        }
    } elseif ($userId > 0 && $action === 'change_username') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $result = adminChangeUsername($userId, $newUsername);
        if ($result === 'ok') {
            $message = 'Username changed.';
        } elseif ($result === 'duplicate') {
            $message = 'That username is already taken.';
        } elseif ($result === 'unchanged') {
            $message = 'That\'s already their username.';
        } else {
            $message = 'Usernames must be 3-20 characters: letters, numbers, underscores only.';
        }
    }
}

$users = getAllUsers();
$users = array_values(array_filter($users, fn($u) => strpos($u['username'], 'deleted_user_') !== 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Users - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main style="max-width: 1050px;">
    <h2 style="text-align:center;">Users (<?= count($users) ?>)</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <div style="overflow-x:auto;">
    <table style="width:auto;">
        <tr><th>Username</th><th>Email</th><th>IP</th><th>Admin</th><th>Verified</th><th>Ranks</th><th>Featured</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><a href="/@<?= e($u['username']) ?>">@<?= e($u['username']) ?></a></td>
                <td><?= $u['email'] ? e($u['email']) : '-' ?></td>
                <td><?= e($u['ip_address'] ?? '—') ?></td>
                <td><?= $u['is_admin'] ? 'Yes' : '—' ?></td>
                <td><?= isUserVerified($u) ? 'Yes' : 'No' ?></td>
                <td style="white-space:nowrap;">
                    <?= renderRankBadges($u) ?: '<span style="opacity:0.5;">—</span>' ?>
                    <div style="margin-top:0.3rem; font-size:0.8rem;">
                        <a href="#" onclick="document.getElementById('<?= $u['is_fan'] ? 'unfan' : 'fan' ?><?= (int)$u['id'] ?>').submit(); return false;"><?= $u['is_fan'] ? 'Remove Fan' : 'Make Fan' ?></a>
                        <form id="fan<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="make_fan">
                        </form>
                        <form id="unfan<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="unmake_fan">
                        </form>
                        &middot;
                        <a href="#" onclick="document.getElementById('<?= $u['is_moderator'] ? 'unmod' : 'mod' ?><?= (int)$u['id'] ?>').submit(); return false;"><?= $u['is_moderator'] ? 'Remove Mod' : 'Make Mod' ?></a>
                        <form id="mod<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="make_moderator">
                        </form>
                        <form id="unmod<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="unmake_moderator">
                        </form>
                        &middot;
                        <a href="#" onclick="document.getElementById('<?= $u['is_head_moderator'] ? 'unheadmod' : 'headmod' ?><?= (int)$u['id'] ?>').submit(); return false;"><?= $u['is_head_moderator'] ? 'Remove Head Mod' : 'Make Head Mod' ?></a>
                        <form id="headmod<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="make_head_moderator">
                        </form>
                        <form id="unheadmod<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="unmake_head_moderator">
                        </form>
                    </div>
                </td>
                <td>
                    <?php $isFeaturedUser = (bool)($u['is_featured_user'] ?? 0); ?>
                    <form method="POST" style="display:inline;padding:0;background:none;box-shadow:none;max-width:none;margin:0;">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="<?= $isFeaturedUser ? 'unmake_featured_user' : 'make_featured_user' ?>">
                        <button type="submit" style="background:none;border:none;cursor:pointer;font-size:1.1em;padding:0;color:<?= $isFeaturedUser ? '#f7931e' : '#ccc' ?>;" title="<?= $isFeaturedUser ? 'Remove Featured User (their articles will drop off the Featured page)' : 'Mark Featured User (all their articles will show on the Featured page)' ?>">&#9733;</button>
                    </form>
                    <?php $isContestWriter = (bool)($u['is_contest_writer'] ?? 0); ?>
                    <form method="POST" style="display:inline;padding:0;background:none;box-shadow:none;max-width:none;margin:0 0 0 0.4em;">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="<?= $isContestWriter ? 'unmake_contest_writer' : 'make_contest_writer' ?>">
                        <button type="submit" style="background:none;border:1px solid <?= $isContestWriter ? '#0084ff' : '#ccc' ?>;border-radius:4px;cursor:pointer;font-size:0.7em;font-weight:700;padding:0.1em 0.3em;color:<?= $isContestWriter ? '#0084ff' : '#ccc' ?>;" title="<?= $isContestWriter ? "Remove Writers' Contest Writer badge" : "Grant Writers' Contest Writer badge" ?>">CW</button>
                    </form>
                    <?php $isContestScratcher = (bool)($u['is_contest_scratcher'] ?? 0); ?>
                    <form method="POST" style="display:inline;padding:0;background:none;box-shadow:none;max-width:none;margin:0 0 0 0.2em;">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="<?= $isContestScratcher ? 'unmake_contest_scratcher' : 'make_contest_scratcher' ?>">
                        <button type="submit" style="background:none;border:1px solid <?= $isContestScratcher ? '#ff8c1a' : '#ccc' ?>;border-radius:4px;cursor:pointer;font-size:0.7em;font-weight:700;padding:0.1em 0.3em;color:<?= $isContestScratcher ? '#ff8c1a' : '#ccc' ?>;" title="<?= $isContestScratcher ? "Remove Writers' Contest Scratcher badge" : "Grant Writers' Contest Scratcher badge" ?>">CS</button>
                    </form>
                </td>
                <td><?= utcTimeTag($u['created_at']) ?></td>
                <td><?= $u['is_banned'] ? '<span style="color:#a33; font-weight:600;">Banned</span>' : 'Active' ?></td>
                <td class="actions" style="white-space:nowrap;">
                    <a href="#" onclick="var p=prompt('New password for @<?= e($u['username']) ?> (min 6 characters):'); if(p===null) return false; if(p.length<6){alert('Password must be at least 6 characters.');return false;} document.getElementById('resetpw<?= (int)$u['id'] ?>_input').value=p; document.getElementById('resetpw<?= (int)$u['id'] ?>').submit(); return false;">Reset Password</a>
                    <form id="resetpw<?= (int)$u['id'] ?>" method="post" style="display:none;">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="new_password" id="resetpw<?= (int)$u['id'] ?>_input" value="">
                    </form>
                    &middot;
                    <a href="#" onclick="var p=prompt('New username for @<?= e($u['username']) ?> (3-20 chars, letters/numbers/underscore):'); if(p===null) return false; document.getElementById('chuser<?= (int)$u['id'] ?>_input').value=p; document.getElementById('chuser<?= (int)$u['id'] ?>').submit(); return false;">Change Username</a>
                    <form id="chuser<?= (int)$u['id'] ?>" method="post" style="display:none;">
                        <?= csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        <input type="hidden" name="action" value="change_username">
                        <input type="hidden" name="new_username" id="chuser<?= (int)$u['id'] ?>_input" value="">
                    </form>
                    <?php if (!$u['is_admin']): ?>
                        &middot;
                        <a href="#" onclick="if(confirm('Log in as @<?= e($u['username']) ?>?')) document.getElementById('imp<?= (int)$u['id'] ?>').submit(); return false;">Log In As</a>
                        <form id="imp<?= (int)$u['id'] ?>" method="post" action="/admin/impersonate" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                        </form>
                        <?php if ($u['is_banned']): ?>
                        <a href="#" onclick="document.getElementById('unban<?= (int)$u['id'] ?>').submit(); return false;">Unban</a>
                        <form id="unban<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="unban">
                        </form>
                        <?php else: ?>
                        <a href="#" onclick="var r=prompt('Reason for banning @<?= e($u['username']) ?> (shown to them):'); if(r===null) return false; document.getElementById('ban<?= (int)$u['id'] ?>_reason').value=r; document.getElementById('ban<?= (int)$u['id'] ?>').submit(); return false;">Ban</a>
                        <form id="ban<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="ban">
                            <input type="hidden" name="ban_reason" id="ban<?= (int)$u['id'] ?>_reason" value="">
                        </form>
                        <?php endif; ?>
                        <a href="#" onclick="if(confirm('Delete this user?')) document.getElementById('del<?= (int)$u['id'] ?>').submit(); return false;">Delete</a>
                        <form id="del<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="delete">
                        </form>
                    <?php else: ?>
                        —
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
    </div>
</main>
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
</body>
</html>
