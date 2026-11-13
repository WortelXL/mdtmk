<?php
/**
 * Ontvangt MKAPP's uitgaande webhook en stuurt op basis daarvan pushmeldingen
 * (fase M5, zonder externe library — zie includes/webpush.php):
 *  - 'melding_toegewezen': naar elk abonnement van elke toegewezen MDT-gebruiker
 *    (sinds V0.0.15 alle leden van een team), tenzij die op dat apparaat
 *    "melding bij toewijzing" uit heeft staan (V0.0.19).
 *  - 'melding_aangemaakt' (V0.0.19): alleen bij prioriteit 'kritiek', naar elk
 *    apparaat dat "kritieke meldingen voor iedereen" aan heeft staan.
 * Kritieke meldingen krijgen "🔴 KRITIEK" in de titel en Urgency: high.
 *
 * Publiek endpoint (geen login — MKAPP zelf is niet ingelogd op MDT),
 * daarom beveiligd met een los deelbaar token in de query-string. Stel
 * deze URL in MKAPP in bij Beheer > Connectiviteit > Webhook toevoegen:
 *   https://<mdt-domein>/webhook_ontvangen.php?token=<WEBHOOK_TOKEN>
 *   Events: melding_toegewezen + melding_aangemaakt · Platform: generiek
 *
 * Faalt altijd stil (2xx) naar MKAPP toe voor zaken die niet aan MKAPP
 * liggen (bv. de toegewezen gebruiker heeft geen pushabonnement) --
 * MKAPP's eigen webhook-log zou anders "mislukt" tonen voor iets dat
 * gewoon een normale situatie is.
 */
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/webpush.php';
$pdo = get_pdo();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'fout' => 'Alleen POST toegestaan.']);
    exit;
}

if (WEBHOOK_TOKEN === '' || !hash_equals(WEBHOOK_TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'fout' => 'Ongeldig of ontbrekend token.']);
    exit;
}

if (VAPID_PUBLIC_KEY === '' || VAPID_PRIVATE_KEY === '') {
    // Geen sleutelpaar ingesteld -- niets te versturen, maar dit is een
    // configuratieprobleem aan MDT's kant, niet iets dat MKAPP's
    // webhook-log als "mislukt" hoeft te tonen.
    http_response_code(200);
    echo json_encode(['ok' => false, 'fout' => 'VAPID-sleutelpaar niet ingesteld op MDT.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$event = is_array($body) ? (string) ($body['event'] ?? '') : '';
if (!in_array($event, ['melding_toegewezen', 'melding_aangemaakt'], true)) {
    http_response_code(200);
    echo json_encode(['ok' => true, 'genegeerd' => true]);
    exit;
}

$data = $body['data'] ?? [];
$kritiek = (string) ($data['prioriteit'] ?? '') === 'kritiek';
$melding_id = (int) ($data['id'] ?? 0);
$titel = (string) ($data['titel'] ?? 'Melding');
$meld_id = (string) ($data['meld_id'] ?? '');
$naam = ($meld_id !== '' ? $meld_id . ' — ' : '') . $titel;

// Te versturen abonnementen, ontdubbeld op abonnement-id.
$abonnementen = [];

if ($event === 'melding_aangemaakt') {
    // Alleen kritieke nieuwe meldingen zijn push-waardig (voor wie dat aanzet).
    if (!$kritiek) {
        http_response_code(200);
        echo json_encode(['ok' => true, 'genegeerd' => true]);
        exit;
    }
    foreach (push_abonnementen_kritiek_alle($pdo) as $abonnement) {
        $abonnementen[(int) $abonnement['id']] = $abonnement;
    }
    $locatie = trim((string) ($data['locatie'] ?? ''));
    $tekst = 'Nieuwe kritieke melding' . ($locatie !== '' ? ' — ' . $locatie : '');
} else {
    // V0.0.15: MKAPP V2.0.2.22 stuurt voortaan 'gekoppelde_gebruiker_ids'
    // (array, kan meerdere leden bevatten bij een team) -- val terug op het
    // oude enkelvoudige 'gekoppelde_gebruiker_id' als een oudere MKAPP-versie
    // dat nieuwe veld nog niet meestuurt.
    $gebruiker_ids = [];
    if (isset($data['gekoppelde_gebruiker_ids']) && is_array($data['gekoppelde_gebruiker_ids'])) {
        $gebruiker_ids = array_values(array_unique(array_map('intval', $data['gekoppelde_gebruiker_ids'])));
    } elseif (isset($data['gekoppelde_gebruiker_id']) && (int) $data['gekoppelde_gebruiker_id'] > 0) {
        $gebruiker_ids = [(int) $data['gekoppelde_gebruiker_id']];
    }
    $gebruiker_ids = array_filter($gebruiker_ids, fn($id) => $id > 0);
    if (!$gebruiker_ids) {
        // Team zonder leden -- niemand om te pushen.
        http_response_code(200);
        echo json_encode(['ok' => true, 'genegeerd' => true]);
        exit;
    }
    $voorkeuren = push_voorkeuren_beschikbaar($pdo);
    foreach ($gebruiker_ids as $gebruiker_id) {
        foreach (push_abonnementen_voor_gebruiker($pdo, $gebruiker_id) as $abonnement) {
            // V0.0.19: apparaat met "melding bij toewijzing" uit slaan we over.
            if ($voorkeuren && !(int) $abonnement['toewijzing']) {
                continue;
            }
            $abonnementen[(int) $abonnement['id']] = $abonnement;
        }
    }
    $context = $data['team_naam'] ?? null;
    $tekst = $context ? 'Toegewezen aan team ' . $context : 'Rechtstreeks aan jou toegewezen';
}

$payload = [
    'titel'     => ($kritiek ? '🔴 KRITIEK — ' : '') . $naam,
    'tekst'     => $tekst,
    'url'       => '/melding.php?id=' . $melding_id,
    'kritiek'   => $kritiek,
    'melding_id' => $melding_id,
];

$verstuurd = 0;
foreach ($abonnementen as $abonnement) {
    $resultaat = webpush_versturen(
        $abonnement['endpoint'],
        $abonnement['p256dh'],
        $abonnement['auth'],
        $payload,
        VAPID_PRIVATE_KEY,
        VAPID_PUBLIC_KEY,
        VAPID_SUBJECT,
        $kritiek ? 'high' : ''
    );
    if ($resultaat['ok']) {
        $verstuurd++;
    } elseif (in_array($resultaat['http_status'], [404, 410], true)) {
        // Pushdienst zegt: dit abonnement bestaat niet meer (browserdata
        // gewist, uitgeschreven bij de pushdienst, enz.) -- opruimen.
        push_abonnement_verwijderen_op_id($pdo, (int) $abonnement['id']);
    }
}

http_response_code(200);
echo json_encode(['ok' => true, 'verstuurd' => $verstuurd, 'abonnementen' => count($abonnementen)]);
