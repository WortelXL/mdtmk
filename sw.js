// MDT service worker (fase M5) -- ontvangt Web Push-berichten en toont
// een notificatie; een tik erop opent (of activeert) de bijbehorende
// melding in MDT. Bewust minimaal: geen offline-cache/PWA-installatie,
// alleen wat push-ontvangst nodig heeft.

self.addEventListener('push', function (event) {
    var data = {};
    try {
        data = event.data ? event.data.json() : {};
    } catch (e) {
        data = {};
    }

    var titel = data.titel || 'MDT';
    var opties = {
        body: data.tekst || '',
        data: { url: data.url || '/index.php' },
    };

    event.waitUntil(self.registration.showNotification(titel, opties));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/index.php';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (vensters) {
            for (var i = 0; i < vensters.length; i++) {
                if (vensters[i].url.indexOf(url) !== -1 && 'focus' in vensters[i]) {
                    return vensters[i].focus();
                }
            }
            if (clients.openWindow) {
                return clients.openWindow(url);
            }
        })
    );
});
