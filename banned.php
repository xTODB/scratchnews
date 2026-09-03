<?php
require_once __DIR__ . '/functions.php';
startSession();

$reason = null;
if (!empty($_SESSION['reader_id'])) {
    $reason = getUserBanReason((int)$_SESSION['reader_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Account Banned - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.banned-page { max-width: 600px; margin: 0 auto; }
.banned-reason { padding: 0.9rem; border: 1px solid rgba(163,51,51,0.35); border-radius: 8px; background: rgba(163,51,51,0.08); margin: 1rem 0; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <div class="banned-page">
        <h2>Your account has been banned</h2>
        <?php if ($reason): ?>
            <p>Reason given:</p>
            <div class="banned-reason"><?= nl2br(e($reason)) ?></div>
        <?php else: ?>
            <p>No specific reason was given.</p>
        <?php endif; ?>
        <p>You can still browse ScratchNews, but liking, commenting, submitting articles, and other account actions are disabled while banned.</p>
        <p>Think this is a mistake? <a href="/contact">Contact Us</a> to appeal.</p>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
