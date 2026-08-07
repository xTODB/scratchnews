<?php
require_once __DIR__ . '/auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $text = trim($_POST['text'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($link === '' || empty($_FILES['image']['tmp_name'])) {
            $error = 'An image and a link are required.';
        } else {
            try {
                $imageUrl = saveUploadedImage($_FILES['image'], 'banners');
                createBanner($imageUrl, $text ?: null, $link, $sortOrder);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $existing = getBannerById($id);
        if ($existing) {
            $text = trim($_POST['text'] ?? '');
            $link = trim($_POST['link'] ?? '');
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            $isActive = !empty($_POST['is_active']);
            $imageUrl = $existing['image_url'];
            try {
                if (!empty($_FILES['image']['tmp_name'])) {
                    $imageUrl = saveUploadedImage($_FILES['image'], 'banners');
                }
                updateBanner($id, $imageUrl, $text ?: null, $link, $sortOrder, $isActive);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        deleteBanner((int)($_POST['id'] ?? 0));
    }

    if ($error === '') {
        header('Location: /admin/banners');
        exit;
    }
}

$banners = getAllBanners();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Banners - Admin - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=10">
<style>
.banner-admin-list { display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem; }
.banner-admin-row { display: flex; gap: 1rem; align-items: flex-start; border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 1rem; }
.banner-admin-row img { width: 160px; height: 90px; object-fit: cover; border-radius: 6px; }
.banner-admin-fields { flex: 1; display: flex; flex-direction: column; gap: 0.4rem; }
.banner-admin-fields input[type="text"], .banner-admin-fields input[type="number"] { width: 100%; }
.banner-admin-row-actions { display: flex; gap: 0.5rem; align-items: center; }
.banner-form-new { border: 1px dashed rgba(128,128,128,0.4); border-radius: 8px; padding: 1rem; margin-bottom: 2rem; }
</style>
</head>
<body <?php include __DIR__ . '/../includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Banners</h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <div class="banner-admin-list">
        <?php foreach ($banners as $b): ?>
            <form method="post" enctype="multipart/form-data" class="banner-admin-row">
                <img src="<?= e($b['image_url']) ?>" alt="">
                <div class="banner-admin-fields">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                    <label>Replace image (optional)</label>
                    <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                    <label>Text (optional)</label>
                    <label>Link</label>
                    <input type="text" name="link" value="<?= e($b['link']) ?>" required>
                    <label>Priority weight (higher = shown more often)</label>
                    <input type="number" name="sort_order" value="<?= (int)$b['sort_order'] ?>">
                    <label><input type="checkbox" name="is_active" <?= $b['is_active'] ? 'checked' : '' ?>> Active</label>
                    <div class="banner-admin-row-actions">
                        <button class="btn" type="submit">Save</button>
                    </div>
                </div>
            </form>
            <form method="post" onsubmit="return confirm('Delete this banner?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button class="btn secondary" type="submit" style="margin-top:-1rem; margin-bottom:0.5rem;">Delete Banner #<?= (int)$b['id'] ?></button>
            </form>
        <?php endforeach; ?>
        <?php if (empty($banners)): ?><p>No banners yet.</p><?php endif; ?>
    </div>

    <h3>Add a Banner</h3>
    <form method="post" enctype="multipart/form-data" class="banner-form-new">
        <input type="hidden" name="action" value="create">
        <label>Image</label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" required>
        <label>Text (optional)</label>
        <label>Link (internal path or full URL)</label>
        <input type="text" name="link" placeholder="/register or https://scratch.mit.edu/users/ScratchNews" required>
        <label>Priority weight (higher = shown more often)</label>
        <input type="number" name="sort_order" value="0">
        <button class="btn" type="submit">Add Banner</button>
    </form>
</main>
</body>
</html>