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
