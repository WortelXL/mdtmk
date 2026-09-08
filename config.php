<?php
/**
 * CONFIGURATIE — MDT (Mobiel Data Terminal)
 *
 * MDT is een losse app die verbindt met DEZELFDE database als MKAPP
 * (het hoofdsysteem). MDT schrijft alleen in het logboek en leest verder
 * mee — er is bewust geen eigen kopie van meldingen/classificaties/etc.
 *
 * Draai je dit via Docker? Dan hoef je dit bestand meestal niet aan te
 * passen: alle waarden hieronder kunnen ook via omgevingsvariabelen
 * (environment variables) worden aangeleverd, bijvoorbeeld vanuit
 * docker-compose.yml. Een omgevingsvariabele overschrijft altijd de
 * standaardwaarde hieronder.
 *
 * Gebruik voor DB_USER/DB_PASS een BEPERKT databaseaccount (niet het
 * bestaande phpserver-account van MKAPP) — zie README.md voor het
 * aanmaakcommando en welke rechten dit account precies nodig heeft.
 */

function env_of(string $naam, $standaard)
{
    $waarde = getenv($naam);
    return $waarde !== false && $waarde !== '' ? $waarde : $standaard;
}

// ---- Gedeelde database (dezelfde database als MKAPP) --------------------
define('DB_HOST', env_of('DB_HOST', 'localhost'));
define('DB_PORT', env_of('DB_PORT', '3306'));
define('DB_NAME', env_of('DB_NAME', 'mkapp'));
define('DB_USER', env_of('DB_USER', 'mdt_user'));
define('DB_PASS', env_of('DB_PASS', 'wijzig_dit_wachtwoord'));
define('DB_CHARSET', env_of('DB_CHARSET', 'utf8mb4'));

// ---- App -------------------------------------------------------------
// Versienummer, apart bijgehouden van de MKAPP-fasering (M1 t/m M5) —
// zie CHANGELOG.md voor wat er per versie is toegevoegd/gewijzigd.
define('APP_VERSION', env_of('APP_VERSION', 'V0.0.12'));

// ---- Web Push (fase M5, zonder externe library) -------------------------
// Voor pushmeldingen bij een toewijzing (zie includes/webpush.php). Een
// VAPID-sleutelpaar identificeert MDT bij de pushdienst van de browser
// (Chrome/Firefox/etc.) -- genereer 1x met genereer_vapid_sleutels.php en
// zet de 2 waarden hieronder als omgevingsvariabelen. Zonder een
// ingevulde public/private key blijft het pushmeldingen-paneel gewoon
// verborgen (rest van MDT werkt onveranderd door).
define('VAPID_PUBLIC_KEY', env_of('VAPID_PUBLIC_KEY', ''));
// De private key is een PEM (meerdere regels) -- in een .env/omgevings-
// variabele staat die met letterlijke '\n' in plaats van echte
// regeleindes; dat wordt hier teruggezet naar een normale PEM-string.
define('VAPID_PRIVATE_KEY', str_replace('\\n', "\n", env_of('VAPID_PRIVATE_KEY', '')));
// mailto-adres waarop een pushdienst bij misbruik contact kan opnemen —
// verplicht onderdeel van het VAPID-JWT, mag een fictief/algemeen adres
// zijn.
define('VAPID_SUBJECT', env_of('VAPID_SUBJECT', 'mailto:beheer@voorbeeld.nl'));

// Deelbaar geheim waarmee webhook_ontvangen.php de aanroep van MKAPP's
// webhook herkent (via ?token=... in de webhook-URL die je in MKAPP bij
// Beheer > Connectiviteit instelt) -- zonder dit zou elke willekeurige
// bezoeker een "melding toegewezen"-pushmelding kunnen laten versturen.
// Zet dit op eenzelfde, lang en willekeurig token aan beide kanten.
define('WEBHOOK_TOKEN', env_of('WEBHOOK_TOKEN', ''));

// ---- Overig ------------------------------------------------------------
date_default_timezone_set(env_of('APP_TIMEZONE', 'Europe/Amsterdam'));

// Sessie moet gestart zijn voordat er output is (voor login beheer)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
