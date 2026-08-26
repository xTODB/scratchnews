<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['is_admin']) && empty($_SESSION['is_moderator'])) {
    header('Location: /login');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Moderator Guidelines - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Moderator Guidelines</h2>
    <a href="/moderator" class="btn secondary">&larr; Back to Moderator Panel</a>
    <br><br>

    <p>ScratchNews moderators are the people who keep ScratchNews safe and thriving. These are all the rules for ScratchNews Moderators, to ensure the ScratchNews community stays stable.</p>

    <div class="submission-card" style="border:1px solid #ccc; border-radius:8px; padding:1rem; margin-bottom:1rem;">
        <h3>1. Be Active</h3>
        <p>Try to be active if you can! Don't assume someone else can do it for you, that's not how a moderator works. Moderators have the power to report comments and review the reports themselves, review submissions and feedback, so use that power when you can.</p>
    </div>

    <div class="submission-card" style="border:1px solid #ccc; border-radius:8px; padding:1rem; margin-bottom:1rem;">
        <h3>2. Do Not Make Actions That Use Your Moderation Powers Unfairly</h3>
        <p>You're not allowed to false report comments for no reason, or comment explicit stuff using the feedback page. If a user wants you to make a moderation decision not to keep the site safe, but instead to give something back, like followers or something else, do not respond.</p>
    </div>

    <div class="submission-card" style="border:1px solid #ccc; border-radius:8px; padding:1rem; margin-bottom:1rem;">
        <h3>3. If You Can't Make a More Powerful Moderation Action, Contact <a href="/@ScratchNews">@ScratchNews</a></h3>
        <p>This site is in its early state, so more powerful moderator actions (like banning and unbanning accounts, deleting accounts, etc.) are up to me to decide. I'm probably going to add more power to moderators, possibly a "Head Moderator" rank for more access, but until then I'm the one that maintains most of this site.</p>
    </div>
</main>
</body>
</html>
