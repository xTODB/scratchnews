<?php
require_once __DIR__ . '/functions.php';
startSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Community Guidelines - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=9">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/empty-header.php'; ?>
<main>
    <h2>Community Guidelines</h2>
    <p>- community guidelines so that the site doesn't go kaboom. disrespecting these guidelines will result in a ban/permanent account deletion, or a warning</p>
    <h3><b>1 - The most important rule of all...</b></h3>
    <h3><b>1.1: Respect the Scratch Community Guidelines.</b></h3>
    <p>Every action that you make on this has to not violate any of the Scratch Community Guidelines. <a href="https://scratch.mit.edu/community_guidelines">(seen here.)</a><br>
    This includes comments, replies and articles.</p><br>
    <i>ScratchNews is a site about Scratch-related news; therefore, Scratchers will use it; therefore, Scratch <b>requires</b> the website to make the website as safe as Scratch itself or safer, and if ScratchNews isn't safer than Scratch, then say goodbye to the idea of Scratchers using it.</i><br>
    <h3><b>2 - What articles you can and can't submit</b></h3>
    <h3><b>2.1: No AI text or images.</b></h3>
    <p>Pretty self-explanatory.</p><br>
    <h3><b>2.2: No self-promotion</b></h3>
    <p>Promote <i>yourself,</i> not what you made. If everyone were to self-promote, this website might as well be the Show And Tell forum 2.0.</p><br>
    <p><b>And that's all!</b> Have fun on ScratchNews!</p>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
