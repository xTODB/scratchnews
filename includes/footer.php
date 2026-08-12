<footer class="site-footer">
    <div class="footer-top">
        <div class="footer-logo">
<svg viewBox="0,0,136.90609,31.33279" class="footer-logo-svg" xmlns="http://www.w3.org/2000/svg"><g transform="translate(-172.33196,-164.33361)"><g stroke-miterlimit="10"><text transform="translate(217.16809,185.696) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="#ffaa33" stroke-width="3" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><text transform="translate(217.16809,185.696) scale(0.5,0.5)" font-size="40" fill="#ffffff" stroke="none" stroke-width="1" font-family="Scratch" font-weight="normal" text-anchor="start"><tspan x="0" dy="0">ScratchNews</tspan></text><path d="M181.04509,195.6488h-8.71313v-10.6397h8.71313z" fill="#cc8829" stroke="none"/><path d="M176.88046,195.66641v-20.46677l3.90302,-0.07587l0.09189,-5.0222h6.64479v20.71239l-3.91923,0.16783l-0.04923,4.68462z" fill="#ffaa33" stroke="none"/><path d="M201.4019,164.35123h8.71313v10.6397h-8.71313z" fill="#cc8829" stroke="none"/><path d="M205.56654,164.33362v20.46677l-3.90302,0.07587l-0.09189,5.0222h-6.64479v-20.71239l3.91923,-0.16783l0.04923,-4.68462z" fill="#ffaa33" stroke="none"/><path d="M190.0646,189.91167l-0.03808,-3.62362l-3.03158,-0.12982v-16.02128h5.13983l0.07108,3.88473l3.01904,0.05869v15.8313z" fill="#ffaa33" stroke="none"/></g></g></svg>
        </div>
        <div class="footer-columns">
            <div class="footer-col">
                <h4>Info</h4>
                <a href="/about">About</a>
                <a href="/changelog">Changelog</a>
                <a href="/community-guidelines">Community Guidelines</a>
            </div>
            <div class="footer-col">
                <h4>Developers</h4>
                <a href="/api.php">API</a>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                <a href="/feedback">Feedback</a>
                <?php if (!empty($_SESSION['reader_username'])): ?><a href="/delete-account">Delete Account</a><?php endif; ?>
            </div>
            <div class="footer-col">
                <h4>More</h4>
                <a href="/random-article">Random Article</a>
                <a href="https://ko-fi.com/scratchnews">Donate</a>
                <a href="/download">Download</a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="footer-copyright">&copy; <?= e(SITE_NAME) ?> v<?= e(SITE_VERSION) ?></div>
        <div class="footer-language">
            <form method="post" action="/set-language.php" id="footerLangForm">
                <?= csrfField() ?>
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI'] ?? '/') ?>">
                <label for="footerLangSelect" class="visually-hidden">Translate articles</label>
                <select name="translate_lang" id="footerLangSelect" onchange="document.getElementById('footerLangForm').submit();">
                    <option value="" <?= getTranslateTarget() === '' ? 'selected' : '' ?>>🌐 English (original)</option>
                    <?php foreach (translateLanguageOptions() as $code => $label): ?>
                        <option value="<?= e($code) ?>" <?= getTranslateTarget() === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
</footer>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('time.local-date, time.local-datetime').forEach(function(el) {
        var d = new Date(el.getAttribute('datetime'));
        if (isNaN(d.getTime())) return;
        if (el.classList.contains('local-datetime')) {
            el.textContent = d.toLocaleString(undefined, { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
        } else {
            el.textContent = d.toLocaleDateString(undefined, { year: 'numeric', month: 'long', day: 'numeric' });
        }
    });
});
</script>
<script>
(function() {
    var KEY_NAME = 'sn_session_key';
    var SRC_NAME = 'sn_session_source';
    var INTERVAL_MS = 15000;
    var key = sessionStorage.getItem(KEY_NAME);
    if (!key) {
        key = crypto.randomUUID ? crypto.randomUUID().replace(/-/g, '') : (Date.now().toString(16) + Math.random().toString(16).slice(2));
        sessionStorage.setItem(KEY_NAME, key);
    }
    if (sessionStorage.getItem(SRC_NAME) === null) {
        var urlSrc = new URLSearchParams(window.location.search).get('src');
        sessionStorage.setItem(SRC_NAME, urlSrc || '');
    }
    var source = sessionStorage.getItem(SRC_NAME);
    function ping() {
        if (document.hidden) return;
        var data = new URLSearchParams({ session_key: key, source: source });
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/heartbeat', data);
        } else {
            fetch('/heartbeat', { method: 'POST', body: data, keepalive: true });
        }
    }
    setInterval(ping, INTERVAL_MS);
})();
</script>