<?php
require_once __DIR__ . '/functions.php';
startSession();
requireCsrf();

$lang = trim($_POST['translate_lang'] ?? '');
if (!array_key_exists($lang, translateLanguageOptions())) $lang = '';

if (!empty($_SESSION['reader_id'])) {
    setTranslatePreference($_SESSION['reader_id'], $lang);
    $_SESSION['translate_lang'] = $lang;
} else {
    setcookie('sn_translate_lang', $lang, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'secure' => true,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

$redirect = $_POST['redirect'] ?? '/';
// Only allow same-site relative redirects (block "//evil.com"-style open redirects).
if (!preg_match('#^/(?!/)#', $redirect)) $redirect = '/';
header('Location: ' . $redirect);
exit;
