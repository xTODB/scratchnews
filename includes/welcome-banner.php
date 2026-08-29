<?php if (empty($_SESSION['reader_username'])): ?>
<section class="welcome-banner">
    <div class="welcome-banner-text">
        <h1>Welcome to ScratchNews</h1>
        <p>ScratchNews is a place for Scratchers to connect and a place to get informed about the latest Scratch News.</p>
        <div class="welcome-banner-actions">
            <a href="/explore" class="btn inline welcome-banner-btn">Explore Articles</a>
            <button type="button" id="welcomeNotifyBtn" class="btn inline welcome-banner-btn" data-vapid-key="<?= e(getApiSetting('vapid_public_key', '')) ?>">Notify Me</button>
            <a href="/register" class="btn inline welcome-banner-btn welcome-banner-join">Join</a>
        </div>
        <p class="welcome-notify-status" id="welcomeNotifyStatus"></p>
    </div>
    <div class="welcome-banner-media">
        <img src="/assets/welcome-banner.gif" alt="" class="welcome-banner-img">
    </div>
</section>
<script>
(function() {
    var btn = document.getElementById('welcomeNotifyBtn');
    var status = document.getElementById('welcomeNotifyStatus');
    if (!btn) return;

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
        return outputArray;
    }

    function showCustomizeLink() {
        status.innerHTML = 'Notifications on. <a href="/notify-preferences">Customize categories</a>';
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        btn.style.display = 'none';
        return;
    }

    // Reflect existing subscription state on load, without prompting for permission.
    navigator.serviceWorker.getRegistration('/sw.js').then(function(reg) {
        if (!reg) return;
        return reg.pushManager.getSubscription();
    }).then(function(sub) {
        if (sub) {
            btn.textContent = 'Notifications On';
            btn.disabled = true;
            showCustomizeLink();
        }
    }).catch(function() {});

    btn.addEventListener('click', function() {
        var vapidKey = btn.getAttribute('data-vapid-key');
        if (!vapidKey) {
            status.textContent = "Notifications aren't set up yet - check back soon.";
            return;
        }
        btn.disabled = true;
        btn.textContent = 'Working...';
        Notification.requestPermission().then(function(permission) {
            if (permission !== 'granted') {
                btn.disabled = false;
                btn.textContent = 'Notify Me';
                status.textContent = 'Notifications need to be allowed in your browser to turn this on.';
                return Promise.reject('denied');
            }
            return navigator.serviceWorker.register('/sw.js');
        }).then(function() {
            return navigator.serviceWorker.ready;
        }).then(function(reg) {
            return reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(vapidKey)
            });
        }).then(function(sub) {
            return fetch('/push-subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(Object.assign({ action: 'subscribe', categoryIds: [] }, sub.toJSON()))
            });
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (res.ok) {
                btn.textContent = 'Notifications On';
                showCustomizeLink();
            } else {
                btn.disabled = false;
                btn.textContent = 'Notify Me';
                status.textContent = 'Something went wrong turning notifications on - try again.';
            }
        }).catch(function(err) {
            if (err !== 'denied') {
                btn.disabled = false;
                btn.textContent = 'Notify Me';
                status.textContent = "Couldn't turn on notifications - try again.";
            }
        });
    });
})();
</script>
<?php endif; ?>