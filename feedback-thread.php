<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}

$feedbackId = (int)($_GET['id'] ?? 0);
$feedback = $feedbackId > 0 ? getFeedbackById($feedbackId) : null;

// Only the original (logged-in) submitter can view/reply to their own thread.
// Anonymous feedback has no user_id to match against, so this naturally
// blocks it too - anon feedback has no thread page at all.
if (!$feedback || (int)($feedback['user_id'] ?? 0) !== (int)$_SESSION['reader_id']) {
    header('Location: /feedback');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $message = trim($_POST['message'] ?? '');
    if (($feedback['status'] ?? 'none') === 'closed') {
        $error = 'This feedback has been closed and can no longer receive replies.';
    } elseif ($message === '') {
        $error = 'Please enter a message before sending.';
    } else {
        addFeedbackReply($feedbackId, 'reader', (int)$_SESSION['reader_id'], $message);
        header('Location: /feedback-thread.php?id=' . $feedbackId);
        exit;
    }
}

$thread = getFeedbackThread($feedbackId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Feedback Thread - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.thread-page { max-width: 700px; margin: 0 auto; }
.thread-msg { padding: 0.9rem; border: 1px solid rgba(128,128,128,0.2); border-radius: 8px; margin-bottom: 0.6rem; }
.thread-msg.from-admin { border-color: #e8a33d; }
.thread-msg-meta { opacity: 0.65; font-size: 0.8rem; margin-bottom: 0.3rem; }
.thread-page textarea { width: 100%; min-height: 80px; padding: 0.6rem; border: 1px solid #ccc; border-radius: 6px; font-family: inherit; margin-bottom: 0.6rem; }
.feedback-tag { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; color: #fff; margin-left: 0.4rem; }
.feedback-tag-added { background: #2e9e4b; }
.feedback-tag-fixed { background: #2b7fd6; }
.feedback-tag-closed { background: #4b3f9e; }
.feedback-closed-notice { opacity: 0.7; font-size: 0.85rem; font-style: italic; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Feedback Thread</h2>
    <div class="thread-page">
        <div class="thread-msg">
            <div class="thread-msg-meta">You &middot; <?= utcTimeTag($feedback['created_at'], 'datetime') ?>
                <?php if (($feedback['status'] ?? 'none') !== 'none'): ?>
                    <span class="feedback-tag feedback-tag-<?= e($feedback['status']) ?>"><?= e(ucfirst($feedback['status'])) ?></span>
                <?php endif; ?>
            </div>
            <div><?= nl2br(e($feedback['message'])) ?></div>
            <?php if (!empty($feedback['image_url'])): ?>
                <img src="<?= e($feedback['image_url']) ?>" alt="" style="max-width:280px;display:block;border-radius:6px;margin-top:0.5rem;">
            <?php endif; ?>
        </div>

        <?php foreach ($thread as $msg): ?>
            <div class="thread-msg <?= $msg['sender_type'] === 'admin' ? 'from-admin' : '' ?>">
                <div class="thread-msg-meta">
                    <?= $msg['sender_type'] === 'admin' ? 'ScratchNews' . ($msg['sender_username'] ? ' (@' . e($msg['sender_username']) . ')' : '') : 'You' ?>
                    &middot; <?= utcTimeTag($msg['created_at'], 'datetime') ?>
                </div>
                <div><?= nl2br(e($msg['message'])) ?></div>
            </div>
        <?php endforeach; ?>

        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php if (($feedback['status'] ?? 'none') === 'closed'): ?>
            <p class="feedback-closed-notice">This feedback has been closed and can no longer receive replies.</p>
        <?php else: ?>
        <form method="post">
            <?= csrfField() ?>
            <textarea name="message" placeholder="Reply..." required></textarea>
            <button class="btn" type="submit">Send</button>
        </form>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>