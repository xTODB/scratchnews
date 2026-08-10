<?php
require_once __DIR__ . '/auth.php';
$articles = getAllArticles(true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Dashboard - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <a href="/admin/create" class="btn">+ New Article</a>
    <br><br>
    <?php if (empty($articles)): ?>
        <p>No articles yet.</p>
    <?php else: ?>
        <table>
            <tr><th>ID</th><th>Title</th><th>Author</th><th>Date</th><th>Status</th><th>Actions</th></tr>
            <?php foreach ($articles as $a): ?>
                <tr>
                    <td>#<?= (int)$a['id'] ?></td>
                    <td><?= e($a['title']) ?></td>
                    <td><?= e($a['author']) ?></td>
                    <td><?= utcTimeTag($a['created_at']) ?></td>
                    <td><?= ($a['status'] ?? 'published') === 'draft' ? '<span style="color:#a67c00;font-weight:600;">Draft</span>' : 'Published' ?></td>
                    <td class="actions">
                        <?php if (($a['status'] ?? 'published') !== 'draft'): ?>
                        <a href="/article/<?= (int)$a['id'] ?>" target="_blank">View</a>
                        <?php endif; ?>
                        <a href="/admin/edit?id=<?= (int)$a['id'] ?>">Edit</a>
                        <a href="/admin/delete?id=<?= (int)$a['id'] ?>" style="color:#d9392a;"
                           onclick="return confirm('Delete this article? This cannot be undone.');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</main>
<script>
document.querySelectorAll('time.local-date, time.local-datetime').forEach(function(el) {
    var d = new Date(el.getAttribute('datetime'));
    if (isNaN(d.getTime())) return;
    if (el.classList.contains('local-datetime')) {
        el.textContent = d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
    } else {
        el.textContent = d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
    }
});
</script>
</body>
</html>