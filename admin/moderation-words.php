<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        addModerationWord($_POST['category'] ?? '', $_POST['word'] ?? '');
    } elseif ($action === 'remove') {
        removeModerationWord((int)($_POST['id'] ?? 0));
    } elseif ($action === 'reset_strikes') {
        $target = getUserByUsername(trim($_POST['username'] ?? ''));
        if ($target) resetModerationStrikes((int)$target['id']);
    }
    header('Location: /admin/moderation-words');
    exit;
}

$words = getModerationWords();
$grouped = [];
foreach (MODERATION_CATEGORIES as $cat) $grouped[$cat] = [];
foreach ($words as $w) $grouped[$w['category']][] = $w;

$categoryLabels = [
    'profanity' => 'Profanity & Slurs',
    'sexual' => 'Sexual Content',
    'violence_selfharm' => 'Violence & Self-Harm',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Moderation Words - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=2">
<style>
.mod-category { margin-bottom: 2rem; }
.mod-word-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 0.75rem 0; }
.mod-word-chip { display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(128,128,128,0.15); border-radius: 999px; padding: 0.3rem 0.4rem 0.3rem 0.8rem; font-size: 0.85rem; }
.mod-word-chip button {
    background: rgba(0,0,0,0.15); border: none; border-radius: 50%;
    color: inherit; opacity: 0.7; cursor: pointer;
    width: 18px; height: 18px; min-width: 18px;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 0.75rem; line-height: 1; padding: 0;
}
.mod-word-chip button:hover { opacity: 1; color: #c33; }
.mod-add-row { display: flex; gap: 0.5rem; }
.mod-add-row input { flex: 1; }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Moderation Words</h2>
    <p>Words here are checked against every comment (article and profile). Editing takes effect immediately, no deploy needed.</p>

    <?php foreach (MODERATION_CATEGORIES as $cat): ?>
        <div class="mod-category">
            <h3><?= e($categoryLabels[$cat] ?? $cat) ?></h3>
            <div class="mod-word-list">
                <?php foreach ($grouped[$cat] as $w): ?>
                    <span class="mod-word-chip">
                        <?= e($w['word']) ?>
                        <form method="post" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                            <button type="submit" title="Remove">&times;</button>
                        </form>
                    </span>
                <?php endforeach; ?>
                <?php if (empty($grouped[$cat])): ?><span style="opacity:0.6;">No words yet.</span><?php endif; ?>
            </div>
            <form method="post" class="mod-add-row">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="category" value="<?= e($cat) ?>">
                <input type="text" name="word" placeholder="Add a word or phrase..." required>
                <button type="submit" class="btn">Add</button>
            </form>
        </div>
    <?php endforeach; ?>

    <p style="opacity:0.7;font-size:0.85rem;">Email/phone/scam-link detection is handled separately by fixed patterns and isn't editable here.</p>

    <div class="mod-category">
        <h3>Reset a User's Timeout</h3>
        <p>Clears strikes and lifts any active comment lock for the given username. Use this if someone got flagged unfairly, e.g. during testing.</p>
        <form method="post" class="mod-add-row">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="reset_strikes">
            <input type="text" name="username" placeholder="Username" required>
            <button type="submit" class="btn">Reset</button>
        </form>
    </div>
</main>
</body>
</html>