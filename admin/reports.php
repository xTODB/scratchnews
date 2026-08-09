<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $reportId = (int)($_POST['report_id'] ?? 0);
    $commentId = (int)($_POST['comment_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($reportId > 0 && in_array($action, ['delete', 'dismiss'])) {
        if ($action === 'delete' && $commentId > 0) {
            adminDeleteComment($commentId);
        }
        resolveReport($reportId);
        $message = $action === 'delete' ? 'Comment deleted.' : 'Report dismissed.';
    }
}

$reports = getPendingReports();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Reports - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Reported Comments</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <?php if (empty($reports)): ?>
        <p>No pending reports.</p>
    <?php else: ?>
        <?php foreach ($reports as $r): ?>
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
                    <input type="hidden" name="action" value="delete">
                    <button class="btn" type="submit" style="background:#a33;" onclick="return confirm('Delete this comment? This also deletes its replies.');">Delete Comment</button>
                </form>
                <form method="post" style="display:inline;">
                    <?= csrfField() ?>
                    <input type="hidden" name="report_id" value="<?= (int)$r['report_id'] ?>">
                    <input type="hidden" name="action" value="dismiss">
                    <button class="btn secondary" type="submit">Dismiss</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>