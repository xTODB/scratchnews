<?php
require_once __DIR__ . '/auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = getArticleById($id);

if (!$article) {
    header('Location: /admin/');
    exit;
}

$currentUser = getUserById((int)($_SESSION['reader_id'] ?? 0));
$autosaveEnabled = $currentUser ? !empty($currentUser['autosave_enabled']) : true;
$autosaveInterval = $currentUser ? (int)($currentUser['autosave_interval'] ?? 30) : 30;
$autocolorLinks = $currentUser ? !empty($currentUser['autocolor_links']) : true;
// Autosave only applies while this article is still a draft. Autosaving an edit to an
// already-published article would either silently fail (autosaveArticle() refuses to
// touch a non-draft row) or, worse, show half-finished edits to the public before the
// Publish button is ever clicked - so it's disabled outright here instead.
$autosaveApplies = $article['status'] === 'draft';

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
<link rel="stylesheet" href="/assets/style.css?v=18">
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<style>
.editor-copy-icon-btn { background:transparent; border:none; color:#444; opacity:0.75; padding:3px 5px; cursor:pointer; display:inline-flex; align-items:center; }
.editor-copy-icon-btn:hover { opacity:1; }
.editor-copy-icon-btn svg { width:16px; height:16px; }
body.dark .editor-copy-icon-btn { color:#ccc; }
#autosaveBtn { transition: color 1.5s ease; }
#autosaveBtn.just-saved { color: #2a8a4a; opacity: 1; transition: color 0.15s ease; }
body.dark #autosaveBtn.just-saved { color: #7fdb8f; }
#autosaveBtn[disabled] { opacity: 0.35; cursor: default; }
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

        <label for="content">Full Article Content <span id="wordCountLabel" style="font-weight:normal;font-size:0.85em;">0 words</span></label>
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
        <button type="button" id="resetFormattingBtn" class="editor-copy-icon-btn" title="Clear formatting">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/></svg>
        </button>
        <button type="button" id="autosaveBtn" class="editor-copy-icon-btn" <?= $autosaveApplies ? '' : 'disabled' ?> title="<?= $autosaveApplies ? 'Save now' : 'Autosave is off while editing a published article - click Publish to save changes' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
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
    var range = quill.getSelection(true);
    if (!range) return;
    if (range.length > 0) {
        quill.removeFormat(range.index, range.length);
        return;
    }
    // No selection: clear the formats active at the cursor so text typed from here
    // on comes out plain, the same way toggling Bold with the cursor collapsed
    // affects only what you type next instead of requiring a selection.
    var formats = quill.getFormat(range.index);
    Object.keys(formats).forEach(function(name) {
        quill.format(name, false);
    });
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
var autosaveEnabled = <?= $autosaveEnabled ? 'true' : 'false' ?>;
var autosaveInterval = <?= (int)$autosaveInterval ?>; // seconds; 0 = save shortly after you stop typing
var autocolorLinks = <?= $autocolorLinks ? 'true' : 'false' ?>;
var autosaveApplies = <?= $autosaveApplies ? 'true' : 'false' ?>;
var autosaveDraftId = <?= (int)$id ?>;
var autosaveInFlight = false;
var autosaveIdleTimer = null;

if (autocolorLinks) {
    quill.on('text-change', function(delta) {
        var index = 0;
        var toColor = [];
        delta.ops.forEach(function(op) {
            var len = 0;
            if (typeof op.insert === 'string') len = op.insert.length;
            else if (op.insert !== undefined) len = 1;
            else if (typeof op.retain === 'number') len = op.retain;
            if (op.attributes && op.attributes.link && !op.attributes.color) {
                toColor.push({ index: index, length: len });
            }
            if (op.insert !== undefined || typeof op.retain === 'number') index += len;
        });
        if (toColor.length) {
            toColor.forEach(function(range) {
                quill.formatText(range.index, range.length, 'color', '#1155cc', 'silent');
            });
        }
    });
}

function flashAutosaveSaved() {
    var btn = document.getElementById('autosaveBtn');
    btn.classList.add('just-saved');
    setTimeout(function() { btn.classList.remove('just-saved'); }, 1500);
}

function doAutosave() {
    if (!autosaveApplies || autosaveInFlight) return;
    var title = document.getElementById('title').value.trim();
    var textOnly = quill.getText().trim();
    if (title === '' && textOnly === '') return;
    autosaveInFlight = true;
    var formData = new FormData();
    formData.append('id', autosaveDraftId);
    formData.append('title', title);
    formData.append('summary', document.getElementById('summary').value.trim());
    formData.append('author', document.getElementById('author').value.trim());
    formData.append('user_id', document.getElementById('user_id').value);
    formData.append('content', quill.root.innerHTML);
    fetch('/admin/autosave', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            autosaveInFlight = false;
            if (data.saved) flashAutosaveSaved();
        })
        .catch(function() { autosaveInFlight = false; });
}

document.getElementById('autosaveBtn').addEventListener('click', doAutosave);

if (autosaveEnabled && autosaveApplies) {
    if (autosaveInterval > 0) {
        setInterval(doAutosave, autosaveInterval * 1000);
    } else {
        quill.on('text-change', function(delta, oldDelta, source) {
            if (source !== 'user') return;
            if (autosaveIdleTimer) clearTimeout(autosaveIdleTimer);
            autosaveIdleTimer = setTimeout(doAutosave, 2000);
        });
    }
}

document.querySelector('form').addEventListener('submit', function() {
    document.querySelector('#content').value = quill.root.innerHTML;
});
</script>
</body>
</html>