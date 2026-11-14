<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$terug = '/index.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eenheidsstatus_id = (int) ($_POST['eenheidsstatus_id'] ?? 0);
    $resultaat = null;
    if ($eenheidsstatus_id > 0) {
        $resultaat = zet_eenheidsstatus($pdo, huidige_gebruiker_id(), $eenheidsstatus_id, huidige_gebruiker_naam());
    }

    // V0.0.20: de knoppen staan op de melding zelf -- terug naar die melding
    // (alleen een getal, dus geen vrij in te vullen redirect).
    $melding_id = (int) ($_POST['melding_id'] ?? 0);
    if ($melding_id > 0) {
        $terug = '/melding.php?id=' . $melding_id;
    }

    // V0.0.21: een status die losmaakt van meldingen -- laat zien wat er is gebeurd.
    if ($resultaat && $resultaat['losgekoppeld']) {
        $_SESSION['flash'] = ['tekst' => 'Je bent losgekoppeld van ' . implode(', ', $resultaat['losgekoppeld']) . '.', 'fout' => false];
    } elseif ($resultaat && $resultaat['ontkoppel_fout']) {
        $_SESSION['flash'] = ['tekst' => 'Je status is gezet, maar losmaken van je melding(en) is niet gelukt. Vraag de beheerder om de databaserechten van MDT te controleren.', 'fout' => true];
    }
    // Ben je net losgekoppeld van de melding waar je op zat? Dan mag je hem niet meer openen.
    if ($melding_id > 0 && !mijn_melding_ophalen($pdo, $melding_id, huidige_gebruiker_id())) {
        $terug = '/index.php';
    }
}

header('Location: ' . $terug);
exit;
