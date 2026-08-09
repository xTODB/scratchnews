<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($submissionId > 0 && in_array($action, ['approve', 'reject'])) {
        if ($action === 'approve') {
            approveSubmission($submissionId);
            $message = 'Submission approved and published.';
        } else {
            rejectSubmission($submissionId);
            $message = 'Submission rejected.';
        }
    }
}

$pending = getPendingSubmissions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Pending Submissions - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=9">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Pending Submissions</h2>

    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>

    <?php if (empty($pending)): ?>
        <p>No pending submissions right now.</p>
    <?php else: ?>
        <?php foreach ($pending as $sub): ?>
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
</main>
</body>
</html>