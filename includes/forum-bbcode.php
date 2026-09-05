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
</style>
<script>
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
