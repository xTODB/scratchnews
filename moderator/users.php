<?php
require_once __DIR__ . '/auth.php';

if (empty($isHeadModerator)) {
    header('Location: /moderator');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $userId = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $target = $userId > 0 ? getUserById($userId) : null;

    if ($target && !empty($target['is_admin'])) {
        $message = 'Admins cannot be banned or timed out from here.';
    } elseif ($target && $action === 'ban') {
        banUser($userId, $_POST['ban_reason'] ?? null);
        $message = 'User banned.';
    } elseif ($target && $action === 'unban') {
        unbanUser($userId);
        $message = 'User unbanned.';
    } elseif ($target && $action === 'timeout') {
        $minutes = (int)($_POST['minutes'] ?? 0);
        if ($minutes > 0) {
            setUserCommentTimeout($userId, $minutes);
            $message = 'Comment timeout applied.';
        }
    }
}

// Deliberately read-mostly beyond ban/timeout, per TODB's spec: no email, IP,
// admin flag, verified flag, ranks, reset password, change username, log in
// as, or delete here. That's the full admin/users.php table - use that for
// anything beyond ban/timeout.
$users = getAllUsers();
$users = array_values(array_filter($users, fn($u) => strpos($u['username'], 'deleted_user_') !== 0));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Users - Moderator - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main style="max-width: 850px;">
    <h2 style="text-align:center;">Users (<?= count($users) ?>)</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <div style="overflow-x:auto;">
    <table style="width:auto;">
        <tr><th>Username</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($users as $u): ?>
            <?php $lock = getCommentLockStatus((int)$u['id']); ?>
            <tr>
                <td><a href="/@<?= e($u['username']) ?>">@<?= e($u['username']) ?></a></td>
                <td><?= utcTimeTag($u['created_at']) ?></td>
                <td>
                    <?= $u['is_banned'] ? '<span style="color:#a33; font-weight:600;">Banned</span>' : 'Active' ?>
                    <?php if ($lock['locked']): ?>
                        <br><span style="color:#a76b00; font-size:0.85em;">Timed out until <?= utcTimeTag($lock['until']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="actions" style="white-space:nowrap;">
                    <?php if (!empty($u['is_admin'])): ?>
                        &mdash;
                    <?php else: ?>
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
                        &middot;
                        <form method="post" style="display:inline;padding:0;background:none;box-shadow:none;max-width:none;margin:0;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="timeout">
                            <select name="minutes" onchange="this.form.submit()">
                                <option value="">Timeout...</option>
                                <option value="10">10 min</option>
                                <option value="60">1 hour</option>
                                <option value="1440">1 day</option>
                                <option value="10080">7 days</option>
                            </select>
                        </form>
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
