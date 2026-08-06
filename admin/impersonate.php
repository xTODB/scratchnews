<?php
require_once __DIR__ . '/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $targetId = (int)($_POST['user_id'] ?? 0);
    if ($targetId > 0) {
        impersonateUser((int)$_SESSION['reader_id'], $targetId);
    }
    header('Location: /');
    exit;
}
header('Location: /admin/users');
exit;