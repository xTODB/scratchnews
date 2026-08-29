<?php
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/auth.php';

$days = 30;

$db = getDB();
$daily = $db->query("SELECT visit_date, COUNT(*) AS unique_visitors FROM daily_unique_visitors GROUP BY visit_date ORDER BY visit_date DESC LIMIT 30")->fetch_all(MYSQLI_ASSOC);
$dailyVisitorsChart = getDailyCounts('daily_unique_visitors', 'visit_date', $days);

$totalUniqueIps = $db->query("SELECT COUNT(DISTINCT ip_address) AS c FROM daily_unique_visitors")->fetch_assoc()['c'];
$totalSignups = $db->query("SELECT COUNT(DISTINCT ip) AS c FROM signup_attempts WHERE successful = 1")->fetch_assoc()['c'];
$conversionRate = $totalUniqueIps > 0 ? round(($totalSignups / $totalUniqueIps) * 100, 2) : 0;
$conversionRateChart = getDailyConversionRate($days);

$ct = getCollectiveTimeStats();
$collectiveTimeChart = getDailyCollectiveTimeHours($days);

$tos = getTimeOnSiteStats(7);
$bounce = getBounceRate(7);
$pageTime = getTimePerPageStats(7);
$landingPages = getLandingPageBreakdown(7);
$sourceBounce = getBounceRateBySource(7);

$engagementSeries = [
    'Article views' => getDailyArticleViewCounts($days),
    'Likes' => getDailyCounts('likes', 'DATE(created_at)', $days),
    'Comments' => getDailyCounts('comments', 'DATE(created_at)', $days),
    'Shared-link clicks' => getDailyCounts('share_clicks', 'DATE(created_at)', $days),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Stats - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=22">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Stats (Admin)</h2>
    <p><a href="/stats.php">View public stats page &rarr;</a></p>

    <div class="stat-cards">
        <div class="stat-card"><p class="stat-card-label">Conversion rate (all-time)</p><p class="stat-card-value"><?= e($conversionRate) ?>%</p></div>
        <div class="stat-card"><p class="stat-card-label">Collective time (all-time)</p><p class="stat-card-value"><?= number_format($ct['all_time_hours'], 1) ?>h</p></div>
        <div class="stat-card"><p class="stat-card-label">Collective time (today)</p><p class="stat-card-value"><?= number_format($ct['today_hours'], 1) ?>h</p></div>
    </div>
    <p style="opacity:0.75;"><?= (int)$totalSignups ?> unique-IP signups / <?= (int)$totalUniqueIps ?> unique visitor IPs (all-time within retention window)</p>

    <div class="chart-block">
        <h4>Conversion Rate (last <?= $days ?> days)</h4>
        <p class="chart-caption">Daily signups &divide; daily unique visitors, as a %. Today (lighter) is a partial day.</p>
        <?= renderLineChartSvg($conversionRateChart, ['color' => '#00b368', 'highlight_last' => true]) ?>
    </div>

    <div class="chart-block">
        <h4>Daily Unique Visitors (last <?= $days ?> days)</h4>
        <p class="chart-caption">Today's bar is lighter since it doesn't have a full day of data yet.</p>
        <?= renderBarChartSvg($dailyVisitorsChart, ['highlight_last' => true]) ?>
    </div>

    <div class="chart-block">
        <h4>Collective Time on Site (last <?= $days ?> days)</h4>
        <p class="chart-caption">Total hours across all visitor sessions per day. Today (lighter) is partial.</p>
        <?= renderBarChartSvg($collectiveTimeChart, ['color' => '#8000ff', 'highlight_color' => '#c99cff', 'highlight_last' => true]) ?>
    </div>

    <h3 style="margin-top:0.5rem;">Time on Site (last 7 days)</h3>
    <?php if ($tos['count'] > 0): ?>
        <p>Average: <?= (int)floor($tos['avg_seconds'] / 60) ?>m <?= (int)($tos['avg_seconds'] % 60) ?>s
            &nbsp;|&nbsp; Median: <?= (int)floor($tos['median_seconds'] / 60) ?>m <?= (int)($tos['median_seconds'] % 60) ?>s
            &nbsp;|&nbsp; Sessions counted: <?= (int)$tos['count'] ?></p>
    <?php else: ?>
        <p style="color:#888;">No session data yet — will populate as visitors browse with the heartbeat script live.</p>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem;">Bounce Rate (last 7 days)</h3>
    <p class="chart-caption">A "bounce" = a session that only ever registered one heartbeat (left within ~15s of arriving). Can't see visitors who leave before the first heartbeat fires at all.</p>
    <?php if ($bounce['total'] > 0): ?>
        <p><strong><?= $bounce['rate'] ?>%</strong> bounced (<?= $bounce['bounced'] ?> of <?= $bounce['total'] ?> sessions)</p>
    <?php else: ?>
        <p style="color:#888;">No session data yet.</p>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem;">Time per Page (last 7 days)</h3>
    <p class="chart-caption">Total time visitors spent on each URL, summed across every visit to it (not just landings).</p>
    <?php if ($pageTime): ?>
        <table style="width:100%; border-collapse:collapse;">
            <tr><th style="text-align:left;">Page</th><th style="text-align:right;">Total time</th></tr>
            <?php foreach ($pageTime as $p): ?>
                <tr>
                    <td><?= e($p['page']) ?></td>
                    <td style="text-align:right;"><?= (int)floor($p['total_seconds'] / 60) ?>m <?= (int)($p['total_seconds'] % 60) ?>s</td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p style="color:#888;">No per-page data yet — will populate as visitors browse with the updated heartbeat script live.</p>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem;">Bounce Rate by Landing Page (last 7 days)</h3>
    <p class="chart-caption">Only counts sessions that started AFTER this feature went live — existing sessions have no recorded landing page yet.</p>
    <?php if ($landingPages): ?>
        <table style="width:100%; border-collapse:collapse;">
            <tr><th style="text-align:left;">Landing page</th><th style="text-align:right;">Sessions</th><th style="text-align:right;">Bounce rate</th></tr>
            <?php foreach ($landingPages as $lp): ?>
                <tr>
                    <td><?= e($lp['landing_page']) ?></td>
                    <td style="text-align:right;"><?= $lp['sessions'] ?></td>
                    <td style="text-align:right;"><?= $lp['bounce_rate'] ?>%</td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p style="color:#888;">No landing-page data yet — will populate as new sessions start with this feature live.</p>
    <?php endif; ?>

    <h3 style="margin-top:1.5rem;">Bounce Rate by Traffic Source (last 7 days)</h3>
    <p class="chart-caption">Grouped by the site's <code>?src=</code> campaign param (Discord, YouTube, etc.) - sessions with no param show as "direct".</p>
    <?php if ($sourceBounce): ?>
        <table style="width:100%; border-collapse:collapse;">
            <tr><th style="text-align:left;">Source</th><th style="text-align:right;">Sessions</th><th style="text-align:right;">Bounce rate</th></tr>
            <?php foreach ($sourceBounce as $sb): ?>
                <tr>
                    <td><?= e($sb['source']) ?></td>
                    <td style="text-align:right;"><?= $sb['sessions'] ?></td>
                    <td style="text-align:right;"><?= $sb['bounce_rate'] ?>%</td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p style="color:#888;">No session data yet.</p>
    <?php endif; ?>

    <div class="chart-block">
        <h4>Engagement (last <?= $days ?> days)</h4>
        <p class="chart-caption">Article views, likes, comments, and shared-link clicks per day. View history starts from when this table was added — earlier totals aren't retroactive.</p>
        <?= renderMultiLineChartSvg($engagementSeries, ['height' => 240]) ?>
    </div>

    <h3 style="margin-top:1.5rem;">Daily Unique Visitors (last 30 days, table)</h3>
    <table>
        <tr><th>Date</th><th>Unique Visitors</th></tr>
        <?php foreach ($daily as $d): ?>
            <tr><td><?= e($d['visit_date']) ?></td><td><?= (int)$d['unique_visitors'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</main>
</body>
</html>