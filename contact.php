<?php
require_once __DIR__ . '/functions.php';
startSession();

$error = '';
$threadUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $error = 'Please enter a message before submitting.';
    } else {
        $userId = !empty($_SESSION['reader_id']) ? (int)$_SESSION['reader_id'] : null;
        $result = submitContactMessage($userId, $message);
        $threadUrl = $userId
            ? '/contact-thread.php?id=' . $result['id']
            : '/contact-thread.php?id=' . $result['id'] . '&token=' . $result['token'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Contact Us - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Contact Us</h2>
    <p>Reach the ScratchNews team directly - Head Moderators and the dev see and reply to these. For bug reports or feature ideas, use <a href="/feedback">Feedback</a> instead.</p>

    <?php if ($threadUrl): ?>
        <div class="alert success">
            Message sent! We'll reply here.
            <?php if (empty($_SESSION['reader_id'])): ?>
                <strong>Bookmark this link to check for a reply</strong> - since you're not logged in, this is the only way back to your thread:<br>
                <a href="<?= e($threadUrl) ?>"><?= e($threadUrl) ?></a>
            <?php endif; ?>
        </div>
        <p><a href="<?= e($threadUrl) ?>" class="btn">View Your Thread</a></p>
    <?php else: ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post">
            <?= csrfField() ?>
            <label for="message">Your message</label>
            <textarea name="message" id="message" required></textarea>
            <button class="btn" type="submit">Submit</button>
        </form>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
