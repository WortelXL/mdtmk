<?php
/**
 * Slaat een Web Push-abonnement (fase M5) op of verwijdert het weer --
 * aangeroepen vanuit de client-side JS op index.php zodra iemand
 * pushmeldingen aan-/uitzet voor dit toestel. Vereist login (net als
 * elke andere schrijvende actie in MDT); een pushabonnement is altijd
 * gekoppeld aan de ingelogde gebruiker, nooit aan een meegegeven ID --
 * zo kan iemand nooit voor een ander abonneren/afmelden.
 */
require_once __DIR__ . '/includes/functions.php';
vereis_login();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($input) || !isset($input['actie'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldig verzoek.']);
    exit;
}

$pdo = get_pdo();
$actie = (string) $input['actie'];

if ($actie === 'abonneren') {
    $endpoint = trim((string) ($input['endpoint'] ?? ''));
    $p256dh = trim((string) ($input['p256dh'] ?? ''));
    $auth = trim((string) ($input['auth'] ?? ''));
    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'Onvolledig abonnement.']);
        exit;
    }
    $toestel = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    sla_push_abonnement_op($pdo, huidige_gebruiker_id(), $endpoint, $p256dh, $auth, $toestel ?: null);
    echo json_encode(['ok' => true]);
    exit;
}

if ($actie === 'afmelden') {
    $endpoint = trim((string) ($input['endpoint'] ?? ''));
    if ($endpoint !== '') {
        verwijder_push_abonnement($pdo, huidige_gebruiker_id(), $endpoint);
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'fout' => 'Onbekende actie.']);
