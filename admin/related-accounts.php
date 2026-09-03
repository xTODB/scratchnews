<?php
require_once __DIR__ . '/../functions.php';
startSession();

// Page access: Admin + Head Moderator (same gating as admin/contact.php).
// Raw IP addresses are DEV/ADMIN ONLY though - $isAdminUser gates the
// .ra-ip-tag output further down, so Head Mods see usernames/status but
// never the linking IPs.
if (empty($_SESSION['is_admin']) && empty($_SESSION['is_head_moderator'])) {
    header('Location: /login');
    exit;
}
$isAdminUser = !empty($_SESSION['is_admin']);

$searched = trim($_GET['username'] ?? '');
$result = null;
$notFound = false;
if ($searched !== '') {
    $result = getRelatedAccounts($searched);
    if ($result === null) $notFound = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Related Accounts - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<style>
.ra-search { display: flex; gap: 0.5rem; margin: 1rem 0 1.5rem; max-width: 420px; }
.ra-search input { flex: 1; padding: 0.5rem 0.7rem; border-radius: 6px; border: 1px solid rgba(128,128,128,0.35); }
.ra-root { padding: 0.9rem; border: 1px solid rgba(128,128,128,0.25); border-radius: 8px; margin-bottom: 1rem; }
.ra-row { padding: 0.7rem 0.9rem; border: 1px solid rgba(128,128,128,0.2); border-radius: 8px; margin-bottom: 0.6rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.4rem; }
.ra-row .ra-name { font-weight: 600; }
.ra-row .ra-tags { font-size: 0.78rem; opacity: 0.7; }
.ra-ip-tag { display: inline-block; padding: 0.1rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-family: monospace; background: rgba(232,163,61,0.15); margin-left: 0.3rem; }
.ra-banned { color: #e05555; }
.ra-empty { opacity: 0.7; font-style: italic; }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php if ($isAdminUser) { require_once __DIR__ . '/nav.php'; } else { include __DIR__ . '/../includes/header.php'; } ?>
<main>
    <h2>Related Accounts</h2>
    <p class="meta">Type a username to see every other account that has ever shared an IP with it (checked transitively across the whole network).</p>
    <form class="ra-search" method="get">
        <input type="text" name="username" placeholder="Username" value="<?= e($searched) ?>" required>
        <button class="btn" type="submit">Search</button>
    </form>

    <?php if ($notFound): ?>
        <p class="ra-empty">No user found with that username.</p>
    <?php elseif ($result): ?>
        <div class="ra-root">
            <span class="ra-name">@<?= e($result['root']['username']) ?></span>
            <?php if (!empty($result['root']['is_banned'])): ?><span class="ra-banned">(banned)</span><?php endif; ?>
            <div class="ra-tags">Searched account &middot; joined <?= utcTimeTag($result['root']['created_at']) ?></div>
        </div>

        <?php if (empty($result['network'])): ?>
            <p class="ra-empty">No other accounts share a login IP with this one.</p>
        <?php else: ?>
            <p class="meta"><?= count($result['network']) ?> related account<?= count($result['network']) === 1 ? '' : 's' ?> found.</p>
            <?php foreach ($result['network'] as $u): ?>
                <div class="ra-row">
                    <div>
                        <span class="ra-name">@<?= e($u['username']) ?></span>
                        <?php if (!empty($u['is_banned'])): ?><span class="ra-banned">(banned)</span><?php endif; ?>
                        <?php if (!empty($u['is_admin'])): ?><span class="ra-tags">admin</span><?php endif; ?>
                        <?php if (!empty($u['is_moderator'])): ?><span class="ra-tags">moderator</span><?php endif; ?>
                        <div class="ra-tags">joined <?= utcTimeTag($u['created_at']) ?></div>
                    </div>
                    <?php if ($isAdminUser): ?>
                    <div>
                        <?php foreach ($u['shared_ips'] as $ip): ?>
                            <span class="ra-ip-tag"><?= e($ip) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
