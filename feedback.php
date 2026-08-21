<?php
require_once __DIR__ . '/functions.php';
startSession();

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $error = 'Please enter some feedback before submitting.';
    } else {
        $userId = !empty($_SESSION['reader_id']) ? (int)$_SESSION['reader_id'] : null;
        submitFeedback($userId, $message);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Feedback - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Feedback</h2>
    <p>Got a suggestion, bug report, or idea for ScratchNews? Let us know below.</p>

    <?php if ($success): ?>
        <div class="alert success">Thanks for the feedback! We read every submission.</div>
    <?php else: ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrfField() ?>
            <label for="message">Your feedback</label>
            <textarea name="message" id="message" required></textarea>
            <button class="btn" type="submit">Submit</button>
        </form>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>