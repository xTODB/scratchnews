<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}
$userId = (int)$_SESSION['reader_id'];

$error = '';
$submitted = false;
$old = ['why' => '', 'experience' => '', 'scenario' => '', 'availability' => '', 'agree' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $old['why'] = trim($_POST['why'] ?? '');
    $old['experience'] = trim($_POST['experience'] ?? '');
    $old['scenario'] = trim($_POST['scenario'] ?? '');
    $old['availability'] = trim($_POST['availability'] ?? '');
    $old['agree'] = !empty($_POST['agree']) ? '1' : '';

    $elig = getModApplicationEligibility($userId);
    if (!$elig['ok']) {
        $error = $elig['reason'];
    } elseif (isFormRateLimited('modapp', $ip)) {
        $error = "You're submitting too quickly - please wait a bit and try again.";
    } elseif (mb_strlen($old['why']) < 30 || mb_strlen($old['scenario']) < 30 || mb_strlen($old['experience']) < 2) {
        $error = 'Please answer every question. The "why" and scenario answers need at least a couple of sentences.';
    } elseif (mb_strlen($old['why']) > 1500 || mb_strlen($old['experience']) > 1500 || mb_strlen($old['scenario']) > 1500) {
        $error = 'Each answer can be at most 1500 characters.';
    } elseif (!in_array($old['availability'], MOD_APP_AVAILABILITY, true)) {
        $error = 'Please pick how often you can be active.';
    } elseif ($old['agree'] !== '1') {
        $error = 'You need to confirm you have read the Moderator Guidelines.';
    } else {
        submitModApplication($userId, $old['why'], $old['experience'], $old['scenario'], $old['availability']);
        recordFormSubmission('modapp', $ip);
        $submitted = true;
    }
}

$elig = getModApplicationEligibility($userId);
$latest = getLatestModApplicationForUser($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/includes/favicon.php'; ?>
<title>Moderator Application - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.modapp-status { border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 0.9rem 1rem; margin-bottom: 1rem; }
.modapp-status.accepted { border-color: #0a7d1f; }
.modapp-status.declined { border-color: #a30000; }
.modapp-status.pending { border-color: #e8a33d; }
.modapp-note { opacity: 0.85; margin-top: 0.4rem; padding: 0.5rem 0.7rem; border-radius: 6px; background: rgba(128,128,128,0.08); }
.modapp-form label { display: block; margin-top: 1rem; font-weight: 600; }
.modapp-form .hint { font-weight: 400; opacity: 0.7; font-size: 0.85rem; }
.modapp-form textarea { width: 100%; min-height: 90px; }
.modapp-form .modapp-check { display: flex; gap: 0.5rem; align-items: flex-start; font-weight: 400; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Moderator Application</h2>

    <?php if ($submitted): ?>
        <div class="alert success">Application sent! The team will review it and you'll get a notification when there is a decision.</div>
    <?php else: ?>
        <p>Moderators help keep ScratchNews safe: reviewing submissions, reports, feedback, and group requests. Read the <a href="/moderator-guidelines.php">Moderator Guidelines</a> first. Please do not include personal information (real name, school, contact details) in your answers.</p>

        <?php if ($latest): ?>
            <div class="modapp-status <?= e($latest['status']) ?>">
                <strong>Your latest application:</strong>
                <?= $latest['status'] === 'pending' ? 'Waiting for review' : ($latest['status'] === 'accepted' ? 'Accepted' : 'Declined') ?>
                (sent <?= utcTimeTag($latest['created_at']) ?>)
                <?php if (!empty($latest['review_note']) && $latest['status'] !== 'pending'): ?>
                    <div class="modapp-note"><?= nl2br(e($latest['review_note'])) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <?php if (!$elig['ok']): ?>
            <?php if (!$error): ?><div class="alert"><?= e($elig['reason']) ?></div><?php endif; ?>
        <?php else: ?>
        <form method="post" class="modapp-form">
            <?= csrfField() ?>
            <label for="why">Why do you want to be a moderator? <span class="hint">(a few sentences)</span></label>
            <textarea name="why" id="why" maxlength="1500" required><?= e($old['why']) ?></textarea>

            <label for="experience">Any experience that helps? <span class="hint">(moderating, helping on Scratch or other communities, ScratchNews contributions - "none yet" is fine)</span></label>
            <textarea name="experience" id="experience" maxlength="1500" required><?= e($old['experience']) ?></textarea>

            <label for="scenario">A comment isn't clearly against the rules, but several readers reported it. What do you do? <span class="hint">(a few sentences)</span></label>
            <textarea name="scenario" id="scenario" maxlength="1500" required><?= e($old['scenario']) ?></textarea>

            <label for="availability">How often can you be active?</label>
            <select name="availability" id="availability" required>
                <option value="">Choose...</option>
                <?php foreach (MOD_APP_AVAILABILITY as $opt): ?>
                    <option value="<?= e($opt) ?>" <?= $old['availability'] === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="modapp-check"><input type="checkbox" name="agree" value="1" <?= $old['agree'] === '1' ? 'checked' : '' ?>> I have read the Moderator Guidelines and will follow them.</label>

            <br>
            <button class="btn" type="submit">Submit Application</button>
        </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
