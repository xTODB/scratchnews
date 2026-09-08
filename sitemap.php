<?php
require_once __DIR__ . '/functions.php';
header('Content-Type: application/xml; charset=utf-8');

$db = getDB();

$articles = $db->query("SELECT id, updated_at FROM articles WHERE status = 'published' OR status IS NULL")->fetch_all(MYSQLI_ASSOC);
$profiles = $db->query("SELECT username FROM users WHERE is_banned = 0 AND username NOT LIKE 'deleted_user_%'")->fetch_all(MYSQLI_ASSOC);
$groups = $db->query("SELECT slug FROM `groups` WHERE status = 'active'")->fetch_all(MYSQLI_ASSOC);
$subforums = $db->query("SELECT slug FROM forum_subforums")->fetch_all(MYSQLI_ASSOC);
$topics = $db->query(
    "SELECT t.id, s.slug AS subforum_slug
     FROM forum_topics t
     JOIN forum_subforums s ON s.id = t.subforum_id"
)->fetch_all(MYSQLI_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static / top-level pages
$staticPaths = [
    '/',
    '/about',
    '/explore',
    '/profiles',
    '/groups',
    '/forums',
    '/writers-contest',
    '/changelog',
    '/community-guidelines',
    '/submission-guidelines',
    '/download',
    '/stats',
];
foreach ($staticPaths as $path) {
    echo '  <url><loc>https://scratchnews.net' . e($path) . '</loc></url>' . "\n";
}

// Articles
foreach ($articles as $a) {
    $lastmod = date('c', strtotime($a['updated_at']));
    echo '  <url>';
    echo '<loc>https://scratchnews.net/article/' . (int)$a['id'] . '</loc>';
    echo '<lastmod>' . e($lastmod) . '</lastmod>';
    echo '</url>' . "\n";
}

// Profiles
foreach ($profiles as $p) {
    echo '  <url><loc>https://scratchnews.net/@' . e(rawurlencode($p['username'])) . '</loc></url>' . "\n";
}

// Groups
foreach ($groups as $g) {
    echo '  <url><loc>https://scratchnews.net/group/' . e($g['slug']) . '</loc></url>' . "\n";
}

// Forum subforums
foreach ($subforums as $s) {
    echo '  <url><loc>https://scratchnews.net/forums/' . e($s['slug']) . '</loc></url>' . "\n";
}

// Forum topics
foreach ($topics as $t) {
    echo '  <url><loc>https://scratchnews.net/forums/' . e($t['subforum_slug']) . '/' . (int)$t['id'] . '</loc></url>' . "\n";
}

echo '</urlset>';