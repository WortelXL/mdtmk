// MDT — service worker voor pushmeldingen (fase M5). Doet verder niets
// (geen offline-cache/PWA-gedrag) -- alleen wat een browser nodig heeft
// om `push`- en `notificationclick`-events te kunnen afleveren.

self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('push', function (event) {
    var data = { titel: 'MDT', tekst: 'Nieuwe melding', url: '/index.php' };
    try {
        if (event.data) {
            var ontvangen = event.data.json();
            data.titel = ontvangen.titel || data.titel;
            data.tekst = ontvangen.tekst || data.tekst;
            data.url = ontvangen.url || data.url;
        }
    } catch (e) {
        // Geen geldige JSON -- val terug op de standaardtekst hierboven.
    }

    event.waitUntil(
        self.registration.showNotification(data.titel, {
            body: data.tekst,
            data: { url: data.url },
            tag: 'mdt-melding',
            renotify: true,
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var url = (event.notification.data && event.notification.data.url) || '/index.php';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                var client = clientList[i];
                if ('focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            if (self.clients.openWindow) {
                return self.clients.openWindow(url);
            }
        })
    );
});
