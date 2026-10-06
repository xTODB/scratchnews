<?php
require_once __DIR__ . '/../functions.php';
startSession();

// Same audience as Contact Us: Head Moderators + the dev. Deciding is further
// restricted by MOD_APP_DECISION_ADMIN_ONLY (see includes/mod-app-functions.php).
if (empty($_SESSION['is_admin']) && empty($_SESSION['is_head_moderator'])) {
    header('Location: /login');
    exit;
}
$isAdminUser = !empty($_SESSION['is_admin']);
$canDecide = $isAdminUser || !MOD_APP_DECISION_ADMIN_ONLY;

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    if (!$canDecide) {
        $message = 'Only the dev can accept or decline applications.';
    } else {
        $id = (int)($_POST['id'] ?? 0);
        $action = $_POST['action'] ?? '';
        $note = trim($_POST['note'] ?? '');
        $reviewerId = (int)($_SESSION['reader_id'] ?? 0);
        if ($id > 0 && ($action === 'accept' || $action === 'decline')) {
            $res = decideModApplication($id, $reviewerId, $action === 'accept' ? 'accepted' : 'declined', $note);
            $message = $res['ok'] ? ($action === 'accept' ? 'Accepted. They are now a Moderator.' : 'Declined.') : $res['reason'];
        }
    }
}

$pending = getPendingModApplications();
$recent = getRecentDecidedModApplications(20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/../includes/favicon.php'; ?>
<title>Moderator Applications - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=24">
<style>
.modapp-row { border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
.modapp-q { opacity: 0.7; font-size: 0.8rem; margin: 0.7rem 0 0.15rem 0; font-weight: 600; }
.modapp-a { margin: 0; }
.modapp-meta { opacity: 0.65; font-size: 0.8rem; margin: 0.3rem 0; }
.modapp-actions { margin-top: 0.8rem; }
.modapp-actions textarea { width: 100%; min-height: 45px; }
.modapp-actions .row { display: flex; gap: 0.5rem; margin-top: 0.4rem; }
.modapp-done { opacity: 0.85; padding: 0.4rem 0; border-bottom: 1px solid rgba(128,128,128,0.2); }
</style>
</head>
<body <?php include __DIR__ . '/../includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php if ($isAdminUser) { require_once __DIR__ . '/nav.php'; } else { include __DIR__ . '/../includes/header.php'; } ?>
<main>
    <h2>Moderator Applications</h2>
    <?php if ($message): ?><div class="alert"><?= e($message) ?></div><?php endif; ?>
    <?php if (!$canDecide): ?><p class="modapp-meta">You can read applications. Only the dev can accept or decline them.</p><?php endif; ?>

    <?php if (empty($pending)): ?>
        <p>No pending applications.</p>
    <?php endif; ?>
    <?php foreach ($pending as $a): ?>
        <div class="modapp-row">
            <strong><a href="/@<?= e($a['username']) ?>">@<?= e($a['username']) ?></a></strong>
            <div class="modapp-meta">Joined <?= utcTimeTag($a['user_created_at']) ?> &middot; Applied <?= utcTimeTag($a['created_at']) ?> &middot; Active: <?= e($a['availability']) ?></div>
            <div class="modapp-q">Why they want to moderate</div>
            <p class="modapp-a"><?= nl2br(e($a['why_text'])) ?></p>
            <div class="modapp-q">Experience</div>
            <p class="modapp-a"><?= nl2br(e($a['experience_text'])) ?></p>
            <div class="modapp-q">Borderline reported comment</div>
            <p class="modapp-a"><?= nl2br(e($a['scenario_text'])) ?></p>
            <?php if ($canDecide): ?>
            <form method="post" class="modapp-actions">
                <?= csrfField() ?>
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <textarea name="note" placeholder="Optional message to the applicant (they will see it on their application page)"></textarea>
                <div class="row">
                    <button class="btn" type="submit" name="action" value="accept">Accept</button>
                    <button class="btn secondary" type="submit" name="action" value="decline">Decline</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($recent)): ?>
        <h3>Recent decisions</h3>
        <?php foreach ($recent as $r): ?>
            <div class="modapp-done">
                @<?= e($r['username']) ?> - <strong><?= $r['status'] === 'accepted' ? 'Accepted' : 'Declined' ?></strong>
                <?= !empty($r['reviewer_username']) ? 'by @' . e($r['reviewer_username']) : '' ?>
                <?= !empty($r['reviewed_at']) ? '&middot; ' . utcTimeTag($r['reviewed_at']) : '' ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>
