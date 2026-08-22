<?php
require_once __DIR__ . '/../functions.php';
startSession();

if (empty($_SESSION['is_admin']) && empty($_SESSION['is_moderator'])) {
    header('Location: /login');
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $reviewerId = (int)($_SESSION['reader_id'] ?? 0);
    if ($action === 'approve') {
        approveGroupRequest($requestId, $reviewerId);
        $message = 'Request approved.';
    } elseif ($action === 'reject') {
        rejectGroupRequest($requestId, $reviewerId);
        $message = 'Request rejected.';
    }
}

$requests = getPendingGroupRequests();
$isAdminUser = !empty($_SESSION['is_admin']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Group Requests - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=24">
<style>
.group-req-row { border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
.group-req-row img { max-width: 240px; max-height: 100px; display: block; margin: 0.5rem 0; border-radius: 6px; }
.group-req-actions { display: flex; gap: 0.5rem; margin-top: 0.6rem; }
</style>
</head>
<body <?php include __DIR__ . '/../includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php if ($isAdminUser) { require_once __DIR__ . '/nav.php'; } else { include __DIR__ . '/../includes/header.php'; } ?>
<main>
    <h2>Group Requests <span style="font-size:0.75rem; opacity:0.7;">(Groups beta)</span></h2>
    <?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>
    <?php if (empty($requests)): ?>
        <p>No pending requests.</p>
    <?php endif; ?>
    <?php foreach ($requests as $r): ?>
        <div class="group-req-row">
            <strong><?= e(ucfirst($r['request_type'])) ?></strong> request from @<?= e($r['requester_username']) ?>
            <?php if ($r['name']): ?><p><strong>Name:</strong> <?= e($r['name']) ?></p><?php endif; ?>
            <?php if ($r['description']): ?><p><?= nl2br(e($r['description'])) ?></p><?php endif; ?>
            <?php if (!empty($r['banner_url'])): ?><img src="<?= e($r['banner_url']) ?>" alt=""><?php endif; ?>
            <div class="group-req-actions">
                <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><button class="btn" type="submit">Approve</button></form>
                <form method="post"><?= csrfField() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><button class="btn secondary" type="submit">Reject</button></form>
            </div>
        </div>
    <?php endforeach; ?>
</main>
</body>
</html>
