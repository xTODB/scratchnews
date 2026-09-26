<style>
.bbcode-toolbar { display: flex; gap: 0.3rem; margin-top: 0.3rem; flex-wrap: wrap; }
.bbcode-toolbar button {
    width: auto; padding: 0.3rem 0.7rem; font-size: 0.85rem; font-weight: 700;
    border-radius: 6px; border: 1px solid rgba(128,128,128,0.4);
    background: rgba(128,128,128,0.1); color: inherit; cursor: pointer;
}
.bbcode-toolbar button:hover { background: rgba(128,128,128,0.25); }
.bbcode-quote {
    border-left: 3px solid var(--brand-bright, #ffaa33);
    background: rgba(128,128,128,0.08);
    padding: 0.5rem 0.8rem; margin: 0.5rem 0; border-radius: 0 6px 6px 0;
}
.bbcode-quote-by { font-weight: 700; font-size: 0.85rem; margin-bottom: 0.2rem; opacity: 0.8; }
.bbcode-img { max-width: 100%; border-radius: 6px; margin: 0.4rem 0; }
.bbcode-preview-toggle { display: inline-flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; margin-top: 0.4rem; cursor: pointer; user-select: none; }
.bbcode-preview-toggle input { position: absolute; opacity: 0; width: 0; height: 0; }
.bbcode-preview-check { display: inline-flex; align-items: center; justify-content: center; width: 1.1rem; height: 1.1rem; border: 1px solid rgba(128,128,128,0.5); border-radius: 3px; font-size: 0.8rem; color: transparent; flex-shrink: 0; }
.bbcode-preview-toggle input:checked + .bbcode-preview-check { color: #2ecc71; border-color: #2ecc71; }
.bbcode-preview-box { border: 1px solid rgba(128,128,128,0.3); border-radius: 8px; padding: 0.8rem; margin-top: 0.5rem; background: rgba(128,128,128,0.05); }
.bbcode-preview-box::before { content: "Preview"; display: block; font-size: 0.75rem; font-weight: 700; color: #888; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.03em; }
</style>
<script>
function initBBCodePreview(textareaId, checkboxId, boxId) {
    var ta = document.getElementById(textareaId);
    var cb = document.getElementById(checkboxId);
    var box = document.getElementById(boxId);
    if (!ta || !cb || !box) return;
    var timer = null;
    function refresh() {
        if (!cb.checked) { box.style.display = 'none'; return; }
        box.style.display = 'block';
        fetch('/bbcode-preview', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'content=' + encodeURIComponent(ta.value)
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { box.innerHTML = data.html || '<em>Nothing to preview yet.</em>'; })
        .catch(function () { box.innerHTML = '<em>Preview failed to load.</em>'; });
    }
    cb.addEventListener('change', refresh);
    ta.addEventListener('input', function () {
        if (!cb.checked) return;
        clearTimeout(timer);
        timer = setTimeout(refresh, 500);
    });
}
function bbcodeWrap(textareaId, before, after) {
    var ta = document.getElementById(textareaId);
    var start = ta.selectionStart, end = ta.selectionEnd;
    var selected = ta.value.substring(start, end);
    ta.value = ta.value.substring(0, start) + before + selected + after + ta.value.substring(end);
    ta.focus();
    ta.selectionStart = start + before.length;
    ta.selectionEnd = start + before.length + selected.length;
}
function bbcodeLink(textareaId) {
    var url = prompt('Link URL (https://...)');
    if (!url) return;
    bbcodeWrap(textareaId, '[url=' + url + ']', '[/url]');
}
function bbcodeImage(textareaId) {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = function () {
        if (!input.files.length) return;
        var fd = new FormData();
        fd.append('image', input.files[0]);
        var ta = document.getElementById(textareaId);
        var pos = ta.selectionStart;
        var placeholder = '[uploading image...]';
        ta.value = ta.value.substring(0, pos) + placeholder + ta.value.substring(pos);
        fetch('/upload-image', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var tag = data.url ? ('[img]' + data.url + '[/img]') : '[image upload failed]';
                ta.value = ta.value.replace(placeholder, tag);
            })
            .catch(function () { ta.value = ta.value.replace(placeholder, '[image upload failed]'); });
    };
    input.click();
}
</script>
<?php
function renderBBCodeToolbar(string $textareaId): void {
    ?>
    <div class="bbcode-toolbar">
        <button type="button" onclick="bbcodeWrap('<?= e($textareaId) ?>','[b]','[/b]')" title="Bold"><b>B</b></button>
        <button type="button" onclick="bbcodeWrap('<?= e($textareaId) ?>','[i]','[/i]')" title="Italic"><i>I</i></button>
        <button type="button" onclick="bbcodeWrap('<?= e($textareaId) ?>','[u]','[/u]')" title="Underline"><u>U</u></button>
        <button type="button" onclick="bbcodeWrap('<?= e($textareaId) ?>','[quote]','[/quote]')" title="Quote">&#8220;&#8221;</button>
        <button type="button" onclick="bbcodeLink('<?= e($textareaId) ?>')" title="Link">&#128279;</button>
        <button type="button" onclick="bbcodeImage('<?= e($textareaId) ?>')" title="Insert Image">&#128247;</button>
    </div>
    <?php
}

// v0.28 - Scratch-style preview: a checkbox that reveals a live-rendered
// preview box below the textarea, using the same renderBBCode() the site
// uses for the real post (via the /bbcode-preview endpoint).
function renderBBCodePreviewToggle(string $textareaId): void {
    $cbId = 'preview_cb_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $textareaId);
    $boxId = 'preview_box_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $textareaId);
    ?>
    <label class="bbcode-preview-toggle" for="<?= e($cbId) ?>">
        <input type="checkbox" id="<?= e($cbId) ?>">
        <span class="bbcode-preview-check">&#10003;</span> Show preview
    </label>
    <div id="<?= e($boxId) ?>" class="bbcode-preview-box" style="display:none;"></div>
    <script>initBBCodePreview('<?= e($textareaId) ?>', '<?= e($cbId) ?>', '<?= e($boxId) ?>');</script>
    <?php
}