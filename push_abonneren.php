<?php
/**
 * Pushabonnement registreren/opzeggen (fase M5) — wordt door het script
 * op index.php aangeroepen (fetch, JSON), niet als gewone pagina bezocht.
 * Login vereist: een abonnement hoort altijd bij de ingelogde gebruiker,
 * nooit bij een los apparaat-ID.
 */
require_once __DIR__ . '/includes/functions.php';
vereis_login();
$pdo = get_pdo();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'fout' => 'Alleen POST toegestaan.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldige aanvraag.']);
    exit;
}

$actie = $data['actie'] ?? '';
$endpoint = trim((string) ($data['endpoint'] ?? ''));

if ($endpoint === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'fout' => 'Geen endpoint opgegeven.']);
    exit;
}

if ($actie === 'abonneren') {
    $p256dh = (string) ($data['p256dh'] ?? '');
    $auth = (string) ($data['auth'] ?? '');
    if ($p256dh === '' || $auth === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'fout' => 'Onvolledig pushabonnement (p256dh/auth ontbreekt).']);
        exit;
    }
    $omschrijving = isset($data['omschrijving']) ? substr((string) $data['omschrijving'], 0, 100) : null;
    push_abonnement_opslaan($pdo, huidige_gebruiker_id(), $endpoint, $p256dh, $auth, $omschrijving);
    echo json_encode(['ok' => true]);
    exit;
}

if ($actie === 'opzeggen') {
    push_abonnement_verwijderen($pdo, huidige_gebruiker_id(), $endpoint);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'fout' => 'Onbekende actie.']);
