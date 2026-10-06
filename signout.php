<?php
require_once __DIR__ . '/functions.php';
startSession();
if (!empty($_SESSION['reader_id'])) {
    $parts = !empty($_COOKIE['remember_me']) ? explode(':', $_COOKIE['remember_me'], 2) : [];
    clearRememberToken((int)$_SESSION['reader_id'], $parts[1] ?? null);
}
setcookie('remember_me', '', time() - 3600, '/');
session_destroy();
header('Location: /');
exit;