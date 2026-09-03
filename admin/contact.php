<?php
require_once __DIR__ . '/../functions.php';
startSession();

// Scoped to Head Moderators + the dev, per TODB's spec - deliberately NOT
// open to regular Moderators the way admin/feedback.php is.
if (empty($_SESSION['is_admin']) && empty($_SESSION['is_head_moderator'])) {
    header('Location: /login');
    exit;
}
$isAdminUser = !empty($_SESSION['is_admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0 && $action === 'set_status') {
        setContactThreadStatus($id, (string)($_POST['status'] ?? 'open'));
    } elseif ($id > 0 && $action === 'reply') {
        $replyMessage = trim($_POST['reply_message'] ?? '');
        $thread = getContactThreadById($id);
        if ($thread && ($thread['status'] ?? 'open') !== 'closed' && $replyMessage !== '') {
            addContactReply($id, 'staff', (int)($_SESSION['reader_id'] ?? 0), $replyMessage);
        }
    }
    header('Location: /admin/contact.php');
    exit;
}

$threads = getAllContactThreads();
markAllContactRead();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Contact Us - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.contact-row { padding: 0.9rem; border: 1px solid rgba(128,128,128,0.2); border-radius: 8px; margin-bottom: 0.7rem; }
.contact-row.unread { border-color: #e8a33d; }
.contact-meta { opacity: 0.65; font-size: 0.8rem; margin: 0.3rem 0; }
.contact-tag { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; color: #fff; background: #4b3f9e; }
.contact-thread-msg { opacity: 0.85; margin: 0.4rem 0; padding: 0.5rem 0.7rem; border-radius: 6px; background: rgba(128,128,128,0.08); }
.contact-thread-msg.from-staff { background: rgba(232,163,61,0.12); }
.contact-actions { display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.4rem; }
.contact-actions form { display: inline-block; background: none; padding: 0; box-shadow: none; max-width: none; margin: 0; }
.contact-status-btn { background: none; border: 1px solid rgba(128,128,128,0.35); border-radius: 6px; padding: 0.15rem 0.5rem; font-size: 0.75rem; cursor: pointer; opacity: 0.85; color: #fff; }
.contact-status-btn:hover { opacity: 1; }
.contact-closed-note { opacity: 0.7; font-size: 0.85rem; margin-top: 0.5rem; font-style: italic; }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php if ($isAdminUser) { require_once __DIR__ . '/nav.php'; } else { include __DIR__ . '/../includes/header.php'; } ?>
<main>
    <h2>Contact Us</h2>
    <?php if (empty($threads)): ?>
        <p>No Contact Us messages yet.</p>
    <?php else: ?>
        <?php foreach ($threads as $t): ?>
            <?php $status = $t['status'] ?? 'open'; ?>
            <div class="contact-row <?= empty($t['is_read']) ? 'unread' : '' ?>">
                <div class="contact-actions">
                    <?php if ($status === 'open'): ?>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="status" value="closed">
                            <button type="submit" class="contact-status-btn">Close</button>
                        </form>
                    <?php else: ?>
                        <span class="contact-tag">Closed</span>
                        <form method="post">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="set_status">
                            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                            <input type="hidden" name="status" value="open">
                            <button type="submit" class="contact-status-btn">Reopen</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div><?= nl2br(e($t['message'])) ?></div>
                <div class="contact-meta">
                    <?= $t['username'] ? '@' . e($t['username']) : 'Anonymous' ?> &middot; <?= utcTimeTag($t['created_at']) ?>
                </div>
                <?php foreach (getContactThread((int)$t['id']) as $msg): ?>
                    <p class="contact-thread-msg <?= $msg['sender_type'] === 'staff' ? 'from-staff' : '' ?>">
                        <strong><?= $msg['sender_type'] === 'staff' ? 'Reply' . ($msg['sender_username'] ? ' by @' . e($msg['sender_username']) : '') : (($t['username'] ? '@' . e($t['username']) : 'Anonymous') . ' replied') ?>:</strong>
                        <?= nl2br(e($msg['message'])) ?>
                    </p>
                <?php endforeach; ?>
                <?php if ($status === 'closed'): ?>
                    <p class="contact-closed-note">This thread is closed. Reopen it to keep replying.</p>
                <?php else: ?>
                <form method="post" style="margin-top:0.5rem;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
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
