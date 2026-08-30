<?php
// One-off: retroactively strips hardcoded white/black inline text-color spans
// out of already-published article content, using the same logic as the new
// sanitizeArticleHtml() fix. Run once via browser or CLI, then delete this file.
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$db = getDB();
$result = $db->query("SELECT id, content FROM articles");
$checked = 0;
$changed = [];

while ($row = $result->fetch_assoc()) {
    $checked++;
    $original = $row['content'];
    $cleaned = stripWhiteBlackTextColor($original);
    if ($cleaned !== $original) {
        $stmt = $db->prepare("UPDATE articles SET content = ? WHERE id = ?");
        $stmt->bind_param('si', $cleaned, $row['id']);
        $stmt->execute();
        $stmt->close();
        $changed[] = $row['id'];
    }
}

echo "Checked {$checked} articles.\n";
echo count($changed) ? "Cleaned article IDs: " . implode(', ', $changed) . "\n" : "No articles needed cleaning.\n";
