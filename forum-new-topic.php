<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_username'])) {
    header('Location: /login');
    exit;
}

$slug = $_GET['slug'] ?? '';
$subforum = $slug ? getSubforumBySlug($slug) : null;
if (!$subforum) {
    header('Location: /forums');
    exit;
}

$error = $_GET['error'] ?? '';
$title = $_POST['title'] ?? '';
$content = $_POST['content'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/favicon.php'; ?>
<title>New Topic - <?= e($subforum['name']) ?> - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=27">
<?php include __DIR__ . '/includes/forum-bbcode.php'; ?>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="home-main">
    <div class="forum-breadcrumb"><a href="/forums/<?= e($subforum['slug']) ?>">&larr; <?= e($subforum['name']) ?></a></div>
    <h2>New Topic</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/forum-action">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="new_topic">
        <input type="hidden" name="subforum_id" value="<?= (int)$subforum['id'] ?>">
        <label for="topic_title">Title</label>
        <input type="text" id="topic_title" name="title" maxlength="150" required value="<?= e($title) ?>">

        <label for="bbcode_editor">Message</label>
        <?php renderBBCodeToolbar('bbcode_editor'); ?>
        <textarea id="bbcode_editor" name="content" rows="10" required><?= e($content) ?></textarea>

        <button type="submit" class="btn">Create Topic</button>
    </form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
