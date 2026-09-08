<?php
require_once __DIR__ . '/functions.php';
header('Content-Type: application/xml; charset=utf-8');

$db = getDB();
$articles = $db->query("SELECT id, updated_at FROM articles WHERE status = 'published' OR status IS NULL")->fetch_all(MYSQLI_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
echo '  <url><loc>https://scratchnews.net/</loc></url>' . "\n";
foreach ($articles as $a) {
    $lastmod = date('c', strtotime($a['updated_at']));
    echo '  <url>';
    echo '<loc>https://scratchnews.net/article/' . (int)$a['id'] . '</loc>';
    echo '<lastmod>' . e($lastmod) . '</lastmod>';
    echo '</url>' . "\n";
}
echo '</urlset>';
