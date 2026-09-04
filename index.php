<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$meldingen = mijn_meldingen($pdo, huidige_gebruiker_id());
$mijn_status = huidige_eenheidsstatus($pdo, huidige_gebruiker_id());
$mijn_team = mijn_team($pdo, huidige_gebruiker_id());

$paginatitel = 'Mijn meldingen';
include __DIR__ . '/includes/header.php';
?>

<div class="panel status-panel">
    <h2>Mijn status<?= $mijn_team ? ' · ' . e($mijn_team['naam']) : '' ?></h2>
    <div class="status-grid">
        <?php foreach (alle_eenheidsstatussen($pdo) as $s): ?>
            <form method="post" action="/status.php">
                <input type="hidden" name="eenheidsstatus_id" value="<?= $s['id'] ?>">
                <button type="submit" class="status-btn <?= $mijn_status && $mijn_status['id'] === $s['id'] ? 'actief' : '' ?>">
                    <span class="afk"><?= e($s['afkorting']) ?></span>
                    <span class="naam"><?= e($s['naam']) ?></span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
    <?php if (!alle_eenheidsstatussen($pdo)): ?>
        <p class="log-leeg">Nog geen eenheidsstatussen ingesteld.</p>
    <?php endif; ?>
</div>

<div class="page-head">
    <h1>Mijn meldingen</h1>
    <p>Actieve meldingen die aan jou zijn toegewezen<?= $mijn_team ? ' (rechtstreeks of via team ' . e($mijn_team['naam']) . ')' : '' ?>.</p>
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
