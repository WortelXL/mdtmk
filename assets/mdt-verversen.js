/*
 * MDT — automatisch verversen (V0.0.22).
 *
 * Bewaart per apparaat (localStorage `mdt_verversen`) of de pagina's met
 * auto-refresh (Meldingen, melding-detail) zichzelf verversen en hoe vaak.
 * Ingesteld op instellingen.php; gelezen door includes/footer.php. De
 * verversknop naast het tandwiel roept window.mdtVerversen.nu() aan, die de
 * footer invult (zacht verversen waar dat kan, anders de pagina herladen).
 */
(function () {
    'use strict';

    var SLEUTEL = 'mdt_verversen';
    var STANDAARD = { aan: true, seconden: 30 };
    var KEUZES = [15, 30, 60, 120, 300];

    function laden() {
        var inst = { aan: STANDAARD.aan, seconden: STANDAARD.seconden };
        try {
            var ruw = JSON.parse(localStorage.getItem(SLEUTEL) || '{}');
            if (typeof ruw.aan === 'boolean') { inst.aan = ruw.aan; }
            if (KEUZES.indexOf(ruw.seconden) !== -1) { inst.seconden = ruw.seconden; }
        } catch (e) { /* kapotte of geblokkeerde opslag: standaardwaarden */ }
        return inst;
    }

    function opslaan(inst) {
        try { localStorage.setItem(SLEUTEL, JSON.stringify({ aan: !!inst.aan, seconden: inst.seconden })); } catch (e) { /* geen opslag: geldt dan alleen nu */ }
    }

    window.mdtVerversen = {
        instellingen: laden,
        opslaan: opslaan,
        keuzes: KEUZES,
        nu: function () { location.reload(); }, // wordt door de footer vervangen
    };
})();
