<?php
require_once __DIR__ . '/functions.php';
startSession();

$redirect = safeInternalRedirect($_GET['redirect'] ?? $_POST['redirect'] ?? null);

if (!empty($_SESSION['reader_id'])) {
    header('Location: ' . (!empty($_SESSION['is_admin']) ? '/admin/' : $redirect));
    exit;
}

$error = '';
$loginMethod = 'password';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginMethod = ($_POST['login_method'] ?? 'password') === 'wordlist' ? 'wordlist' : 'password';
    $user = null;

    if ($loginMethod === 'wordlist') {
        $username = trim($_POST['wl_username'] ?? '');
        $user = verifyWordList($username, $_POST['wl_words'] ?? '');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $candidate = getUserByUsername($username);
        if ($candidate && password_verify($password, $candidate['password_hash'])) $user = $candidate;
    }

    if ($user) {
        updateUserIp($user['id'], $_SERVER['REMOTE_ADDR'] ?? '');
        $_SESSION['reader_id'] = $user['id'];
        $_SESSION['reader_username'] = $user['username'];
        $_SESSION['is_admin'] = !empty($user['is_admin']);
        $_SESSION['is_moderator'] = !empty($user['is_moderator']);
        $_SESSION['is_head_moderator'] = !empty($user['is_head_moderator']);
        $_SESSION['dark_mode'] = $user['dark_mode'];
        $_SESSION['color_theme'] = $user['color_theme'] ?? 'default';
        $_SESSION['translate_lang'] = $user['translate_lang'] ?? '';
        $token = setRememberToken($user['id']);
        setcookie('remember_me', $user['id'] . ':' . $token, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: ' . (!empty($user['is_banned']) ? '/banned' : ($_SESSION['is_admin'] ? '/admin/' : $redirect)));
        exit;
    } else {
        $error = $loginMethod === 'wordlist' ? 'Incorrect username or word list.' : 'Incorrect username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Log In - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=21">
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<header>
    <a href="/" class="logo-link">
<svg viewBox="0,0,136.90609,31.33279" class="logo-svg" xmlns="http://www.w3.org/2000/svg"><g transform="translate(-172.33195,-164.3336)"><g stroke-miterlimit="10"><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="#ffaa33" stroke-width="3" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><text transform="translate(217.16808,185.69599) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="none" stroke-width="1" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><path d="M181.04509,195.64879h-8.71313v-10.6397h8.71313z" fill="#cc8829" stroke="none"/><path d="M176.88045,195.6664v-20.46677l3.90302,-0.07587l0.09189,-5.0222h6.64479v20.71239l-3.91923,0.16783l-0.04923,4.68462z" fill="#ffaa33" stroke="none"/><path d="M201.40189,164.35122h8.71313v10.6397h-8.71313z" fill="#cc8829" stroke="none"/><path d="M205.56653,164.33361v20.46677l-3.90302,0.07587l-0.09189,5.0222h-6.64479v-20.71239l3.91923,-0.16783l0.04923,-4.68462z" fill="#ffaa33" stroke="none"/><path d="M190.06459,189.91166l-0.03808,-3.62362l-3.03158,-0.12982v-16.02128h5.13983l0.07108,3.88473l3.01904,0.05869v15.8313z" fill="#ffaa33" stroke="none"/></g></g></svg>
    </a>
    <nav><a href="/register">Sign Up</a></nav>
</header>
<main>
    <h2>Log In</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <div style="display:flex;justify-content:center;margin-bottom:1rem;">
        <div id="g_id_onload"
             data-client_id="<?= e(GOOGLE_CLIENT_ID) ?>"
             data-callback="handleGoogleCredential"
             data-auto_prompt="false"></div>
        <div class="g_id_signin" data-type="standard" data-size="large" data-width="300"></div>
    </div>
    <p style="text-align:center;color:#888;font-size:0.85rem;margin:0.75rem 0;">— or log in with a username and password —</p>
    <form method="post">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required>
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <button class="btn" type="submit">Log In</button>
    </form>
    <p style="text-align:center;margin-top:1rem;"><a href="#" id="wordListToggle">Log in with a word list instead</a></p>
    <form method="post" id="wordListForm" style="<?= $loginMethod === 'wordlist' ? '' : 'display:none;' ?>">
        <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
        <input type="hidden" name="login_method" value="wordlist">
        <label for="wl_username">Username</label>
        <input type="text" id="wl_username" name="wl_username" required>
        <label for="wl_words">Word List</label>
        <textarea id="wl_words" name="wl_words" rows="2" required placeholder="e.g. apple river stone cloud lantern grape summit ember"></textarea>
        <button class="btn" type="submit">Log In</button>
    </form>
    <p style="margin-top:1rem;">Don't have an account? <a href="/register">Sign up</a></p>
</main>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<script>
function handleGoogleCredential(response) {
    fetch('/google-auth', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'credential=' + encodeURIComponent(response.credential) + '&redirect=' + encodeURIComponent(<?= json_encode($redirect) ?>)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.redirect) { window.location.href = data.redirect; }
        else if (data.newSignup) { window.location.href = '/register'; }
        else { alert(data.error || 'Google sign-in failed.'); }
    })
    .catch(function() { alert('Google sign-in failed. Please try again.'); });
}
document.getElementById('wordListToggle').addEventListener('click', function(e) {
    e.preventDefault();
    document.getElementById('wordListForm').style.display = 'block';
    this.style.display = 'none';
});
</script>
</body>
</html>
