<?php

date_default_timezone_set('UTC');

define('DB_HOST', '');
define('DB_NAME', '');
define('DB_USER', '');
define('DB_PASS', '');

define('ADMIN_USER', '');
define('ADMIN_PASS_HASH', '');
define('SITE_NAME', 'ScratchNews');
// SITE_VERSION lives in version.php now, not here - tracked in the repo so it can be
// bumped with a normal git push instead of an InfinityFree File Manager edit.
define('BREVO_API_KEY', '');
define('BREVO_SENDER_EMAIL', 'noreply@example.com');

define('GITHUB_TOKEN', '');
define('GITHUB_REPO', '');
define('GITHUB_BRANCH', '');
define('GOOGLE_CLIENT_ID', '');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            $conn->set_charset('utf8mb4');
            $conn->query("SET time_zone = '+00:00'");
        } catch (mysqli_sql_exception $e) {
            http_response_code(500);
            die('Database connection failed. Double-check the credentials in config.php.');
        }
    }
    return $conn;
}