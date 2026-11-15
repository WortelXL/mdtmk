<?php
/**
 * Sessieduur (MKAPP V2.0.2.50 / MDT V0.0.23).
 *
 * Hoe lang iemand ingelogd blijft ZONDER iets te doen, instelbaar door de
 * beheerder in MKAPP onder Beheer > Instellingen (sleutel `sessie_uren` in de
 * tabel `instellingen`; keuzes 1, 2, 4, 8 of 12 uur, standaard 8). MKAPP en MDT
 * gebruiken dezelfde instelling en dit zelfde bestand.
 *
 * "Zonder iets te doen" betekent: elke pagina die je opent (ook het automatisch
 * verversen van een scherm dat openstaat) telt als activiteit en start de tijd
 * opnieuw. Het sessiecookie blijft ook na het sluiten van de browser/app
 * geldig, tot de ingestelde tijd om is.
 *
 * De waarde wordt kort onthouden in een tijdelijk bestand (30 s), zodat niet
 * elke pagina eerst de database hoeft te raadplegen. Kan de database of de
 * instelling niet worden gelezen (bv. MDT zonder het recht SELECT op
 * `instellingen`), dan geldt de standaard van 8 uur.
 */

const SESSIE_UREN_KEUZES = [1, 2, 4, 8, 12];
const SESSIE_UREN_STANDAARD = 8;
const SESSIE_CACHE_SECONDEN = 30;

function sessie_cache_pad(): string
{
    return rtrim(sys_get_temp_dir(), '/\\') . '/sessie_uren_' . md5((defined('DB_HOST') ? DB_HOST : '') . '|' . (defined('DB_NAME') ? DB_NAME : '') . '|' . __DIR__);
}

/** Maakt de onthouden waarde ongeldig (aanroepen na het opslaan van de instelling). */
function sessie_cache_wissen(): void
{
    @unlink(sessie_cache_pad());
}

/** Geldige sessieduur in uren (1, 2, 4, 8 of 12); anders de standaard. */
function sessie_uren_normaliseren($waarde): int
{
    $uren = (int) $waarde;
    return in_array($uren, SESSIE_UREN_KEUZES, true) ? $uren : SESSIE_UREN_STANDAARD;
}

function sessie_uren(): int
{
    $pad = sessie_cache_pad();
    $mtime = @filemtime($pad);
    if ($mtime !== false && time() - $mtime < SESSIE_CACHE_SECONDEN) {
        $inhoud = @file_get_contents($pad);
        if ($inhoud !== false && $inhoud !== '') {
            return sessie_uren_normaliseren($inhoud);
        }
    }

    $uren = SESSIE_UREN_STANDAARD;
    try {
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET),
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $stmt = $pdo->prepare('SELECT waarde FROM instellingen WHERE sleutel = :s');
        $stmt->execute(['s' => 'sessie_uren']);
        $gevonden = $stmt->fetchColumn();
        if ($gevonden !== false) {
            $uren = sessie_uren_normaliseren($gevonden);
        }
    } catch (Throwable $e) {
        // Geen database/instelling/recht: standaard gebruiken (en toch even onthouden).
    }
    @file_put_contents($pad, (string) $uren, LOCK_EX);
    return $uren;
}

/** Start de sessie met de ingestelde duur en laat een verlopen sessie vervallen. */
function sessie_starten(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $seconden = sessie_uren() * 3600;
    ini_set('session.gc_maxlifetime', (string) $seconden);
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => $seconden,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // PHP stuurt het sessiecookie alleen mee bij een nieuwe sessie; zonder dit
    // zou de vervaldatum daarvan vast blijven staan terwijl je gewoon doorwerkt.
    // Daarom bij elke pagina opnieuw sturen, zodat de tijd schuift met je activiteit.
    setcookie(session_name(), session_id(), [
        'expires'  => time() + $seconden,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // PHP ruimt oude sessiebestanden maar af en toe op; daarom hier zelf
    // controleren of de laatste activiteit binnen de ingestelde tijd lag.
    $nu = time();
    if (isset($_SESSION['_laatste_activiteit']) && $nu - (int) $_SESSION['_laatste_activiteit'] > $seconden) {
        $_SESSION = [];
    }
    $_SESSION['_laatste_activiteit'] = $nu;
}
