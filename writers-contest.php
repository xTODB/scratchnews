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
<title>Writers' Contest - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=21">
<style>
.contest-hero { width:100%; height:auto; border-radius:10px; margin:1rem 0 1.5rem; display:block; }
.contest-scratcher-grid { display:flex; flex-wrap:wrap; gap:0.5rem; margin:0.75rem 0 1.5rem; }
.contest-scratcher-chip {
    border:1px solid #ccc; border-radius:20px; padding:0.35rem 0.9rem; font-size:0.9rem;
}
body.dark .contest-scratcher-chip { border-color:#444; }
.contest-cta-row { margin:1.5rem 0; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Writers' Contest</h2>
    <img src="/assets/writers-contest-banner.svg" alt="Our first ever event - Writers' Contest!" class="contest-hero">

    <p>ScratchNews's first ever event! Pick a Scratcher from the list below, write an article about them, and get them to see it. At the end, the most active writers get badges and a good shot at becoming a moderator.</p>

    <h3>The rules</h3>
    <ul>
        <li>You can write up to <strong>5 contest entries</strong>, each about a <strong>different</strong> Scratcher from the list below.</li>
        <li>Entries get a small "this article is about @username" banner linking to that Scratcher's own ScratchNews profile.</li>
        <li>For that link to actually work, the Scratcher being written about needs to make a <strong>Contest account</strong>: verified as really being them, not something a writer can do on their behalf.</li>
        <li>In the article about your chosen Scratcher, you can talk about their history, influence or evolution. A good example of how an article about a Scratcher could look like can be seen <a href="https://scratchnews.freedev.app/article/27">here.</a></li>
        <li>Do not say rude or disrespectful things about the Scratcher in your article.</li>
    </ul>

    <h3>Pick a Scratcher to write about</h3>
    <div class="contest-scratcher-grid">
        <?php foreach (CONTEST_SCRATCHERS as $s): ?>
        <a class="contest-scratcher-chip" href="https://scratch.mit.edu/users/<?= rawurlencode($s) ?>/" target="_blank" rel="noopener">@<?= e($s) ?></a>
        <?php endforeach; ?>
    </div>

    <h3>Are you one of the Scratchers above?</h3>
    <p>Make your Contest account so entries about you actually link to your profile: and so you can get notified when someone writes about you.</p>
    <div class="contest-cta-row">
        <a href="/register/contest" class="btn">Make a Contest Account</a>
    </div>

    <p><em>Judging, timing, and prizes are still being finalized - check back for updates, or watch <a href="/changelog">the changelog</a>.</em></p>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
