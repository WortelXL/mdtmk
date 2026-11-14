        <p class="footer-note">MDT <?= e(APP_VERSION) ?> — onderdeel van MKAPP</p>
    </div>
    <script>
    // Voorkomt dubbel indienen op een wisselende mobiele verbinding: na de
    // eerste tik op een submit-knop wordt die knop uitgeschakeld en toont
    // hij "Bezig...", zodat een 2e tik (of een trage respons) niet nog een
    // keer hetzelfde formulier verstuurt. Opt-out via data-no-guard op het
    // formulier voor een pagina die dit zelf al afhandelt.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.dataset.noGuard !== undefined) {
            return;
        }
        var btn = form.querySelector('button[type="submit"]');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            btn.textContent = 'Bezig...';
        }
    }, true);

    // Verversen (V0.0.22): zet pagina's met data-auto-refresh="<sec>" periodiek
    // opnieuw op en bedient de verversknop naast het tandwiel. Of er automatisch
    // ververst wordt en hoe vaak staat per apparaat in Instellingen (zie
    // assets/mdt-verversen.js); de pagina bepaalt alleen of ze meedoet. Het
    // automatisch verversen slaat een ronde over terwijl je iets aan het
    // invullen bent (tekstveld met inhoud, focus in een veld, of een formulier
    // dat nog aan het versturen is) of het tabblad niet zichtbaar is -- dat zou
    // half ingevulde invoer kunnen kwijtraken. De knop ververst altijd direct.
    (function () {
        function magVerversen() {
            if (document.hidden) {
                return false;
            }
            var actief = document.activeElement;
            if (actief && (actief.tagName === 'TEXTAREA' || actief.tagName === 'INPUT')) {
                return false;
            }
            var notitie = document.querySelector('textarea[name="notitie"]');
            if (notitie && notitie.value.trim() !== '') {
                return false;
            }
            if (document.querySelector('button[type="submit"]:disabled')) {
                return false;
            }
            return true;
        }

        // V0.0.19: pagina's met data-soft-refresh="<selectoren>" verversen alleen
        // die onderdelen (de inhoud wordt vervangen) i.p.v. de hele pagina. Zo
        // wordt het geluidsalarm niet onderbroken en blijft de browser-toestemming
        // om geluid af te spelen behouden. Gaat er iets mis: gewoon volledig herladen.
        var zacht = (document.body.dataset.softRefresh || '').split(',').filter(Boolean);

        // Geeft een belofte terug die klaar is als de pagina bijgewerkt is.
        function zachtVerversen() {
            return fetch(location.href, { credentials: 'same-origin', cache: 'no-store' }).then(function (resp) {
                if (!resp.ok || /\/login\.php/.test(resp.url)) { throw new Error('opnieuw laden'); }
                return resp.text();
            }).then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                zacht.forEach(function (selector) {
                    var nieuw = doc.querySelectorAll(selector);
                    var huidig = document.querySelectorAll(selector);
                    if (nieuw.length !== huidig.length) { throw new Error('opnieuw laden'); }
                    for (var i = 0; i < huidig.length; i++) {
                        if (huidig[i].innerHTML !== nieuw[i].innerHTML) {
                            huidig[i].innerHTML = nieuw[i].innerHTML;
                        }
                    }
                });
            }).catch(function () {
                location.reload();
            });
        }

        var knop = document.getElementById('tab-refresh');
        var bezig = false;

        // Handmatig verversen: direct, ook als je iets aan het typen bent
        // (op pagina's met zacht verversen blijft je tekstveld dan gewoon staan).
        function nu() {
            if (bezig) { return; }
            bezig = true;
            if (knop) { knop.classList.add('draait'); }
            var klaar = function () {
                bezig = false;
                if (knop) { knop.classList.remove('draait'); }
            };
            if (zacht.length) {
                // Minstens even laten draaien, anders ziet het eruit alsof er niets gebeurt.
                Promise.all([zachtVerversen(), new Promise(function (r) { setTimeout(r, 700); })]).then(klaar, klaar);
            } else {
                setTimeout(function () { location.reload(); }, 150);
            }
        }

        var api = window.mdtVerversen || {};
        api.nu = nu;
        window.mdtVerversen = api;
        if (knop) { knop.addEventListener('click', nu); }

        var seconden = parseInt(document.body.dataset.autoRefresh || '0', 10);
        if (!seconden) {
            return;
        }
        var inst = api.instellingen ? api.instellingen() : { aan: true, seconden: seconden };
        if (!inst.aan) {
            return;
        }

        setInterval(function () {
            if (bezig || !magVerversen()) {
                return;
            }
            if (zacht.length) {
                zachtVerversen();
            } else {
                location.reload();
            }
        }, (inst.seconden || seconden) * 1000);
    })();
    </script>
</body>
</html>
