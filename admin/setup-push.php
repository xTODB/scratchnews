<?php
require_once __DIR__ . '/auth.php';

// One-time setup: generates the site's VAPID identity keypair for Web Push and stores
// it via setApiSetting() (same pattern as the Scratch-verify settings) - no config.php
// edit needed. Safe to revisit this page later; it won't regenerate existing keys
// unless you explicitly use the "Regenerate" button (which will invalidate every
// existing push subscription - they'll need to resubscribe).

$existingPublic = getApiSetting('vapid_public_key', '');
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'generate') {
        $keypair = generateRawEcKeypair();
        setApiSetting('vapid_public_key', $keypair['public']);
        setApiSetting('vapid_private_key', $keypair['private']);
        $existingPublic = $keypair['public'];
        $message = 'New VAPID keypair generated and saved. Any subscriptions made under the OLD key (if there was one) will silently fail to deliver - not a bug, just how VAPID key rotation works.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Push Setup - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=21">
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<main>
    <h2>Push Notification Setup</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($existingPublic): ?>
        <p>A VAPID key is already set up. Public key (safe to be public, this is what the browser uses to verify pushes come from us):</p>
        <p style="word-break:break-all; font-family:monospace; background:rgba(128,128,128,0.15); padding:0.75rem; border-radius:6px;"><?= e($existingPublic) ?></p>
        <form method="post" onsubmit="return confirm('This invalidates every existing push subscription - everyone who turned on notifications will need to do it again. Continue?');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="generate">
            <button class="btn secondary" type="submit">Regenerate (invalidates existing subscriptions)</button>
        </form>
    <?php else: ?>
        <p>No VAPID key set up yet - the "Notify Me" button on the welcome banner won't work until this is done, once.</p>
        <form method="post">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="generate">
            <button class="btn" type="submit">Generate VAPID Keypair</button>
        </form>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
