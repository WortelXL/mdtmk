<?php
/**
 * Eenmalig te draaien hulpscript: genereert een nieuw VAPID-sleutelpaar
 * voor Web Push (fase M5). Draai dit NA `composer install`
 * (bijvoorbeeld: `docker compose exec app php genereer_vapid_sleutels.php`,
 * of gewoon lokaal met `php genereer_vapid_sleutels.php` als je
 * `composer install` ook lokaal gedraaid hebt).
 *
 * Het resultaat (2 sleutels) zet je zelf in docker-compose.yml bij
 * VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY. Dit sleutelpaar is uniek voor
 * jouw installatie -- NOOIT delen, NOOIT hergebruiken tussen
 * omgevingen, en NOOIT in git committen (docker-compose.yml bevat
 * bewust alleen placeholders).
 */

require_once __DIR__ . '/vendor/autoload.php';

$sleutels = \Minishlink\WebPush\VAPID::createVapidKeys();

echo "Nieuw VAPID-sleutelpaar:\n\n";
echo "VAPID_PUBLIC_KEY:  " . $sleutels['publicKey'] . "\n";
echo "VAPID_PRIVATE_KEY: " . $sleutels['privateKey'] . "\n\n";
echo "Zet deze twee waarden in docker-compose.yml en herstart MDT\n";
echo "(docker compose up -d --build).\n";
