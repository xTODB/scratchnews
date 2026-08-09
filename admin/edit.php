<?php
require_once __DIR__ . '/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = getArticleById($id);

if (!$article) {
    header('Location: /admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $author = trim($_POST['author'] ?? 'ScratchNews Staff');

    $status = ($_POST['status'] ?? 'published') === 'draft' ? 'draft' : 'published';

    if ($title === '') {
        $error = 'A title is required.';
    } elseif ($status === 'published' && $content === '') {
        $error = 'Content is required to publish. Save as draft if not ready.';
    } else {
        try {
            $imageUrl = $article['image_url'] ?? null;
            if (!empty($_POST['remove_image']) && $imageUrl) {
                deleteUploadedImage($imageUrl);
                $imageUrl = null;
            }
            if (!empty($_FILES['cover_image']['tmp_name'])) {
                $newUrl = saveUploadedImage($_FILES['cover_image']);
                if ($newUrl) {
                    if ($imageUrl) deleteUploadedImage($imageUrl);
                    $imageUrl = $newUrl;
                }
            }
            $userId = ($_POST['user_id'] ?? '') !== '' ? (int)$_POST['user_id'] : null;
            updateArticle($id, $title, $summary, $content, $author ?: 'ScratchNews Staff', $imageUrl, $status, $userId);
            header('Location: /admin/?updated=' . $id);
            exit;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
    $userId = ($_POST['user_id'] ?? '') !== '' ? (int)$_POST['user_id'] : null;
    $article = ['id' => $id, 'title' => $title, 'summary' => $summary, 'content' => $content, 'author' => $author, 'image_url' => $imageUrl ?? null, 'status' => $status, 'user_id' => $userId];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Edit Article - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=2">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<style>
.editor-copy-icon-btn { background:transparent; border:none; color:#444; opacity:0.75; padding:3px 5px; cursor:pointer; display:inline-flex; align-items:center; }
.editor-copy-icon-btn:hover { opacity:1; }
.editor-copy-icon-btn svg { width:16px; height:16px; }
body.dark .editor-copy-icon-btn { color:#ccc; }
</style>
</head>
<body class="<?= !empty($_SESSION['dark_mode']) ? 'dark' : '' ?>">
<?php require_once __DIR__ . '/nav.php'; ?>
<main>
    <h2>Edit Article #<?= (int)$id ?></h2>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= e($article['title']) ?>" required>

        <label for="summary">Summary (shown on homepage)</label>
        <input type="text" id="summary" name="summary" value="<?= e($article['summary']) ?>">

        <label for="author">Author</label>
        <input type="text" id="author" name="author" value="<?= e($article['author']) ?>">

        <label for="user_id">Attribute to User</label>
        <?php $db = getDB(); $userList = $db->query("SELECT id, username FROM users ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC); $currentUserId = (int)($article['user_id'] ?? 0); ?>
        <select id="user_id" name="user_id">
            <option value="">— None —</option>
            <?php foreach ($userList as $u): ?>
                <option value="<?= (int)$u['id'] ?>" <?= (int)$u['id'] === $currentUserId ? 'selected' : '' ?>><?= e($u['username']) ?> (#<?= (int)$u['id'] ?>)</option>
            <?php endforeach; ?>
        </select>

        <label for="cover_image">Cover Image (optional)</label>
        <?php if (!empty($article['image_url'])): ?>
            <div class="cover-preview">
                <img src="<?= e($article['image_url']) ?>" alt="" style="max-width:200px;display:block;margin-bottom:8px;">
                <label><input type="checkbox" name="remove_image" value="1"> Remove image</label>
            </div>
        <?php endif; ?>
        <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">

        <label for="content">Full Article Content</label>
<div id="editorWrap">
<div id="toolbar">
    <button class="ql-bold" title="Bold (Ctrl+B)"><b>B</b></button>
    <button class="ql-italic" title="Italic (Ctrl+I)"><i>I</i></button>
    <button class="ql-strike" title="Strikethrough"><s>S</s></button>
    <select class="ql-header" title="Heading">
        <option value="1">Heading 1</option>
        <option value="2">Heading 2</option>
        <option value="3">Heading 3</option>
        <option selected value="">Normal</option>
    </select>
    <select class="ql-color" title="Text color"></select>
    <select class="ql-background" title="Highlight color"></select>
    <select class="ql-size" title="Text size">
        <option value="small">Small</option>
        <option selected value="">Normal</option>
        <option value="large">Large</option>
        <option value="huge">Huge</option>
    </select>
    <button class="ql-link" title="Insert link">🔗</button>
    <button class="ql-image" title="Insert image">🖼️</button>
    <span style="float:right;">
        <button type="button" id="copyContentBtn" class="editor-copy-icon-btn" title="Copy selected text (with formatting) to clipboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V6a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2h2"/></svg>
        </button>
        <button type="button" id="resetFormattingBtn" class="editor-copy-icon-btn" title="Clear formatting from selected text">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/></svg>
        </button>
        <button type="button" id="toggleToolbarPos" title="Move formatting bar to bottom">⇕</button>
    </span>
</div>
<div id="editor-container"><?= $article['content'] ?></div>
</div>
<textarea id="content" name="content" style="display:none;"></textarea>
        <button class="btn" type="submit" name="status" value="published">Publish</button>
        <button class="btn secondary" type="submit" name="status" value="draft">Save as Draft</button>
        <a href="/admin/" class="btn secondary">Cancel</a>
    </form>
</main>
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
<script>
var quill = new Quill('#editor-container', {
    theme: 'snow',
    modules: { toolbar: '#toolbar' }
});
quill.getModule('toolbar').addHandler('image', function() {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function() {
        var file = input.files[0];
        if (!file) return;
        var formData = new FormData();
        formData.append('image', file);
        fetch('/admin/upload-image', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.url) {
                    var range = quill.getSelection(true);
                    quill.insertEmbed(range.index, 'image', data.url);
                } else {
                    alert(data.error || 'Upload failed.');
                }
            });
    };
    input.click();
});
document.getElementById('copyContentBtn').addEventListener('click', function() {
    var btn = this;
    var range = quill.getSelection();
    var html, text;
    if (range && range.length > 0) {
        html = quill.getSemanticHTML(range.index, range.length);
        text = quill.getText(range.index, range.length);
    } else {
        html = quill.root.innerHTML;
        text = quill.getText();
    }
    function showCopied() {
        var original = btn.title;
        btn.title = 'Copied!';
        btn.style.opacity = '1';
        setTimeout(function() { btn.title = original; }, 1200);
    }
    function fallbackPlainText() {
        navigator.clipboard.writeText(text).then(showCopied).catch(function() {
            alert('Could not copy. Select the editor text manually instead.');
        });
    }
    if (window.ClipboardItem) {
        var item = new ClipboardItem({
            'text/html': new Blob([html], { type: 'text/html' }),
            'text/plain': new Blob([text], { type: 'text/plain' })
        });
        navigator.clipboard.write([item]).then(showCopied).catch(fallbackPlainText);
    } else {
        fallbackPlainText();
    }
});
document.getElementById('resetFormattingBtn').addEventListener('click', function() {
    var range = quill.getSelection();
    if (!range || range.length === 0) {
        alert('Select the text you want to clear formatting from first.');
        return;
    }
    quill.removeFormat(range.index, range.length);
});
document.getElementById('toggleToolbarPos').addEventListener('click', function() {
    var wrap = document.getElementById('editorWrap');
    var toolbar = document.getElementById('toolbar');
    var editor = document.getElementById('editor-container');
    if (toolbar.nextElementSibling === editor) {
        wrap.appendChild(toolbar);
        this.title = 'Move formatting bar to top';
    } else {
        wrap.insertBefore(toolbar, editor);
        this.title = 'Move formatting bar to bottom';
    }
});
document.querySelector('form').addEventListener('submit', function() {
    document.querySelector('#content').value = quill.root.innerHTML;
});
</script>
</body>
</html>