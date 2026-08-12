<?php
require_once __DIR__ . '/functions.php';
startSession();

if (!empty($_SESSION['reader_id'])) { header('Location: /'); exit; }

$scratchTargetUser = getApiSetting('scratch_verify_target_user', '');
$scratchProjectId = getApiSetting('scratch_verify_project_id', '');
$registerIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$isSuspiciousIp = isSuspiciousIp($registerIp);
$detectedCallingCode = $isSuspiciousIp ? getCountryCallingCode($registerIp) : '+';

if (!$isSuspiciousIp && empty($_SESSION['scratch_verify_code'])) {
    $_SESSION['scratch_verify_code'] = generateScratchVerifyCode();
}
$scratchVerifyCode = $_SESSION['scratch_verify_code'] ?? '';

// A verified-but-not-yet-created Google identity, stashed by google-auth.php when
// someone signs up with Google for the first time. They still have to complete the
// same Scratch-follow or phone verification below before the account is created.
$googlePending = $_SESSION['google_pending'] ?? null;
$suggestedUsername = $googlePending ? suggestUsernameFromName($googlePending['name'] ?? '') : '';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $ip = $registerIp;
    $username = trim($_POST['username'] ?? '');
    $email = $googlePending && ($googlePending['email'] ?? '') !== '' ? $googlePending['email'] : null;
    $password = $googlePending ? bin2hex(random_bytes(16)) : ($_POST['password'] ?? '');
    $honeypot = trim($_POST['website'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    if (mb_strlen($bio) > 500) $bio = mb_substr($bio, 0, 500);
    $darkMode = !empty($_POST['dark_mode']);
    $scratchVerifiedUsername = $isSuspiciousIp ? null : ($_SESSION['scratch_verified_username'] ?? null);
    $phoneNumber = $isSuspiciousIp ? trim($_POST['phone_number'] ?? '') : null;
    if ($phoneNumber === '') $phoneNumber = null;

    if ($honeypot !== '') {
        header('Location: /?justregistered=1');
        exit;
    } elseif (tooManySignupAttempts($ip)) {
        $error = 'Too many signup attempts from your network. Please try again later.';
    } elseif ($username === '' || (!$googlePending && $password === '')) {
        $error = 'Username and password are required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $error = 'Username must be 3-20 characters and can only contain letters, numbers, and underscores.';
    } elseif (!$googlePending && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($isSuspiciousIp && ($phoneNumber === null || !preg_match('/^\+[1-9]\d{6,14}$/', $phoneNumber))) {
        $error = 'Please enter a valid phone number including country code (e.g. +12345678900).';
    } elseif ($isSuspiciousIp && isPhoneNumberLinked($phoneNumber)) {
        $error = 'That phone number is already linked to another ScratchNews account.';
    } elseif (!$isSuspiciousIp && $scratchVerifiedUsername === null) {
        $error = 'Please complete Scratch verification before finishing your account.';
    } else {
        $result = createUser($username, $email, $password, $scratchVerifiedUsername, $phoneNumber);
        if ($result === 'duplicate') {
            $error = 'That username, linked Scratch account, or phone number is already taken.';
            logSignupAttempt($ip, false);
        } else {
            logSignupAttempt($ip, true);

            $avatarUrl = null;
            $bannerUrl = null;
            try {
                if (!empty($_FILES['avatar']['tmp_name'])) $avatarUrl = saveUploadedImage($_FILES['avatar'], 'avatars');
                if (!empty($_FILES['banner']['tmp_name'])) $bannerUrl = saveUploadedImage($_FILES['banner'], 'banners');
            } catch (RuntimeException $e) {
                // Non-fatal: account still gets created without the image.
            }
            if ($avatarUrl !== null || $bannerUrl !== null || $bio !== '') {
                updateUserProfile($result, $avatarUrl, $bannerUrl, $bio);
            }

            setDarkModePreference($result, $darkMode);
            updateUserIp($result, $ip);

            unset($_SESSION['scratch_verify_code'], $_SESSION['scratch_verified_username']);

            if ($googlePending) {
                linkGoogleIdToUser($result, $googlePending['google_id']);
                unset($_SESSION['google_pending']);
            }

            $_SESSION['reader_id'] = $result;
            $_SESSION['reader_username'] = $username;
            $_SESSION['is_admin'] = false;
            $_SESSION['is_moderator'] = false;
            $_SESSION['dark_mode'] = $darkMode;
            $_SESSION['translate_lang'] = '';
            header('Location: /?justregistered=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Sign Up - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=21">
<style>
.google-signin-row { display:flex; justify-content:center; margin-bottom:1rem; }
.wizard-or-divider { text-align:center; color:#888; margin:0.75rem 0; font-size:0.85rem; }
.wizard-card { max-width:640px; margin:0 auto; }
.wizard-progress-row { display:flex; align-items:center; gap:0.75rem; margin:0.5rem 0 1.25rem; }
.wizard-progress-track { flex:1; height:10px; border-radius:5px; background:#ccc; overflow:hidden; }
.wizard-progress-fill { height:100%; background:#e8a33d; border-radius:5px; transition:width 0.25s ease; }
.wizard-progress-label { font-weight:bold; white-space:nowrap; }
.wizard-step { visibility: hidden; height: 0; overflow: hidden; }
.wizard-step.active { visibility: visible; height: auto; overflow: visible; }
.wizard-nav-row { display:flex; justify-content:flex-end; gap:0.6rem; margin-top:1.25rem; }
.wizard-nav-row .btn.secondary { margin-right:auto; }
.wizard-step2-row { display:flex; gap:1.25rem; align-items:flex-start; flex-wrap:wrap; }
.wizard-avatar-upload { width:110px; height:110px; border-radius:50%; border:2px dashed #999; background:#eee no-repeat center/cover; display:flex; align-items:center; justify-content:center; cursor:pointer; flex-shrink:0; color:#666; }
.wizard-avatar-upload svg { width:28px; height:28px; fill:currentColor; }
.wizard-banner-upload { width:100%; max-width:420px; height:110px; border-radius:8px; border:2px dashed #999; background:#eee no-repeat center/contain; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#666; margin-top:0.5rem; }
.wizard-banner-upload svg { width:26px; height:26px; fill:currentColor; }
.wizard-step2-fields { flex:1; min-width:220px; }
.wizard-darkmode-toggle { display:inline-flex; align-items:center; gap:0.4rem; background:none; border:1px solid #999; border-radius:20px; padding:0.3rem 0.8rem; cursor:pointer; color:inherit; margin-top:0.75rem; }
.wizard-darkmode-toggle svg { width:16px; height:16px; fill:currentColor; }
.verify-row { background:#f2f2f2; border-radius:8px; padding:0.85rem 1rem; margin-bottom:0.75rem; display:flex; align-items:flex-start; gap:0.75rem; }
body.dark .verify-row { background:#2a2a2a; }
.verify-num { font-size:1.4rem; font-weight:bold; color:#e8a33d; min-width:1.6rem; }
.verify-body { flex:1; }
.verify-code-row { display:flex; align-items:center; gap:0.5rem; margin-top:0.4rem; }
.verify-code { font-family:monospace; font-size:1rem; background:#e0e0e0; border-radius:5px; padding:0.25rem 0.6rem; }
body.dark .verify-code { background:#444; }
.verify-copy-btn { border:1px solid #999; background:none; border-radius:5px; padding:0.2rem 0.6rem; cursor:pointer; color:inherit; font-size:0.85rem; }
.verify-status { margin-top:0.6rem; font-size:0.9rem; }
.verify-status.success { color:#2e8b2e; }
.verify-status.error { color:#c0392b; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<!-- debug: detected_ip=<?= e($registerIp) ?> flagged_as_suspicious=<?= $isSuspiciousIp ? 'yes' : 'no' ?> -->
<header>
    <a href="/" class="logo-link">
<svg viewBox="0,0,136.90609,31.33279" class="logo-svg" xmlns="http://www.w3.org/2000/svg"><g transform="translate(-172.33195,-164.3336)"><g stroke-miterlimit="10"><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="#ffaa33" stroke-width="3" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="none" stroke-width="1" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><path d="M181.04509,195.64879h-8.71313v-10.6397h8.71313z" fill="#cc8829" stroke="none"/><path d="M176.88045,195.6664v-20.46677l3.90302,-0.07587l0.09189,-5.0222h6.64479v20.71239l-3.91923,0.16783l-0.04923,4.68462z" fill="#ffaa33" stroke="none"/><path d="M201.40189,164.35122h8.71313v10.6397h-8.71313z" fill="#cc8829" stroke="none"/><path d="M205.56653,164.33361v20.46677l-3.90302,0.07587l-0.09189,5.0222h-6.64479v-20.71239l3.91923,-0.16783l0.04923,-4.68462z" fill="#ffaa33" stroke="none"/><path d="M190.06459,189.91166l-0.03808,-3.62362l-3.03158,-0.12982v-16.02128h5.13983l0.07108,3.88473l3.01904,0.05869v15.8313z" fill="#ffaa33" stroke="none"/></g></g></svg>
    </a>
    <nav><a href="/login">Log In</a></nav>
</header>
<main class="wizard-card">
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <div class="wizard-progress-row">
        <div class="wizard-progress-track"><div class="wizard-progress-fill" id="wizardFill" style="width:33%"></div></div>
        <span class="wizard-progress-label" id="wizardLabel">1/3</span>
    </div>

    <form method="post" enctype="multipart/form-data" id="wizardForm">
        <?= csrfField() ?>
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label for="website">Leave this field blank</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <input type="hidden" name="dark_mode" id="darkModeField" value="0">

        <section class="wizard-step active" data-step="1">
            <h2>Create an account in 3 steps</h2>
            <?php if ($googlePending): ?>
            <div class="alert success">Signed in with Google<?= $googlePending['email'] ? ' as ' . e($googlePending['email']) : '' ?>. Pick a username, then finish verification below to create your account.</div>
            <?php else: ?>
            <div class="google-signin-row">
                <div id="g_id_onload"
                     data-client_id="<?= e(GOOGLE_CLIENT_ID) ?>"
                     data-callback="handleGoogleCredential"
                     data-auto_prompt="false"></div>
                <div class="g_id_signin" data-type="standard" data-size="large" data-width="300"></div>
            </div>
            <p class="wizard-or-divider">— or sign up with a username and password —</p>
            <?php endif; ?>
            <h3>Step 1: Basics</h3>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? $suggestedUsername) ?>" required>
            <div id="passwordField" <?= $googlePending ? 'style="display:none;"' : '' ?>>
                <label for="password">Password</label>
                <input type="password" id="password" name="password" <?= $googlePending ? '' : 'required minlength="6"' ?>>
            </div>
            <div class="wizard-nav-row">
                <button type="button" class="btn" data-next>Next</button>
            </div>
        </section>

        <section class="wizard-step" data-step="2">
            <h3>Step 2: Customization</h3>
            <div class="wizard-step2-row">
                <label class="wizard-avatar-upload" id="avatarUploadBox" title="Upload a profile picture">
                    <svg viewBox="0 0 24 24"><path d="M5 20h14a1 1 0 001-1v-9a1 1 0 00-1-1h-3.17l-1.24-1.86A1 1 0 0013.76 6h-3.52a1 1 0 00-.83.44L8.17 9H5a1 1 0 00-1 1v9a1 1 0 001 1zm7-3a4 4 0 110-8 4 4 0 010 8z"/></svg>
                    <input type="file" name="avatar" id="avatarInput" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" style="display:none;">
                </label>
                <div class="wizard-step2-fields">
                    <label for="bio">Description (optional)</label>
                    <textarea id="bio" name="bio" rows="3" maxlength="500" placeholder="Say something about yourself..."></textarea>
                    <label class="wizard-banner-upload" id="bannerUploadBox" title="Upload a banner">
                        <svg viewBox="0 0 24 24"><path d="M5 20h14a1 1 0 001-1v-9a1 1 0 00-1-1h-3.17l-1.24-1.86A1 1 0 0013.76 6h-3.52a1 1 0 00-.83.44L8.17 9H5a1 1 0 00-1 1v9a1 1 0 001 1zm7-3a4 4 0 110-8 4 4 0 010 8z"/></svg>
                        <input type="file" name="banner" id="bannerInput" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" style="display:none;">
                    </label>
                    <button type="button" class="wizard-darkmode-toggle" id="darkModeToggle">
                        <svg id="darkModeIcon" viewBox="0 0 24 24"><path d="M20.7 14.9A8.5 8.5 0 019.1 3.3a1 1 0 00-1.2-1.3 10 10 0 1013.9 13.9 1 1 0 00-1.1-1z"/></svg>
                        <span id="darkModeLabel">Dark mode: off</span>
                    </button>
                </div>
            </div>
            <div class="wizard-nav-row">
                <button type="button" class="btn secondary" data-prev>Previous</button>
                <button type="button" class="btn" data-next>Next</button>
            </div>
        </section>

        <?php if ($isSuspiciousIp): ?>
        <section class="wizard-step" data-step="3">
            <h3>Verify your ScratchNews account using your phone number</h3>
            <div class="verify-row">
                <span class="verify-num">1</span>
                <div class="verify-body">
                    Enter your phone number (with country code)
                    <div class="verify-code-row">
                        <input type="tel" id="phoneNumber" name="phone_number" placeholder="+12345678900" value="<?= e($detectedCallingCode) ?>" style="max-width:220px;" required>
                    </div>
                </div>
            </div>
            <p class="wizard-or-divider" style="text-align:left; margin:0.5rem 0 0;">
                Your account will be verified instantly using this number.
            </p>
            <div class="wizard-nav-row">
                <button type="button" class="btn secondary" data-prev>Previous</button>
                <button type="submit" class="btn" id="finishBtn">Finish</button>
            </div>
        </section>
        <?php else: ?>
        <section class="wizard-step" data-step="3">
            <h3>Verify your ScratchNews account using Scratch</h3>
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
                        <span class="verify-code" id="verifyCode"><?= e($scratchVerifyCode) ?></span>
                        <button type="button" class="verify-copy-btn" id="verifyCopyBtn">Copy</button>
                    </div>
                </div>
            </div>
            <div class="verify-row">
                <span class="verify-num">3</span>
                <div class="verify-body">
                    Click "Verify"
                    <div class="verify-code-row">
                        <button type="button" class="btn" id="verifyBtn">Verify</button>
                    </div>
                    <div class="verify-status" id="verifyStatus"></div>
                </div>
            </div>
            <div class="wizard-nav-row">
                <button type="button" class="btn secondary" data-prev>Previous</button>
                <button type="submit" class="btn" id="finishBtn" disabled>Finish</button>
            </div>
        </section>
        <?php endif; ?>
    </form>
</main>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
function handleGoogleCredential(response) {
    fetch('/google-auth', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'credential=' + encodeURIComponent(response.credential)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.redirect) { window.location.href = data.redirect; }
        else if (data.newSignup) { window.location.reload(); }
        else { alert(data.error || 'Google sign-in failed.'); }
    })
    .catch(function() { alert('Google sign-in failed. Please try again.'); });
}
</script>
<script>
(function() {
    var googlePending = <?= $googlePending ? 'true' : 'false' ?>;
    var steps = Array.prototype.slice.call(document.querySelectorAll('.wizard-step'));
    var fill = document.getElementById('wizardFill');
    var label = document.getElementById('wizardLabel');
    var totalSteps = steps.length;
    var current = 1;

    function showStep(n) {
        steps.forEach(function(s) { s.classList.toggle('active', parseInt(s.dataset.step, 10) === n); });
        fill.style.width = (n / totalSteps * 100) + '%';
        label.textContent = n + '/' + totalSteps;
        current = n;
    }

    function validateStep1() {
        var username = document.getElementById('username');
        var password = document.getElementById('password');
        if (!/^[A-Za-z0-9_]{3,20}$/.test(username.value)) { alert('Username must be 3-20 characters (letters, numbers, underscores only).'); return false; }
        if (!googlePending && password.value.length < 6) { alert('Password must be at least 6 characters.'); return false; }
        return true;
    }

    document.querySelectorAll('[data-next]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (current === 1 && !validateStep1()) return;
            showStep(current + 1);
        });
    });
    document.querySelectorAll('[data-prev]').forEach(function(btn) {
        btn.addEventListener('click', function() { showStep(current - 1); });
    });

    <?php if ($error): ?>
    showStep(<?= $scratchVerifiedUsername ?? ($_SESSION['scratch_verified_username'] ?? null) ? 3 : 1 ?>);
    <?php endif; ?>

    // Avatar/banner preview
    document.getElementById('avatarUploadBox').addEventListener('click', function(e) {
        if (e.target.tagName !== 'INPUT') document.getElementById('avatarInput').click();
    });
    document.getElementById('avatarInput').addEventListener('change', function() {
        var f = this.files[0];
        if (!f) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatarUploadBox').style.backgroundImage = 'url(' + e.target.result + ')';
        };
        reader.readAsDataURL(f);
    });
    document.getElementById('bannerUploadBox').addEventListener('click', function(e) {
        if (e.target.tagName !== 'INPUT') document.getElementById('bannerInput').click();
    });
    document.getElementById('bannerInput').addEventListener('change', function() {
        var f = this.files[0];
        if (!f) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('bannerUploadBox').style.backgroundImage = 'url(' + e.target.result + ')';
        };
        reader.readAsDataURL(f);
    });

    // Dark mode toggle (preview only until account is created)
    var darkEnabled = false;
    document.getElementById('darkModeToggle').addEventListener('click', function() {
        darkEnabled = !darkEnabled;
        document.getElementById('darkModeField').value = darkEnabled ? '1' : '0';
        document.getElementById('darkModeLabel').textContent = 'Dark mode: ' + (darkEnabled ? 'on' : 'off');
        document.body.classList.toggle('dark', darkEnabled);
        document.getElementById('darkModeIcon').innerHTML = darkEnabled
            ? '<path d="M12 7a5 5 0 100 10 5 5 0 000-10zm0-5a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm0 17a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zm9-7a1 1 0 01-1 1h-1a1 1 0 110-2h1a1 1 0 011 1zM5 12a1 1 0 01-1 1H3a1 1 0 110-2h1a1 1 0 011 1zm12.66-6.66a1 1 0 010 1.42l-.71.7a1 1 0 11-1.41-1.41l.7-.71a1 1 0 011.42 0zM6.46 17.54a1 1 0 010 1.42l-.7.7a1 1 0 11-1.42-1.41l.71-.71a1 1 0 011.41 0zm11.2 0a1 1 0 011.41 0l.71.71a1 1 0 11-1.42 1.41l-.7-.7a1 1 0 010-1.42zM6.46 6.46a1 1 0 01-1.41 0l-.71-.7a1 1 0 111.42-1.42l.7.71a1 1 0 010 1.41z"/>'
            : '<path d="M20.7 14.9A8.5 8.5 0 019.1 3.3a1 1 0 00-1.2-1.3 10 10 0 1013.9 13.9 1 1 0 00-1.1-1z"/>';
    });

    // Copy verification code (Scratch branch only)
    var verifyCopyBtn = document.getElementById('verifyCopyBtn');
    if (verifyCopyBtn) {
        verifyCopyBtn.addEventListener('click', function() {
            var code = document.getElementById('verifyCode').textContent;
            navigator.clipboard.writeText(code).then(function() {
                var btn = document.getElementById('verifyCopyBtn');
                var original = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = original; }, 1500);
            });
        });
    }

    // Verify Scratch follow + comment (Scratch branch only)
    var verifyBtn = document.getElementById('verifyBtn');
    if (verifyBtn) {
        verifyBtn.addEventListener('click', function() {
            var btn = this;
            var status = document.getElementById('verifyStatus');
            var finishBtn = document.getElementById('finishBtn');
            btn.disabled = true;
            btn.textContent = 'Checking...';
            status.className = 'verify-status';
            status.textContent = '';

            var formData = new URLSearchParams();
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch('/verify-scratch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.disabled = false;
                btn.textContent = 'Verify';
                if (data.success) {
                    status.className = 'verify-status success';
                    status.textContent = 'Verified as @' + data.username + '!';
                    finishBtn.disabled = false;
                } else {
                    status.className = 'verify-status error';
                    status.textContent = (data.error || 'Verification failed. Please try again.') + (data.debug ? ' [' + data.debug + ']' : '');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.textContent = 'Verify';
                status.className = 'verify-status error';
                status.textContent = 'Something went wrong. Please try again.';
            });
        });
    }
})();
</script>
</body>
</html>