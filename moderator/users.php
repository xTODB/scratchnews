<?php
require_once __DIR__ . '/auth.php';

if (empty($isHeadModerator)) {
    header('Location: /moderator');
    exit;
}

// Deliberately read-mostly per TODB's spec: no email, IP, admin flag, verified
// flag, ranks, reset password, change username, log in as, or delete here.
// That's the full admin/users.php table - use that for anything beyond this.
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
<main style="max-width: 700px;">
    <h2 style="text-align:center;">Users (<?= count($users) ?>)</h2>
    <div style="overflow-x:auto;">
    <table style="width:auto;">
        <tr><th>Username</th><th>Joined</th><th>Status</th></tr>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><a href="/@<?= e($u['username']) ?>">@<?= e($u['username']) ?></a></td>
                <td><?= utcTimeTag($u['created_at']) ?></td>
                <td><?= $u['is_banned'] ? '<span style="color:#a33; font-weight:600;">Banned</span>' : 'Active' ?></td>
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
