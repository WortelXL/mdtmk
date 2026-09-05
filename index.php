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

<div class="panel push-panel" id="push-panel">
    <h2>Pushmeldingen</h2>
    <p class="log-leeg" id="push-niet-beschikbaar" hidden>
        Pushmeldingen zijn op dit toestel/deze verbinding niet
        beschikbaar (vereist een beveiligde verbinding — werkt dus niet
        via het gewone adres op het lokale netwerk, wel via het echte,
        beveiligde MDT-adres).
    </p>
    <button type="button" class="btn" id="push-knop" hidden>Pushmeldingen aanzetten</button>
</div>
<script>
(function () {
    var VAPID_PUBLIC_KEY = <?= json_encode(VAPID_PUBLIC_KEY) ?>;
    var knop = document.getElementById('push-knop');
    var nietBeschikbaar = document.getElementById('push-niet-beschikbaar');

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - base64String.length % 4) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var rawData = window.atob(base64);
        var outputArray = new Uint8Array(rawData.length);
        for (var i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray;
    }

    function stuurAbonneren(subscription) {
        var json = subscription.toJSON();
        return fetch('/push_abonneren.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                actie: 'abonneren',
                endpoint: json.endpoint,
                p256dh: json.keys.p256dh,
                auth: json.keys.auth,
            }),
        });
    }

    function stuurAfmelden(endpoint) {
        return fetch('/push_abonneren.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ actie: 'afmelden', endpoint: endpoint }),
        });
    }

    function verversKnop(abonnement) {
        knop.textContent = abonnement ? 'Pushmeldingen uitzetten' : 'Pushmeldingen aanzetten';
        knop.dataset.staat = abonnement ? 'aan' : 'uit';
    }

    if (!VAPID_PUBLIC_KEY || !window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        nietBeschikbaar.hidden = false;
        return;
    }

    knop.hidden = false;

    navigator.serviceWorker.register('/sw.js').then(function (registratie) {
        return registratie.pushManager.getSubscription();
    }).then(function (abonnement) {
        verversKnop(abonnement);
    }).catch(function () {
        nietBeschikbaar.hidden = false;
        knop.hidden = true;
    });

    knop.addEventListener('click', function () {
        knop.disabled = true;
        navigator.serviceWorker.ready.then(function (registratie) {
            if (knop.dataset.staat === 'aan') {
                return registratie.pushManager.getSubscription().then(function (abonnement) {
                    if (!abonnement) {
                        return;
                    }
                    var endpoint = abonnement.endpoint;
                    return abonnement.unsubscribe().then(function () {
                        return stuurAfmelden(endpoint);
                    });
                }).then(function () {
                    verversKnop(null);
                });
            }
            return registratie.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            }).then(function (abonnement) {
                return stuurAbonneren(abonnement).then(function () {
                    verversKnop(abonnement);
                });
            });
        }).catch(function () {
            alert('Pushmeldingen inschakelen is niet gelukt. Controleer of je toestemming hebt gegeven.');
        }).finally(function () {
            knop.disabled = false;
        });
    });
})();
</script>

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
