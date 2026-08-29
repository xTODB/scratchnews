<?php
require_once __DIR__ . '/../functions.php';
startSession();

if (empty($_SESSION['is_admin']) && empty($_SESSION['is_moderator'])) {
    header('Location: /login');
    exit;
}
$isAdminUser = !empty($_SESSION['is_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if ($isAdminUser && ($_POST['action'] ?? '') === 'delete') {
        deleteFeedback((int)($_POST['id'] ?? 0));
    }
    if ($isAdminUser && ($_POST['action'] ?? '') === 'set_status') {
        setFeedbackStatus((int)($_POST['id'] ?? 0), (string)($_POST['status'] ?? 'none'));
    }
    if (($_POST['action'] ?? '') === 'reply') {
        $replyMessage = trim($_POST['reply_message'] ?? '');
        $feedbackId = (int)($_POST['id'] ?? 0);
        if ($feedbackId > 0 && $replyMessage !== '') {
            $targetFeedback = getFeedbackById($feedbackId);
            if (($targetFeedback['status'] ?? 'none') !== 'closed') {
                // Logged-in submitters get a real thread (unlimited back-and-forth);
                // anonymous feedback has no account to thread with, so it keeps the
                // original one-shot reply_message behavior.
                if (!empty($targetFeedback['user_id'])) {
                    addFeedbackReply($feedbackId, 'admin', (int)($_SESSION['reader_id'] ?? 0), $replyMessage);
                } else {
                    replyToFeedback($feedbackId, (int)($_SESSION['reader_id'] ?? 0), $replyMessage);
                }
            }
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
.feedback-status-row { display: flex; align-items: center; gap: 0.5rem; margin: 0; flex-wrap: wrap; }
.feedback-status-row form { margin: 0; }
.feedback-tag { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; color: #fff; }
.feedback-tag-added { background: #2e9e4b; }
.feedback-tag-fixed { background: #2b7fd6; }
.feedback-tag-closed { background: #4b3f9e; }
.feedback-status-btn { background: none; border: 1px solid rgba(128,128,128,0.35); border-radius: 6px; padding: 0.15rem 0.5rem; font-size: 0.75rem; cursor: pointer; opacity: 0.85; color: #fff; }
.feedback-status-btn:hover { opacity: 1; }
.feedback-closed-notice { opacity: 0.7; font-size: 0.85rem; margin-top: 0.5rem; font-style: italic; }
.feedback-thread-msg { opacity: 0.85; margin: 0.4rem 0; padding: 0.5rem 0.7rem; border-radius: 6px; background: rgba(128,128,128,0.08); }
.feedback-thread-msg.from-admin { background: rgba(232,163,61,0.12); }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php if ($isAdminUser) { require_once __DIR__ . '/nav.php'; } else { include __DIR__ . '/../includes/header.php'; } ?>
<main>
    <h2>Feedback</h2>
    <?php if (empty($feedback)): ?>
        <p>No feedback yet.</p>
    <?php else: ?>
        <?php foreach ($feedback as $f): ?>
            <?php $status = $f['status'] ?? 'none'; ?>
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
                            <?php if ($status !== 'none'): ?>
                                <span class="feedback-tag feedback-tag-<?= e($status) ?>"><?= e(ucfirst($status)) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($isAdminUser): ?>
                    <div>
                        <form method="post" onsubmit="return confirm('Delete this feedback?');">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                            <button type="submit" class="feedback-delete">Delete</button>
                        </form>
                        <div class="feedback-status-row">
                            <?php foreach (['added' => 'Added', 'fixed' => 'Fixed', 'closed' => 'Closed'] as $val => $label): ?>
                                <?php if ($status !== $val): ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="set_status">
                                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                    <input type="hidden" name="status" value="<?= e($val) ?>">
                                    <button type="submit" class="feedback-status-btn"><?= e($label) ?></button>
                                </form>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($status !== 'none'): ?>
                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="set_status">
                                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                    <input type="hidden" name="status" value="none">
                                    <button type="submit" class="feedback-status-btn">Clear</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($f['user_id'])): ?>
                    <?php foreach (getFeedbackThread((int)$f['id']) as $msg): ?>
                        <p class="feedback-thread-msg <?= $msg['sender_type'] === 'admin' ? 'from-admin' : '' ?>">
                            <strong><?= $msg['sender_type'] === 'admin' ? 'Reply' . ($msg['sender_username'] ? ' by @' . e($msg['sender_username']) : '') : '@' . e($f['username']) . ' replied' ?>:</strong>
                            <?= nl2br(e($msg['message'])) ?>
                        </p>
                    <?php endforeach; ?>
                    <?php if ($status === 'closed'): ?>
                        <p class="feedback-closed-notice">This feedback is closed. Clear the status to reopen it for replies.</p>
                    <?php else: ?>
                    <form method="post" style="margin-top:0.5rem;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                        <textarea name="reply_message" placeholder="Reply..." required style="width:100%;min-height:50px;"></textarea>
                        <button class="btn" type="submit">Send Reply</button>
                    </form>
                    <?php endif; ?>
                <?php elseif (!empty($f['reply_message'])): ?>
                    <p style="opacity:0.8; margin-top:0.5rem;"><strong>Reply<?= $f['replied_by_username'] ? ' by @' . e($f['replied_by_username']) : '' ?>:</strong> <?= nl2br(e($f['reply_message'])) ?></p>
                <?php elseif ($status === 'closed'): ?>
                    <p class="feedback-closed-notice">This feedback is closed. Clear the status to reopen it for replies.</p>
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