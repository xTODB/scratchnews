<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/auth.php';

$db = getDB();
$daily = $db->query("SELECT visit_date, COUNT(*) AS unique_visitors FROM daily_unique_visitors GROUP BY visit_date ORDER BY visit_date DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);

$totalUniqueIps = $db->query("SELECT COUNT(DISTINCT ip_address) AS c FROM daily_unique_visitors")->fetch_assoc()['c'];
$totalSignups = $db->query("SELECT COUNT(DISTINCT ip) AS c FROM signup_attempts WHERE successful = 1")->fetch_assoc()['c'];
$conversionRate = $totalUniqueIps > 0 ? round(($totalSignups / $totalUniqueIps) * 100, 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Stats - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Stats (Admin)</h2>
    <p><a href="/stats.php">View public stats page &rarr;</a></p>

    <p><strong>Overall conversion rate:</strong> <?= e($conversionRate) ?>%
        (<?= (int)$totalSignups ?> unique-IP signups / <?= (int)$totalUniqueIps ?> unique visitor IPs, all-time within retention window)</p>

    <h3 style="margin-top:2rem;">Visitor Map (last 90 days)</h3>
    <div id="visitorMap" style="height:400px; background:#222; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#888;">
        Map rendering — needs a follow-up session to wire up (Leaflet/simple SVG world map + click-into-region), have the lat/long data now.
    </div>

<h3 style="margin-top:2rem;">Collective Time</h3>
    <?php $ct = getCollectiveTimeStats(); ?>
    <p>All-time: <strong><?= number_format($ct['all_time_hours'], 1) ?> hours</strong>
        &nbsp;|&nbsp; Today: <strong><?= number_format($ct['today_hours'], 1) ?> hours</strong></p>

    <h3 style="margin-top:2rem;">Time on Site (last 7 days)</h3>
    <?php $tos = getTimeOnSiteStats(7); ?>
    <?php if ($tos['count'] > 0): ?>
        <p>Average: <?= (int)floor($tos['avg_seconds'] / 60) ?>m <?= (int)($tos['avg_seconds'] % 60) ?>s
            &nbsp;|&nbsp; Median: <?= (int)floor($tos['median_seconds'] / 60) ?>m <?= (int)($tos['median_seconds'] % 60) ?>s
            &nbsp;|&nbsp; Sessions counted: <?= (int)$tos['count'] ?></p>
    <?php else: ?>
        <p style="color:#888;">No session data yet — will populate as visitors browse with the heartbeat script live.</p>
    <?php endif; ?>
    <h3 style="margin-top:2rem;">Daily Unique Visitors (last 30 days)</h3>
    <table>
        <tr><th>Date</th><th>Unique Visitors</th></tr>
        <?php foreach ($daily as $d): ?>
            <tr><td><?= e($d['visit_date']) ?></td><td><?= (int)$d['unique_visitors'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</main>
</body>
</html>