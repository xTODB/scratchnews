<?php
require_once __DIR__ . '/auth.php';

$includeIp = trim($_GET['include_ip'] ?? '');
$excludeIp = trim($_GET['exclude_ip'] ?? '');

$visits = getRecentVisits(200, $includeIp ?: null, $excludeIp ?: null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Dashboard - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Visitor Log</h2>
    <p>Showing the most recent 200 visits (auto-trimmed on each new visit).</p>
    <form method="get" style="display:flex; gap:1rem; align-items:flex-end; flex-wrap:wrap; margin-bottom:1.2rem;">
        <div>
            <label for="include_ip">Show only this IP</label>
            <input type="text" id="include_ip" name="include_ip" value="<?= e($includeIp) ?>" placeholder="e.g. 89.28.123.202">
        </div>
        <div>
            <label for="exclude_ip">Hide this IP</label>
            <input type="text" id="exclude_ip" name="exclude_ip" value="<?= e($excludeIp) ?>" placeholder="e.g. your own IP">
        </div>
        <button class="btn" type="submit" style="margin-top:0;">Filter</button>
        <?php if ($includeIp || $excludeIp): ?>
        <a href="/admin/visits" class="btn secondary" style="margin-top:0;">Clear</a>
        <?php endif; ?>
    </form>
    <table>
        <tr><th>Time</th><th>IP Address</th><th>Page</th><th>User Agent</th></tr>
        <?php foreach ($visits as $v): ?>
        <tr>
            <td><?= utcTimeTag($v['visited_at'], 'datetime') ?></td>
            <td><?= e($v['ip_address']) ?></td>
            <td><?= e($v['page']) ?></td>
            <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?= e($v['user_agent']) ?></td>
        </tr>
        <?php endforeach; ?>
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