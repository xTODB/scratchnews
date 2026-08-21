<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $user = getUserById($_SESSION['reader_id']);

    if ($user && password_verify($password, $user['password_hash'])) {
        deleteUserAccount($user['id']);
        session_destroy();
        header('Location: /?deleted=1');
        exit;
    } else {
        $error = 'Incorrect password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Delete Account - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Delete Your Account</h2>
    <p>This will permanently delete your account, along with all of your comments and likes. This cannot be undone.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <label for="password">Enter your password to confirm</label>
        <input type="password" id="password" name="password" required>
        <button class="btn" type="submit">Permanently Delete My Account</button>
    </form>
</main>
</body>
</html>