<?php
require_once __DIR__ . '/functions.php';
startSession();

// Update this if the desktop app repo ever gets renamed or moved.
$repo = 'xTODB/scratchnews-desktop';
$downloadWin = "https://github.com/$repo/releases/latest/download/ScratchNews-Setup.exe";
$downloadMac = "https://github.com/$repo/releases/latest/download/ScratchNews.dmg";
$downloadLinux = "https://github.com/$repo/releases/latest/download/ScratchNews.AppImage";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Download - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.download-grid { display:flex; flex-wrap:wrap; gap:1rem; margin:1.5rem 0 2rem; }
.download-card {
    border:1px solid #ccc; border-radius:10px; padding:1.5rem; flex:1 1 220px; max-width:280px;
    text-align:center; display:flex; flex-direction:column; align-items:center; gap:0.6rem;
}
body.dark .download-card { border-color:#444; }
.download-card h4 { margin:0; }
.download-btn {
    display:inline-block; margin-top:0.4rem; padding:0.6rem 1.4rem; border-radius:8px;
    background:var(--brand); color:#fff; font-weight:600; text-decoration:none;
}
.download-btn:hover { opacity:0.9; }
.download-note { font-size:0.85rem; opacity:0.75; margin-top:0.3rem; }
.warning-box {
    border:1px solid #e0a030; background:rgba(224,160,48,0.08); border-radius:8px;
    padding:1rem 1.2rem; margin:1.5rem 0;
}
.warning-box h4 { margin-top:0; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/empty-header.php'; ?>
<main>
    <h2>Download ScratchNews Desktop</h2>
    <p>A desktop app for ScratchNews: same site, its own window, its own icon in your taskbar or dock. Whatever changes on the site shows up here automatically; there's nothing extra to update.</p>

    <div class="download-grid">
        <div class="download-card">
            <h4>Windows</h4>
            <a href="<?= e($downloadWin) ?>" class="download-btn">Download .exe</a>
        </div>
        <div class="download-card">
            <h4>macOS</h4>
            <a href="<?= e($downloadMac) ?>" class="download-btn">Download .dmg</a>
        </div>
        <div class="download-card">
            <h4>Linux</h4>
            <a href="<?= e($downloadLinux) ?>" class="download-btn">Download .AppImage</a>
        </div>
    </div>

    <div class="warning-box">
        <h4>Seeing a security warning?</h4>
        <p>That's expected, not a virus:  the app just isn't code-signed (that costs money we haven't spent, since we're a small free project). On Windows, click <strong>"More info"</strong> then <strong>"Run anyway."</strong> On Mac, right-click the app and choose <strong>"Open"</strong> once. After that first time, it opens normally.</p>
    </div>

    <p class="download-note">Prefer not to install anything? <a href="/">ScratchNews</a> works exactly the same right in your browser: the desktop app is just a shortcut, not a separate experience.</p>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
