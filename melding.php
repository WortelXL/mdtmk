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

$foto_fout = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['actie'] ?? '') === 'foto_toevoegen') {
    // mag_schrijven server-side afdwingen (fase M6), zelfde als bij het
    // logboek -- een foto toevoegen is ook een schrijfactie.
    if ($instellingen['mag_schrijven']) {
        foreach ($_FILES['fotos']['name'] ?? [] as $i => $naam) {
            $bestand = [
                'name'     => $_FILES['fotos']['name'][$i],
                'type'     => $_FILES['fotos']['type'][$i],
                'tmp_name' => $_FILES['fotos']['tmp_name'][$i],
                'error'    => $_FILES['fotos']['error'][$i],
                'size'     => $_FILES['fotos']['size'][$i],
            ];
            $fout = voeg_bijlage_toe($pdo, $melding['id'], $bestand, huidige_gebruiker_id(), huidige_gebruiker_naam());
            if ($fout && !$foto_fout) {
                $foto_fout = $fout;
            }
        }
    }
    if (!$foto_fout) {
        header('Location: /melding.php?id=' . $melding['id']);
        exit;
    }
}

$logboek = melding_logboek($pdo, $melding['id']);
$bijlagen = melding_bijlagen($pdo, $melding['id']);

$actief_nav = 'meldingen';
$paginatitel = $melding['meld_id'];
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
    <h2>Logboek toevoegen</h2>
    <form method="post" class="logboek-form">
        <input type="hidden" name="actie" value="logboek_toevoegen">
        <textarea name="notitie" rows="3" placeholder="Typ hier een logboekregel..." required></textarea>
        <button type="submit" class="btn">Toevoegen</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h2>Logboek</h2>
    <?php if (!$logboek): ?>
        <p class="log-leeg">Nog geen logboekregels.</p>
    <?php endif; ?>
    <?php foreach ($logboek as $regel): ?>
        <div class="log-entry">
            <div class="kop"><?= (new DateTime($regel['aangemaakt_op']))->format('d-m-Y H:i') ?> · <?= e($regel['auteur'] ?: 'onbekend') ?></div>
            <div class="tekst"><?= nl2br(e($regel['notitie'])) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($instellingen['mag_schrijven']): ?>
<div class="panel">
    <h2>Foto toevoegen</h2>
    <?php if ($foto_fout): ?>
        <div class="alert alert-fout"><?= e($foto_fout) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="foto-form">
        <input type="hidden" name="actie" value="foto_toevoegen">
        <input type="file" name="fotos[]" accept="image/*" capture="environment" multiple required>
        <button type="submit" class="btn">Foto('s) toevoegen</button>
    </form>
</div>
<?php endif; ?>

<div class="panel">
    <h2>Foto's</h2>
    <?php if (!$bijlagen): ?>
        <p class="log-leeg">Nog geen foto's toegevoegd.</p>
    <?php else: ?>
        <div class="foto-grid">
            <?php foreach ($bijlagen as $b): ?>
                <a href="<?= e($b['url']) ?>" target="_blank" rel="noopener" class="foto-thumb-link">
                    <img src="<?= e($b['url']) ?>" alt="<?= e($b['bestandsnaam']) ?>" class="foto-thumb" loading="lazy">
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
