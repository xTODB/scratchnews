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
$generatedWordList = null;

$scratchTargetUser = getApiSetting('scratch_verify_target_user', '');
$scratchProjectId = getApiSetting('scratch_verify_project_id', '');
if (!isUserVerified($user) && empty($_SESSION['scratch_verify_code_settings'])) {
    $_SESSION['scratch_verify_code_settings'] = generateScratchVerifyCode();
}
$scratchVerifyCode = $_SESSION['scratch_verify_code_settings'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_dark_mode') {
        $enabled = !empty($_POST['dark_mode']);
        setDarkModePreference($user['id'], $enabled);
        $_SESSION['dark_mode'] = $enabled ? 1 : 0;
        $user['dark_mode'] = $enabled ? 1 : 0;
        $message = 'Dark mode preference saved.';
    } elseif ($action === 'update_theme') {
        $theme = trim($_POST['color_theme'] ?? 'default');
        if (!array_key_exists($theme, colorThemeOptions())) $theme = 'default';
        setColorThemePreference($user['id'], $theme);
        $_SESSION['color_theme'] = $theme;
        $user['color_theme'] = $theme;
        $message = 'Color theme saved.';
    } elseif ($action === 'update_autosave') {
        $enabled = !empty($_POST['autosave_enabled']);
        $interval = (int)($_POST['autosave_interval'] ?? 30);
        if (!in_array($interval, [0, 15, 30, 60, 120, 300], true)) $interval = 30;
        setAutosavePreference($user['id'], $enabled, $interval);
        $user['autosave_enabled'] = $enabled ? 1 : 0;
        $user['autosave_interval'] = $interval;
        $message = 'Auto-save preference saved.';
    } elseif ($action === 'toggle_group_activity_notifs') {
        $enabled = !empty($_POST['group_activity_notifs']);
        setGroupActivityNotifsPreference($user['id'], $enabled);
        $user['group_activity_notifs'] = $enabled ? 1 : 0;
        $message = 'Group activity notification preference saved.';
    } elseif ($action === 'toggle_autocolor_links') {
        $enabled = !empty($_POST['autocolor_links']);
        setAutocolorLinksPreference($user['id'], $enabled);
        $user['autocolor_links'] = $enabled ? 1 : 0;
        $message = 'Auto-color links preference saved.';
    } elseif ($action === 'update_translate') {
        $lang = trim($_POST['translate_lang'] ?? '');
        if (!array_key_exists($lang, translateLanguageOptions())) $lang = '';
        setTranslatePreference($user['id'], $lang);
        $_SESSION['translate_lang'] = $lang;
        $user['translate_lang'] = $lang !== '' ? $lang : null;
        $message = $lang !== '' ? 'Translation preference saved.' : 'Translation turned off.';
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
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $googleOnlyPassword = !empty($user['google_id']);
        if (strlen($new) < 6) {
            $message = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $message = 'New passwords do not match.';
        } elseif (!$googleOnlyPassword && !password_verify($current, $user['password_hash'])) {
            $message = 'Current password is incorrect.';
        } else {
            changePassword($user['id'], $new);
            $message = 'Password updated.';
        }
    } elseif ($action === 'generate_word_list') {
        $generatedWordList = generateWordList($user['id']);
        $user = getUserById($user['id']);
        $message = 'New word list generated: save it now, it will not be shown again.';
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
<link rel="stylesheet" href="/assets/style.css?v=21">
<style>
.settings-layout { display: flex; gap: 2rem; align-items: flex-start; flex-wrap: wrap; }
.settings-tabs { display: flex; flex-direction: column; gap: 0.5rem; min-width: 180px; }
.settings-tab { display: block; padding: 0.7rem 1rem; border-radius: 6px; text-decoration: none; color: inherit; border: 1px solid rgba(128,128,128,0.3); font-weight: 600; }
.settings-tab.active { background: var(--brand-bright); color: #2a2a2a; border-color: var(--brand-bright); }
.settings-panel { flex: 1; min-width: 280px; }
.settings-row { display: flex; align-items: center; justify-content: space-between; padding: 0.9rem 0; border-bottom: 1px solid rgba(128,128,128,0.2); }
form.settings-row { background: transparent !important; padding: 0.9rem 0 !important; border-radius: 0 !important; box-shadow: none !important; max-width: none !important; margin: 0 !important; }
.settings-row:last-child { border-bottom: none; }
.settings-label { font-weight: 600; }
.settings-sub { font-size: 0.85rem; opacity: 0.75; margin-top: 0.2rem; }
.verified-badge { color: #2a8a4a; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; }
.unverified-badge { color: #a33; font-weight: 600; }
.settings-row .btn { margin-top: 0; width: auto; height: auto; padding: 0.55rem 1.2rem; }
.lock-icon { width: 16px; height: 16px; flex-shrink: 0; }
.settings-row .btn, .settings-tab, button { -webkit-tap-highlight-color: transparent; }
.settings-row .btn:focus, .settings-tab:focus { outline: 2px solid var(--brand-bright); outline-offset: 2px; }
.username-form-row { display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; }
.username-form-row input[type=text] { flex: 1; min-width: 160px; }
.username-cooldown-note { font-size: 0.8rem; opacity: 0.7; margin-top: 0.4rem; }
.theme-swatch-grid { display: flex; flex-wrap: wrap; gap: 0.9rem; }
.theme-swatch { display: flex; flex-direction: column; align-items: center; gap: 0.4rem; cursor: pointer; width: 76px; }
.theme-swatch input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }
.theme-swatch-preview { width: 52px; height: 52px; border-radius: 50%; border: 3px solid transparent; box-shadow: 0 1px 4px rgba(0,0,0,0.25); transition: border-color 0.15s, transform 0.15s; }
.theme-swatch.selected .theme-swatch-preview { border-color: var(--ink); transform: scale(1.08); }
body.dark .theme-swatch.selected .theme-swatch-preview { border-color: #fff; }
.theme-swatch-label { font-size: 0.78rem; text-align: center; opacity: 0.85; }
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
                <form method="post" class="settings-row" style="border:none;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_group_activity_notifs">
                    <div>
                        <div class="settings-label">Group Activity Notifications</div>
                        <div class="settings-sub">Get notified when someone joins, gets promoted, or comments in a group you're in.</div>
                    </div>
                    <button type="submit" name="group_activity_notifs" value="<?= !empty($user['group_activity_notifs']) ? '0' : '1' ?>" class="btn">
                        <?= !empty($user['group_activity_notifs']) ? 'Turn Off' : 'Turn On' ?>
                    </button>
                </form>
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
                        <div class="settings-label">Color Theme</div>
                        <div class="settings-sub">Pick an accent color, used for buttons, links, and gradients across the site. Works with Dark Mode either way.</div>
                    </div>
                    <form method="post" style="margin-top:0.75rem;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_theme">
                        <div class="theme-swatch-grid">
                            <?php $currentTheme = $user['color_theme'] ?? 'default'; ?>
                            <?php foreach (colorThemeOptions() as $key => $t): ?>
                                <label class="theme-swatch <?= $currentTheme === $key ? 'selected' : '' ?>">
                                    <input type="radio" name="color_theme" value="<?= e($key) ?>" <?= $currentTheme === $key ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="theme-swatch-preview" style="background:linear-gradient(135deg, <?= e($t['bright']) ?>, <?= e($t['brand']) ?>);"></span>
                                    <span class="theme-swatch-label"><?= e($t['label']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
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
                <div class="settings-row" style="display:block;">
                    <div>
                        <div class="settings-label">Auto-Save</div>
                        <div class="settings-sub">Automatically save your article as a draft while you write, in the editor's toolbar.</div>
                    </div>
                    <form method="post" style="margin-top:0.6rem; display:flex; gap:0.8rem; align-items:center; flex-wrap:wrap;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_autosave">
                        <label style="display:flex; align-items:center; gap:0.4rem; font-weight:normal;">
                            <input type="checkbox" name="autosave_enabled" value="1" <?= !empty($user['autosave_enabled']) ? 'checked' : '' ?>> Enabled
                        </label>
                        <select name="autosave_interval">
                            <?php $currentInterval = (int)($user['autosave_interval'] ?? 30); ?>
                            <option value="15" <?= $currentInterval === 15 ? 'selected' : '' ?>>Every 15 seconds</option>
                            <option value="30" <?= $currentInterval === 30 ? 'selected' : '' ?>>Every 30 seconds</option>
                            <option value="60" <?= $currentInterval === 60 ? 'selected' : '' ?>>Every 1 minute</option>
                            <option value="120" <?= $currentInterval === 120 ? 'selected' : '' ?>>Every 2 minutes</option>
                            <option value="300" <?= $currentInterval === 300 ? 'selected' : '' ?>>Every 5 minutes</option>
                            <option value="0" <?= $currentInterval === 0 ? 'selected' : '' ?>>When you stop typing</option>
                        </select>
                        <button type="submit" class="btn">Save</button>
                    </form>
                </div>
                <form method="post" class="settings-row" style="border:none;">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="toggle_autocolor_links">
                    <div>
                        <div class="settings-label">Auto-Color Links</div>
                        <div class="settings-sub">Automatically color linked text blue in the article editor, without coloring it manually.</div>
                    </div>
                    <button type="submit" name="autocolor_links" value="<?= !empty($user['autocolor_links']) ? '0' : '1' ?>" class="btn">
                        <?= !empty($user['autocolor_links']) ? 'Turn Off' : 'Turn On' ?>
                    </button>
                </form>
                <div class="settings-row" style="display:block;">
                    <div>
                        <div class="settings-label">Translate Articles</div>
                        <div class="settings-sub">Automatically translate article titles and content into your preferred language.</div>
                    </div>
                    <form method="post" style="margin-top:0.6rem; display:flex; gap:0.8rem; align-items:center; flex-wrap:wrap;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="update_translate">
                        <select name="translate_lang">
                            <option value="" <?= empty($user['translate_lang']) ? 'selected' : '' ?>>Off (English)</option>
                            <?php foreach (translateLanguageOptions() as $code => $label): ?>
                                <option value="<?= e($code) ?>" <?= ($user['translate_lang'] ?? '') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn">Save</button>
                    </form>
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
                <?php if (!isUserVerified($user)): ?>
                <div style="margin-top:1.5rem;">
                    <h3 style="margin-bottom:0.75rem;">Verify your account with Scratch</h3>
                    <?= csrfField() ?>
                    <div class="verify-row">
                        <span class="verify-num">1</span>
                        <div class="verify-body">
                            Follow <strong>@<?= e($scratchTargetUser) ?></strong> on Scratch
                            <div class="verify-code-row">
                                <a class="btn secondary" href="https://scratch.mit.edu/users/<?= rawurlencode($scratchTargetUser) ?>/" target="_blank" rel="noopener">Visit Scratch profile</a>
                            </div>
                        </div>
                    </div>
                    <div class="verify-row">
                        <span class="verify-num">2</span>
                        <div class="verify-body">
                            Comment your code on the verification project
                            <div class="verify-code-row">
                                <a class="btn secondary" href="https://scratch.mit.edu/projects/<?= rawurlencode($scratchProjectId) ?>/" target="_blank" rel="noopener">Visit Scratch project</a>
                            </div>
                            <div class="verify-code-row">
                                <span class="verify-code" id="settingsVerifyCode"><?= e($scratchVerifyCode) ?></span>
                                <button type="button" class="verify-copy-btn" id="settingsVerifyCopyBtn">Copy</button>
                            </div>
                        </div>
                    </div>
                    <div class="verify-row">
                        <span class="verify-num">3</span>
                        <div class="verify-body">
                            Click "Verify"
                            <div class="verify-code-row">
                                <button type="button" class="btn" id="settingsVerifyBtn">Verify</button>
                            </div>
                            <div class="verify-status" id="settingsVerifyStatus"></div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div style="margin-top:1.5rem;">
                    <h3 style="margin-bottom:0.75rem;">Change Password</h3>
                    <?php if (!empty($user['google_id'])): ?>
                        <p class="settings-sub">Your account uses Google Sign-In. Setting a password here also lets you log in with a username and password.</p>
                    <?php endif; ?>
                    <form method="post">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_password">
                        <?php if (empty($user['google_id'])): ?>
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                        <?php endif; ?>
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        <button class="btn" type="submit">Update Password</button>
                    </form>
                </div>
                <div style="margin-top:1.5rem;">
                    <h3 style="margin-bottom:0.75rem;">Word List Backup Login</h3>
                    <p class="settings-sub">
                        A word list is a random set of words you can use to log in instead of your password.
                        <?php if (!empty($user['word_list_generated_at'])): ?>
                            You last generated one on <?= utcTimeTag($user['word_list_generated_at']) ?>. Generating a new one replaces it.
                        <?php else: ?>
                            You haven't generated one yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($generatedWordList): ?>
                        <div class="verify-code-row" style="margin:0.75rem 0;">
                            <span class="verify-code" id="settingsWordList"><?= e($generatedWordList) ?></span>
                            <button type="button" class="verify-copy-btn" id="settingsWordListCopyBtn">Copy</button>
                        </div>
                        <a class="btn secondary" href="data:text/plain;charset=utf-8,<?= rawurlencode("ScratchNews word list for @" . $user['username'] . "\nGenerated " . date('Y-m-d H:i') . " UTC\nKeep this private - anyone with these words can log into your account.\n\n" . $generatedWordList . "\n") ?>" download="scratchnews-wordlist-<?= e($user['username']) ?>.txt">Download as .txt</a>
                        <p class="settings-sub" style="margin-top:0.5rem;">This is only shown once. Save it somewhere safe now.</p>
                    <?php endif; ?>
                    <form method="post" style="margin-top:0.75rem;" id="wordListGenForm">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="generate_word_list">
                        <button class="btn secondary" type="submit"><?= !empty($user['word_list_hash']) ? 'Generate New Word List' : 'Generate Word List' ?></button>
                    </form>
                </div>
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
(function() {
    var copyBtn = document.getElementById('settingsVerifyCopyBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            var code = document.getElementById('settingsVerifyCode').textContent;
            navigator.clipboard.writeText(code).then(function() {
                copyBtn.textContent = 'Copied!';
                setTimeout(function() { copyBtn.textContent = 'Copy'; }, 1500);
            });
        });
    }
    var wlCopyBtn = document.getElementById('settingsWordListCopyBtn');
    if (wlCopyBtn) {
        wlCopyBtn.addEventListener('click', function() {
            var words = document.getElementById('settingsWordList').textContent;
            navigator.clipboard.writeText(words).then(function() {
                wlCopyBtn.textContent = 'Copied!';
                setTimeout(function() { wlCopyBtn.textContent = 'Copy'; }, 1500);
            });
        });
    }
    var wlGenForm = document.getElementById('wordListGenForm');
    if (wlGenForm && <?= !empty($user['word_list_hash']) ? 'true' : 'false' ?>) {
        wlGenForm.addEventListener('submit', function(e) {
            if (!confirm('This replaces your existing word list. The old one will stop working. Continue?')) e.preventDefault();
        });
    }
    var verifyBtn = document.getElementById('settingsVerifyBtn');
    if (verifyBtn) {
        verifyBtn.addEventListener('click', function() {
            var status = document.getElementById('settingsVerifyStatus');
            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Checking...';
            status.className = 'verify-status';
            status.textContent = '';

            var formData = new URLSearchParams();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch('/verify-scratch-account.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify';
                if (data.success) {
                    status.className = 'verify-status success';
                    status.textContent = 'Verified as @' + data.username + '! Refreshing...';
                    setTimeout(function() { location.reload(); }, 1200);
                } else {
                    status.className = 'verify-status error';
                    status.textContent = (data.error || 'Verification failed. Please try again.') + (data.debug ? ' [' + data.debug + ']' : '');
                }
            })
            .catch(function() {
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify';
                status.className = 'verify-status error';
                status.textContent = 'Something went wrong. Please try again.';
            });
        });
    }
})();
</script>
</body>
</html>
