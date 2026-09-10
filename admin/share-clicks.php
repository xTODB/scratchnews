<?php
require_once __DIR__ . '/auth.php';

$sidFilter = trim($_GET['sid'] ?? '');
$ownerFilter = trim($_GET['owner'] ?? '');

$clicks = getShareClicksDetail(500, $sidFilter ?: null, $ownerFilter ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
<title>Dashboard - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Share Clicks</h2>
    <p>Every recorded click on a shared article link (most recent 500), and who shared it - if the Share ID has ever been claimed by a logged-in account.</p>
    <form method="get" style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap; margin-bottom:1.2rem;">
        <div>
            <label for="sid">Share ID</label>
            <input type="text" id="sid" name="sid" value="<?= e($sidFilter) ?>" placeholder="e.g. W6_fnw">
        </div>
        <div>
            <label for="owner">Shared by (username)</label>
            <input type="text" id="owner" name="owner" value="<?= e($ownerFilter) ?>" placeholder="e.g. Cookirde">
        </div>
        <button class="btn" type="submit" style="margin-top:0;">Filter</button>
        <?php if ($sidFilter || $ownerFilter): ?>
        <a href="/admin/share-clicks" class="btn secondary" style="margin-top:0;">Clear</a>
        <?php endif; ?>
    </form>
    <table>
        <tr><th>Time</th><th>Article</th><th>Share ID</th><th>Shared while</th><th>Shared by</th></tr>
        <?php foreach ($clicks as $c): ?>
        <tr>
            <td><?= utcTimeTag($c['created_at'], 'datetime') ?></td>
            <td><?= $c['article_id'] ? '<a href="/article/' . (int)$c['article_id'] . '">' . e($c['article_title'] ?? ('#' . $c['article_id'])) . '</a>' : '—' ?></td>
            <td><?= e($c['sid']) ?></td>
            <td><?= $c['from_account'] ? 'Logged in' : 'Guest' ?></td>
            <td><?= $c['owner_username'] ? '<a href="/@' . e($c['owner_username']) . '">@' . e($c['owner_username']) . '</a>' : '<em>Unclaimed / guest SID</em>' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$clicks): ?>
        <tr><td colspan="5"><em>No share clicks found<?= ($sidFilter || $ownerFilter) ? ' for this filter' : '' ?>.</em></td></tr>
        <?php endif; ?>
    </table>
</main>
<script>
document.querySelectorAll('time.local-date, time.local-datetime').forEach(function(el) {
    var d = new Date(el.getAttribute('datetime'));
    if (isNaN(d.getTime())) return;
    if (el.classList.contains('local-datetime')) {
        el.textContent = d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    } else {
        el.textContent = d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    }
});
</script>
</body>
</html>
