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

define('GOOGLE_CLIENT_ID', '');

// v0.24 Groups beta: comma-separated IP(s) allowed to access /groups while it's
// being built. Everyone else sees a "Work in progress" notice. Admin logins always
// get through regardless of this list. Leave empty to lock it to admins only, or
// clear it out entirely once Groups is ready to launch to everyone.
define('GROUPS_BETA_IPS', '');

// How many reply levels get visual indent before comment threads on article/profile/group
// pages stop nesting further right. Replies past this depth still work exactly the same
// (post, display, reply to again) - they just render at 0 extra indent instead of pushing
// the page wider, which is what was cutting off the header/profile UI on mobile. Lower this
// if threads still look too wide on mobile; raise it if you want deeper visible nesting.
// If this line is missing from your live config.php, functions.php defaults to 4.
define('MAX_COMMENT_REPLY_DEPTH', 4);

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
