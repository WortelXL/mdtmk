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

    // Auto-refresh: ververst de pagina periodiek zodat een wijziging van
    // een ander (nieuwe toewijzing, logboekregel, statuswijziging, foto)
    // vanzelf zichtbaar wordt zonder handmatig te hoeven verversen. Alleen
    // actief op pagina's die dit aanzetten via data-auto-refresh="<sec>"
    // op <body> (zie includes/header.php). Ververst nooit terwijl je iets
    // aan het invullen bent (tekstveld met inhoud, focus in een veld,
    // klaargezette foto's die nog niet verstuurd zijn, of een formulier
    // dat nog aan het versturen is) of terwijl het tabblad niet zichtbaar
    // is -- dat zou half ingevulde invoer kunnen kwijtraken.
    (function () {
        var seconden = parseInt(document.body.dataset.autoRefresh || '0', 10);
        if (!seconden) {
            return;
        }

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
            var fotoPreview = document.getElementById('foto-preview');
            if (fotoPreview && fotoPreview.children.length > 0) {
                return false;
            }
            if (document.querySelector('button[type="submit"]:disabled')) {
                return false;
            }
            return true;
        }

        setInterval(function () {
            if (magVerversen()) {
                location.reload();
            }
        }, seconden * 1000);
    })();
    </script>
</body>
</html>
