<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title><?= isset($paginatitel) ? e($paginatitel) . ' — MDT' : 'MDT' ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="topbar">
        <a href="/index.php" class="brand"><span class="dot"></span> MDT</a>
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
