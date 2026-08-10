<?php
require_once __DIR__ . '/functions.php';
sendNoCacheHeaders();
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}

$user = getUserById($_SESSION['reader_id']);
if (!$user) {
    header('Location: /login');
    exit;
}

markAllNotificationsRead($user['id']);
$notifications = getNotificationsForUser($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Messages - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.messages-page { max-width: 700px; margin: 0 auto; }
.messages-empty { opacity: 0.7; padding: 2rem 0; text-align: center; }
.message-row {
    display: flex; align-items: flex-start; gap: 0.75rem;
    padding: 0.9rem; border-radius: 8px;
    text-decoration: none; color: inherit; cursor: pointer;
    border: 1px solid rgba(128,128,128,0.2); margin-bottom: 0.5rem;
}
.message-row a { position: relative; z-index: 1; }
.message-row-icon { width: 28px; height: 28px; flex-shrink: 0; margin-top: 2px; }
.message-row-snippet { opacity: 0.7; font-size: 0.85rem; margin-top: 2px; }
.message-row-time { opacity: 0.55; font-size: 0.75rem; margin-top: 4px; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Messages</h2>
    <div class="messages-page">
        <?php if (empty($notifications)): ?>
            <div class="messages-empty">No messages yet.</div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <?php
                    $icon = NOTIFICATION_ICONS[$n['type']] ?? '/assets/icons/message.svg';
                    // If the notification points at an actor's profile (/@username) but that
                    // actor's account has since been deleted, the link is stale and 404s.
                    // Fall back to a non-navigating row instead of a broken link.
                    $actorMissing = !empty($n['actor_id']) && empty($n['actor_username']);
                    $isProfileLink = $n['link'] && str_starts_with($n['link'], '/@');
                    $clickable = !($isProfileLink && $actorMissing);
                    $link = $clickable ? ($n['link'] ?: '#') : '#';
                ?>
                <div class="message-row"<?= $clickable ? ' onclick="location.href=\'' . e($link) . '\'"' : '' ?>>
                    <img src="<?= e($icon) ?>" class="message-row-icon" alt="">
                    <div>
                        <div><?= renderNotificationText($n) ?></div>
                        <?php if (!empty($n['message'])): ?>
                            <div class="message-row-snippet"><?= e(mb_strimwidth($n['message'], 0, 120, '...')) ?></div>
                        <?php endif; ?>
                        <div class="message-row-time"><?= utcTimeTag($n['created_at'], 'datetime') ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('click', function(e) {
    var menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-nav')) menu.classList.remove('open');
});
</script>
</body>
</html>