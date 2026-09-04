<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$meldingen = mijn_meldingen($pdo, huidige_gebruiker_id());

$paginatitel = 'Mijn meldingen';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Mijn meldingen</h1>
    <p>Actieve meldingen die aan jou zijn toegewezen.</p>
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
