<?php
/**
 * MDT — gedeelde helperfuncties (fase M1).
 *
 * MDT leest/schrijft in dezelfde database als MKAPP. Deze functies zijn
 * bewust een kleine, eigen set — geen kopie van MKAPP's volledige
 * functions.php — en beperken zich tot wat fase M1 nodig heeft:
 * inloggen, "mijn meldingen" lezen, en 1 melding + logboek tonen.
 */

require_once __DIR__ . '/db.php';

// Web Push-library (fase M5) -- alleen aanwezig ná `composer install`
// (gebeurt automatisch tijdens de Docker-build, zie Dockerfile). Buiten
// Docker (bv. een lokale php -S zonder composer install) bestaat dit
// bestand niet -- de push-functies verderop in dit bestand controleren
// dat zelf en doen dan gewoon niets, i.p.v. een fatale fout te geven.
$mdt_webpush_autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($mdt_webpush_autoload)) {
    require_once $mdt_webpush_autoload;
}
unset($mdt_webpush_autoload);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ---- Inloggen -------------------------------------------------------------

function is_ingelogd(): bool
{
    return !empty($_SESSION['gebruiker_id']);
}

function huidige_gebruiker_naam(): string
{
    return $_SESSION['gebruiker_naam'] ?? '';
}

function huidige_gebruiker_id(): int
{
    return (int) ($_SESSION['gebruiker_id'] ?? 0);
}

function vereis_login(): void
{
    if (!is_ingelogd()) {
        header('Location: /login.php');
        exit;
    }
}

/**
 * Logt in tegen de gedeelde `gebruikers`-tabel. Mag inloggen op MDT als
 * er een actieve rij voor dit account in `mdt_gebruikers` staat (sinds
 * V0.0.3/MKAPP V2.0.2.2, fase M6 — los MDT-gebruikersbeheer, i.p.v. de
 * oude `gebruikers.mag_inloggen_mdt`-vlag) — zelfde wachtwoord als
 * (eventueel) MKAPP, geen apart accountbeheer hier.
 *
 * @return array{ok:bool, fout?:string}
 */
function probeer_inloggen(PDO $pdo, string $gebruikersnaam, string $wachtwoord): array
{
    $stmt = $pdo->prepare(
        'SELECT g.id, g.naam, g.wachtwoord_hash, g.actief AS gebruiker_actief, m.actief AS mdt_actief
         FROM gebruikers g
         LEFT JOIN mdt_gebruikers m ON m.gebruiker_id = g.id
         WHERE g.gebruikersnaam = :u'
    );
    $stmt->execute(['u' => $gebruikersnaam]);
    $gebruiker = $stmt->fetch();

    if (!$gebruiker || !password_verify($wachtwoord, $gebruiker['wachtwoord_hash'])) {
        return ['ok' => false, 'fout' => 'Onjuiste gebruikersnaam of wachtwoord.'];
    }
    if (!$gebruiker['gebruiker_actief']) {
        return ['ok' => false, 'fout' => 'Dit account is gedeactiveerd.'];
    }
    if (!$gebruiker['mdt_actief']) {
        return ['ok' => false, 'fout' => 'Dit account heeft geen toegang tot MDT. Vraag een beheerder om dit in MKAPP aan te zetten (Beheer > MDT-gebruikers).'];
    }

    $_SESSION['gebruiker_id']   = (int) $gebruiker['id'];
    $_SESSION['gebruiker_naam'] = $gebruiker['naam'];

    return ['ok' => true];
}

/**
 * De MDT-instellingen van een gebruiker (fase M6): statusoverzicht,
 * "alle meldingen", mag schrijven, en de classificatiescope van een
 * eventueel gekoppelde rol. Bestaat er (nog) geen mdt_gebruikers-rij
 * (zou niet moeten gebeuren voor iemand die al ingelogd is, maar voor
 * de zekerheid) dan de veiligste standaardwaarden: alles uit/dicht
 * behalve statusoverzicht.
 */
function mdt_instellingen(PDO $pdo, int $gebruiker_id): array
{
    static $cache = [];
    if (isset($cache[$gebruiker_id])) {
        return $cache[$gebruiker_id];
    }

    $stmt = $pdo->prepare(
        'SELECT m.toon_status_overzicht, m.alle_meldingen, m.mag_schrijven, m.rol_id, r.hoofdclassificatie_id
         FROM mdt_gebruikers m
         LEFT JOIN rollen r ON r.id = m.rol_id
         WHERE m.gebruiker_id = :gid'
    );
    $stmt->execute(['gid' => $gebruiker_id]);
    $rij = $stmt->fetch();

    $instellingen = $rij ?: [
        'toon_status_overzicht' => 1,
        'alle_meldingen'        => 0,
        'mag_schrijven'         => 0,
        'rol_id'                => null,
        'hoofdclassificatie_id' => null,
    ];
    $cache[$gebruiker_id] = $instellingen;
    return $instellingen;
}

// ---- Statussen (alleen lezen, gedeeld met MKAPP) --------------------------

function get_statussen(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT * FROM statussen ORDER BY volgorde ASC, id ASC')->fetchAll();
    }
    return $cache;
}

function status_label(PDO $pdo, string $status): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = array_column(get_statussen($pdo), 'naam', 'sleutel');
    }
    return $cache[$status] ?? $status;
}

function status_kleur(PDO $pdo, string $status): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = array_column(get_statussen($pdo), 'kleur', 'sleutel');
    }
    return $cache[$status] ?? '#6b7280';
}

function status_tag_html(PDO $pdo, string $status): string
{
    $kleur = status_kleur($pdo, $status);
    return '<span class="tag" style="background:' . $kleur . '22; color:' . $kleur . ';">'
        . '<span class="tag-dot" style="background:' . $kleur . ';"></span>'
        . e(status_label($pdo, $status)) . '</span>';
}

function prioriteit_label(string $prioriteit): string
{
    return [
        'laag'    => 'Laag',
        'normaal' => 'Normaal',
        'hoog'    => 'Hoog',
        'kritiek' => 'Kritiek',
    ][$prioriteit] ?? $prioriteit;
}

function prioriteit_class(string $prioriteit): string
{
    return 'prio-' . $prioriteit;
}

// ---- Team (fase M2) ---------------------------------------------------

/**
 * Het team dat op dit moment aan de gebruiker gekoppeld is (een team
 * heeft altijd hoogstens 1 gekoppelde gebruiker tegelijk), of null als
 * er geen team aan dit account hangt. Gebruikt om ook team-toegewezen
 * meldingen mee te tellen (naast rechtstreekse individuele toewijzing).
 */
function mijn_team(PDO $pdo, int $gebruiker_id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM teams WHERE gekoppelde_gebruiker_id = :gid LIMIT 1');
    $stmt->execute(['gid' => $gebruiker_id]);
    $team = $stmt->fetch();
    return $team ?: null;
}

// ---- Meldingen ----------------------------------------------------------

/**
 * De meldingen die de ingelogde gebruiker mag zien. Standaard (modus
 * "toegewezen"): alleen wat aan hem rechtstreeks is toegewezen
 * (`toegewezen_aan_gebruiker_id`) of via een gekoppeld team
 * (`toegewezen_aan_team_id`, fase M2). Heeft de gebruiker "alle
 * meldingen" aanstaan (fase M6) en wordt modus "alle" gevraagd, dan
 * vervalt die eigendomsbeperking — optioneel nog beperkt tot de
 * classificatie van een gekoppelde rol, en anders écht alles. De modus
 * wordt hier server-side tegen de instelling gecontroleerd, niet
 * zomaar aangenomen van de aanroeper.
 *
 * Standaard alleen de actieve (niet-afgeronde) meldingen, want dat is
 * wat er voor een crewlid onderweg toe doet; afgeronde meldingen
 * kunnen nog gewoon rechtstreeks via de link geopend worden.
 */
function mijn_meldingen(PDO $pdo, int $gebruiker_id, bool $ook_afgerond = false, string $weergave = 'toegewezen'): array
{
    $statussen = get_statussen($pdo);
    $actieve_sleutels = array_column(
        array_filter($statussen, fn($s) => $s['categorie'] === 'actief'),
        'sleutel'
    );
    $instellingen = mdt_instellingen($pdo, $gebruiker_id);
    $alle_meldingen_toegestaan = $weergave === 'alle' && $instellingen['alle_meldingen'];

    $sql = "SELECT m.*, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur, s.naam AS sub_naam
            FROM meldingen m
            LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
            LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
            WHERE 1=1";
    $params = [];

    if ($alle_meldingen_toegestaan) {
        if ($instellingen['hoofdclassificatie_id']) {
            $sql .= ' AND m.hoofdclassificatie_id = :hc';
            $params['hc'] = $instellingen['hoofdclassificatie_id'];
        }
        // Geen gekoppelde rol (of geen classificatiekoppeling erop) = echt alles, geen extra filter.
    } else {
        $team = mijn_team($pdo, $gebruiker_id);
        $team_id = $team['id'] ?? 0; // 0 matcht nooit een echte team_id, ook niet als toegewezen_aan_team_id NULL is
        $sql .= ' AND (m.toegewezen_aan_gebruiker_id = :gid OR m.toegewezen_aan_team_id = :team_id)';
        $params['gid'] = $gebruiker_id;
        $params['team_id'] = $team_id;
    }

    if (!$ook_afgerond && $actieve_sleutels) {
        $plekhouders = [];
        foreach ($actieve_sleutels as $i => $sleutel) {
            $plekhouders[] = ':s' . $i;
            $params['s' . $i] = $sleutel;
        }
        $sql .= ' AND m.status IN (' . implode(',', $plekhouders) . ')';
    }

    $sql .= ' ORDER BY FIELD(m.prioriteit,"kritiek","hoog","normaal","laag"), m.aangemaakt_op DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * 1 melding, maar alleen als de gebruiker 'm mag zien: rechtstreeks
 * toegewezen, via een gekoppeld team, of — met "alle meldingen" aan —
 * binnen de classificatiescope van een gekoppelde rol (of, zonder
 * classificatiekoppeling, gewoon elke melding). MDT mag nooit een
 * melding tonen die buiten al deze gevallen valt. Retourneert null als
 * de melding niet bestaat of niet mag. Dit is ook de poort voor
 * schrijven (logboek toevoegen): mag een gebruiker een melding via
 * "alle meldingen" zien, dan mag hij er ook in loggen.
 */
function mijn_melding_ophalen(PDO $pdo, int $melding_id, int $gebruiker_id): ?array
{
    $team = mijn_team($pdo, $gebruiker_id);
    $team_id = $team['id'] ?? 0;
    $instellingen = mdt_instellingen($pdo, $gebruiker_id);

    $sql = "SELECT m.*, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur, s.naam AS sub_naam
            FROM meldingen m
            LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
            LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
            WHERE m.id = :id AND (m.toegewezen_aan_gebruiker_id = :gid OR m.toegewezen_aan_team_id = :team_id";
    $params = ['id' => $melding_id, 'gid' => $gebruiker_id, 'team_id' => $team_id];

    if ($instellingen['alle_meldingen']) {
        if ($instellingen['hoofdclassificatie_id']) {
            $sql .= ' OR m.hoofdclassificatie_id = :hc';
            $params['hc'] = $instellingen['hoofdclassificatie_id'];
        } else {
            $sql .= ' OR 1 = 1';
        }
    }
    $sql .= ')';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $melding = $stmt->fetch();
    return $melding ?: null;
}

/** Het logboek van 1 melding (omschrijving + losse notities), nieuwste eerst. */
function melding_logboek(PDO $pdo, int $melding_id): array
{
    $stmt = $pdo->prepare('SELECT * FROM melding_notities WHERE melding_id = :id ORDER BY aangemaakt_op DESC');
    $stmt->execute(['id' => $melding_id]);
    return $stmt->fetchAll();
}

/**
 * Voegt een vrije-tekst logboekregel toe aan een melding (fase M2) —
 * schrijft rechtstreeks in de bestaande `melding_notities`-tabel, exact
 * hetzelfde logboek dat MKAPP op de melding-pagina toont, met
 * `bron = 'mdt'` (fase M6) zodat de regel in MKAPP herkenbaar is als
 * vanaf de telefoon geplaatst. Aanroeper moet zelf al bevestigd hebben
 * dat deze melding aan de gebruiker (of zijn team, of via "alle
 * meldingen") toegewezen/zichtbaar is (zie mijn_melding_ophalen()) —
 * deze functie doet zelf geen ownership-check.
 */
function voeg_logboekregel_toe(PDO $pdo, int $melding_id, string $tekst, int $gebruiker_id, string $auteur_naam): void
{
    $stmt = $pdo->prepare(
        "INSERT INTO melding_notities (melding_id, notitie, auteur, gebruiker_id, bron) VALUES (:m, :n, :a, :g, 'mdt')"
    );
    $stmt->execute(['m' => $melding_id, 'n' => $tekst, 'a' => $auteur_naam, 'g' => $gebruiker_id]);
}

// ---- Eenheidsstatus (fase M2, per rol sinds fase M7) --------------------

/**
 * De eenheidsstatussen die bij $rol_id horen, op volgorde. Sinds fase
 * M7 hoort elke status bij precies 1 rol (Beheer > Eenheidsstatussen
 * in MKAPP) — geen gekoppelde rol (null) levert dus altijd een lege
 * lijst op, bewust geen generieke terugvallijst.
 */
function alle_eenheidsstatussen(PDO $pdo, ?int $rol_id): array
{
    static $cache = [];
    if ($rol_id === null) {
        return [];
    }
    if (!isset($cache[$rol_id])) {
        $stmt = $pdo->prepare('SELECT * FROM eenheidsstatussen WHERE rol_id = :r ORDER BY volgorde ASC, id ASC');
        $stmt->execute(['r' => $rol_id]);
        $cache[$rol_id] = $stmt->fetchAll();
    }
    return $cache[$rol_id];
}

/** De eenheidsstatus die de gebruiker nu heeft, of null als die nog nooit gezet is. */
function huidige_eenheidsstatus(PDO $pdo, int $gebruiker_id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT e.* FROM gebruikers g
         JOIN eenheidsstatussen e ON e.id = g.huidige_eenheidsstatus_id
         WHERE g.id = :gid'
    );
    $stmt->execute(['gid' => $gebruiker_id]);
    $status = $stmt->fetch();
    return $status ?: null;
}

/**
 * Zet de eenheidsstatus van de gebruiker (1 tik = OW/TP/IR/BS/PS/OP).
 * Is er op dat moment een actieve melding aan de gebruiker (of zijn
 * team) toegewezen, dan komt er automatisch een logboekregel bij op
 * die melding(en) — zichtbaar voor de centralist zonder dat de crew
 * iets hoeft te typen. Is er geen actieve melding, dan wordt alleen de
 * eigen status bijgewerkt (bv. bij "Beschikbaar"/"Op de post").
 *
 * Staat `toon_status_overzicht` uit voor dit account (fase M6), dan
 * weigert deze functie server-side — los van `mag_schrijven`, dat gaat
 * alleen over het vrije-tekst-logboek (zie melding.php).
 *
 * Sinds fase M7 moet de status ook echt bij de eigen gekoppelde rol
 * horen — dit is een server-side controle, niet alleen het verbergen
 * van knoppen in de UI: zonder gekoppelde rol, of bij een status van
 * een andere rol, weigert deze functie.
 */
function zet_eenheidsstatus(PDO $pdo, int $gebruiker_id, int $eenheidsstatus_id, string $gebruiker_naam): ?array
{
    $instellingen = mdt_instellingen($pdo, $gebruiker_id);
    if (!$instellingen['toon_status_overzicht']) {
        return null;
    }
    if (!$instellingen['rol_id']) {
        return null;
    }

    $status_stmt = $pdo->prepare('SELECT * FROM eenheidsstatussen WHERE id = :id');
    $status_stmt->execute(['id' => $eenheidsstatus_id]);
    $status = $status_stmt->fetch();
    if (!$status || (int) $status['rol_id'] !== (int) $instellingen['rol_id']) {
        return null;
    }

    $stmt = $pdo->prepare('UPDATE gebruikers SET huidige_eenheidsstatus_id = :s WHERE id = :gid');
    $stmt->execute(['s' => $eenheidsstatus_id, 'gid' => $gebruiker_id]);

    $actieve_meldingen = mijn_meldingen($pdo, $gebruiker_id, false);
    $regel = 'Status: ' . $status['naam'] . ' (' . $status['afkorting'] . ')';
    foreach ($actieve_meldingen as $melding) {
        voeg_logboekregel_toe($pdo, (int) $melding['id'], $regel, $gebruiker_id, $gebruiker_naam);
    }

    return $status;
}

// ---- Crew-lijst met bellen (fase M3) -----------------------------------

/**
 * Gecombineerde bellijst: de bestaande `crew`-tabel (contactpersonen
 * zonder eigen account) samen met de actieve MDT-gebruikers die een
 * telefoonnummer hebben (via Beheer > MDT-gebruikers in MKAPP, fase
 * M3) — 1 gesorteerde lijst, zodat je onderweg niet tussen 2 losse
 * lijstjes hoeft te kiezen om iemand te bellen. Iemand zonder
 * telefoonnummer staat er gewoon bij (herkenbaar aan het ontbreken van
 * een belknop) in plaats van stilzwijgend te verdwijnen.
 */
function crew_en_collegas(PDO $pdo): array
{
    return $pdo->query(
        "SELECT naam, functie, telefoonnummer, 'crew' AS type FROM crew
         UNION ALL
         SELECT g.naam, g.functie, m.telefoonnummer, 'collega' AS type
         FROM mdt_gebruikers m
         JOIN gebruikers g ON g.id = m.gebruiker_id
         WHERE m.actief = 1 AND g.actief = 1
         ORDER BY naam ASC"
    )->fetchAll();
}

// ---- Foto's (fase M4) ----------------------------------------------------

/** Toegestane afbeeldingstypen voor een foto-upload, met hun opslag-extensie. */
function toegestane_foto_mimetypes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];
}

/** Foto's bij 1 melding, nieuwste eerst. */
function melding_bijlagen(PDO $pdo, int $melding_id): array
{
    $stmt = $pdo->prepare('SELECT * FROM melding_bijlagen WHERE melding_id = :m ORDER BY aangemaakt_op DESC');
    $stmt->execute(['m' => $melding_id]);
    return $stmt->fetchAll();
}

/**
 * Verwerkt 1 geuploade foto (1 item uit $_FILES) voor een melding:
 * valideert het bestand echt als afbeelding (niet alleen de extensie of
 * het Content-Type dat de browser meestuurt, via getimagesize()), slaat
 * het op onder een willekeurige, niet-raadbare bestandsnaam op MDT's
 * eigen schijf/volume (/uploads/<melding_id>/...), zet de metadata +
 * volledige URL in de gedeelde database, en plaatst een logboekregel
 * zodat de toevoeging ook in het bestaande logboek zichtbaar is.
 * Aanroeper moet zelf al bevestigd hebben dat deze melding aan de
 * gebruiker toegewezen/zichtbaar is en dat mag_schrijven aan staat --
 * deze functie doet zelf geen ownership-check.
 *
 * @param array $bestand 1 item uit $_FILES (dus met tmp_name/name/size/error)
 * @return string|null null bij succes, anders een foutmelding voor de gebruiker
 */
function voeg_bijlage_toe(PDO $pdo, int $melding_id, array $bestand, int $gebruiker_id, string $auteur_naam): ?string
{
    if (($bestand['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // niets gekozen voor dit veld, geen foutmelding nodig
    }
    if ($bestand['error'] !== UPLOAD_ERR_OK) {
        return 'Uploaden van "' . $bestand['name'] . '" is mislukt (foutcode ' . $bestand['error'] . ').';
    }
    if (($bestand['size'] ?? 0) <= 0) {
        return 'Leeg bestand overgeslagen: ' . $bestand['name'];
    }

    $toegestaan = toegestane_foto_mimetypes();
    $info = @getimagesize($bestand['tmp_name']);
    $mime = $info['mime'] ?? null;
    if (!$mime || !isset($toegestaan[$mime])) {
        return 'Alleen afbeeldingen (jpg, png, webp, gif) kunnen toegevoegd worden: ' . $bestand['name'];
    }

    $map = __DIR__ . '/../uploads/' . $melding_id;
    if (!is_dir($map) && !mkdir($map, 0775, true) && !is_dir($map)) {
        return 'Kon de foto niet opslaan (map aanmaken mislukt): ' . $bestand['name'];
    }

    $opslagnaam = bin2hex(random_bytes(8)) . '.' . $toegestaan[$mime];
    if (!move_uploaded_file($bestand['tmp_name'], $map . '/' . $opslagnaam)) {
        return 'Kon de foto niet opslaan: ' . $bestand['name'];
    }

    $url = APP_BASE_URL . '/uploads/' . $melding_id . '/' . $opslagnaam;
    $originele_naam = $bestand['name'] !== '' ? $bestand['name'] : $opslagnaam;

    $stmt = $pdo->prepare(
        'INSERT INTO melding_bijlagen (melding_id, bestandsnaam, url, geupload_door_gebruiker_id) VALUES (:m, :b, :u, :g)'
    );
    $stmt->execute(['m' => $melding_id, 'b' => $originele_naam, 'u' => $url, 'g' => $gebruiker_id]);

    voeg_logboekregel_toe($pdo, $melding_id, '📷 Foto toegevoegd: ' . $originele_naam, $gebruiker_id, $auteur_naam);

    return null;
}

// ---- Push-meldingen (fase M5) ----------------------------------------------

/** Alle push-abonnementen (toestellen) van 1 gebruiker, nieuwste eerst. */
function mijn_push_abonnementen(PDO $pdo, int $gebruiker_id): array
{
    $stmt = $pdo->prepare(
        'SELECT id, toestel_omschrijving, aangemaakt_op FROM push_abonnementen
         WHERE gebruiker_id = :g ORDER BY aangemaakt_op DESC'
    );
    $stmt->execute(['g' => $gebruiker_id]);
    return $stmt->fetchAll();
}

/**
 * Slaat een nieuw push-abonnement (toestel/browser) op voor deze
 * gebruiker. "endpoint" is uniek (zie database.sql in de MKAPP-repo):
 * meldt hetzelfde toestel zich opnieuw aan (bv. na het wissen van
 * browserdata), dan werkt dit de bestaande rij bij in plaats van een
 * dubbele aan te maken.
 */
function sla_push_abonnement_op(PDO $pdo, int $gebruiker_id, string $endpoint, string $p256dh, string $auth, ?string $toestel_omschrijving): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO push_abonnementen (gebruiker_id, endpoint, p256dh, auth, toestel_omschrijving)
         VALUES (:g, :e, :p, :a, :t)
         ON DUPLICATE KEY UPDATE gebruiker_id = VALUES(gebruiker_id), p256dh = VALUES(p256dh),
             auth = VALUES(auth), toestel_omschrijving = VALUES(toestel_omschrijving)'
    );
    $stmt->execute([
        'g' => $gebruiker_id,
        'e' => $endpoint,
        'p' => $p256dh,
        'a' => $auth,
        't' => $toestel_omschrijving,
    ]);
}

/** Verwijdert het push-abonnement voor deze endpoint -- alleen als het echt van deze gebruiker is. */
function verwijder_push_abonnement(PDO $pdo, int $gebruiker_id, string $endpoint): void
{
    $stmt = $pdo->prepare('DELETE FROM push_abonnementen WHERE gebruiker_id = :g AND endpoint = :e');
    $stmt->execute(['g' => $gebruiker_id, 'e' => $endpoint]);
}

/**
 * Verwijdert 1 push-abonnement op basis van zijn endpoint, ongeacht
 * eigenaar -- gebruikt om een abonnement op te ruimen zodra de
 * push-dienst zelf meldt dat het niet meer bestaat (HTTP 404/410).
 */
function verwijder_push_abonnement_op_endpoint(PDO $pdo, string $endpoint): void
{
    $stmt = $pdo->prepare('DELETE FROM push_abonnementen WHERE endpoint = :e');
    $stmt->execute(['e' => $endpoint]);
}

/**
 * Stuurt een pushbericht naar alle toestellen van 1 gebruiker (fase
 * M5). Faalt altijd stil -- net als verstuur_webhooks() aan de
 * MKAPP-kant mag een mislukte push nooit de aanroepende actie (het
 * verwerken van de binnenkomende webhook) laten crashen. Ruimt
 * onderweg abonnementen op die de push-dienst als verlopen/ongeldig
 * meldt.
 *
 * Gebruikt de beproefde minishlink/web-push-library (composer) voor de
 * VAPID-ondertekening en de payload-encryptie -- bewust geen
 * zelfgebouwde cryptografie voor dit ene, foutgevoelige onderdeel (zie
 * de toelichting in Dockerfile/README.md).
 */
function verstuur_push_naar_gebruiker(PDO $pdo, int $gebruiker_id, string $titel, string $tekst, string $url): void
{
    if (!VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY || !class_exists(\Minishlink\WebPush\WebPush::class)) {
        return; // nog geen VAPID-sleutelpaar ingesteld, of composer install nog niet gedraaid
    }

    $stmt = $pdo->prepare('SELECT endpoint, p256dh, auth FROM push_abonnementen WHERE gebruiker_id = :g');
    $stmt->execute(['g' => $gebruiker_id]);
    $abonnementen = $stmt->fetchAll();
    if (!$abonnementen) {
        return;
    }

    try {
        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject'    => VAPID_SUBJECT,
                'publicKey'  => VAPID_PUBLIC_KEY,
                'privateKey' => VAPID_PRIVATE_KEY,
            ],
        ]);
    } catch (Throwable $e) {
        return; // ongeldig sleutelpaar -- niets te versturen
    }

    $payload = json_encode(['titel' => $titel, 'tekst' => $tekst, 'url' => $url], JSON_UNESCAPED_UNICODE);

    foreach ($abonnementen as $abonnement) {
        try {
            $subscription = \Minishlink\WebPush\Subscription::create([
                'endpoint' => $abonnement['endpoint'],
                'keys'     => ['p256dh' => $abonnement['p256dh'], 'auth' => $abonnement['auth']],
            ]);
            $webPush->queueNotification($subscription, $payload);
        } catch (Throwable $e) {
            // Deze ene rij overslaan, de rest gewoon proberen.
        }
    }

    try {
        foreach ($webPush->flush() as $report) {
            if (!$report->isSuccess() && $report->isSubscriptionExpired()) {
                verwijder_push_abonnement_op_endpoint($pdo, $report->getEndpoint());
            }
        }
    } catch (Throwable $e) {
        // Nooit laten crashen op een kapotte/onbereikbare push-dienst.
    }
}
