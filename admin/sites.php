<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_site') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $repoUrl = trim($_POST['repo_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = !empty($_POST['is_active']);
        if ($name === '') {
            $error = 'Site name is required.';
        } else {
            createSite($name, $description, $repoUrl, $isActive, $sortOrder);
        }
    } elseif ($action === 'update_site') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $repoUrl = trim($_POST['repo_url'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        $isActive = !empty($_POST['is_active']);
        if ($name === '') {
            $error = 'Site name is required.';
        } else {
            updateSite($id, $name, $description, $repoUrl, $isActive, $sortOrder);
        }
    } elseif ($action === 'delete_site') {
        deleteSite((int)($_POST['id'] ?? 0));
    }

    if ($error === '') {
        header('Location: /admin/sites');
        exit;
    }
}

$sites = getAllSites();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php include __DIR__ . '/../includes/favicon.php'; ?>
<title>Sites - Admin - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=27">
<style>
.forum-admin-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
.forum-admin-row { display: flex; gap: 1rem; align-items: flex-end; flex-wrap: wrap; border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 1rem; }
.forum-admin-row label { margin-top: 0; }
.forum-admin-row .field { display: flex; flex-direction: column; gap: 0.2rem; }
.forum-admin-row input[type="text"] { min-width: 160px; }
.forum-admin-row input[type="number"] { width: 80px; }
.forum-admin-form-new { border: 1px dashed rgba(128,128,128,0.4); border-radius: 8px; padding: 1rem; margin-bottom: 2rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
.forum-admin-form-new .field { display: flex; flex-direction: column; gap: 0.2rem; }
</style>
</head>
<body <?php include __DIR__ . '/../includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>ScratchNews Sites</h2>
    <p><a href="/s">View the public Sites page &rarr;</a></p>
    <p style="color:#888;">Each site lives in its own GitHub repo and deploys straight into <code>/s/&lt;slug&gt;/</code> on this account - this page only manages the public directory entry, not the site's actual code.</p>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <div class="forum-admin-list">
        <?php foreach ($sites as $s): ?>
        <form method="post" class="forum-admin-row">
            <input type="hidden" name="action" value="update_site">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <div class="field"><label>Name</label><input type="text" name="name" value="<?= e($s['name']) ?>" required></div>
            <div class="field"><label>Description</label><input type="text" name="description" value="<?= e($s['description'] ?? '') ?>"></div>
            <div class="field"><label>Repo URL</label><input type="text" name="repo_url" value="<?= e($s['repo_url'] ?? '') ?>" placeholder="https://github.com/xTODB/scratchcensus"></div>
            <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="<?= (int)$s['sort_order'] ?>"></div>
            <div class="field"><label>Slug</label><span>/s/<?= e($s['slug']) ?></span></div>
            <div class="field"><label>&nbsp;</label><label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;"><input type="checkbox" name="is_active" value="1" <?= !empty($s['is_active']) ? 'checked' : '' ?>> Active (listed)</label></div>
            <button class="btn inline" type="submit">Save</button>
        </form>
        <form method="post" onsubmit="return confirm('Remove this site from the directory? (Does not delete the deployed subfolder or the GitHub repo.)');">
            <input type="hidden" name="action" value="delete_site">
            <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
            <button class="btn inline danger" type="submit" style="margin-top:-0.7rem;margin-bottom:0.5rem;">Remove Site #<?= (int)$s['id'] ?></button>
        </form>
        <?php endforeach; ?>
        <?php if (!$sites): ?><p>No sites yet.</p><?php endif; ?>
    </div>

    <form method="post" class="forum-admin-form-new">
        <input type="hidden" name="action" value="create_site">
        <div class="field"><label>New site name</label><input type="text" name="name" required></div>
        <div class="field"><label>Description</label><input type="text" name="description"></div>
        <div class="field"><label>Repo URL</label><input type="text" name="repo_url" placeholder="https://github.com/xTODB/scratchcensus"></div>
        <div class="field"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
        <div class="field"><label>&nbsp;</label><label style="display:flex;align-items:center;gap:0.4rem;font-weight:400;"><input type="checkbox" name="is_active" value="1" checked> Active (listed)</label></div>
        <button class="btn" type="submit">Add Site</button>
    </form>
</main>
</body>
</html>