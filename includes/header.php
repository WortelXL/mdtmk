<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($paginatitel) ? e($paginatitel) . ' — MDT' : 'MDT' ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#030917">
    <link rel="stylesheet" href="/assets/style.css">
    <?php if (is_ingelogd()): ?>
    <script src="/assets/mdt-geluid.js?v=<?= e(APP_VERSION) ?>"></script>
    <?php endif; ?>
</head>
<body<?= isset($auto_refresh_seconden) ? ' data-auto-refresh="' . (int) $auto_refresh_seconden . '"' : '' ?><?= isset($zacht_verversen) ? ' data-soft-refresh="' . e($zacht_verversen) . '"' : '' ?>>
    <div class="topbar">
        <a href="/index.php" class="brand"><img src="/assets/logo-96.png" alt="" width="36" height="36"><span>M<i>D</i>T</span></a>
        <div class="who">
            <?= e(huidige_gebruiker_naam()) ?><br>
            <a href="/logout.php">Uitloggen</a>
        </div>
    </div>
    <?php if (is_ingelogd()): ?>
    <nav class="tabbar">
        <a href="/index.php" class="<?= ($actief_nav ?? '') === 'meldingen' ? 'actief' : '' ?>">Meldingen</a>
        <a href="/crew.php" class="<?= ($actief_nav ?? '') === 'crew' ? 'actief' : '' ?>">Crew</a>
        <a href="/instellingen.php" class="tab-gear <?= ($actief_nav ?? '') === 'instellingen' ? 'actief' : '' ?>" aria-label="Instellingen" title="Instellingen"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></a>
    </nav>
    <?php endif; ?>
    <div class="container">
