<?php
require_once __DIR__ . '/functions.php';
startSession();

$threadId = (int)($_GET['id'] ?? 0);
$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$thread = $threadId > 0 ? getContactThreadById($threadId) : null;

// Two ways in: the logged-in submitter's own session, or an anonymous
// submitter's access_token (their only way back to a thread with no account).
$isOwner = $thread
    && !empty($thread['user_id'])
    && (int)$thread['user_id'] === (int)($_SESSION['reader_id'] ?? 0);
$isTokenMatch = $thread
    && empty($thread['user_id'])
    && !empty($thread['access_token'])
    && $token !== ''
    && hash_equals($thread['access_token'], $token);

if (!$thread || (!$isOwner && !$isTokenMatch)) {
    header('Location: /contact');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $message = trim($_POST['message'] ?? '');
    if (($thread['status'] ?? 'open') === 'closed') {
        $error = 'This thread has been closed and can no longer receive replies.';
    } elseif ($message === '') {
        $error = 'Please enter a message before sending.';
    } else {
        addContactReply($threadId, 'reader', $isOwner ? (int)$_SESSION['reader_id'] : null, $message);
        header('Location: /contact-thread.php?id=' . $threadId . ($isTokenMatch ? '&token=' . urlencode($token) : ''));
        exit;
    }
}

$replies = getContactThread($threadId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Contact Us Thread - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.thread-page { max-width: 700px; margin: 0 auto; }
.thread-msg { padding: 0.9rem; border: 1px solid rgba(128,128,128,0.2); border-radius: 8px; margin-bottom: 0.6rem; }
.thread-msg.from-staff { border-color: var(--brand-bright); }
.thread-msg-meta { opacity: 0.65; font-size: 0.8rem; margin-bottom: 0.3rem; }
.thread-page textarea { width: 100%; min-height: 80px; padding: 0.6rem; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; margin-bottom: 0.6rem; }
.contact-tag { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; color: #fff; margin-left: 0.4rem; background: #4b3f9e; }
.contact-closed-notice { opacity: 0.7; font-size: 0.85rem; font-style: italic; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Contact Us Thread</h2>
    <div class="thread-page">
        <?php if (!$isOwner): ?>
            <p class="contact-closed-notice">You're viewing this thread via your bookmarked link. Save it to check for replies later.</p>
        <?php endif; ?>
        <div class="thread-msg">
            <div class="thread-msg-meta">You &middot; <?= utcTimeTag($thread['created_at'], 'datetime') ?>
                <?php if (($thread['status'] ?? 'open') === 'closed'): ?>
                    <span class="contact-tag">Closed</span>
                <?php endif; ?>
            </div>
            <div><?= nl2br(e($thread['message'])) ?></div>
        </div>

        <?php foreach ($replies as $msg): ?>
            <div class="thread-msg <?= $msg['sender_type'] === 'staff' ? 'from-staff' : '' ?>">
                <div class="thread-msg-meta">
                    <?= $msg['sender_type'] === 'staff' ? 'ScratchNews' . ($msg['sender_username'] ? ' (@' . e($msg['sender_username']) . ')' : '') : 'You' ?>
                    &middot; <?= utcTimeTag($msg['created_at'], 'datetime') ?>
                </div>
                <div><?= nl2br(e($msg['message'])) ?></div>
            </div>
        <?php endforeach; ?>

        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php if (($thread['status'] ?? 'open') === 'closed'): ?>
            <p class="contact-closed-notice">This thread has been closed and can no longer receive replies.</p>
        <?php else: ?>
        <form method="post">
            <?= csrfField() ?>
            <?php if ($isTokenMatch): ?><input type="hidden" name="token" value="<?= e($token) ?>"><?php endif; ?>
            <textarea name="message" placeholder="Reply..." required></textarea>
            <button class="btn" type="submit">Send</button>
        </form>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
