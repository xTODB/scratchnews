<?php
require_once __DIR__ . '/functions.php';
startSession();

if (!empty($_SESSION['reader_id'])) { header('Location: /'); exit; }

$scratchTargetUser = getApiSetting('scratch_verify_target_user', '');
$scratchProjectId = getApiSetting('scratch_verify_project_id', '');

if (empty($_SESSION['contest_verify_code'])) {
    $_SESSION['contest_verify_code'] = generateScratchVerifyCode();
}
$contestVerifyCode = $_SESSION['contest_verify_code'];

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $honeypot = trim($_POST['website'] ?? '');
    $contestScratcher = trim($_POST['contest_scratcher'] ?? '');
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $verifiedUsername = $_SESSION['contest_verified_username'] ?? null;

    if ($honeypot !== '') {
        header('Location: /?justregistered=1');
        exit;
    } elseif (tooManySignupAttempts($ip)) {
        $error = 'Too many signup attempts from your network. Please try again later.';
    } elseif ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $username)) {
        $error = 'Username must be 3-20 characters and can only contain letters, numbers, and underscores.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!in_array($contestScratcher, CONTEST_SCRATCHERS, true)) {
        $error = 'Please select which Scratcher this Contest account is for.';
    } elseif (!preg_match('/^\+[1-9]\d{6,14}$/', $phoneNumber)) {
        $error = 'Please enter a valid phone number including country code (e.g. +12345678900). This is just a backup contact for TODB if you ever lose this account - it is not texted or auto-verified.';
    } elseif (isPhoneNumberLinked($phoneNumber)) {
        $error = 'That phone number is already linked to another ScratchNews account.';
    } elseif ($verifiedUsername === null || strcasecmp($verifiedUsername, $contestScratcher) !== 0) {
        $error = 'Please complete Comment Auth as @' . $contestScratcher . ' before finishing your account.';
    } else {
        $result = createUser($username, null, $password, $verifiedUsername, $phoneNumber);
        if ($result === 'duplicate') {
            $error = 'That username, linked Scratch account, or phone number is already taken.';
            logSignupAttempt($ip, false);
        } else {
            logSignupAttempt($ip, true);
            setUserContestScratcher($result, true);
            updateUserIp($result, $ip);

            unset($_SESSION['contest_verify_code'], $_SESSION['contest_verified_username']);

            $_SESSION['reader_id'] = $result;
            $_SESSION['reader_username'] = $username;
            $_SESSION['is_admin'] = false;
            $_SESSION['is_moderator'] = false;
            $_SESSION['dark_mode'] = false;
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
<title>Writers' Contest Account - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=21">
<style>
.wizard-card { max-width:640px; margin:0 auto; }
.wizard-progress-row { display:flex; align-items:center; gap:0.75rem; margin:0.5rem 0 1.25rem; }
.wizard-progress-track { flex:1; height:10px; border-radius:5px; background:#ccc; overflow:hidden; }
.wizard-progress-fill { height:100%; background:var(--brand-bright); border-radius:5px; transition:width 0.25s ease; }
.wizard-progress-label { font-weight:bold; white-space:nowrap; }
.wizard-step { visibility: hidden; height: 0; overflow: hidden; }
.wizard-step.active { visibility: visible; height: auto; overflow: visible; }
.wizard-nav-row { display:flex; justify-content:flex-end; gap:0.6rem; margin-top:1.25rem; }
.wizard-nav-row .btn.secondary { margin-right:auto; }
.verify-row { background:#f2f2f2; border-radius:8px; padding:0.85rem 1rem; margin-bottom:0.75rem; display:flex; align-items:flex-start; gap:0.75rem; }
body.dark .verify-row { background:#2a2a2a; }
.verify-num { font-size:1.4rem; font-weight:bold; color:var(--brand-bright); min-width:1.6rem; }
.verify-body { flex:1; }
.verify-code-row { display:flex; align-items:center; gap:0.5rem; margin-top:0.4rem; }
.verify-code { font-family:monospace; font-size:1rem; background:#e0e0e0; border-radius:5px; padding:0.25rem 0.6rem; }
body.dark .verify-code { background:#444; }
.verify-copy-btn { border:1px solid #999; background:none; border-radius:5px; padding:0.2rem 0.6rem; cursor:pointer; color:inherit; font-size:0.85rem; }
.verify-status { margin-top:0.6rem; font-size:0.9rem; }
.verify-status.success { color:#2e8b2e; }
.verify-status.error { color:#c0392b; }
.contest-note { font-size:0.85rem; color:#777; margin-top:0.25rem; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<header>
    <a href="/" class="logo-link">
<svg viewBox="0,0,136.90609,31.33279" class="logo-svg" xmlns="http://www.w3.org/2000/svg"><g transform="translate(-172.33195,-164.3336)"><g stroke-miterlimit="10"><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="#ffaa33" stroke-width="3" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="none" stroke-width="1" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><path d="M181.04509,195.64879h-8.71313v-10.6397h8.71313z" fill="#cc8829" stroke="none"/><path d="M176.88045,195.6664v-20.46677l3.90302,-0.07587l0.09189,-5.0222h6.64479v20.71239l-3.91923,0.16783l-0.04923,4.68462z" fill="#ffaa33" stroke="none"/><path d="M201.40189,164.35122h8.71313v10.6397h-8.71313z" fill="#cc8829" stroke="none"/><path d="M205.56653,164.33361v20.46677l-3.90302,0.07587l-0.09189,5.0222h-6.64479v-20.71239l3.91923,-0.16783l0.04923,-4.68462z" fill="#ffaa33" stroke="none"/><path d="M190.06459,189.91166l-0.03808,-3.62362l-3.03158,-0.12982v-16.02128h5.13983l0.07108,3.88473l3.01904,0.05869v15.8313z" fill="#ffaa33" stroke="none"/></g></g></svg>
    </a>
    <nav><a href="/login">Log In</a></nav>
</header>
<main class="wizard-card">
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <div class="wizard-progress-row">
        <div class="wizard-progress-track"><div class="wizard-progress-fill" id="wizardFill" style="width:50%"></div></div>
        <span class="wizard-progress-label" id="wizardLabel">1/2</span>
    </div>

    <form method="post" id="wizardForm">
        <?= csrfField() ?>
        <div style="position:absolute;left:-9999px;" aria-hidden="true">
            <label for="website">Leave this field blank</label>
            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <section class="wizard-step active" data-step="1">
            <h2>Create your Writers' Contest account</h2>
            <p class="contest-note">This account is for one of the 15 Scratchers this round's contest entries can reference. Writers can't create this for you - only you, verified as that Scratch account, can.</p>
            <h3>Step 1: Basics</h3>
            <label for="username">ScratchNews username</label>
            <input type="text" id="username" name="username" value="<?= e($_POST['username'] ?? '') ?>" required>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="6">
            <label for="contestScratcher">Which Scratcher are you?</label>
            <select id="contestScratcher" name="contest_scratcher" required>
                <option value="">Select your Scratch username...</option>
                <?php foreach (CONTEST_SCRATCHERS as $s): ?>
                <option value="<?= e($s) ?>" <?= (($_POST['contest_scratcher'] ?? '') === $s) ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="phoneNumber">Phone number</label>
            <input type="tel" id="phoneNumber" name="phone_number" placeholder="+12345678900" value="<?= e($_POST['phone_number'] ?? '+') ?>" required>
            <p class="contest-note">This is just a backup way for TODB to reach you if you ever lose this account - a couple past contest subjects have. It's not texted, called, or auto-verified, and only TODB can see it.</p>
            <div class="wizard-nav-row">
                <button type="button" class="btn" data-next>Next</button>
            </div>
        </section>

        <section class="wizard-step" data-step="2">
            <h3>Step 2: Verify you're that Scratcher</h3>
            <div class="verify-row">
                <span class="verify-num">1</span>
                <div class="verify-body">
                    Comment your code on the verification project, from <strong id="verifyAsLabel">the account you selected</strong>
                    <div class="verify-code-row">
                        <a class="btn secondary" href="https://scratch.mit.edu/projects/<?= rawurlencode($scratchProjectId) ?>/" target="_blank" rel="noopener">Visit Scratch project</a>
                    </div>
                    <div class="verify-code-row">
                        <span class="verify-code" id="verifyCode"><?= e($contestVerifyCode) ?></span>
                        <button type="button" class="verify-copy-btn" data-copy-target="verifyCode">Copy</button>
                    </div>
                </div>
            </div>
            <div class="verify-row">
                <span class="verify-num">2</span>
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
    </form>
</main>
<script>
(function() {
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
        var scratcher = document.getElementById('contestScratcher');
        var phone = document.getElementById('phoneNumber');
        if (!/^[A-Za-z0-9_]{3,20}$/.test(username.value)) { alert('Username must be 3-20 characters (letters, numbers, underscores only).'); return false; }
        if (password.value.length < 6) { alert('Password must be at least 6 characters.'); return false; }
        if (!scratcher.value) { alert('Please select which Scratcher this account is for.'); return false; }
        if (!/^\+[1-9]\d{6,14}$/.test(phone.value)) { alert('Please enter a valid phone number including country code.'); return false; }
        return true;
    }

    document.querySelectorAll('[data-next]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            if (current === 1 && !validateStep1()) return;
            document.getElementById('verifyAsLabel').textContent = '@' + document.getElementById('contestScratcher').value;
            showStep(current + 1);
        });
    });
    document.querySelectorAll('[data-prev]').forEach(function(btn) {
        btn.addEventListener('click', function() { showStep(current - 1); });
    });

    <?php if ($error): ?>
    showStep(<?= ($_SESSION['contest_verified_username'] ?? null) ? 2 : 1 ?>);
    <?php endif; ?>

    document.querySelectorAll('.verify-copy-btn[data-copy-target]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = document.getElementById(btn.dataset.copyTarget);
            navigator.clipboard.writeText(target.textContent).then(function() {
                var original = btn.textContent;
                btn.textContent = 'Copied!';
                setTimeout(function() { btn.textContent = original; }, 1500);
            });
        });
    });

    document.getElementById('verifyBtn').addEventListener('click', function() {
        var btn = this;
        var status = document.getElementById('verifyStatus');
        var finishBtn = document.getElementById('finishBtn');
        var scratcher = document.getElementById('contestScratcher').value;
        if (!scratcher) { status.className = 'verify-status error'; status.textContent = 'Please go back and select which Scratcher this is for.'; return; }

        var formData = new URLSearchParams();
        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
        formData.append('contest_scratcher', scratcher);

        btn.disabled = true;
        btn.textContent = 'Checking...';
        status.className = 'verify-status';
        status.textContent = '';

        fetch('/verify-contest-auth.php', {
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
                status.textContent = data.error || 'Verification failed. Please try again.';
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Verify';
            status.className = 'verify-status error';
            status.textContent = 'Something went wrong. Please try again.';
        });
    });
})();
</script>
</body>
</html>
