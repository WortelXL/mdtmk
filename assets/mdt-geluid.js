/*
 * MDT — geluid in de app (V0.0.19).
 *
 * Speelt een toon af als er een pushmelding binnenkomt terwijl MDT open staat
 * (de service worker stuurt dan een bericht naar de pagina, zie sw.js), en laat
 * bij een KRITIEKE melding een herhalend alarm horen tot je de melding opent of
 * het alarm stopt. De toonkeuze staat per apparaat in localStorage (`mdt_geluid`)
 * en wordt ingesteld op instellingen.php.
 *
 * Beperkingen van browsers/iOS, bewust zo gelaten:
 *  - Geluid mag pas na een tik/klik op de pagina. Tot die eerste tik blijft het
 *    stil en toont alleen de balk onderin; een tik op die balk ontgrendelt het geluid.
 *  - Een gesloten app speelt nooit dit geluid; dan geldt het geluid van de telefoon.
 */
(function () {
    'use strict';

    var SLEUTEL = 'mdt_geluid';
    var ALARM_SLEUTEL = 'mdt_alarm';
    var HERHAAL_MS = 2800;            // pauze tussen twee alarmreeksen
    var MAX_ALARM_MS = 10 * 60 * 1000; // geluid stopt na 10 min (de balk blijft staan)
    var STANDAARD = { aan: true, toon: 'alarm', volume: 70, herhaal: true };
    var TONEN = ['alarm', 'piep', 'bel'];

    // ---- Instellingen (localStorage, per apparaat) ------------------------

    function laden() {
        var inst = { aan: STANDAARD.aan, toon: STANDAARD.toon, volume: STANDAARD.volume, herhaal: STANDAARD.herhaal };
        try {
            var ruw = JSON.parse(localStorage.getItem(SLEUTEL) || '{}');
            if (typeof ruw.aan === 'boolean') { inst.aan = ruw.aan; }
            if (TONEN.indexOf(ruw.toon) !== -1) { inst.toon = ruw.toon; }
            if (typeof ruw.volume === 'number' && ruw.volume >= 0 && ruw.volume <= 100) { inst.volume = ruw.volume; }
            if (typeof ruw.herhaal === 'boolean') { inst.herhaal = ruw.herhaal; }
        } catch (e) { /* kapotte of geblokkeerde opslag: standaardwaarden */ }
        return inst;
    }

    function opslaan(inst) {
        try { localStorage.setItem(SLEUTEL, JSON.stringify(inst)); } catch (e) { /* geen opslag: instelling geldt dan alleen nu */ }
    }

    // ---- Audio ------------------------------------------------------------

    var ctx = null;

    function contextMaken() {
        if (!ctx) {
            var AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) { return null; }
            try { ctx = new AC(); } catch (e) { return null; }
        }
        return ctx;
    }

    // Eerste aanraking: context maken/hervatten en een stil geluidje afspelen
    // (iOS Safari ontgrendelt het geluid pas na een echt afgespeeld geluid).
    function ontgrendelen() {
        var c = contextMaken();
        if (!c) { return; }
        if (c.state === 'suspended') { c.resume(); }
        try {
            var bron = c.createBufferSource();
            bron.buffer = c.createBuffer(1, 1, 22050);
            bron.connect(c.destination);
            bron.start(0);
        } catch (e) { /* niet erg */ }
    }

    ['pointerdown', 'touchend', 'keydown', 'click'].forEach(function (naam) {
        document.addEventListener(naam, ontgrendelen, { passive: true });
    });

    function isActief() {
        return !!ctx && ctx.state === 'running';
    }

    // 1 oscillator-noot met een korte aan/uit-envelop (voorkomt geklik).
    function noot(c, uit, type, freq, start, duur, piek) {
        var osc = c.createOscillator();
        var gain = c.createGain();
        osc.type = type;
        osc.frequency.value = freq;
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(Math.max(piek, 0.0002), start + 0.012);
        gain.gain.setValueAtTime(Math.max(piek, 0.0002), start + Math.max(duur - 0.03, 0.02));
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duur);
        osc.connect(gain);
        gain.connect(uit);
        osc.start(start);
        osc.stop(start + duur + 0.02);
    }

    // Een bel: een paar boventonen die langzaam uitsterven.
    function belslag(c, uit, start, piek) {
        [[880, 1], [2217, 0.45], [2640, 0.3]].forEach(function (deel) {
            var osc = c.createOscillator();
            var gain = c.createGain();
            osc.type = 'sine';
            osc.frequency.value = deel[0];
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(Math.max(piek * deel[1], 0.0002), start + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 1.3);
            osc.connect(gain);
            gain.connect(uit);
            osc.start(start);
            osc.stop(start + 1.35);
        });
    }

    /**
     * Speelt 1 reeks van de gekozen toon. volume = 0..100.
     * Geeft true terug als het geluid daadwerkelijk is gestart.
     */
    function speel(toon, volume) {
        var c = contextMaken();
        if (!c) { return false; }
        if (c.state === 'suspended') { c.resume(); }
        if (c.state !== 'running') { return false; }

        var niveau = Math.max(0, Math.min(100, Number(volume))) / 100;
        if (niveau <= 0) { return true; }
        var uit = c.createGain();
        uit.gain.value = niveau;
        uit.connect(c.destination);
        var nu = c.currentTime + 0.02;
        var i;

        if (toon === 'piep') {
            for (i = 0; i < 3; i++) { noot(c, uit, 'sine', 1000, nu + i * 0.24, 0.13, 0.55); }
        } else if (toon === 'bel') {
            belslag(c, uit, nu, 0.5);
            belslag(c, uit, nu + 0.7, 0.4);
        } else { // alarm: sirene met twee afwisselende tonen
            for (i = 0; i < 6; i++) { noot(c, uit, 'square', i % 2 === 0 ? 880 : 660, nu + i * 0.26, 0.25, 0.28); }
        }
        return true;
    }

    // ---- Kritiek alarm ----------------------------------------------------

    var alarm = null;   // { id, url, titel, start, timer }
    var balk = null;

    function paginaMeldingId() {
        if (location.pathname.replace(/\/+$/, '') !== '/melding.php') { return 0; }
        var m = /[?&]id=(\d+)/.exec(location.search);
        return m ? parseInt(m[1], 10) : 0;
    }

    function balkVerwijderen() {
        if (balk && balk.parentNode) { balk.parentNode.removeChild(balk); }
        balk = null;
    }

    function alarmStoppen() {
        if (alarm && alarm.timer) { clearInterval(alarm.timer); }
        alarm = null;
        try { sessionStorage.removeItem(ALARM_SLEUTEL); } catch (e) { /* niet erg */ }
        balkVerwijderen();
    }

    function balkTonen() {
        balkVerwijderen();
        balk = document.createElement('div');
        balk.className = 'alarm-balk';
        balk.setAttribute('role', 'alert');

        var tekst = document.createElement('a');
        tekst.className = 'alarm-tekst';
        tekst.href = alarm.url || '/index.php';
        tekst.textContent = '🔴 Kritiek: ' + (alarm.titel || 'nieuwe melding');
        tekst.addEventListener('click', function () { alarmStoppen(); });

        var stop = document.createElement('button');
        stop.type = 'button';
        stop.className = 'alarm-stop';
        stop.textContent = 'Stop alarm';
        stop.addEventListener('click', function () { alarmStoppen(); });

        balk.appendChild(tekst);
        balk.appendChild(stop);
        document.body.appendChild(balk);
    }

    function alarmSpelen() {
        if (!alarm) { return; }
        var inst = laden();
        if (!inst.aan) { return; }
        if (Date.now() - alarm.start > MAX_ALARM_MS) {
            clearInterval(alarm.timer);
            alarm.timer = null;
            return;
        }
        speel(inst.toon, inst.volume);
    }

    function alarmStarten(info, hervat) {
        // Kijk je al naar deze melding? Dan hoeft er niets te schreeuwen.
        if (info.id && paginaMeldingId() === info.id) { return; }
        alarmStoppen();
        alarm = {
            id: info.id || 0,
            url: info.url || '/index.php',
            // de pushtitel begint al met "🔴 KRITIEK — "; de balk zet zelf een kop ervoor
            titel: (info.titel || '').replace(/^\uD83D\uDD34\s*KRITIEK\s*\u2014\s*/i, ''),
            start: hervat && info.start ? info.start : Date.now(),
            timer: null,
        };
        try { sessionStorage.setItem(ALARM_SLEUTEL, JSON.stringify({ id: alarm.id, url: alarm.url, titel: alarm.titel, start: alarm.start })); } catch (e) { /* niet erg */ }

        function tonen() {
            if (!document.body) { document.addEventListener('DOMContentLoaded', tonen); return; }
            balkTonen();
        }
        tonen();

        var inst = laden();
        if (inst.aan) {
            alarmSpelen();
            if (inst.herhaal) { alarm.timer = setInterval(alarmSpelen, HERHAAL_MS); }
        }
    }

    // ---- Binnenkomende push (bericht van de service worker) -----------------

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.addEventListener('message', function (event) {
            var d = event.data;
            if (!d || d.type !== 'mdt-push') { return; }
            if (d.kritiek) {
                alarmStarten({ id: d.melding_id, url: d.url, titel: d.titel }, false);
            } else {
                var inst = laden();
                if (inst.aan) { speel(inst.toon, inst.volume); }
            }
        });
    }

    // ---- Bij het laden: alarm hervatten of juist stoppen --------------------

    function bijLaden() {
        var opgeslagen = null;
        try { opgeslagen = JSON.parse(sessionStorage.getItem(ALARM_SLEUTEL) || 'null'); } catch (e) { opgeslagen = null; }
        if (!opgeslagen) { return; }
        if (opgeslagen.id && paginaMeldingId() === opgeslagen.id) {
            alarmStoppen(); // de melding is geopend -- klaar
            return;
        }
        alarmStarten(opgeslagen, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bijLaden);
    } else {
        bijLaden();
    }

    window.mdtGeluid = {
        instellingen: laden,
        opslaan: opslaan,
        speel: speel,
        ontgrendel: ontgrendelen,
        isActief: isActief,
        stopAlarm: alarmStoppen,
        testAlarm: function () {
            alarmStarten({ id: 0, url: '/instellingen.php', titel: 'Testmelding' }, false);
        },
    };
})();
