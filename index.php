<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$instellingen = mdt_instellingen($pdo, huidige_gebruiker_id());

// "Alle meldingen"-schakelaar (fase M6) -- alleen van toepassing als
// de instelling dat toestaat; anders altijd terugvallen op "toegewezen".
$weergave = ($_GET['weergave'] ?? '') === 'alle' && $instellingen['alle_meldingen'] ? 'alle' : 'toegewezen';

$meldingen = mijn_meldingen($pdo, huidige_gebruiker_id(), false, $weergave);
$mijn_status = huidige_eenheidsstatus($pdo, huidige_gebruiker_id());
$mijn_team = mijn_team($pdo, huidige_gebruiker_id());
// Sinds fase M7: statussen horen bij een rol -- zonder gekoppelde rol
// (mdt_instellingen['rol_id']) levert dit altijd een lege lijst op.
$mijn_statussen = alle_eenheidsstatussen($pdo, $instellingen['rol_id'] ? (int) $instellingen['rol_id'] : null);

$actief_nav = 'meldingen';
$paginatitel = 'Mijn meldingen';
include __DIR__ . '/includes/header.php';
?>

<?php if ($instellingen['toon_status_overzicht']): ?>
<div class="panel status-panel">
    <h2>Mijn status<?= $mijn_team ? ' · ' . e($mijn_team['naam']) : '' ?></h2>
    <div class="status-grid">
        <?php foreach ($mijn_statussen as $s): ?>
            <form method="post" action="/status.php">
                <input type="hidden" name="eenheidsstatus_id" value="<?= $s['id'] ?>">
                <button type="submit" class="status-btn <?= $mijn_status && $mijn_status['id'] === $s['id'] ? 'actief' : '' ?>">
                    <span class="afk"><?= e($s['afkorting']) ?></span>
                    <span class="naam"><?= e($s['naam']) ?></span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
    <?php if (!$mijn_statussen): ?>
        <p class="log-leeg">
            <?= $instellingen['rol_id']
                ? 'Nog geen eenheidsstatussen ingesteld voor jouw rol (Beheer &gt; Eenheidsstatussen in MKAPP).'
                : 'Je hebt nog geen rol gekoppeld — vraag een beheerder om dit in te stellen bij Beheer &gt; MDT-gebruikers in MKAPP, dan verschijnen hier je statusknoppen.' ?>
        </p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (VAPID_PUBLIC_KEY !== ''): ?>
<div class="panel push-panel" id="push-panel" hidden>
    <h2>Pushmeldingen</h2>
    <div class="push-row">
        <div class="push-label">
            Melding bij toewijzing
            <span class="push-sub">Krijg een melding op dit apparaat zodra een melding aan jou (of je team) wordt toegewezen.</span>
        </div>
        <label class="toggle-switch">
            <input type="checkbox" id="push-toggle">
            <span class="slider"></span>
        </label>
    </div>
    <p class="push-melding" id="push-melding" hidden></p>
</div>
<script>
(function () {
    var paneel = document.getElementById('push-panel');
    var toggle = document.getElementById('push-toggle');
    var meldingEl = document.getElementById('push-melding');
    var vapidPublicKey = <?= json_encode(VAPID_PUBLIC_KEY) ?>;
    var abonneerUrl = '/push_abonneren.php';

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
        for (var i = 0; i < ruw.length; i++) {
            output[i] = ruw.charCodeAt(i);
        }
        return output;
    }

    // Geen serviceworker/push-ondersteuning (of geen HTTPS) -- paneel
    // blijft verborgen, de rest van MDT werkt hier gewoon zonder door.
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    paneel.hidden = false;

    navigator.serviceWorker.register('/sw.js').then(function (reg) {
        return reg.pushManager.getSubscription();
    }).then(function (sub) {
        toggle.checked = !!sub;
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
                return fetch(abonneerUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        actie: 'abonneren',
                        endpoint: json.endpoint,
                        p256dh: json.keys.p256dh,
                        auth: json.keys.auth,
                        omschrijving: navigator.userAgent.slice(0, 100),
                    }),
                }).then(function (resp) {
                    if (!resp.ok) { throw new Error('server'); }
                });
            }).catch(function (fout) {
                toggle.checked = false;
                if (fout && fout.name === 'NotAllowedError') {
                    toonMelding('Toestemming voor meldingen geweigerd — zet dit aan bij de siteinstellingen van je browser om pushmeldingen te ontvangen.', true);
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
                var endpoint = sub.endpoint;
                return sub.unsubscribe().then(function () {
                    return fetch(abonneerUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ actie: 'opzeggen', endpoint: endpoint }),
                    });
                });
            }).catch(function () {
                toonMelding('Uitzetten is niet helemaal gelukt — probeer het nog eens.', true);
                toggle.checked = true;
            }).finally(function () {
                toggle.disabled = false;
            });
        }
    });
})();
</script>
<?php endif; ?>

<div class="page-head">
    <h1>Mijn meldingen</h1>
    <p>
        <?php if ($weergave === 'alle'): ?>
            Alle meldingen<?= $instellingen['hoofdclassificatie_id'] ? ' binnen jouw classificatie' : '' ?>.
        <?php else: ?>
            Actieve meldingen die aan jou zijn toegewezen<?= $mijn_team ? ' (rechtstreeks of via team ' . e($mijn_team['naam']) . ')' : '' ?>.
        <?php endif; ?>
    </p>
    <?php if ($instellingen['alle_meldingen']): ?>
        <div class="weergave-schakelaar">
            <a href="/index.php" class="<?= $weergave === 'toegewezen' ? 'actief' : '' ?>">Toegewezen</a>
            <a href="/index.php?weergave=alle" class="<?= $weergave === 'alle' ? 'actief' : '' ?>">Alle meldingen</a>
        </div>
    <?php endif; ?>
</div>

<div class="melding-lijst">
    <?php if (!$meldingen): ?>
        <div class="empty">Je hebt op dit moment geen actieve meldingen toegewezen.</div>
    <?php endif; ?>

    <?php foreach ($meldingen as $m): ?>
        <a href="/melding.php?id=<?= (int) $m['id'] ?>" class="melding-card <?= prioriteit_class($m['prioriteit']) ?>">
            <div class="top-row">
                <span class="meld-id"><?= e($m['meld_id']) ?></span>
                <span class="meta"><?= (new DateTime($m['aangemaakt_op']))->format('d-m H:i') ?></span>
            </div>
            <div class="titel"><?= e($m['titel']) ?></div>
            <div class="meta"><?= e($m['locatie'] ?: 'Geen locatie opgegeven') ?></div>
            <div class="tags">
                <span class="tag" style="background:var(--amber)22; color:var(--amber);"><?= prioriteit_label($m['prioriteit']) ?></span>
                <?= status_tag_html($pdo, $m['status']) ?>
                <?php if ($m['hoofd_naam']): ?>
                    <span class="tag" style="background:<?= e($m['hoofd_kleur']) ?>22; color:<?= e($m['hoofd_kleur']) ?>;">
                        <?= e($m['hoofd_naam']) ?><?= $m['sub_naam'] ? ' · ' . e($m['sub_naam']) : '' ?>
                    </span>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
