<?php
require_once __DIR__ . '/../functions.php';
startSession();

if (empty($_SESSION['is_admin']) && empty($_SESSION['is_moderator']) && empty($_SESSION['is_head_moderator'])) {
    header('Location: /login');
    exit;
}

// Admins get Head Moderator-level panel access too (they already have the full
// admin panel, but visiting /moderator shouldn't be more restrictive for them).
$isAdminUser = !empty($_SESSION['is_admin']);
$isHeadModerator = $isAdminUser || !empty($_SESSION['is_head_moderator']);
