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
// V0.0.15: lid van meerdere teams tegelijk mogelijk -- $mijn_teams_namen
// is de kommagescheiden weergavetekst, hierboven werkte dit nog met 1 team.
$mijn_teams = mijn_teams($pdo, huidige_gebruiker_id());
$mijn_teams_namen = implode(', ', array_column($mijn_teams, 'naam'));

$actief_nav = 'meldingen';
$paginatitel = 'Mijn meldingen';
$auto_refresh_seconden = 30;
// V0.0.19: alleen de lijst (en sinds V0.0.20 de statusregel) verversen, niet de
// hele pagina -- zo blijft een lopend alarm (en de geluidsontgrendeling) intact.
$zacht_verversen = '.melding-lijst,.mijn-status-regel';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Mijn meldingen</h1>
    <p>
        <?php if ($weergave === 'alle'): ?>
            Alle meldingen<?= $instellingen['hoofdclassificatie_id'] ? ' binnen jouw classificatie' : '' ?>.
        <?php else: ?>
            Actieve meldingen die aan jou zijn toegewezen<?= $mijn_teams_namen !== '' ? ' (rechtstreeks of via team ' . e($mijn_teams_namen) . ')' : '' ?>.
        <?php endif; ?>
    </p>
    <?php if ($instellingen['alle_meldingen']): ?>
        <div class="weergave-schakelaar">
            <a href="/index.php" class="<?= $weergave === 'toegewezen' ? 'actief' : '' ?>">Toegewezen</a>
            <a href="/index.php?weergave=alle" class="<?= $weergave === 'alle' ? 'actief' : '' ?>">Alle meldingen</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($instellingen['toon_status_overzicht'] && $instellingen['rol_id']): ?>
<p class="mijn-status-regel">
    Jouw status:
    <?php if ($mijn_status): ?>
        <strong><?= e($mijn_status['naam']) ?></strong> (<?= e($mijn_status['afkorting']) ?>)
    <?php else: ?>
        <strong>nog niet gezet</strong>
    <?php endif; ?>
    <?= $mijn_teams_namen !== '' ? ' · ' . e($mijn_teams_namen) : '' ?>
    <span class="mijn-status-hint">Zet je status op een melding.</span>
</p>
<?php endif; ?>

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
                <span class="tag" style="background:var(--amber)22; color:var(--amber-text);"><?= prioriteit_label($m['prioriteit']) ?></span>
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
