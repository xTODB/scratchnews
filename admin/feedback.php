<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (($_POST['action'] ?? '') === 'delete') {
        deleteFeedback((int)($_POST['id'] ?? 0));
    }
    if (($_POST['action'] ?? '') === 'reply') {
        $replyMessage = trim($_POST['reply_message'] ?? '');
        $feedbackId = (int)($_POST['id'] ?? 0);
        if ($feedbackId > 0 && $replyMessage !== '') {
            replyToFeedback($feedbackId, (int)($_SESSION['reader_id'] ?? 0), $replyMessage);
        }
    }
    header('Location: /admin/feedback.php');
    exit;
}

$feedback = getAllFeedback();
markAllFeedbackRead();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Feedback - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.feedback-row { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; padding: 0.9rem; border: 1px solid rgba(128,128,128,0.2); border-radius: 8px; margin-bottom: 0.5rem; }
.feedback-row.unread { border-color: #e8a33d; }
.feedback-meta { opacity: 0.65; font-size: 0.8rem; margin-top: 0.3rem; }
.feedback-delete { background: none; border: none; color: #c33; cursor: pointer; font-size: 0.85rem; }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Feedback</h2>
    <?php if (empty($feedback)): ?>
        <p>No feedback yet.</p>
    <?php else: ?>
        <?php foreach ($feedback as $f): ?>
            <div class="feedback-row <?= empty($f['is_read']) ? 'unread' : '' ?>" style="flex-direction:column; align-items:stretch;">
                <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start;">
                    <div>
                        <div><?= nl2br(e($f['message'])) ?></div>
                        <?php if (!empty($f['image_url'])): ?>
                            <img src="<?= e($f['image_url']) ?>" alt="" style="max-width:280px;display:block;border-radius:6px;margin-top:0.5rem;">
                        <?php endif; ?>
                        <div class="feedback-meta">
                            <?= $f['username'] ? '@' . e($f['username']) : 'Anonymous' ?> ·
                            <?= e($f['created_at']) ?>
                        </div>
                    </div>
                    <form method="post" onsubmit="return confirm('Delete this feedback?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                        <button type="submit" class="feedback-delete">Delete</button>
                    </form>
                </div>
                <?php if (!empty($f['reply_message'])): ?>
                    <p style="opacity:0.8; margin-top:0.5rem;"><strong>Reply<?= $f['replied_by_username'] ? ' by @' . e($f['replied_by_username']) : '' ?>:</strong> <?= nl2br(e($f['reply_message'])) ?></p>
                <?php else: ?>
                    <form method="post" style="margin-top:0.5rem;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                        <textarea name="reply_message" placeholder="Reply..." required style="width:100%;min-height:50px;"></textarea>
                        <button class="btn" type="submit">Send Reply</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>