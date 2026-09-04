<?php
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eenheidsstatus_id = (int) ($_POST['eenheidsstatus_id'] ?? 0);
    if ($eenheidsstatus_id > 0) {
        zet_eenheidsstatus($pdo, huidige_gebruiker_id(), $eenheidsstatus_id, huidige_gebruiker_naam());
    }
}

header('Location: /index.php');
exit;
