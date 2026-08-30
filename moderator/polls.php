<?php
require_once __DIR__ . '/auth.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create_poll') {
        $question = trim($_POST['poll_question'] ?? '');
        $pollType = ($_POST['poll_type'] ?? 'single') === 'multi' ? 'multi' : 'single';
        $options = array_filter(array_map('trim', explode("\n", $_POST['poll_options'] ?? '')), fn($o) => $o !== '');
        $endsAtRaw = trim($_POST['poll_ends_at'] ?? '');
        $endsAt = $endsAtRaw !== '' ? str_replace('T', ' ', $endsAtRaw) . ':00' : null;
        if ($question === '' || count($options) < 2) {
            $message = 'A poll question and at least 2 options are required.';
        } else {
            createPoll($question, $pollType, $options, 0, (int)($_SESSION['reader_id'] ?? 0) ?: null, $endsAt);
            $message = 'Poll created.';
        }
    }
}

$allPolls = getAllPolls();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Polls - Moderator - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Create Poll</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <form method="post" style="margin-bottom:1.5rem;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_poll">
        <p><input type="text" name="poll_question" placeholder="Poll question" style="width:100%;" required></p>
        <p><textarea name="poll_options" placeholder="One option per line (at least 2)" rows="4" style="width:100%;" required></textarea></p>
        <p>
            <label><input type="radio" name="poll_type" value="single" checked> Single choice</label>
            &nbsp;&nbsp;
            <label><input type="radio" name="poll_type" value="multi"> Multiple choice</label>
        </p>
        <p><label>Ends at (optional - leave blank to run indefinitely)</label><br>
        <input type="datetime-local" name="poll_ends_at"></p>
        <button class="btn" type="submit">Create Poll</button>
    </form>

    <h2>Poll Results</h2>
    <?php if (empty($allPolls)): ?>
        <p>No polls yet.</p>
    <?php else: ?>
        <?php foreach ($allPolls as $p): $expired = isPollExpired($p); ?>
            <div class="submission-card" style="border:1px solid #ccc; border-radius:8px; padding:1rem; margin-bottom:1rem;">
                <p><strong><?= e($p['question']) ?></strong> <span class="meta">(<?= $p['is_active'] ? 'active' : 'inactive' ?><?= $expired ? ' &middot; ended' : '' ?> &middot; <?= getPollVoterCount((int)$p['id']) ?> voters &middot; by <?= e($p['creator_username'] ?? 'Unknown') ?>)</span></p>
                <?php foreach (getPollResults((int)$p['id']) as $opt): ?>
                    <p><?= e($opt['option_text']) ?>: <?= (int)$opt['votes'] ?></p>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>