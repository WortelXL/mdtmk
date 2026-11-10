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
</head>
<body<?= isset($auto_refresh_seconden) ? ' data-auto-refresh="' . (int) $auto_refresh_seconden . '"' : '' ?>>
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
    </nav>
    <?php endif; ?>
    <div class="container">
