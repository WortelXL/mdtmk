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
 * Alle teams waar de gebruiker op dit moment lid van is (V0.0.15 --
 * iemand mag lid zijn van meerdere teams tegelijk, via de gedeelde
 * tabel `team_leden`; MKAPP V2.0.2.22). Lege array = geen enkel team.
 * Gebruikt om ook team-toegewezen meldingen mee te tellen (naast
 * rechtstreekse individuele toewijzing).
 */
function mijn_teams(PDO $pdo, int $gebruiker_id): array
{
    $stmt = $pdo->prepare(
        'SELECT t.* FROM team_leden tl JOIN teams t ON t.id = tl.team_id
         WHERE tl.gebruiker_id = :gid ORDER BY t.naam ASC'
    );
    $stmt->execute(['gid' => $gebruiker_id]);
    return $stmt->fetchAll();
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
        // V0.0.15: iemand kan lid zijn van meerdere teams tegelijk --
        // toegewezen_aan_team_id moet dus in de hele lijst matchen, niet
        // tegen 1 vast team_id. Geen enkel team = een lege IN-lijst, die
        // matcht nooit iets (net als de oude team_id=0-truc).
        $teams = mijn_teams($pdo, $gebruiker_id);
        $team_ids = array_column($teams, 'id');
        $sql .= ' AND (m.toegewezen_aan_gebruiker_id = :gid';
        if ($team_ids) {
            $plekhouders = [];
            foreach ($team_ids as $i => $tid) {
                $plekhouders[] = ':t' . $i;
                $params['t' . $i] = $tid;
            }
            $sql .= ' OR m.toegewezen_aan_team_id IN (' . implode(',', $plekhouders) . ')';
        }
        $sql .= ')';
        $params['gid'] = $gebruiker_id;
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
    // V0.0.15: lid van meerdere teams tegelijk mogelijk, zie mijn_meldingen().
    $teams = mijn_teams($pdo, $gebruiker_id);
    $team_ids = array_column($teams, 'id');
    $instellingen = mdt_instellingen($pdo, $gebruiker_id);

    $sql = "SELECT m.*, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur, s.naam AS sub_naam
            FROM meldingen m
            LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
            LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
            WHERE m.id = :id AND (m.toegewezen_aan_gebruiker_id = :gid";
    $params = ['id' => $melding_id, 'gid' => $gebruiker_id];
    if ($team_ids) {
        $plekhouders = [];
        foreach ($team_ids as $i => $tid) {
            $plekhouders[] = ':t' . $i;
            $params['t' . $i] = $tid;
        }
        $sql .= ' OR m.toegewezen_aan_team_id IN (' . implode(',', $plekhouders) . ')';
    }

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
 * Alle meldingen die (direct of indirect, transitief) aan elkaar
 * gekoppeld zijn, per gevraagde melding_id -- overgenomen uit mkapp
 * includes/functions.php (V2.0.2.21), zelfde tabel `melding_koppelingen`,
 * gedeelde database. BFS over alle koppelingen.
 *
 * @param array<int> $melding_ids
 * @return array<int, array<int>> geïndexeerd op melding_id -- de eigen
 *   id zit altijd in de eigen keten (ook zonder koppelingen).
 */
function melding_koppel_ketens(PDO $pdo, array $melding_ids): array
{
    $melding_ids = array_values(array_unique(array_map('intval', $melding_ids)));
    if (!$melding_ids) {
        return [];
    }

    $adjacency = [];
    foreach ($pdo->query('SELECT melding_id, gekoppelde_melding_id FROM melding_koppelingen')->fetchAll() as $rij) {
        $a = (int) $rij['melding_id'];
        $b = (int) $rij['gekoppelde_melding_id'];
        $adjacency[$a][] = $b;
        $adjacency[$b][] = $a;
    }

    $resultaat = [];
    foreach ($melding_ids as $start) {
        $keten = [$start => true];
        $wachtrij = [$start];
        while ($wachtrij) {
            $huidige = array_shift($wachtrij);
            foreach ($adjacency[$huidige] ?? [] as $buur) {
                if (!isset($keten[$buur])) {
                    $keten[$buur] = true;
                    $wachtrij[] = $buur;
                }
            }
        }
        $resultaat[$start] = array_keys($keten);
    }
    return $resultaat;
}

/**
 * Samengevoegd kladblok (V0.0.14, zelfde aanpak als mkapp V2.0.2.21):
 * voor elke gevraagde melding_id, alle melding_notities van de hele
 * koppelketen (zichzelf + alle direct/indirect gekoppelde meldingen),
 * chronologisch oplopend. Elke regel krijgt bron_meld_id/bron_titel mee
 * (welke melding hem geschreven heeft) en is_eigen (of dat de gevraagde
 * melding zelf is) -- zo blijft zichtbaar welke info uit welke melding
 * komt. Gebruikt op melding.php.
 *
 * @param array<int> $melding_ids
 * @return array<int, array<int, array>> geïndexeerd op melding_id
 */
function melding_notities_samengevoegd(PDO $pdo, array $melding_ids): array
{
    $ketens = melding_koppel_ketens($pdo, $melding_ids);
    if (!$ketens) {
        return [];
    }

    $alle_betrokken_ids = [];
    foreach ($ketens as $keten) {
        foreach ($keten as $bid) {
            $alle_betrokken_ids[$bid] = true;
        }
    }
    $alle_betrokken_ids = array_keys($alle_betrokken_ids);
    $plekhouders = implode(',', array_fill(0, count($alle_betrokken_ids), '?'));

    $meta = [];
    $stmt = $pdo->prepare("SELECT id, meld_id, titel FROM meldingen WHERE id IN ($plekhouders)");
    $stmt->execute($alle_betrokken_ids);
    foreach ($stmt->fetchAll() as $rij) {
        $meta[(int) $rij['id']] = $rij;
    }

    $stmt = $pdo->prepare("SELECT * FROM melding_notities WHERE melding_id IN ($plekhouders) ORDER BY aangemaakt_op ASC, id ASC");
    $stmt->execute($alle_betrokken_ids);
    $per_bron = [];
    foreach ($stmt->fetchAll() as $rij) {
        $per_bron[(int) $rij['melding_id']][] = $rij;
    }

    $resultaat = [];
    foreach ($ketens as $melding_id => $keten) {
        $regels = [];
        foreach ($keten as $bron_id) {
            foreach ($per_bron[$bron_id] ?? [] as $rij) {
                $rij['bron_meld_id'] = $meta[$bron_id]['meld_id'] ?? '?';
                $rij['bron_titel']   = $meta[$bron_id]['titel'] ?? '';
                $rij['is_eigen']     = ($bron_id === $melding_id);
                $regels[] = $rij;
            }
        }
        usort($regels, function ($a, $b) {
            $c = strcmp($a['aangemaakt_op'], $b['aangemaakt_op']);
            return $c !== 0 ? $c : ((int) $a['id'] <=> (int) $b['id']);
        });
        $resultaat[$melding_id] = $regels;
    }
    return $resultaat;
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
 *
 * V0.0.12: alleen wie op Beheer > Crew (in MKAPP) als "Zichtbaar in
 * MDT" staat aangevinkt komt hier nog in -- zo houdt de centralist
 * deze lijst schoon zonder iemand te hoeven verwijderen of
 * deactiveren. Vereist MKAPP V2.0.2.20 (kolom `zichtbaar_in_mdt` op
 * `crew`/`mdt_gebruikers`).
 */
function crew_en_collegas(PDO $pdo): array
{
    return $pdo->query(
        "SELECT naam, functie, telefoonnummer, 'crew' AS type FROM crew
         WHERE zichtbaar_in_mdt = 1
         UNION ALL
         SELECT g.naam, g.functie, m.telefoonnummer, 'collega' AS type
         FROM mdt_gebruikers m
         JOIN gebruikers g ON g.id = m.gebruiker_id
         WHERE m.actief = 1 AND g.actief = 1 AND m.zichtbaar_in_mdt = 1
         ORDER BY naam ASC"
    )->fetchAll();
}

// ---- Web Push-abonnementen (fase M5) ---------------------------------------

/**
 * Slaat een pushabonnement op (of ververst een bestaand abonnement met
 * dezelfde endpoint voor deze gebruiker -- een browser levert soms een
 * nieuwe p256dh/auth voor dezelfde endpoint bij hernieuwing). 1 rij per
 * combinatie van gebruiker + browser/apparaat.
 */
function push_abonnement_opslaan(PDO $pdo, int $gebruiker_id, string $endpoint, string $p256dh, string $auth, ?string $omschrijving): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO push_abonnementen (gebruiker_id, endpoint, p256dh, auth, omschrijving)
         VALUES (:g, :e, :p, :a, :o)
         ON DUPLICATE KEY UPDATE p256dh = VALUES(p256dh), auth = VALUES(auth), omschrijving = VALUES(omschrijving)'
    );
    $stmt->execute([
        'g' => $gebruiker_id,
        'e' => $endpoint,
        'p' => $p256dh,
        'a' => $auth,
        'o' => $omschrijving,
    ]);
}

/** Verwijdert 1 pushabonnement van deze gebruiker (bv. bij uitzetten, of een verlopen abonnement). */
function push_abonnement_verwijderen(PDO $pdo, int $gebruiker_id, string $endpoint): void
{
    $stmt = $pdo->prepare('DELETE FROM push_abonnementen WHERE gebruiker_id = :g AND endpoint = :e');
    $stmt->execute(['g' => $gebruiker_id, 'e' => $endpoint]);
}

/** Heeft deze gebruiker op dit moment minstens 1 actief pushabonnement (voor de aan/uit-status van het paneel)? */
function heeft_push_abonnement(PDO $pdo, int $gebruiker_id): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM push_abonnementen WHERE gebruiker_id = :g LIMIT 1');
    $stmt->execute(['g' => $gebruiker_id]);
    return (bool) $stmt->fetchColumn();
}

/** Alle pushabonnementen van 1 gebruiker (gebruikt door webhook_ontvangen.php om te versturen). */
function push_abonnementen_voor_gebruiker(PDO $pdo, int $gebruiker_id): array
{
    $stmt = $pdo->prepare('SELECT * FROM push_abonnementen WHERE gebruiker_id = :g');
    $stmt->execute(['g' => $gebruiker_id]);
    return $stmt->fetchAll();
}

/** Verwijdert 1 pushabonnement op basis van zijn id (bv. nadat de pushdienst 404/410 teruggaf -- het abonnement bestaat niet meer). */
function push_abonnement_verwijderen_op_id(PDO $pdo, int $abonnement_id): void
{
    $stmt = $pdo->prepare('DELETE FROM push_abonnementen WHERE id = :id');
    $stmt->execute(['id' => $abonnement_id]);
}
