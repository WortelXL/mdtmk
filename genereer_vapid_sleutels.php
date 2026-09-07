<?php
/**
 * Eenmalig te draaien op de commandoregel om een nieuw VAPID-sleutelpaar
 * te genereren voor pushmeldingen (fase M5). Print 2 waarden die je als
 * VAPID_PUBLIC_KEY en VAPID_PRIVATE_KEY in je omgevingsvariabelen
 * (docker-compose.yml / .env) zet -- zie README.md.
 *
 * Gebruik:
 *   php genereer_vapid_sleutels.php
 *   -- of, binnen de draaiende Docker-container --
 *   docker compose exec mdt php genereer_vapid_sleutels.php
 *
 * Bewaar de private key geheim (net als een wachtwoord) -- wie deze
 * heeft kan zich voordoen als deze MDT-installatie bij pushdiensten.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Dit script is alleen bedoeld om op de commandoregel te draaien.');
}

require __DIR__ . '/includes/webpush.php';

$paar = vapid_genereer_sleutelpaar();

// De private PEM heeft echte regeleindes -- voor een .env/omgevings-
// variabele (1 regel) zetten we die om naar letterlijke '\n', precies
// zoals config.php ze weer terugverwacht.
$private_voor_env = str_replace(["\r\n", "\n"], '\\n', trim($paar['private_pem']));

echo "Nieuw VAPID-sleutelpaar gegenereerd.\n";
echo "Zet deze 2 regels in je omgevingsvariabelen (docker-compose.yml / .env):\n\n";
echo "VAPID_PUBLIC_KEY={$paar['public_b64url']}\n";
echo "VAPID_PRIVATE_KEY={$private_voor_env}\n\n";
echo "Herstart MDT daarna zodat de nieuwe waarden geladen worden.\n";
