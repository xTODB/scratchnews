<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $question = trim($_POST['question'] ?? '');
        $pollType = ($_POST['poll_type'] ?? 'single') === 'multi' ? 'multi' : 'single';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $options = array_filter(array_map('trim', explode("\n", $_POST['options'] ?? '')), fn($o) => $o !== '');
        if ($question === '' || count($options) < 2) {
            $error = 'A question and at least 2 options are required.';
        } else {
            createPoll($question, $pollType, $options, $sortOrder);
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        if (getPollById($id)) {
            $question = trim($_POST['question'] ?? '');
            $pollType = ($_POST['poll_type'] ?? 'single') === 'multi' ? 'multi' : 'single';
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = !empty($_POST['is_active']);
            updatePoll($id, $question, $pollType, $sortOrder, $isActive);
        }
    } elseif ($action === 'delete') {
        deletePoll((int)($_POST['id'] ?? 0));
    }

    if ($error === '') {
        header('Location: /admin/polls');
        exit;
    }
}

$polls = getAllPolls();
foreach ($polls as &$p) { $p['options'] = getPollOptions((int)$p['id']); }
unset($p);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Polls - Admin - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=24">
<style>
.poll-admin-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
.poll-admin-row { border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 1rem; display: flex; flex-direction: column; gap: 0.4rem; }
.poll-admin-row input[type="text"], .poll-admin-row input[type="number"], .poll-admin-row select, .poll-admin-row textarea { width: 100%; }
.poll-admin-row-actions { display: flex; gap: 0.5rem; align-items: center; }
.poll-form-new { border: 1px dashed rgba(128,128,128,0.4); border-radius: 8px; padding: 1rem; margin-bottom: 2rem; }
.poll-admin-options { font-size: 0.85rem; opacity: 0.8; }
</style>
</head>
<body <?php include __DIR__ . '/../includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Polls</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <p>Poll options can't be edited after creation - delete and recreate the poll if the options need to change. Weight works the same as banner priority weight: they share one pool, higher number = shown more often. Vote results are on the <a href="/moderator/polls">Moderator Panel's Polls page</a>.</p>

    <div class="poll-admin-list">
        <?php foreach ($polls as $p): ?>
            <form method="post" class="poll-admin-row">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <label>Question</label>
                <input type="text" name="question" value="<?= e($p['question']) ?>" required>
                <label>Type</label>
                <select name="poll_type">
                    <option value="single" <?= $p['poll_type'] === 'single' ? 'selected' : '' ?>>Single choice</option>
                    <option value="multi" <?= $p['poll_type'] === 'multi' ? 'selected' : '' ?>>Multiple choice</option>
                </select>
                <p class="poll-admin-options">Options: <?= e(implode(', ', array_column($p['options'], 'option_text'))) ?></p>
                <label>Weight (higher = shown more often, shares a pool with banners)</label>
                <input type="number" name="sort_order" value="<?= (int)$p['sort_order'] ?>">
                <label><input type="checkbox" name="is_active" <?= $p['is_active'] ? 'checked' : '' ?>> Active</label>
                <div class="poll-admin-row-actions">
                    <button class="btn" type="submit">Save</button>
                </div>
            </form>
            <form method="post" onsubmit="return confirm('Delete this poll? This also deletes its votes.');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn secondary" type="submit" style="margin-top:-1rem; margin-bottom:0.5rem;">Delete Poll #<?= (int)$p['id'] ?></button>
            </form>
        <?php endforeach; ?>
        <?php if (empty($polls)): ?><p>No polls yet.</p><?php endif; ?>
    </div>

    <h3>Add a Poll</h3>
    <form method="post" class="poll-form-new">
        <input type="hidden" name="action" value="create">
        <label>Question</label>
        <input type="text" name="question" required>
        <label>Type</label>
        <select name="poll_type">
            <option value="single">Single choice</option>
            <option value="multi">Multiple choice</option>
        </select>
        <label>Options (one per line, at least 2)</label>
        <textarea name="options" rows="4" required></textarea>
        <label>Weight (higher = shown more often, shares a pool with banners)</label>
        <input type="number" name="sort_order" value="0">
        <button class="btn" type="submit">Add Poll</button>
    </form>
</main>
</body>
</html>
