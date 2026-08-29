// ScratchNews service worker - push notifications only, no offline caching.
// Must live at site root so its default scope covers the whole site.

self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'ScratchNews', body: event.data ? event.data.text() : '' };
    }
    var title = data.title || 'ScratchNews';
    var options = {
        body: data.body || '',
        icon: '/assets/favicon.svg',
        badge: '/assets/favicon.svg',
        data: { url: data.url || '/' }
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                if (clientList[i].url.indexOf(url) !== -1 && 'focus' in clientList[i]) {
                    return clientList[i].focus();
                }
            }
            if (clients.openWindow) return clients.openWindow(url);
        })
    );
});
