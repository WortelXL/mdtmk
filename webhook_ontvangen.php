<?php
/**
 * Ontvangt MKAPP's uitgaande webhook voor "Melding toegewezen aan team"
 * (fase M5) en zet die om in een echt pushbericht naar de MDT-gebruiker
 * die op dat moment aan het team gekoppeld is. Deze URL wordt door
 * MKAPP zelf aangeroepen (Beheer > Connectiviteit) -- er is geen login
 * (dit is geen browserpagina), dus in plaats daarvan wordt een simpel
 * gedeeld geheim gecontroleerd (?token=..., zie config.php/README.md).
 *
 * Reageert alleen op event "melding_toegewezen" -- MKAPP's
 * webhook-mechanisme is generiek en kan ook andere events sturen naar
 * dezelfde URL als een beheerder dat zo instelt; die worden hier stil
 * genegeerd (geen foutmelding, gewoon niets doen).
 */
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

$token = (string) ($_GET['token'] ?? '');
if ($token === '' || !hash_equals(WEBHOOK_TOKEN, $token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldig of ontbrekend token.']);
    exit;
}

$body = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($body) || ($body['event'] ?? '') !== 'melding_toegewezen') {
    echo json_encode(['ok' => true, 'genegeerd' => true]);
    exit;
}

$data = is_array($body['data'] ?? null) ? $body['data'] : [];
$gebruiker_id = isset($data['gekoppelde_gebruiker_id']) ? (int) $data['gekoppelde_gebruiker_id'] : 0;

if ($gebruiker_id <= 0) {
    // Team (nog) niet gekoppeld aan een MDT-gebruiker -- niemand om naartoe te pushen.
    echo json_encode(['ok' => true, 'genegeerd' => true]);
    exit;
}

$pdo = get_pdo();
$meld_id = (string) ($data['meld_id'] ?? '');
$titel = (string) ($data['titel'] ?? '');
$melding_id = (int) ($data['id'] ?? 0);

verstuur_push_naar_gebruiker(
    $pdo,
    $gebruiker_id,
    'Melding toegewezen: ' . $meld_id,
    $titel,
    '/melding.php?id=' . $melding_id
);

echo json_encode(['ok' => true]);
