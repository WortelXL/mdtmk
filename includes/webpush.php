<?php
/**
 * MDT — Web Push zonder externe library (fase M5, herbouwd zonder de
 * eerder gebruikte composer-dependency minishlink/web-push-php).
 *
 * Twee dingen zijn hier zelf geïmplementeerd volgens de officiële RFC's,
 * met alleen PHP's ingebouwde openssl-extensie (geen composer):
 *
 * 1. VAPID (RFC 8292) -- een JWT, ondertekend met de servers eigen P-256
 *    sleutelpaar (ES256/ECDSA), waarmee een pushdienst (Chrome/Firefox/
 *    Apple's dienst) de afzender herkent zonder een account daar aan te
 *    hoeven maken.
 * 2. Payload-encryptie (RFC 8291, "aes128gcm" content-coding uit
 *    RFC 8188) -- het bericht zelf wordt end-to-end versleuteld met een
 *    sleutel die alleen de browser van de gebruiker kan afleiden (uit
 *    diens eigen abonnement, nooit door de pushdienst zelf leesbaar).
 *
 * De ECDH-sleuteluitwisseling (stap 2) leunt op PHP's eigen
 * openssl_pkey_derive() (sinds PHP 7.3, met EC-sleutels) -- geen losse
 * elliptische-krommewiskunde nodig. Getest tegen een eigen Node.js-
 * testharnas dat de RFC 8291-stappen aan de ontvangende (browser-)kant
 * naspeelt en het resultaat terug ontsleutelt tot de originele tekst.
 */

// ---- Base64url ------------------------------------------------------------

function b64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function b64url_decode(string $data): string
{
    $data = strtr($data, '-_', '+/');
    $pad = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode($data);
}

// ---- VAPID-sleutelpaar ------------------------------------------------------

/**
 * Genereert een nieuw VAPID-sleutelpaar (P-256). Levert 2 stukken op om
 * op te slaan: de private key als PEM-tekst (config/env, geheim) en de
 * public key als base64url in het "ongecomprimeerde punt"-formaat
 * (0x04 || X || Y, 65 bytes) -- dat laatste formaat is ook precies wat de
 * browser als `applicationServerKey` nodig heeft bij het aanmaken van een
 * pushabonnement.
 *
 * @return array{private_pem:string, public_b64url:string}
 */
function vapid_genereer_sleutelpaar(): array
{
    $res = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($res === false) {
        throw new RuntimeException('Kon geen EC-sleutelpaar genereren: ' . openssl_error_string());
    }
    openssl_pkey_export($res, $private_pem);
    $details = openssl_pkey_get_details($res);
    $public_raw = "\x04" . $details['ec']['x'] . $details['ec']['y'];

    return [
        'private_pem'   => $private_pem,
        'public_b64url' => b64url_encode($public_raw),
    ];
}

/**
 * Zet een DER-gecodeerde ECDSA-signature (wat openssl_sign() teruggeeft)
 * om naar de "raw" r||s-vorm die een JWS/ES256-signature nodig heeft
 * (elk exact 32 bytes, aan elkaar geplakt -- 64 bytes totaal). Een DER-
 * INTEGER kan een extra leidende 0x00-byte hebben (als het hoogste bit
 * anders als negatief gelezen zou worden) of juist korter zijn dan 32
 * bytes -- dit normaliseert altijd naar precies 32 bytes per helft.
 */
function ecdsa_der_naar_raw(string $der): string
{
    $offset = 0;
    if (ord($der[$offset]) !== 0x30) {
        throw new RuntimeException('Onverwacht DER-formaat (geen SEQUENCE).');
    }
    $offset++;
    $len = ord($der[$offset]);
    $offset++;
    if ($len & 0x80) { // lange-vorm lengte, sla de extra lengte-bytes over
        $offset += $len & 0x7f;
    }

    $lees_integer = function (string $der, int &$offset): string {
        if (ord($der[$offset]) !== 0x02) {
            throw new RuntimeException('Onverwacht DER-formaat (geen INTEGER).');
        }
        $offset++;
        $intLen = ord($der[$offset]);
        $offset++;
        $bytes = substr($der, $offset, $intLen);
        $offset += $intLen;
        // Leidende 0x00-paddingbytes weghalen, dan links opvullen naar 32 bytes.
        $bytes = ltrim($bytes, "\x00");
        return str_pad($bytes, 32, "\x00", STR_PAD_LEFT);
    };

    $r = $lees_integer($der, $offset);
    $s = $lees_integer($der, $offset);

    return $r . $s;
}

/**
 * Bouwt en ondertekent het VAPID-JWT voor 1 pushbericht naar 1 specifieke
 * pushdienst-origin (bv. https://fcm.googleapis.com, https://updates.push.
 * services.mozilla.com). Geldig 12 uur (RFC 8292 raadt max. 24 uur aan).
 */
function vapid_jwt_maken(string $audience_origin, string $subject_mailto, string $vapid_private_pem): string
{
    $header = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = b64url_encode(json_encode([
        'aud' => $audience_origin,
        'exp' => time() + 12 * 3600,
        'sub' => $subject_mailto,
    ]));

    $privateKey = openssl_pkey_get_private($vapid_private_pem);
    if ($privateKey === false) {
        throw new RuntimeException('Ongeldige VAPID private key: ' . openssl_error_string());
    }
    $signInput = $header . '.' . $payload;
    if (!openssl_sign($signInput, $der_signature, $privateKey, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('Kon VAPID-JWT niet ondertekenen: ' . openssl_error_string());
    }
    $signature = b64url_encode(ecdsa_der_naar_raw($der_signature));

    return $signInput . '.' . $signature;
}

// ---- HKDF (RFC 5869), alleen met de stappen die hier nodig zijn ------------

function hkdf_extract(string $salt, string $ikm): string
{
    return hash_hmac('sha256', $ikm, $salt, true);
}

function hkdf_expand(string $prk, string $info, int $lengte): string
{
    // Voor deze toepassing is 1 blok (32 bytes SHA-256-output) altijd
    // genoeg (we vragen hoogstens 16 of 12 bytes op) -- geen lus met
    // meerdere T(n)-blokken nodig zoals de volledige RFC 5869 wel kent.
    $t = hash_hmac('sha256', $info . "\x01", $prk, true);
    return substr($t, 0, $lengte);
}

// ---- Payload-encryptie (RFC 8291 + RFC 8188 "aes128gcm") -------------------

/**
 * Versleutelt $plaintext voor 1 pushabonnement, volgens RFC 8291. Levert
 * de kant-en-klare binaire body op die als HTTP-body naar de pushdienst
 * gaat (Content-Encoding: aes128gcm) -- salt + record-header + het
 * ongecomprimeerde ephemere public-key-punt + de versleutelde inhoud,
 * allemaal in 1 aaneengesloten reeks bytes, precies zoals RFC 8188
 * voorschrijft.
 *
 * @param string $ua_public_b64url  de `p256dh`-waarde uit het abonnement (base64url, ongecomprimeerd EC-punt)
 * @param string $auth_secret_b64url  de `auth`-waarde uit het abonnement (base64url, 16 bytes)
 */
function webpush_payload_versleutelen(string $plaintext, string $ua_public_b64url, string $auth_secret_b64url): string
{
    $ua_public_raw = b64url_decode($ua_public_b64url);
    $auth_secret = b64url_decode($auth_secret_b64url);
    if (strlen($ua_public_raw) !== 65 || ord($ua_public_raw[0]) !== 0x04) {
        throw new RuntimeException('Ongeldige p256dh-sleutel in het pushabonnement.');
    }
    if (strlen($auth_secret) !== 16) {
        throw new RuntimeException('Ongeldige auth-waarde in het pushabonnement (moet 16 bytes zijn).');
    }

    // 1. Eigen, wegwerpbare (ephemere) sleutelpaar voor dit ene bericht --
    //    nooit hergebruikt, zodat elk bericht zijn eigen sleutelmateriaal
    //    heeft (forward secrecy).
    $asRes = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    $asDetails = openssl_pkey_get_details($asRes);
    $as_public_raw = "\x04" . $asDetails['ec']['x'] . $asDetails['ec']['y'];

    // 2. ECDH: het gedeelde geheim tussen onze ephemere private key en de
    //    public key uit het abonnement van de browser.
    $ua_public_pem = ec_public_raw_naar_pem($ua_public_raw);
    $uaPublicKey = openssl_pkey_get_public($ua_public_pem);
    $ecdh_secret = openssl_pkey_derive($uaPublicKey, $asRes);
    if ($ecdh_secret === false) {
        throw new RuntimeException('ECDH-sleuteluitwisseling mislukt: ' . openssl_error_string());
    }

    // 3. RFC 8291 §3.4 -- van het ECDH-geheim naar de "IKM" (input keying
    //    material) voor de volgende stap, met het abonnement se auth-
    //    secret als HKDF-sleutel en een info-string die beide publieke
    //    sleutels bevat (bindt de sleutelafleiding aan dit specifieke
    //    sleutelpaar-paar).
    $key_info = "WebPush: info\x00" . $ua_public_raw . $as_public_raw;
    $prk = hkdf_extract($auth_secret, $ecdh_secret);
    $ikm = hkdf_expand($prk, $key_info, 32);

    // 4. RFC 8188 §2.1 -- van de IKM naar de echte AES-sleutel (CEK) en
    //    het nonce, met een verse, willekeurige salt per bericht.
    $salt = random_bytes(16);
    $prk2 = hkdf_extract($salt, $ikm);
    $cek = hkdf_expand($prk2, "Content-Encoding: aes128gcm\x00", 16);
    $nonce = hkdf_expand($prk2, "Content-Encoding: nonce\x00", 12);

    // 5. Het bericht zelf: 1 padding-scheidingsbyte (0x02 = laatste/enige
    //    record, RFC 8188 §2) achter de eigenlijke inhoud, dan AES-128-GCM.
    $record = $plaintext . "\x02";
    $ciphertext = openssl_encrypt($record, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $gcm_tag);
    if ($ciphertext === false) {
        throw new RuntimeException('AES-128-GCM-versleuteling mislukt: ' . openssl_error_string());
    }

    // 6. RFC 8188 §2 record-header: salt (16) + record-size (4, big-endian)
    //    + keyid-lengte (1) + keyid (= onze ephemere public key, 65 bytes).
    $record_size = pack('N', 4096);
    $keyid_len = chr(strlen($as_public_raw));

    return $salt . $record_size . $keyid_len . $as_public_raw . $ciphertext . $gcm_tag;
}

/** Zet een ruw (0x04||X||Y) EC-publieke-sleutelpunt om naar een PEM die openssl_pkey_get_public() accepteert. */
function ec_public_raw_naar_pem(string $raw_point): string
{
    if (strlen($raw_point) !== 65 || ord($raw_point[0]) !== 0x04) {
        throw new RuntimeException('Verwacht een ongecomprimeerd P-256-punt van 65 bytes.');
    }
    // SubjectPublicKeyInfo voor een P-256 (prime256v1/secp256r1) publieke
    // sleutel: een vaste ASN.1-prefix (algoritme-identifier voor
    // id-ecPublicKey + prime256v1) gevolgd door het ruwe punt als BIT STRING.
    $prefix = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200');
    $der = $prefix . $raw_point;
    $pem = "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
    return $pem;
}

/**
 * Verstuurt 1 pushbericht naar de pushdienst-endpoint uit het abonnement.
 * Geeft {ok, http_status, fout?} terug -- gooit bewust geen exception naar
 * de aanroeper (1 mislukt abonnement mag een reeks andere niet blokkeren).
 */
function webpush_versturen(
    string $endpoint,
    string $ua_public_b64url,
    string $auth_secret_b64url,
    array $payload,
    string $vapid_private_pem,
    string $vapid_public_b64url,
    string $vapid_subject_mailto
): array {
    try {
        $body = webpush_payload_versleutelen(json_encode($payload), $ua_public_b64url, $auth_secret_b64url);

        $origin_parts = parse_url($endpoint);
        $audience = $origin_parts['scheme'] . '://' . $origin_parts['host'] . (isset($origin_parts['port']) ? ':' . $origin_parts['port'] : '');
        $jwt = vapid_jwt_maken($audience, $vapid_subject_mailto, $vapid_private_pem);

        $headers = [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: ' . (4 * 7 * 24 * 3600), // 4 weken
            'Authorization: vapid t=' . $jwt . ', k=' . $vapid_public_b64url,
        ];

        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers),
                'content'       => $body,
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);

        $resultaat = @file_get_contents($endpoint, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $regel) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $regel, $m)) {
                $status = (int) $m[1];
            }
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true, 'http_status' => $status];
        }
        return ['ok' => false, 'http_status' => $status, 'fout' => 'Pushdienst antwoordde met status ' . $status . ($resultaat ? ': ' . substr($resultaat, 0, 200) : '')];
    } catch (Throwable $e) {
        return ['ok' => false, 'http_status' => 0, 'fout' => $e->getMessage()];
    }
}
