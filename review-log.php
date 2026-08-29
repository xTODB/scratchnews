<?php
require_once __DIR__ . '/../functions.php';
startSession();

if (empty($_SESSION['is_admin']) && empty($_SESSION['is_moderator'])) {
    header('Location: /login');
    exit;
}
$isAdminUser = !empty($_SESSION['is_admin']);

$reviewed = getReviewedSubmissions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Review Log - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.review-log-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
.review-log-table th, .review-log-table td { text-align: left; padding: 0.6rem 0.7rem; border-bottom: 1px solid rgba(128,128,128,0.25); vertical-align: top; }
.review-log-table th { font-size: 0.8rem; text-transform: uppercase; opacity: 0.7; }
.review-log-status { font-size: 0.75rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 999px; display: inline-block; }
.review-log-status.approved { background: rgba(60,180,100,0.15); color: #2f9e56; }
.review-log-status.rejected { background: rgba(200,60,60,0.15); color: #c33; }
.review-log-reason { font-size: 0.85rem; opacity: 0.85; max-width: 320px; }
.review-log-unknown { opacity: 0.6; font-style: italic; }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php if ($isAdminUser) { require_once __DIR__ . '/nav.php'; } else { include __DIR__ . '/../includes/header.php'; } ?>
<main>
    <h2>Review Log</h2>
    <p class="meta">Who approved or rejected each submission, most recent first. Only submissions reviewed after this log was added have a reviewer on file.</p>

    <?php if (empty($reviewed)): ?>
        <p>No reviewed submissions yet.</p>
    <?php else: ?>
        <table class="review-log-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Submitter</th>
                    <th>Status</th>
                    <th>Reviewer</th>
                    <th>Reviewed At</th>
                    <th>Rejection Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviewed as $row): ?>
                    <tr>
                        <td><?= e($row['title']) ?></td>
                        <td><a href="/@<?= e($row['submitter_username']) ?>">@<?= e($row['submitter_username']) ?></a></td>
                        <td><span class="review-log-status <?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                        <td>
                            <?php if (!empty($row['reviewer_username'])): ?>
                                <a href="/@<?= e($row['reviewer_username']) ?>">@<?= e($row['reviewer_username']) ?></a>
                            <?php else: ?>
                                <span class="review-log-unknown">Unknown (reviewed before tracking)</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $row['reviewed_at'] ? date('M j, Y g:i A', strtotime($row['reviewed_at'])) : '—' ?></td>
                        <td class="review-log-reason"><?= $row['rejection_reason'] ? e($row['rejection_reason']) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>
</body>
</html>
