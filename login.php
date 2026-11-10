<?php
require_once __DIR__ . '/includes/functions.php';

if (is_ingelogd()) {
    header('Location: /index.php');
    exit;
}

$pdo = get_pdo();
$fout = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gebruikersnaam = trim($_POST['gebruikersnaam'] ?? '');
    $wachtwoord = $_POST['wachtwoord'] ?? '';

    if ($gebruikersnaam === '' || $wachtwoord === '') {
        $fout = 'Vul je gebruikersnaam en wachtwoord in.';
    } else {
        $resultaat = probeer_inloggen($pdo, $gebruikersnaam, $wachtwoord);
        if ($resultaat['ok']) {
            header('Location: /index.php');
            exit;
        }
        $fout = $resultaat['fout'];
    }
}
?>
<!doctype html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen — MDT</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/favicon-32.png">
    <link rel="apple-touch-icon" href="/assets/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#030917">
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <div class="login-wrap">
        <div class="login-card">
            <img class="login-hero" src="/assets/logo-512.png" alt="MDT" width="150" height="150">
            <h1 class="sr-only">MDT</h1>
            <p class="sub">Mobiel Data Terminal — voor crew op terrein</p>

            <?php if ($fout): ?>
                <div class="alert alert-fout"><?= e($fout) ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="field">
                    <label for="gebruikersnaam">Gebruikersnaam</label>
                    <input type="text" id="gebruikersnaam" name="gebruikersnaam" autocomplete="username" autocapitalize="off" required>
                </div>
                <div class="field">
                    <label for="wachtwoord">Wachtwoord</label>
                    <input type="password" id="wachtwoord" name="wachtwoord" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn">Inloggen</button>
            </form>
        </div>
    </div>
</body>
</html>
