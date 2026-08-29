<?php
require_once __DIR__ . '/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'reset_timeout') {
        $target = getUserByUsername(trim($_POST['username'] ?? ''));
        if ($target) {
            resetModerationStrikes((int)$target['id']);
            $message = 'Timeout cleared for @' . $target['username'] . '.';
        } else {
            $message = 'No user found with that username.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Clear Timeout - Moderator - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Clear a User's Timeout</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <p>Clears strikes and lifts any active comment lock for the given username. Use this if someone got flagged unfairly.</p>
    <form method="post" style="display:flex; gap:0.5rem; max-width:400px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reset_timeout">
        <input type="text" name="username" placeholder="Username" required style="flex:1;">
        <button type="submit" class="btn">Clear</button>
    </form>
</main>
</body>
</html>
