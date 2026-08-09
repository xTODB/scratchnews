<?php
require_once __DIR__ . '/functions.php';
sendNoCacheHeaders();
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}

$user = getUserById($_SESSION['reader_id']);
if (!$user) {
    header('Location: /login');
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_dark_mode') {
        $enabled = !empty($_POST['dark_mode']);
        setDarkModePreference($user['id'], $enabled);
        $_SESSION['dark_mode'] = $enabled ? 1 : 0;
        $user['dark_mode'] = $enabled ? 1 : 0;
        $message = 'Theme preference saved.';
    } elseif ($action === 'change_username') {
        $result = changeUsername($user['id'], trim($_POST['new_username'] ?? ''));
        if ($result === 'ok') {
            $user = getUserById($user['id']);
            $_SESSION['reader_username'] = $user['username'];
            $message = 'Username updated.';
        } elseif ($result === 'duplicate') {
            $message = 'That username is already taken.';
        } elseif ($result === 'too_soon') {
            $message = 'You can only change your username once every 7 days.';
        } elseif ($result === 'unchanged') {
            $message = 'That\'s already your username.';
        } else {
            $message = 'Usernames must be 3-20 characters (letters, numbers, underscores only).';
        }
    }
}

$activeTab = $_GET['tab'] ?? 'general';
if ($activeTab === 'appearance') $activeTab = 'customization';
if (!in_array($activeTab, ['general', 'customization', 'security'], true)) $activeTab = 'general';
$usernameCooldown = canChangeUsername($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Settings - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=10">
<style>
.settings-layout { display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap; }
.settings-tabs { display: flex; flex-direction: column; gap: 0.5rem; min-width: 180px; }
.settings-tab { display: block; padding: 0.7rem 1rem; border-radius: 6px; text-decoration: none; color: inherit; border: 1px solid rgba(128,128,128,0.3); font-weight: 600; }
.settings-tab.active { background: #e8a33d; color: #2a2a2a; border-color: #e8a33d; }
.settings-panel { flex: 1; min-width: 280px; }
.settings-row { display: flex; align-items: center; justify-content: space-between; padding: 0.9rem 0; border-bottom: 1px solid rgba(128,128,128,0.2); }
form.settings-row { background: transparent !important; padding: 0.9rem 0 !important; border-radius: 0 !important; box-shadow: none !important; max-width: none !important; margin: 0 !important; }
.settings-row:last-child { border-bottom: none; }
.settings-label { font-weight: 600; }
.settings-sub { font-size: 0.85rem; opacity: 0.75; margin-top: 0.2rem; }
.verified-badge { color: #2a8a4a; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; }
.unverified-badge { color: #a33; font-weight: 600; }
.settings-row .btn { margin-top: 0; width: auto; height: auto; padding: 0.55rem 1.2rem; background: #ff8c1a; }
.lock-icon { width: 16px; height: 16px; flex-shrink: 0; }
.settings-row .btn, .settings-tab, button { -webkit-tap-highlight-color: transparent; }
.settings-row .btn:focus, .settings-tab:focus { outline: 2px solid #e8a33d; outline-offset: 2px; }
.username-form-row { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
.username-form-row input[type=text] { flex: 1; min-width: 160px; }
.username-cooldown-note { font-size: 0.8rem; opacity: 0.7; margin-top: 0.4rem; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Settings</h2>
    <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
    <div class="settings-layout">
        <div class="settings-tabs">
            <a href="/settings?tab=general" class="settings-tab <?= $activeTab === 'general' ? 'active' : '' ?>">General</a>
            <a href="/settings?tab=customization" class="settings-tab <?= $activeTab === 'customization' ? 'active' : '' ?>">Customization</a>
            <a href="/settings?tab=security" class="settings-tab <?= $activeTab === 'security' ? 'active' : '' ?>">Security</a>
        </div>

        <div class="settings-panel">
            <?php if ($activeTab === 'general'): ?>
                <div class="settings-row">
                    <div>
                        <div class="settings-label">@<?= e($user['username']) ?></div>
                        <div class="settings-sub"><?= e($user['email']) ?></div>
                    </div>
                    <a href="/@<?= e($user['username']) ?>" class="btn secondary">View Profile</a>
                </div>
                <p class="settings-sub" style="margin-top:1rem;">More general settings are coming soon.</p>

            <?php elseif ($activeTab === 'customization'): ?>
                <form method="post" class="settings-row" style="border:none;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_dark_mode">
                    <div>
                        <div class="settings-label">Dark Mode</div>
                        <div class="settings-sub">Switch between light and dark theme.</div>
                    </div>
                    <button type="submit" name="dark_mode" value="<?= !empty($user['dark_mode']) ? '0' : '1' ?>" class="btn">
                        <?= !empty($user['dark_mode']) ? 'Turn Off' : 'Turn On' ?>
                    </button>
                </form>
                <div class="settings-row" style="display:block;">
                    <div>
                        <div class="settings-label">Username</div>
                        <div class="settings-sub">Change your @username. Limited to once every 7 days.</div>
                    </div>
                    <form method="post" class="username-form-row" style="margin-top:0.6rem;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_username">
                        <input type="text" name="new_username" value="<?= e($user['username']) ?>" minlength="3" maxlength="20" pattern="[A-Za-z0-9_]+" <?= $usernameCooldown['allowed'] ? '' : 'disabled' ?> required>
                        <button type="submit" class="btn" <?= $usernameCooldown['allowed'] ? '' : 'disabled' ?>>Change Username</button>
                    </form>
                    <?php if (!$usernameCooldown['allowed']): ?>
                        <div class="username-cooldown-note">You can change your username again on <?= e($usernameCooldown['next_at']) ?>.</div>
                    <?php endif; ?>
                </div>
                <div class="settings-row">
                    <div>
                        <div class="settings-label">Avatar, Banner &amp; Bio</div>
                        <div class="settings-sub">Manage your profile customization from your profile page.</div>
                    </div>
                    <a href="/@<?= e($user['username']) ?>" class="btn secondary">Profile</a>
                </div>

            <?php elseif ($activeTab === 'security'): ?>
                <div class="settings-row">
                    <div>
                        <div class="settings-label">Account Verification</div>
                        <div class="settings-sub">
                            <?php if (!empty($user['scratch_verified_at'])): ?>
                                Verified via your Scratch account<?= !empty($user['scratch_username']) ? ' (@' . e($user['scratch_username']) . ')' : '' ?>
                            <?php elseif (!empty($user['phone_verified_at'])): ?>
                                Verified via phone
                            <?php elseif (!empty($user['email_verified'])): ?>
                                Verified
                            <?php else: ?>
                                Not verified yet
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (isUserVerified($user)): ?>
                        <span class="verified-badge">
                            <svg class="lock-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <rect x="5" y="11" width="14" height="10" rx="2" fill="currentColor"/>
                                <path d="M8 11V7a4 4 0 0 1 8 0v4" stroke="currentColor" stroke-width="2" fill="none"/>
                            </svg>
                            Verified
                        </span>
                    <?php endif; ?>
                </div>
                <p class="settings-sub" style="margin-top:1rem;">More security settings are coming soon.</p>
            <?php endif; ?>
        </div>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
document.addEventListener('click', function(e) {
    var menu = document.getElementById('userMenu');
    if (menu && !e.target.closest('.user-nav')) menu.classList.remove('open');
});
</script>
</body>
</html>