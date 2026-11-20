<?php

header('Content-Type: application/javascript; charset=utf-8');
header('Service-Worker-Allowed: /');
header('Cache-Control: no-cache, no-store, must-revalidate');
?>
self.addEventListener('push', function (event) {
    let data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = { title: 'WHMCS Notification', body: event.data ? event.data.text() : '' };
    }
    const title = data.title || 'WHMCS Notification';
    const options = {
        body: data.body || '',
        icon: data.icon || '',
        data: { url: data.url || '/' },
        renotify: true
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const target = new URL(event.notification.data.url || '/', self.location.origin).href;
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (windows) {
        for (const client of windows) {
            if (client.url === target && 'focus' in client) return client.focus();
        }
        if (clients.openWindow) return clients.openWindow(target);
    }));
});
