<?php
require_once __DIR__ . '/functions.php';
startSession();

if (!isGroupsBetaAllowed()) {
    header('Location: /groups');
    exit;
}

$myId = !empty($_SESSION['reader_id']) ? (int)$_SESSION['reader_id'] : 0;
if (!$myId) {
    header('Location: /login');
    exit;
}
logVisit('/create-group');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $bannerUrl = null;
    if (!empty($_FILES['banner']['tmp_name'])) {
        try {
            $bannerUrl = saveUploadedImage($_FILES['banner'], 'group_banners');
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
    if ($error === '') {
        if ($name === '' || mb_strlen($name) > 100) {
            $error = 'Please enter a group name (up to 100 characters).';
        } else {
            $result = createGroupRequest($myId, $name, $description, $bannerUrl);
            if ($result['ok']) {
                header('Location: /groups?notice=' . urlencode('Your group request has been submitted for review.'));
                exit;
            }
            $error = $result['reason'];
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
<title>Create a Group - <?= e(SITE_NAME) ?></title>
<meta name="description" content="Request a new ScratchNews group.">
<link rel="stylesheet" href="/assets/style.css?v=24">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <a class="back-link" href="/groups">&larr; Back to Groups</a>
    <h2>Create a Group</h2>
    <p>Groups are moderator/dev-reviewed before they go live. You can own up to <?= GROUP_MAX_PER_USER ?> groups at a time.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/create-group" enctype="multipart/form-data">
        <?= csrfField() ?>
        <label>Group name</label>
        <input type="text" name="name" maxlength="100" required value="<?= e($_POST['name'] ?? '') ?>">
        <label>Description</label>
        <textarea name="description" rows="3" maxlength="2000"><?= e($_POST['description'] ?? '') ?></textarea>
        <label>Banner image (optional)</label>
        <input type="file" name="banner" accept="image/*">
        <button class="btn" type="submit">Submit Request</button>
    </form>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>