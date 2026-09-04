<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$lijst = crew_en_collegas($pdo);

$actief_nav = 'crew';
$paginatitel = 'Crew';
include __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1>Crew</h1>
    <p>Iedereen die je vanaf hier direct kunt bellen — crew zonder eigen account, en collega's met een account en telefoonnummer.</p>
</div>

<div class="crew-lijst">
    <?php if (!$lijst): ?>
        <div class="empty">Nog niemand in de crew-lijst.</div>
    <?php endif; ?>
    <?php foreach ($lijst as $persoon): ?>
        <div class="crew-card">
            <div>
                <div class="naam"><?= e($persoon['naam']) ?></div>
                <div class="meta"><?= e($persoon['functie'] ?: ($persoon['type'] === 'collega' ? 'Collega' : 'Crew')) ?></div>
            </div>
            <?php if ($persoon['telefoonnummer']): ?>
                <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $persoon['telefoonnummer'])) ?>" class="bel-btn" aria-label="Bel <?= e($persoon['naam']) ?>">☎</a>
            <?php else: ?>
                <span class="bel-btn bel-btn-uit" aria-label="Geen telefoonnummer bekend">—</span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
