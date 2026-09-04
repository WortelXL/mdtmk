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

// ---- Meldingen (alleen lezen — schrijven komt in fase M2) -----------------

/**
 * De meldingen die aan de ingelogde gebruiker zijn toegewezen
 * (`toegewezen_aan_gebruiker_id`). Fase M1 leest alleen deze kolom —
 * toewijzing via een Team (`toegewezen_aan_team_id`) komt in fase M2.
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

    $sql = "SELECT m.*, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur, s.naam AS sub_naam
            FROM meldingen m
            LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
            LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
            WHERE m.toegewezen_aan_gebruiker_id = :gid";
    $params = ['gid' => $gebruiker_id];

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
 * toegewezen — MDT mag geen meldingen van anderen tonen. Retourneert
 * null als de melding niet bestaat of niet van jou is.
 */
function mijn_melding_ophalen(PDO $pdo, int $melding_id, int $gebruiker_id): ?array
{
    $stmt = $pdo->prepare(
        "SELECT m.*, h.naam AS hoofd_naam, h.kleur AS hoofd_kleur, s.naam AS sub_naam
         FROM meldingen m
         LEFT JOIN hoofdclassificaties h ON h.id = m.hoofdclassificatie_id
         LEFT JOIN subclassificaties s ON s.id = m.subclassificatie_id
         WHERE m.id = :id AND m.toegewezen_aan_gebruiker_id = :gid"
    );
    $stmt->execute(['id' => $melding_id, 'gid' => $gebruiker_id]);
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
