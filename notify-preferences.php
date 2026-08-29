<?php
require_once __DIR__ . '/functions.php';
startSession();
sendNoCacheHeaders();
$categories = getAllCategories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="/assets/favicon.svg">
<title>Notification Preferences - <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="/assets/style.css?v=21">
<style>
.notify-prefs-box { max-width: 480px; }
.notify-cat-row { display: flex; align-items: center; gap: 0.6rem; padding: 0.6rem 0; border-bottom: 1px solid rgba(128,128,128,0.2); }
.notify-cat-row:last-child { border-bottom: none; }
.notify-prefs-note { font-size: 0.85rem; opacity: 0.75; margin: 0.75rem 0 1.25rem; }
</style>
</head>
<body <?php include __DIR__ . '/includes/theme-body.php'; ?>>
<script>if(document.body.hasAttribute('data-theme-auto')&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches){document.body.classList.add('dark');}</script>
<?php include __DIR__ . '/includes/header.php'; ?>
<main>
    <h2>Notification Preferences</h2>
    <div class="notify-prefs-box">
        <div id="notifyPrefsLoading">Checking your notification status...</div>
        <div id="notifyPrefsNotSubscribed" style="display:none;">
            <p>You don't have notifications turned on in this browser yet.</p>
            <button type="button" class="btn" id="notifyPrefsSubscribeBtn" data-vapid-key="<?= e(getApiSetting('vapid_public_key', '')) ?>">Turn On Notifications</button>
        </div>
        <div id="notifyPrefsForm" style="display:none;">
            <p class="notify-prefs-note">Choose which categories to get notified about. Leave everything unchecked to get notified about every new article.</p>
            <div id="notifyCatList">
                <?php foreach ($categories as $cat): ?>
                    <label class="notify-cat-row">
                        <input type="checkbox" class="notify-cat-checkbox" value="<?= (int)$cat['id'] ?>">
                        <?= e($cat['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:1rem; display:flex; gap:0.6rem; flex-wrap:wrap;">
                <button type="button" class="btn" id="notifyPrefsSaveBtn">Save</button>
                <button type="button" class="btn secondary" id="notifyPrefsTurnOffBtn">Turn Off Notifications</button>
            </div>
            <p class="welcome-notify-status" id="notifyPrefsStatus"></p>
        </div>
    </div>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
(function() {
    var loading = document.getElementById('notifyPrefsLoading');
    var notSub = document.getElementById('notifyPrefsNotSubscribed');
    var form = document.getElementById('notifyPrefsForm');
    var status = document.getElementById('notifyPrefsStatus');
    var subscribeBtn = document.getElementById('notifyPrefsSubscribeBtn');
    var saveBtn = document.getElementById('notifyPrefsSaveBtn');
    var turnOffBtn = document.getElementById('notifyPrefsTurnOffBtn');

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) outputArray[i] = rawData.charCodeAt(i);
        return outputArray;
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        loading.textContent = "Your browser doesn't support notifications.";
        return;
    }

    function loadFormFor(sub) {
        loading.style.display = 'none';
        notSub.style.display = 'none';
        form.style.display = 'block';
        fetch('/push-subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_categories', endpoint: sub.endpoint })
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (res.ok && res.categoryIds) {
                document.querySelectorAll('.notify-cat-checkbox').forEach(function(cb) {
                    cb.checked = res.categoryIds.indexOf(parseInt(cb.value, 10)) !== -1;
                });
            }
        });

        saveBtn.addEventListener('click', function() {
            var ids = Array.prototype.slice.call(document.querySelectorAll('.notify-cat-checkbox:checked')).map(function(cb) { return parseInt(cb.value, 10); });
            saveBtn.disabled = true;
            fetch('/push-subscribe.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_categories', endpoint: sub.endpoint, categoryIds: ids })
            }).then(function(r) { return r.json(); }).then(function(res) {
                saveBtn.disabled = false;
                status.textContent = res.ok ? 'Saved.' : 'Something went wrong saving that.';
            }).catch(function() {
                saveBtn.disabled = false;
                status.textContent = 'Something went wrong saving that.';
            });
        });

        turnOffBtn.addEventListener('click', function() {
            turnOffBtn.disabled = true;
            sub.unsubscribe().then(function() {
                return fetch('/push-subscribe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'unsubscribe', endpoint: sub.endpoint })
                });
            }).then(function() {
                form.style.display = 'none';
                notSub.style.display = 'block';
                status.textContent = '';
            }).catch(function() {
                turnOffBtn.disabled = false;
                status.textContent = "Couldn't turn off notifications - try again.";
            });
        });
    }

    navigator.serviceWorker.getRegistration('/sw.js').then(function(reg) {
        return reg ? reg.pushManager.getSubscription() : null;
    }).then(function(sub) {
        if (sub) {
            loadFormFor(sub);
        } else {
            loading.style.display = 'none';
            notSub.style.display = 'block';
        }
    }).catch(function() {
        loading.style.display = 'none';
        notSub.style.display = 'block';
    });

    subscribeBtn.addEventListener('click', function() {
        var vapidKey = subscribeBtn.getAttribute('data-vapid-key');
        if (!vapidKey) {
            status.textContent = "Notifications aren't set up yet - check back soon.";
            return;
        }
        subscribeBtn.disabled = true;
        Notification.requestPermission().then(function(permission) {
            if (permission !== 'granted') return Promise.reject('denied');
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
            }).then(function() { return sub; });
        }).then(function(sub) {
            notSub.style.display = 'none';
            loadFormFor(sub);
        }).catch(function(err) {
            subscribeBtn.disabled = false;
            if (err !== 'denied') {
                document.getElementById('notifyPrefsStatus').textContent = "Couldn't turn on notifications - try again.";
            }
        });
    });
})();
</script>
</body>
</html>
