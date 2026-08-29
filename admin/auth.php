<?php
require_once __DIR__ . '/../functions.php';
startSession();

if (empty($_SESSION['is_admin'])) {
    header('Location: /login');
    exit;
}
