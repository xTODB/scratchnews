<?php
require_once __DIR__ . '/functions.php';
startSession();
$token = $_GET['token'] ?? '';
$success = $token !== '' && unsubscribeByToken($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Unsubscribe - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main style="text-align:center; padding:3rem 1rem;">
    <?php if ($success): ?>
        <h2>You've been unsubscribed</h2>
        <p>Sorry to see you go! You won't get any more emails from ScratchNews.</p>
    <?php else: ?>
        <h2>Hmm, that link didn't work</h2>
        <p>You may have already unsubscribed.</p>
    <?php endif; ?>
    <a href="/" class="btn">Back to ScratchNews</a>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>