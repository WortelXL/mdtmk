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
    var data = { titel: 'MDT', tekst: 'Nieuwe melding', url: '/index.php', kritiek: false, melding_id: 0, test: false };
    try {
        if (event.data) {
            var ontvangen = event.data.json();
            data.titel = ontvangen.titel || data.titel;
            data.tekst = ontvangen.tekst || data.tekst;
            data.url = ontvangen.url || data.url;
            data.kritiek = !!ontvangen.kritiek;
            data.melding_id = parseInt(ontvangen.melding_id, 10) || 0;
            data.test = !!ontvangen.test;
        }
    } catch (e) {
        // Geen geldige JSON -- val terug op de standaardtekst hierboven.
    }

    var opties = {
        body: data.tekst,
        icon: '/assets/icon-192.png',
        data: { url: data.url },
        // Kritiek: eigen tag per melding, blijft staan tot je er iets mee doet
        // (Android/desktop; iOS negeert requireInteraction en vibratie).
        tag: data.kritiek ? 'mdt-kritiek-' + data.melding_id : 'mdt-melding',
        renotify: true,
    };
    if (data.kritiek) {
        opties.requireInteraction = true;
        opties.vibrate = [400, 150, 400, 150, 400, 150, 800];
    }

    // Staat MDT open? Dan krijgt de pagina ook een bericht, zodat die het
    // geluid uit de instellingen kan afspelen (een service worker kan zelf
    // geen geluid maken). De systeemmelding tonen we altijd -- iOS eist dat.
    var berichtNaarPaginas = self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (lijst) {
        lijst.forEach(function (client) {
            client.postMessage({
                type: 'mdt-push',
                kritiek: data.kritiek,
                melding_id: data.melding_id,
                titel: data.titel,
                tekst: data.tekst,
                url: data.url,
                test: data.test,
            });
        });
    });

    event.waitUntil(Promise.all([
        self.registration.showNotification(data.titel, opties),
        berichtNaarPaginas,
    ]));
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
