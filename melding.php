<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$id = (int) ($_GET['id'] ?? 0);
$melding = $id ? mijn_melding_ophalen($pdo, $id, huidige_gebruiker_id()) : null;

if (!$melding) {
    http_response_code(404);
    $actief_nav = 'meldingen';
    $paginatitel = 'Niet gevonden';
    include __DIR__ . '/includes/header.php';
    echo '<div class="empty">Deze melding bestaat niet, of is niet aan jou toegewezen.</div>';
    echo '<p style="text-align:center; margin-top:16px;"><a href="/index.php" class="back-link">&larr; Terug naar mijn meldingen</a></p>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$instellingen = mdt_instellingen($pdo, huidige_gebruiker_id());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'logboek_toevoegen') {
    // mag_schrijven server-side afdwingen (fase M6) -- niet alleen het
    // formulier verbergen, ook de POST zelf weigeren.
    $tekst = trim($_POST['notitie'] ?? '');
    if ($instellingen['mag_schrijven'] && $tekst !== '') {
        voeg_logboekregel_toe($pdo, $melding['id'], $tekst, huidige_gebruiker_id(), huidige_gebruiker_naam());
    }
    header('Location: /melding.php?id=' . $melding['id']);
    exit;
}

// V0.0.14: kladblok samengevoegd over de hele koppelketen (ook indirecte
// koppelingen), zelfde aanpak als mkapp V2.0.2.21 -- elke regel weet welke
// melding 'm oorspronkelijk geschreven heeft (bron_meld_id/is_eigen), zie
// melding_notities_samengevoegd() in functions.php.
$logboek = melding_notities_samengevoegd($pdo, [$melding['id']])[$melding['id']] ?? [];
$logboek = array_reverse($logboek);
$aantal_gekoppelde_regels = count(array_filter($logboek, fn($n) => !$n['is_eigen']));

$actief_nav = 'meldingen';
$paginatitel = $melding['meld_id'];
$auto_refresh_seconden = 30;
include __DIR__ . '/includes/header.php';
?>

<a href="/index.php" class="back-link">&larr; Mijn meldingen</a>

<div class="detail-kop">
    <div class="meld-id"><?= e($melding['meld_id']) ?></div>
    <h1><?= e($melding['titel']) ?></h1>
    <div class="tags">
        <span class="tag" style="background:var(--amber)22; color:var(--amber);"><?= prioriteit_label($melding['prioriteit']) ?></span>
        <?= status_tag_html($pdo, $melding['status']) ?>
        <?php if ($melding['hoofd_naam']): ?>
            <span class="tag" style="background:<?= e($melding['hoofd_kleur']) ?>22; color:<?= e($melding['hoofd_kleur']) ?>;">
                <?= e($melding['hoofd_naam']) ?><?= $melding['sub_naam'] ? ' · ' . e($melding['sub_naam']) : '' ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="meta">
        <?= e($melding['locatie'] ?: 'Geen locatie opgegeven') ?><br>
        Aangemaakt <?= (new DateTime($melding['aangemaakt_op']))->format('d-m-Y H:i') ?>
    </div>
</div>

<?php if ($melding['omschrijving']): ?>
    <div class="panel">
        <h2>Omschrijving</h2>
        <p class="body-text"><?= nl2br(e($melding['omschrijving'])) ?></p>
    </div>
<?php endif; ?>

<?php if ($instellingen['mag_schrijven']): ?>
<div class="panel">
    <h2>Kladblok toevoegen</h2>
    <form method="post" class="logboek-form">
        <input type="hidden" name="actie" value="logboek_toevoegen">
        <textarea name="notitie" rows="3" placeholder="Typ hier een kladblokregel..." required></textarea>
        <button type="submit" class="btn">Toevoegen</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h2>Kladblok</h2>
    <?php if ($aantal_gekoppelde_regels > 0): ?>
        <p style="color:var(--muted); font-size:12px; margin:-6px 0 12px;">Inclusief <?= $aantal_gekoppelde_regels ?> regel<?= $aantal_gekoppelde_regels === 1 ? '' : 's' ?> uit gekoppelde meldingen (🔗).</p>
    <?php endif; ?>
    <?php if (!$logboek): ?>
        <p class="log-leeg">Nog geen kladblokregels.</p>
    <?php endif; ?>
    <?php foreach ($logboek as $regel): ?>
        <div class="log-entry">
            <div class="kop">
                <?php if (!$regel['is_eigen']): ?>
                    <a href="/melding.php?id=<?= (int) $regel['melding_id'] ?>" style="color:var(--amber); text-decoration:none; font-weight:600;" title="Regel van gekoppelde melding <?= e($regel['bron_meld_id']) ?> — <?= e($regel['bron_titel']) ?>">🔗 <?= e($regel['bron_meld_id']) ?></a> ·
                <?php endif; ?>
                <?= (new DateTime($regel['aangemaakt_op']))->format('d-m-Y H:i') ?> · <?= e($regel['auteur'] ?: 'onbekend') ?>
            </div>
            <div class="tekst"><?= nl2br(e($regel['notitie'])) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
