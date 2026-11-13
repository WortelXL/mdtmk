<?php
/**
 * Pushabonnement registreren/opzeggen (fase M5) en, sinds V0.0.19, de
 * voorkeuren per apparaat (status, voorkeuren, test) — wordt door het script
 * op instellingen.php aangeroepen (fetch, JSON), niet als gewone pagina bezocht.
 * Login vereist: een abonnement hoort altijd bij de ingelogde gebruiker,
 * nooit bij een los apparaat-ID.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/webpush.php';
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
    // Nieuw abonnement: standaard "bij toewijzing" aan, "kritiek voor iedereen" uit
    // (alleen bij een echt nieuw apparaat -- een vernieuwing behoudt de keuzes).
    $bestaand = push_voorkeuren_ophalen($pdo, huidige_gebruiker_id(), $endpoint);
    echo json_encode(['ok' => true, 'voorkeuren' => $bestaand]);
    exit;
}

if ($actie === 'status') {
    echo json_encode([
        'ok' => true,
        'beschikbaar' => push_voorkeuren_beschikbaar($pdo),
        'voorkeuren' => push_voorkeuren_ophalen($pdo, huidige_gebruiker_id(), $endpoint),
    ]);
    exit;
}

if ($actie === 'voorkeuren') {
    $opgeslagen = push_voorkeuren_opslaan(
        $pdo,
        huidige_gebruiker_id(),
        $endpoint,
        !empty($data['toewijzing']),
        !empty($data['kritiek_alle'])
    );
    if (!$opgeslagen) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'fout' => 'Voorkeuren zijn nog niet beschikbaar: voer eerst migratie_push_voorkeuren.sql uit in MKAPP.']);
        exit;
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($actie === 'test') {
    $stmt = $pdo->prepare('SELECT * FROM push_abonnementen WHERE gebruiker_id = :g AND endpoint = :e LIMIT 1');
    $stmt->execute(['g' => huidige_gebruiker_id(), 'e' => $endpoint]);
    $abonnement = $stmt->fetch();
    if (!$abonnement) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'fout' => 'Dit apparaat is niet geabonneerd op pushmeldingen.']);
        exit;
    }
    if (VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'fout' => 'VAPID-sleutelpaar niet ingesteld op MDT.']);
        exit;
    }
    $kritiek = ($data['soort'] ?? '') === 'kritiek';
    $resultaat = webpush_versturen(
        $abonnement['endpoint'],
        $abonnement['p256dh'],
        $abonnement['auth'],
        [
            'titel'      => ($kritiek ? '🔴 KRITIEK — ' : '') . 'MDT testmelding',
            'tekst'      => $kritiek ? 'Dit is een test van een kritieke melding.' : 'Pushmeldingen werken op dit apparaat.',
            'url'        => '/instellingen.php',
            'kritiek'    => $kritiek,
            'melding_id' => 0,
            'test'       => true,
        ],
        VAPID_PRIVATE_KEY,
        VAPID_PUBLIC_KEY,
        VAPID_SUBJECT,
        $kritiek ? 'high' : ''
    );
    if (!$resultaat['ok'] && in_array($resultaat['http_status'], [404, 410], true)) {
        push_abonnement_verwijderen_op_id($pdo, (int) $abonnement['id']);
    }
    echo json_encode(['ok' => (bool) $resultaat['ok'], 'fout' => $resultaat['fout'] ?? null]);
    exit;
}

if ($actie === 'opzeggen') {
    push_abonnement_verwijderen($pdo, huidige_gebruiker_id(), $endpoint);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'fout' => 'Onbekende actie.']);
