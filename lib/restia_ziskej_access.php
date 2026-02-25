<?php
// lib/restia_ziskej_access.php * Verze: V1 * Aktualizace: 24.2.2026 * Počet řádků: 132
declare(strict_types=1);

/*
 * RESTIA – získání nového access tokenu
 *
 * Účel:
 * - vezme refresh_token ze secrets.php
 * - zavolá POST /api/login/token
 * - uloží access_token + expires_at do restia_token
 * - zapíše volání do api_restia (přes session buffer)
 *
 * NIC VÍC.
 */

require_once __DIR__ . '/bootstrap.php';

global $SECRETS;

$conn = db();

$refreshToken = $SECRETS['restia']['refresh_token'] ?? null;

if (!is_string($refreshToken) || $refreshToken === '') {
    return;
}

$url = 'https://apilite.restia.cz/api/login/token';

$payload = json_encode(
    ['refreshToken' => $refreshToken],
    JSON_UNESCAPED_SLASHES
);

$headers = [
    'Accept: application/json',
    'Content-Type: application/json',
];

$t0 = microtime(true);

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_HTTPHEADER      => $headers,
    CURLOPT_POSTFIELDS      => $payload,
    CURLOPT_TIMEOUT         => 20,
    CURLOPT_SSL_VERIFYPEER  => true,
    CURLOPT_SSL_VERIFYHOST  => 2,
]);

$body = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($body === false) {
    curl_close($ch);
    return;
}

curl_close($ch);

$json = json_decode($body, true);

$access = $json['accessToken'] ?? null;
$expIso = $json['accessTokenExpires'] ?? null;

if ($http !== 200 || !is_string($access) || !is_string($expIso)) {
    return;
}

/* převod ISO času na DATETIME(3) UTC */
try {
    $dt = new DateTimeImmutable($expIso);
    $dt = $dt->setTimezone(new DateTimeZone('UTC'));
    $expiresDb = $dt->format('Y-m-d H:i:s.v');
} catch (Throwable $e) {
    return;
}

/* uložit do DB */
$sql = '
    INSERT INTO restia_token (id_restia_token, access_token, expires_at)
    VALUES (1, ?, ?)
    ON DUPLICATE KEY UPDATE
        access_token = VALUES(access_token),
        expires_at = VALUES(expires_at),
        vytvoreno = CURRENT_TIMESTAMP(3)
';

$stmt = $conn->prepare($sql);
if ($stmt !== false) {
    $stmt->bind_param('ss', $access, $expiresDb);
    $stmt->execute();
    $stmt->close();
}

/* zalogovat do api_restia (session buffer) */
$_SESSION['api_restia_buffer'][] = [
    'kdy_start'     => gmdate('Y-m-d H:i:s.u'),
    'ms'            => (int)round((microtime(true) - $t0) * 1000),
    'metoda'        => 'POST',
    'endpoint'      => '/api/login/token',
    'url'           => $url,
    'active_pos_id' => null,
    'http_status'   => $http,
    'bytes_out'     => strlen((string)$payload),
    'bytes_in'      => strlen((string)$body),
    'pocet_zaznamu' => null,
    'total_count'   => null,
    'chyba'         => null,
    'poznamka'      => 'token_refresh',
    'ok'            => 1
];

// lib/restia_ziskej_access.php * Verze: V1 * Aktualizace: 24.2.2026 * Počet řádků: 132
// Konec souboru