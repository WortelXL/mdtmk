<?php
/**
 * Instellingen (V0.0.19): pushmeldingen aan/uit per apparaat (verhuisd van
 * index.php), kritieke push voor iedereen, testmelding en geluid in de app.
 * Pushvoorkeuren staan in de database (push_abonnementen, per apparaat); de
 * geluidsinstellingen in localStorage van dit apparaat (zie assets/mdt-geluid.js).
 * V0.0.22: ook "Verversen" (automatisch verversen aan/uit + hoe vaak, zie
 * assets/mdt-verversen.js).
 */
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$push_ingesteld = VAPID_PUBLIC_KEY !== '';
$voorkeuren_ok = push_voorkeuren_beschikbaar($pdo);

$actief_nav = 'instellingen';
$paginatitel = 'Instellingen';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Instellingen</h1>
    <p>Deze keuzes gelden voor dit apparaat. Op een ander apparaat kun je andere keuzes maken.</p>
</div>

<div class="panel set-sec" id="push-panel">
    <h2>Pushmeldingen <span class="badge-ok" id="push-badge" hidden>Aan</span></h2>

    <?php if (!$push_ingesteld): ?>
        <p class="sub">Pushmeldingen zijn nog niet ingesteld op deze MDT-server (VAPID-sleutels ontbreken). Vraag de beheerder.</p>
    <?php else: ?>
        <p class="sub">Een melding op je scherm, ook als MDT dicht staat.</p>

        <div class="set-note set-note-warn" id="push-ios-installeren" hidden>
            <strong>Eerst op je beginscherm zetten.</strong> Op iPhone en iPad werken pushmeldingen alleen als MDT als app op je beginscherm staat:
            tik op <em>Deel</em> en kies <em>Zet op beginscherm</em>, open MDT daarna via dat nieuwe icoon en kom hier terug.
        </div>

        <div class="set-row">
            <div class="lbl">Pushmeldingen op dit apparaat<small>Hoofdschakelaar. Zonder deze aan krijg je geen pushmeldingen.</small></div>
            <label class="toggle-switch"><input type="checkbox" id="push-toggle" disabled><span class="slider"></span></label>
        </div>
        <div class="set-row">
            <div class="lbl">Melding bij toewijzing<small>Zodra een melding aan jou of je team wordt toegewezen.</small></div>
            <label class="toggle-switch"><input type="checkbox" id="push-toewijzing" disabled><span class="slider"></span></label>
        </div>
        <div class="set-row">
            <div class="lbl">Kritieke meldingen voor iedereen<small>Bij elke nieuwe kritieke melding, ook als die niet aan jou is toegewezen. De titel begint dan met 🔴 KRITIEK.</small></div>
            <label class="toggle-switch"><input type="checkbox" id="push-kritiek" disabled><span class="slider"></span></label>
        </div>
        <div class="set-row">
            <div class="lbl">Testmelding<small>Stuurt direct een pushmelding naar dit apparaat.</small></div>
            <div class="set-ctl">
                <button class="btn-sm" type="button" id="push-test" disabled>Test</button>
                <button class="btn-sm" type="button" id="push-test-kritiek" disabled>Test kritiek</button>
            </div>
        </div>
        <p class="push-melding" id="push-melding" hidden></p>
        <?php if (!$voorkeuren_ok): ?>
            <p class="push-melding fout">De keuzes voor toewijzing en kritiek kunnen nog niet worden opgeslagen: de MKAPP-migratie <code>migratie_push_voorkeuren.sql</code> (V2.0.2.47) is nog niet uitgevoerd. Tot die tijd krijg je zoals voorheen alleen meldingen bij toewijzing.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="panel set-sec" id="verversen-panel">
    <h2>Verversen</h2>
    <p class="sub">Houdt Meldingen en een geopende melding vanzelf bij. Met de knop <span class="ico-inline" aria-hidden="true">&#8635;</span> links van het tandwiel ververs je op elk moment zelf.</p>

    <div class="set-row">
        <div class="lbl">Automatisch verversen<small>Wordt overgeslagen zolang je typt of het scherm niet open staat.</small></div>
        <label class="toggle-switch"><input type="checkbox" id="verversen-aan"><span class="slider"></span></label>
    </div>
    <div class="set-row">
        <div class="lbl">Hoe vaak</div>
        <div class="set-ctl">
            <select id="verversen-interval" aria-label="Hoe vaak automatisch verversen">
                <option value="15">Elke 15 seconden</option>
                <option value="30">Elke 30 seconden</option>
                <option value="60">Elke minuut</option>
                <option value="120">Elke 2 minuten</option>
                <option value="300">Elke 5 minuten</option>
            </select>
        </div>
    </div>
</div>

<div class="panel set-sec" id="geluid-panel">
    <h2>Geluid in de app</h2>
    <p class="sub">Werkt zolang MDT open staat op je scherm. Een pushmelding op een gesloten app gebruikt altijd het geluid van je telefoon.</p>

    <div class="set-row">
        <div class="lbl">Geluid bij nieuwe melding</div>
        <label class="toggle-switch"><input type="checkbox" id="geluid-aan"><span class="slider"></span></label>
    </div>
    <div class="set-row">
        <div class="lbl">Toon</div>
        <div class="set-ctl">
            <select id="geluid-toon" aria-label="Toon">
                <option value="alarm">Alarm</option>
                <option value="piep">Piep</option>
                <option value="bel">Bel</option>
            </select>
            <button class="btn-sm" type="button" id="geluid-test" aria-label="Toon afspelen">&#9654;</button>
        </div>
    </div>
    <div class="set-row">
        <div class="lbl">Volume</div>
        <div class="set-ctl"><input type="range" id="geluid-volume" min="0" max="100" step="5" aria-label="Volume"></div>
    </div>
    <div class="set-row">
        <div class="lbl">Kritiek: herhalend alarm<small>Herhaalt het alarm tot je de melding opent of op Stop alarm tikt (maximaal 10 minuten).</small></div>
        <div class="set-ctl">
            <button class="btn-sm" type="button" id="geluid-test-kritiek">Test</button>
            <label class="toggle-switch"><input type="checkbox" id="geluid-herhaal"><span class="slider"></span></label>
        </div>
    </div>
    <p class="push-melding" id="geluid-hint" hidden></p>
</div>

<div class="set-note" id="ios-note">
    <strong>iPhone / iPad:</strong> pushmeldingen werken alleen als je MDT eerst op je beginscherm zet (Deel → Zet op beginscherm) en de app daarvandaan opent.
    Apple staat voor webapps geen “Kritieke meldingen” toe die de stilteknop of Niet storen doorbreken, en geen eigen pushgeluid; daarvoor is een echte App Store-app nodig.
    Kritieke pushmeldingen komen wel direct en prominent binnen, en met het geluid hierboven klinkt er een alarm zolang MDT open staat.
</div>

<script>
(function () {
    // ---- Verversen ----------------------------------------------------------
    var verversen = window.mdtVerversen;
    var verAanEl = document.getElementById('verversen-aan');
    var verIntEl = document.getElementById('verversen-interval');
    if (verversen) {
        var vinst = verversen.instellingen();
        verAanEl.checked = vinst.aan;
        verIntEl.value = String(vinst.seconden);
        verIntEl.disabled = !vinst.aan;
        var bewaarVerversen = function () {
            verIntEl.disabled = !verAanEl.checked;
            verversen.opslaan({ aan: verAanEl.checked, seconden: parseInt(verIntEl.value, 10) });
        };
        verAanEl.addEventListener('change', bewaarVerversen);
        verIntEl.addEventListener('change', bewaarVerversen);
    }

    // ---- Geluid -------------------------------------------------------------
    var geluid = window.mdtGeluid;
    var aanEl = document.getElementById('geluid-aan');
    var toonEl = document.getElementById('geluid-toon');
    var volEl = document.getElementById('geluid-volume');
    var herhaalEl = document.getElementById('geluid-herhaal');
    var hintEl = document.getElementById('geluid-hint');

    if (geluid) {
        var inst = geluid.instellingen();
        aanEl.checked = inst.aan;
        toonEl.value = inst.toon;
        volEl.value = inst.volume;
        herhaalEl.checked = inst.herhaal;

        var bewaar = function () {
            geluid.opslaan({
                aan: aanEl.checked,
                toon: toonEl.value,
                volume: parseInt(volEl.value, 10),
                herhaal: herhaalEl.checked,
            });
        };
        [aanEl, toonEl, volEl, herhaalEl].forEach(function (el) { el.addEventListener('change', bewaar); });

        var hint = function (tekst) { hintEl.textContent = tekst; hintEl.hidden = !tekst; };
        document.getElementById('geluid-test').addEventListener('click', function () {
            geluid.ontgrendel();
            var gelukt = geluid.speel(toonEl.value, parseInt(volEl.value, 10));
            hint(gelukt ? '' : 'Je browser blokkeert het geluid nog. Tik nog eens op ▶ — lukt het dan nog niet, controleer dan of je telefoon niet op stil staat en het volume hoger staat.');
        });
        document.getElementById('geluid-test-kritiek').addEventListener('click', function () {
            geluid.ontgrendel();
            bewaar();
            geluid.testAlarm();
        });
    }

    // ---- Pushmeldingen ------------------------------------------------------
    var toggle = document.getElementById('push-toggle');
    if (!toggle) { return; }   // push niet ingesteld op de server

    var toewijzingEl = document.getElementById('push-toewijzing');
    var kritiekEl = document.getElementById('push-kritiek');
    var testEl = document.getElementById('push-test');
    var testKritiekEl = document.getElementById('push-test-kritiek');
    var badgeEl = document.getElementById('push-badge');
    var meldingEl = document.getElementById('push-melding');
    var iosInstallEl = document.getElementById('push-ios-installeren');
    var vapidPublicKey = <?= json_encode(VAPID_PUBLIC_KEY) ?>;
    var url = '/push_abonneren.php';
    var voorkeurenOk = <?= $voorkeuren_ok ? 'true' : 'false' ?>;
    var endpoint = null;

    function toonMelding(tekst, isFout) {
        meldingEl.textContent = tekst;
        meldingEl.classList.toggle('fout', !!isFout);
        meldingEl.hidden = !tekst;
    }

    function base64UrlNaarUint8Array(base64Url) {
        var padding = '='.repeat((4 - base64Url.length % 4) % 4);
        var base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
        var ruw = window.atob(base64);
        var output = new Uint8Array(ruw.length);
        for (var i = 0; i < ruw.length; i++) { output[i] = ruw.charCodeAt(i); }
        return output;
    }

    function post(data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        }).then(function (resp) {
            return resp.json().catch(function () { return {}; }).then(function (json) {
                if (!resp.ok || json.ok === false) {
                    var fout = new Error(json.fout || 'server');
                    fout.serverFout = true;
                    throw fout;
                }
                return json;
            });
        });
    }

    // Toon de schakelaars in de juiste stand voor "aan"/"uit" op dit apparaat.
    function zetStand(aan, voorkeuren) {
        toggle.checked = aan;
        badgeEl.hidden = !aan;
        toewijzingEl.disabled = !aan || !voorkeurenOk;
        kritiekEl.disabled = !aan || !voorkeurenOk;
        testEl.disabled = !aan;
        testKritiekEl.disabled = !aan;
        toewijzingEl.checked = aan ? (voorkeuren ? voorkeuren.toewijzing : true) : false;
        kritiekEl.checked = aan ? (voorkeuren ? voorkeuren.kritiek_alle : false) : false;
    }

    var ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var alsApp = window.navigator.standalone === true || (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches);

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        // iPhone in Safari: PushManager bestaat pas als de app op het beginscherm staat.
        if (ios && !alsApp) {
            iosInstallEl.hidden = false;
        } else {
            toonMelding('Dit apparaat of deze browser ondersteunt geen pushmeldingen (of MDT draait niet via HTTPS).', true);
        }
        return;
    }

    toggle.disabled = false;

    navigator.serviceWorker.register('/sw.js').then(function (reg) {
        return reg.pushManager.getSubscription();
    }).then(function (sub) {
        if (!sub) { zetStand(false, null); return; }
        endpoint = sub.endpoint;
        return post({ actie: 'status', endpoint: endpoint }).then(function (antw) {
            if (antw.voorkeuren) { zetStand(true, antw.voorkeuren); return; }
            // Abonnement bestaat in de browser maar niet (meer) op de server: herstel dat.
            var json = sub.toJSON();
            return post({ actie: 'abonneren', endpoint: endpoint, p256dh: json.keys.p256dh, auth: json.keys.auth, omschrijving: navigator.userAgent.slice(0, 100) })
                .then(function (r) { zetStand(true, r.voorkeuren); });
        });
    }).catch(function () {
        toonMelding('Kon de pushstatus niet ophalen op dit apparaat.', true);
    });

    toggle.addEventListener('change', function () {
        toggle.disabled = true;
        toonMelding('');

        if (toggle.checked) {
            navigator.serviceWorker.register('/sw.js').then(function (reg) {
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64UrlNaarUint8Array(vapidPublicKey),
                });
            }).then(function (sub) {
                var json = sub.toJSON();
                endpoint = json.endpoint;
                return post({
                    actie: 'abonneren',
                    endpoint: json.endpoint,
                    p256dh: json.keys.p256dh,
                    auth: json.keys.auth,
                    omschrijving: navigator.userAgent.slice(0, 100),
                });
            }).then(function (antw) {
                zetStand(true, antw.voorkeuren);
            }).catch(function (fout) {
                zetStand(false, null);
                if (fout && fout.name === 'NotAllowedError') {
                    toonMelding('Toestemming voor meldingen geweigerd — zet dit aan bij de siteinstellingen van je browser (of bij Instellingen > Meldingen op je telefoon) om pushmeldingen te ontvangen.', true);
                } else {
                    toonMelding('Aanzetten van pushmeldingen is niet gelukt (werkt alleen via HTTPS).', true);
                }
            }).finally(function () {
                toggle.disabled = false;
            });
        } else {
            navigator.serviceWorker.register('/sw.js').then(function (reg) {
                return reg.pushManager.getSubscription();
            }).then(function (sub) {
                if (!sub) { return; }
                var ep = sub.endpoint;
                return sub.unsubscribe().then(function () {
                    return post({ actie: 'opzeggen', endpoint: ep });
                });
            }).then(function () {
                endpoint = null;
                zetStand(false, null);
            }).catch(function () {
                toonMelding('Uitzetten is niet helemaal gelukt — probeer het nog eens.', true);
                toggle.checked = true;
            }).finally(function () {
                toggle.disabled = false;
            });
        }
    });

    function bewaarVoorkeuren(el) {
        if (!endpoint) { return; }
        toonMelding('');
        post({ actie: 'voorkeuren', endpoint: endpoint, toewijzing: toewijzingEl.checked, kritiek_alle: kritiekEl.checked }).catch(function () {
            el.checked = !el.checked;   // niet opgeslagen: schakelaar terugzetten
            toonMelding('Opslaan is niet gelukt — probeer het nog eens.', true);
        });
    }
    toewijzingEl.addEventListener('change', function () { bewaarVoorkeuren(toewijzingEl); });
    kritiekEl.addEventListener('change', function () { bewaarVoorkeuren(kritiekEl); });

    function verstuurTest(soort, knop) {
        if (!endpoint) { return; }
        knop.disabled = true;
        toonMelding('');
        post({ actie: 'test', endpoint: endpoint, soort: soort }).then(function () {
            toonMelding('Testmelding verstuurd. Binnen een paar seconden zou hij op dit apparaat moeten verschijnen.', false);
        }).catch(function (fout) {
            toonMelding('Testmelding versturen mislukt' + (fout && fout.serverFout && fout.message !== 'server' ? ': ' + fout.message : '.'), true);
        }).finally(function () {
            knop.disabled = !toggle.checked;
        });
    }
    testEl.addEventListener('click', function () { verstuurTest('normaal', testEl); });
    testKritiekEl.addEventListener('click', function () { verstuurTest('kritiek', testKritiekEl); });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
