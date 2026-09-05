<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($name === '') {
            $error = 'Category name is required.';
        } else {
            createForumCategory($name, $sortOrder);
        }
    } elseif ($action === 'update_category') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($name === '') {
            $error = 'Category name is required.';
        } else {
            updateForumCategory($id, $name, $sortOrder);
        }
    } elseif ($action === 'delete_category') {
        deleteForumCategory((int)($_POST['id'] ?? 0));
    } elseif ($action === 'create_subforum') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($name === '' || !$categoryId) {
            $error = 'Subforum name and category are required.';
        } else {
            createForumSubforum($categoryId, $name, $description, $sortOrder);
        }
    } elseif ($action === 'update_subforum') {
        $id = (int)($_POST['id'] ?? 0);
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($name === '' || !$categoryId) {
            $error = 'Subforum name and category are required.';
        } else {
            updateForumSubforum($id, $categoryId, $name, $description, $sortOrder);
        }
    } elseif ($action === 'delete_subforum') {
        deleteForumSubforum((int)($_POST['id'] ?? 0));
    }

    if ($error === '') {
        header('Location: /admin/forums');
        exit;
    }
}

$categories = getAllForumCategories();
$subforums = getAllForumSubforums();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Forums - Admin - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=27">
<style>
.forum-admin-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
.forum-admin-row { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 1rem; }
.forum-admin-row label { margin-top: 0; }
.forum-admin-row .field { display: flex; flex-direction: column; gap: 0.2rem; }
.forum-admin-row input[type="text"], .forum-admin-row select { min-width: 160px; }
.forum-admin-row input[type="number"] { width: 80px; }
.forum-admin-form-new { border: 1px dashed rgba(128,128,128,0.4); border-radius: 8px; padding: 1rem; margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
.forum-admin-form-new .field { display: flex; flex-direction: column; gap: 0.2rem; }
</style>
</head>
<body <?php include __DIR__ . '/../includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Forums - Categories &amp; Subforums</h2>
    <p><a href="/forums">View the public Forums page &rarr;</a></p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <h3>Categories</h3>
    <div class="forum-admin-list">
        <?php foreach ($categories as $cat): ?>
        <form method="post" class="forum-admin-row">
            <input type="hidden" name="action" value="update_category">
            <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
            <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($cat['name']) ?>" required></div>
            <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="<?= (int)$cat['sort_order'] ?>"></div>
            <button class="btn inline" type="submit">Save</button>
        </form>
        <form method="post" onsubmit="return confirm('Delete this category and ALL its subforums, topics, and posts?');">
            <input type="hidden" name="action" value="delete_category">
            <input type="hidden" name="id" value="<?= (int)$cat['id'] ?>">
            <button class="btn inline danger" type="submit" style="margin-top:-0.7rem;margin-bottom:0.5rem;">Delete Category #<?= (int)$cat['id'] ?></button>
        </form>
        <?php endforeach; ?>
        <?php if (!$categories): ?><p>No categories yet.</p><?php endif; ?>
    </div>

    <form method="post" class="forum-admin-form-new">
        <input type="hidden" name="action" value="create_category">
        <div class="field"><label>New category name</label><input type="text" name="name" required></div>
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
        <button class="btn" type="submit">Add Category</button>
    </form>

    <h3>Subforums</h3>
    <div class="forum-admin-list">
        <?php foreach ($subforums as $sf): ?>
        <form method="post" class="forum-admin-row">
            <input type="hidden" name="action" value="update_subforum">
            <input type="hidden" name="id" value="<?= (int)$sf['id'] ?>">
            <div class="field"><label>Category</label>
                <select name="category_id">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)$sf['category_id'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($sf['name']) ?>" required></div>
            <div class="field"><label>Description</label><input type="text" name="description" value="<?= e($sf['description'] ?? '') ?>"></div>
            <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="<?= (int)$sf['sort_order'] ?>"></div>
            <div class="field"><label>Slug</label><span>/forums/<?= e($sf['slug']) ?></span></div>
            <button class="btn inline" type="submit">Save</button>
        </form>
        <form method="post" onsubmit="return confirm('Delete this subforum and ALL its topics and posts?');">
            <input type="hidden" name="action" value="delete_subforum">
            <input type="hidden" name="id" value="<?= (int)$sf['id'] ?>">
            <button class="btn inline danger" type="submit" style="margin-top:-0.7rem;margin-bottom:0.5rem;">Delete Subforum #<?= (int)$sf['id'] ?></button>
        </form>
        <?php endforeach; ?>
        <?php if (!$subforums): ?><p>No subforums yet.</p><?php endif; ?>
    </div>

    <form method="post" class="forum-admin-form-new">
        <input type="hidden" name="action" value="create_subforum">
        <div class="field"><label>Category</label>
            <select name="category_id">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field"><label>New subforum name</label><input type="text" name="name" required></div>
        <div class="field"><label>Description</label><input type="text" name="description"></div>
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
        <button class="btn" type="submit">Add Subforum</button>
    </form>
</main>
</body>
</html>
