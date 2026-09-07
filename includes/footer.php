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
    </script>
</body>
</html>
