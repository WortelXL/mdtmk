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
 * Logt in tegen de gedeelde `gebruikers`-tabel. Alleen accounts met
 * mag_inloggen_mdt = 1 en actief = 1 mogen op MDT inloggen — zelfde
 * wachtwoord als (eventueel) MKAPP, geen apart accountbeheer hier.
 *
 * @return array{ok:bool, fout?:string}
 */
function probeer_inloggen(PDO $pdo, string $gebruikersnaam, string $wachtwoord): array
{
    $stmt = $pdo->prepare(
        'SELECT id, naam, wachtwoord_hash, actief, mag_inloggen_mdt FROM gebruikers WHERE gebruikersnaam = :u'
    );
    $stmt->execute(['u' => $gebruikersnaam]);
    $gebruiker = $stmt->fetch();

    if (!$gebruiker || !password_verify($wachtwoord, $gebruiker['wachtwoord_hash'])) {
        return ['ok' => false, 'fout' => 'Onjuiste gebruikersnaam of wachtwoord.'];
    }
    if (!$gebruiker['actief']) {
        return ['ok' => false, 'fout' => 'Dit account is gedeactiveerd.'];
    }
    if (!$gebruiker['mag_inloggen_mdt']) {
        return ['ok' => false, 'fout' => 'Dit account heeft geen toegang tot MDT. Vraag een beheerder om dit in MKAPP aan te zetten (Beheer > Gebruikers).'];
    }

    $_SESSION['gebruiker_id']   = (int) $gebruiker['id'];
    $_SESSION['gebruiker_naam'] = $gebruiker['naam'];

    return ['ok' => true];
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
 * De meldingen die aan de ingelogde gebruiker zijn toegewezen, hetzij
 * rechtstreeks (`toegewezen_aan_gebruiker_id`), hetzij via een team dat
 * aan dit account gekoppeld is (`toegewezen_aan_team_id`, fase M2).
 * Standaard alleen de actieve (niet-afgeronde) meldingen, want dat is
 * wat er voor een crewlid onderweg toe doet; afgeronde meldingen
 * kunnen nog gewoon rechtstreeks via de link geopend worden.
 */
function mijn_meldingen(PDO $pdo, int $gebruiker_id, bool $ook_afgerond = false): array
{
    $statussen = get_statussen($pdo);
    $actieve_sleutels = array_column(
        array_filter($statussen, fn($s) => $s['categorie'] === 'actief'),
        'sleutel'
    );
    $team = mijn_team($pdo, $gebruiker_id);
    $team_id = $team['id'] ?? 0; // 0 matcht nooit een echte team_id, ook niet als toegewezen_aan_team_id NULL is

    $sql = "SELECT m.*, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur, s.naam AS sub_naam
            FROM meldingen m
            LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
            LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
            WHERE (m.toegewezen_aan_gebruiker_id = :gid OR m.toegewezen_aan_team_id = :team_id)";
    $params = ['gid' => $gebruiker_id, 'team_id' => $team_id];

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
 * 1 melding, maar alleen als die aan de ingelogde gebruiker is
 * toegewezen (rechtstreeks, of via een gekoppeld team) — MDT mag geen
 * meldingen van anderen tonen. Retourneert null als de melding niet
 * bestaat of niet van jou is.
 */
function mijn_melding_ophalen(PDO $pdo, int $melding_id, int $gebruiker_id): ?array
{
    $team = mijn_team($pdo, $gebruiker_id);
    $team_id = $team['id'] ?? 0;

    $stmt = $pdo->prepare(
        "SELECT m.*, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur, s.naam AS sub_naam
         FROM meldingen m
         LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
         LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
         WHERE m.id = :id AND (m.toegewezen_aan_gebruiker_id = :gid OR m.toegewezen_aan_team_id = :team_id)"
    );
    $stmt->execute(['id' => $melding_id, 'gid' => $gebruiker_id, 'team_id' => $team_id]);
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
 * hetzelfde logboek dat MKAPP op de melding-pagina toont. Aanroeper
 * moet zelf al bevestigd hebben dat deze melding aan de gebruiker (of
 * zijn team) toegewezen is (zie mijn_melding_ophalen()) — deze functie
 * doet zelf geen ownership-check.
 */
function voeg_logboekregel_toe(PDO $pdo, int $melding_id, string $tekst, int $gebruiker_id, string $auteur_naam): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO melding_notities (melding_id, notitie, auteur, gebruiker_id) VALUES (:m, :n, :a, :g)'
    );
    $stmt->execute(['m' => $melding_id, 'n' => $tekst, 'a' => $auteur_naam, 'g' => $gebruiker_id]);
}

// ---- Eenheidsstatus (fase M2) ------------------------------------------

/** Alle eenheidsstatussen (OW/TP/IR/BS/PS/OP), op volgorde. */
function alle_eenheidsstatussen(PDO $pdo): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = $pdo->query('SELECT * FROM eenheidsstatussen ORDER BY volgorde ASC, id ASC')->fetchAll();
    }
    return $cache;
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
 */
function zet_eenheidsstatus(PDO $pdo, int $gebruiker_id, int $eenheidsstatus_id, string $gebruiker_naam): ?array
{
    $status_stmt = $pdo->prepare('SELECT * FROM eenheidsstatussen WHERE id = :id');
    $status_stmt->execute(['id' => $eenheidsstatus_id]);
    $status = $status_stmt->fetch();
    if (!$status) {
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
