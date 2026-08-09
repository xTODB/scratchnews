<?php
require_once __DIR__ . '/auth.php';

$db = getDB();
$result = $db->query(
    "SELECT il.*, a.username AS admin_username, u.username AS target_username
     FROM impersonation_log il
     JOIN users a ON a.id = il.admin_id
     JOIN users u ON u.id = il.target_user_id
     ORDER BY il.started_at DESC
     LIMIT 100"
);
$logs = $result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Impersonation Log - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=17">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Impersonation Log</h2>
    <table>
        <tr><th>Admin</th><th>Target</th><th>Time</th></tr>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= e($log['admin_username']) ?></td>
            <td><?= e($log['target_username']) ?></td>
            <td><?= e($log['started_at']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</main>
</body>
</html>