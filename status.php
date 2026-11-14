<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

$terug = '/index.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eenheidsstatus_id = (int) ($_POST['eenheidsstatus_id'] ?? 0);
    if ($eenheidsstatus_id > 0) {
        zet_eenheidsstatus($pdo, huidige_gebruiker_id(), $eenheidsstatus_id, huidige_gebruiker_naam());
    }
    // V0.0.20: de knoppen staan op de melding zelf -- terug naar die melding
    // (alleen een getal, dus geen vrij in te vullen redirect).
    $melding_id = (int) ($_POST['melding_id'] ?? 0);
    if ($melding_id > 0) {
        $terug = '/melding.php?id=' . $melding_id;
    }
}

header('Location: ' . $terug);
exit;
