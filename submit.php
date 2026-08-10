<?php
require_once __DIR__ . '/functions.php';
startSession();

if (empty($_SESSION['reader_id'])) {
    header('Location: /login');
    exit;
}

$readerId = (int)$_SESSION['reader_id'];

$db = getDB();
$stmt = $db->prepare("SELECT is_banned, email_verified, scratch_verified_at, phone_verified_at FROM users WHERE id = ?");
$stmt->bind_param("i", $readerId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$isBanned = $user && (int)$user['is_banned'] === 1;
$isPhonePending = $user && isPhoneVerificationPending($readerId);
$isVerified = $user && !$isBanned && !$isPhonePending && (
    !empty($user['scratch_verified_at']) ||
    !empty($user['phone_verified_at']) ||
    (int)$user['email_verified'] === 1
);

// Load an existing draft for editing, if requested.
$draftId = (int)($_GET['draft_id'] ?? 0);
$draft = null;
$draftCategoryIds = [];
if ($draftId > 0) {
    $draft = getUserSubmissionById($draftId, $readerId);
    if (!$draft || $draft['status'] !== 'draft') {
        header('Location: /my-articles?view=drafts');
        exit;
    }
    $draftCategoryIds = getSubmissionCategoryIds($draftId);
}

$error = '';
$success = false;
$savedDraft = false;

if ($isVerified && $_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    $title = trim($_POST['title'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    $content = $_POST['content'] ?? '';
    $categoryIds = $_POST['categories'] ?? [];
    $postedDraftId = (int)($_POST['draft_id'] ?? 0);
    $saveAsDraft = ($_POST['submit_action'] ?? '') === 'draft';
    $status = $saveAsDraft ? 'draft' : 'pending';

    $existingDraft = $postedDraftId > 0 ? getUserSubmissionById($postedDraftId, $readerId) : null;

    if ($title === '') {
        $error = 'A title is required.';
    } elseif (!$saveAsDraft && ($summary === '' || $content === '')) {
        $error = 'All fields are required to submit for review. Save as a draft if not ready.';
    } else {
        $cleanContent = $content !== '' ? sanitizeArticleHtml($content) : '';

        $imageUrl = $existingDraft['image_url'] ?? null;
        if (!empty($_FILES['cover_image']['tmp_name'])) {
            $imageUrl = saveUploadedImage($_FILES['cover_image']);
        } elseif (!empty($_POST['remove_cover_image'])) {
            $imageUrl = null;
        }

        try {
            if ($postedDraftId > 0) {
                if (!$existingDraft || $existingDraft['status'] !== 'draft') {
                    throw new RuntimeException('That draft could not be found.');
                }
                updateSubmission($postedDraftId, $title, $summary, $cleanContent, $imageUrl, $categoryIds, $status);
                $savedId = $postedDraftId;
            } else {
                $savedId = createSubmission($readerId, $title, $summary, $cleanContent, $imageUrl, $categoryIds, $status);
            }

            if ($saveAsDraft) {
                $savedDraft = true;
                $draftId = $savedId;
            } else {
                $success = true;
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }

    if ($error !== '' || $savedDraft) {
        // Re-load the draft so the form reflects what's actually saved (or keep posted values on error).
        if ($savedDraft) {
            $draft = getUserSubmissionById($draftId, $readerId);
            $draftCategoryIds = getSubmissionCategoryIds($draftId);
        } else {
            $draft = [
                'id' => $postedDraftId,
                'title' => $title,
                'summary' => $summary,
                'content' => $content,
                'image_url' => $imageUrl ?? ($existingDraft['image_url'] ?? null),
            ];
            $draftCategoryIds = array_map('intval', $categoryIds);
        }
    }
}

$allCategories = getAllCategories();
$formTitle = $draft['title'] ?? '';
$formSummary = $draft['summary'] ?? '';
$formContent = $draft['content'] ?? '';
$formImageUrl = $draft['image_url'] ?? null;
$formDraftId = (int)($draft['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Submit an Article - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=18">
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<style>
.cover-preview-wrap { display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem; }
.cover-preview-thumb { width: 120px; height: 80px; object-fit: cover; border-radius: 6px; border: 1px solid rgba(128,128,128,0.3); display: none; }
.cover-preview-thumb.has-image { display: block; }
.cover-remove-btn { background: transparent; border: 1px solid currentColor; color: inherit; opacity: 0.75; padding: 0.25rem 0.7rem; border-radius: 4px; font-size: 0.85rem; cursor: pointer; display: none; }
.cover-remove-btn.show { display: inline-block; }
.editor-copy-icon-btn { background:transparent; border:none; color:#444; opacity:0.75; padding:3px 5px; cursor:pointer; display:inline-flex; align-items:center; }
.editor-copy-icon-btn:hover { opacity:1; }
.editor-copy-icon-btn svg { width:16px; height:16px; }
body.dark .editor-copy-icon-btn { color:#ccc; }
.draft-saved-note { font-size: 0.85rem; opacity: 0.75; margin-top: -0.5rem; margin-bottom: 1rem; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/empty-header.php'; ?>
<main>
    <h2><?= $formDraftId > 0 ? 'Edit Draft' : 'Submit an Article' ?></h2>
    <p><a href="/submission-guidelines">Read our submission guidelines</a> before submitting: it covers what gets approved and what doesn't.</p>

    <?php if (!$isVerified): ?>
        <div class="alert error">
            <?php if ($isPhonePending): ?>
                Your account is pending phone verification. You'll be able to submit once an admin approves it.
            <?php else: ?>
                Your account needs to be verified before submitting an article.
                <a href="/profile">Visit your profile</a> for more info.
            <?php endif; ?>
        </div>
    <?php elseif ($success): ?>
        <div class="alert success">
            Thanks! Your submission is pending review. You'll get an email once it's approved or rejected.
        </div>
    <?php else: ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <?php if ($savedDraft): ?><div class="draft-saved-note">Draft saved. You can keep editing or find it later under <a href="/my-articles?view=drafts">My Articles &rarr; Drafts</a>.</div><?php endif; ?>
        <form method="post" id="submitForm" enctype="multipart/form-data">
            <?= csrfField() ?>
            <input type="hidden" name="draft_id" value="<?= $formDraftId ?>">
            <input type="hidden" name="remove_cover_image" id="removeCoverImageField" value="">

            <label for="title">Title</label>
            <input type="text" id="title" name="title" value="<?= e($formTitle) ?>" required>

            <label for="summary">Summary</label>
            <input type="text" id="summary" name="summary" value="<?= e($formSummary) ?>">

            <label for="cover_image">Cover Image (optional)</label>
            <div class="cover-preview-wrap">
                <img id="coverPreviewThumb" class="cover-preview-thumb <?= $formImageUrl ? 'has-image' : '' ?>" src="<?= e($formImageUrl ?? '') ?>" alt="">
                <button type="button" id="coverRemoveBtn" class="cover-remove-btn <?= $formImageUrl ? 'show' : '' ?>">Remove image</button>
            </div>
            <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">

            <label>Categories (pick up to 3)</label>
            <div class="category-checkboxes">
                <?php foreach ($allCategories as $cat): ?>
                    <label class="category-checkbox">
                        <input type="checkbox" name="categories[]" value="<?= (int)$cat['id'] ?>" class="category-cb" <?= in_array((int)$cat['id'], $draftCategoryIds, true) ? 'checked' : '' ?>>
                        <?= e($cat['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>

            <label for="content">Content</label>
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
<div id="editor-container"><?= $formContent ?></div>
</div>
<textarea id="content" name="content" style="display:none;"></textarea>

            <button class="btn" type="submit" name="submit_action" value="submit">Submit for Review</button>
            <button class="btn secondary" type="submit" name="submit_action" value="draft">Save as Draft</button>
            <a href="/my-articles?view=drafts" class="btn secondary">Cancel</a>
        </form>
    <?php endif; ?>
</main>

<footer>
    &copy; <?= e(SITE_NAME) ?> &middot; <a href="/delete-account">Delete Account</a>
</footer>

<?php if ($isVerified && !$success): ?>
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
        fetch('/upload-image', { method: 'POST', body: formData })
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

// Cover image thumbnail preview
var coverInput = document.getElementById('cover_image');
var coverThumb = document.getElementById('coverPreviewThumb');
var coverRemoveBtn = document.getElementById('coverRemoveBtn');
var removeCoverField = document.getElementById('removeCoverImageField');
coverInput.addEventListener('change', function() {
    var file = coverInput.files[0];
    if (!file) return;
    removeCoverField.value = '';
    var reader = new FileReader();
    reader.onload = function(e) {
        coverThumb.src = e.target.result;
        coverThumb.classList.add('has-image');
        coverRemoveBtn.classList.add('show');
    };
    reader.readAsDataURL(file);
});
coverRemoveBtn.addEventListener('click', function() {
    coverInput.value = '';
    coverThumb.src = '';
    coverThumb.classList.remove('has-image');
    coverRemoveBtn.classList.remove('show');
    removeCoverField.value = '1';
});

document.getElementById('submitForm').addEventListener('submit', function(e) {
    document.getElementById('content').value = quill.root.innerHTML;
});

document.querySelectorAll('.category-cb').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var checked = document.querySelectorAll('.category-cb:checked');
        if (checked.length > 3) this.checked = false;
    });
});
</script>
<?php endif; ?>
</body>
</html>