<?php
require_once __DIR__ . '/moderator/auth.php';

$pendingSubmissionsCount = getPendingSubmissionsCount();
$pendingReportsCount = getPendingReportsCount();
$pendingFeedbackCount = getPendingFeedbackCount();
$pendingGroupRequestsCount = getPendingGroupRequestsCount();
$activePollCount = count(array_filter(getAllPolls(), fn($p) => !empty($p['is_active'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Moderator Panel - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.mod-tile-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-top: 1rem; }
.mod-tile { border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 1rem; text-decoration: none; color: inherit; display: block; position: relative; }
.mod-tile:hover { border-color: rgba(128,128,128,0.6); }
.mod-tile h3 { margin: 0 0 0.3rem 0; }
.mod-tile p { margin: 0; opacity: 0.75; font-size: 0.9rem; }
.mod-tile .admin-nav-badge { position: absolute; top: 0.8rem; right: 0.8rem; }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/moderator/nav.php'; ?>
<main>
    <h2>Moderator Panel</h2>
    <p class="meta">Overview - pick a page below. <?= $isHeadModerator ? 'You have Head Moderator access.' : '' ?></p>

    <div class="mod-tile-grid">
        <a class="mod-tile" href="/admin/submissions">
            <h3>Submissions</h3>
            <p>Review and approve pending article submissions.</p>
            <?php if ($pendingSubmissionsCount > 0): ?><span class="admin-nav-badge"><?= $pendingSubmissionsCount ?></span><?php endif; ?>
        </a>
        <a class="mod-tile" href="/moderator/reports">
            <h3>Reported Comments</h3>
            <p>Handle comments flagged by readers.</p>
            <?php if ($pendingReportsCount > 0): ?><span class="admin-nav-badge"><?= $pendingReportsCount ?></span><?php endif; ?>
        </a>
        <a class="mod-tile" href="/admin/group-requests">
            <h3>Group Requests</h3>
            <p>Approve or reject new group and rename requests.</p>
            <?php if ($pendingGroupRequestsCount > 0): ?><span class="admin-nav-badge"><?= $pendingGroupRequestsCount ?></span><?php endif; ?>
        </a>
        <a class="mod-tile" href="/admin/feedback">
            <h3>Feedback</h3>
            <p>Reply to site feedback from readers.</p>
            <?php if ($pendingFeedbackCount > 0): ?><span class="admin-nav-badge"><?= $pendingFeedbackCount ?></span><?php endif; ?>
        </a>
        <a class="mod-tile" href="/moderator/polls">
            <h3>Polls</h3>
            <p>Create polls and view results (<?= $activePollCount ?> active).</p>
        </a>
        <a class="mod-tile" href="/admin/review-log">
            <h3>Review Log</h3>
            <p>See who approved or rejected each submission.</p>
        </a>
        <a class="mod-tile" href="/moderator/clear-timeout">
            <h3>Clear Timeout</h3>
            <p>Lift a user's comment lock and clear their strikes.</p>
        </a>
        <a class="mod-tile" href="/moderator-guidelines.php">
            <h3>Guidelines</h3>
            <p>The rules moderators are expected to follow.</p>
        </a>
        <?php if ($isHeadModerator): ?>
        <a class="mod-tile" href="/moderator/users">
            <h3>Users</h3>
            <p>Read-only user list (no email, IP, or account actions).</p>
        </a>
        <a class="mod-tile" href="/admin/stats">
            <h3>Admin Stats</h3>
            <p>Site-wide traffic and engagement stats.</p>
        </a>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
