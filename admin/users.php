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
            banUser($userId);
            $message = 'User banned.';
        } elseif ($action === 'unban') {
            unbanUser($userId);
            $message = 'User unbanned.';
        } else {
            anonymizeUser($userId);
            $message = 'User deleted and anonymized.';
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
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main style="max-width: 1050px;">
    <h2 style="text-align:center;">Users (<?= count($users) ?>)</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <div style="overflow-x:auto;">
    <table style="width:auto;">
        <tr><th>Username</th><th>Email</th><th>IP</th><th>Admin</th><th>Verified</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><a href="/@<?= e($u['username']) ?>">@<?= e($u['username']) ?></a></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['ip_address'] ?? '—') ?></td>
                <td><?= $u['is_admin'] ? 'Yes' : '—' ?></td>
                <td><?= $u['email_verified'] ? 'Yes' : 'No' ?></td>
                <td><?= utcTimeTag($u['created_at']) ?></td>
                <td><?= $u['is_banned'] ? '<span style="color:#a33; font-weight:600;">Banned</span>' : 'Active' ?></td>
                <td class="actions" style="white-space:nowrap;">
                    <?php if (!$u['is_admin']): ?>
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
                        <a href="#" onclick="if(confirm('Ban this user?')) document.getElementById('ban<?= (int)$u['id'] ?>').submit(); return false;">Ban</a>
                        <form id="ban<?= (int)$u['id'] ?>" method="post" style="display:none;">
                            <?= csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="action" value="ban">
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