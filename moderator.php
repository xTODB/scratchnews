<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['is_admin']) && empty($_SESSION['is_moderator'])) {
    header('Location: /login');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if (in_array($action, ['approve', 'reject'], true)) {
        $submissionId = (int)($_POST['submission_id'] ?? 0);
        if ($submissionId > 0) {
            if ($action === 'approve') {
                approveSubmission($submissionId);
                $message = 'Submission approved and published.';
            } else {
                rejectSubmission($submissionId);
                $message = 'Submission rejected.';
            }
        }
    } elseif (in_array($action, ['delete_comment', 'dismiss_report'], true)) {
        $reportId = (int)($_POST['report_id'] ?? 0);
        $commentId = (int)($_POST['comment_id'] ?? 0);
        if ($reportId > 0) {
            if ($action === 'delete_comment' && $commentId > 0) {
                adminDeleteComment($commentId);
            }
            resolveReport($reportId);
            $message = $action === 'delete_comment' ? 'Comment deleted.' : 'Report dismissed.';
        }
    } elseif ($action === 'reset_timeout') {
        $target = getUserByUsername(trim($_POST['username'] ?? ''));
        if ($target) {
            resetModerationStrikes((int)$target['id']);
            $message = 'Timeout cleared for @' . $target['username'] . '.';
        } else {
            $message = 'No user found with that username.';
        }
    } elseif ($action === 'reply_feedback') {
        $replyMessage = trim($_POST['reply_message'] ?? '');
        $feedbackId = (int)($_POST['id'] ?? 0);
        if ($feedbackId > 0 && $replyMessage !== '') {
            replyToFeedback($feedbackId, (int)($_SESSION['reader_id'] ?? 0), $replyMessage);
            $message = 'Reply sent.';
        }
    }

    header('Location: /moderator');
    exit;
}

$pendingSubmissions = getPendingSubmissions();
$pendingReports = getPendingReports();
$allPolls = getAllPolls();
$allFeedback = getAllFeedback();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Moderator Panel - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Moderator Panel</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>

    <h3>Pending Submissions</h3>
    <?php if (empty($pendingSubmissions)): ?>
        <p>No pending submissions right now.</p>
    <?php else: ?>
        <?php foreach ($pendingSubmissions as $sub): ?>
            <div class="submission-card" style="border:1px solid #ccc; border-radius:8px; padding:1rem; margin-bottom:1.5rem;">
                <h3><?= e($sub['title']) ?></h3>
                <p><strong>By:</strong> <a href="/@<?= e($sub['username']) ?>"><?= e($sub['username']) ?></a> &middot; <?= e($sub['created_at']) ?></p>
                <p><em><?= e($sub['summary']) ?></em></p>
                <div class="submission-content"><?= $sub['content'] /* already sanitized on submit */ ?></div>

                <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button class="btn" type="submit">Approve</button>
                </form>
                <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="submission_id" value="<?= (int)$sub['id'] ?>">
                    <input type="hidden" name="action" value="reject">
                    <button class="btn" type="submit" style="background:#a33;">Reject</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Reported Comments</h3>
    <?php if (empty($pendingReports)): ?>
        <p>No pending reports.</p>
    <?php else: ?>
        <?php foreach ($pendingReports as $r): ?>
            <div class="submission-card" style="border:1px solid #ccc; border-radius:8px; padding:1rem; margin-bottom:1rem;">
                <p class="meta">
                    Comment by <a href="/@<?= e($r['commenter_username']) ?>">@<?= e($r['commenter_username']) ?></a>
                    on <a href="/article/<?= (int)$r['article_id'] ?>">article #<?= (int)$r['article_id'] ?></a>
                    &middot; reported by @<?= e($r['reporter_username']) ?>
                    &middot; <?= date('M j, Y g:i A', strtotime($r['reported_at'])) ?>
                </p>
                <p><?= e($r['content']) ?></p>
                <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                    <input type="hidden" name="comment_id" value="<?= (int)$r['comment_id'] ?>">
                    <input type="hidden" name="action" value="delete_comment">
                    <button class="btn" type="submit" style="background:#a33;" onclick="return confirm('Delete this comment? This also deletes its replies.');">Delete Comment</button>
                </form>
                <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                    <input type="hidden" name="action" value="dismiss_report">
                    <button class="btn secondary" type="submit">Dismiss</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Poll Results</h3>
    <?php if (empty($allPolls)): ?>
        <p>No polls yet.</p>
    <?php else: ?>
        <?php foreach ($allPolls as $p): ?>
            <div class="submission-card" style="border:1px solid #ccc; border-radius:8px; padding:1rem; margin-bottom:1rem;">
                <p><strong><?= e($p['question']) ?></strong> <span class="meta">(<?= $p['is_active'] ? 'active' : 'inactive' ?> &middot; <?= getPollVoterCount((int)$p['id']) ?> voters)</span></p>
                <?php foreach (getPollResults((int)$p['id']) as $opt): ?>
                    <p><?= e($opt['option_text']) ?>: <?= (int)$opt['votes'] ?></p>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Feedback</h3>
    <?php if (empty($allFeedback)): ?>
        <p>No feedback yet.</p>
    <?php else: ?>
        <?php foreach ($allFeedback as $f): ?>
            <div class="submission-card" style="border:1px solid #ccc; border-radius:8px; padding:1rem; margin-bottom:1rem;">
                <p class="meta"><?= $f['username'] ? '@' . e($f['username']) : 'Anonymous' ?> &middot; <?= e($f['created_at']) ?></p>
                <p><?= nl2br(e($f['message'])) ?></p>
                <?php if (!empty($f['image_url'])): ?>
                    <img src="<?= e($f['image_url']) ?>" alt="" style="max-width:280px;display:block;border-radius:6px;margin-bottom:0.5rem;">
                <?php endif; ?>
                <?php if (!empty($f['reply_message'])): ?>
                    <p style="opacity:0.8;"><strong>Reply<?= $f['replied_by_username'] ? ' by @' . e($f['replied_by_username']) : '' ?>:</strong> <?= nl2br(e($f['reply_message'])) ?></p>
                <?php else: ?>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reply_feedback">
                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                        <textarea name="reply_message" placeholder="Reply..." required style="width:100%;min-height:60px;"></textarea>
                        <button class="btn" type="submit">Send Reply</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <h3>Clear a User's Timeout</h3>
    <p>Clears strikes and lifts any active comment lock for the given username. Use this if someone got flagged unfairly.</p>
    <form method="post" style="display:flex; gap:0.5rem; max-width:400px;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reset_timeout">
        <input type="text" name="username" placeholder="Username" required style="flex:1;">
        <button type="submit" class="btn">Clear</button>
    </form>
</main>
</body>
</html>