<?php
// lib/restia_client.php * Verze: V1 * Aktualizace: 22.2.2026
declare(strict_types=1);

/*
 * RESTIA – HTTP KLIENT (jen čtení tokenu z DB, žádný refresh)
 *
 * Účel:
 * - načte access_token z tabulky restia_token (id=1)
 * - provede HTTP GET přes cURL s hlavičkou Authorization: Bearer ...
 * - změří čas, velikosti, načte HTTP status + response headers + body
 * - přidá řádek do $_SESSION['api_restia_buffer'] (pro pozdější flush do api_restia)
 *
 * Neobsahuje:
 * - žádné obnovování tokenu (to dělá lib/restia_init.php)
 * - žádné přímé výpisy
 */

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('cb_restia_dt6_utc_now')) {
    function cb_restia_dt6_utc_now(): string
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return $dt->format('Y-m-d H:i:s.u');
    }
}

if (!function_exists('cb_restia_db_get_access_token')) {
    function cb_restia_db_get_access_token(mysqli $conn): array
    {
        $sql = 'SELECT access_token, expires_at
                FROM restia_token
                WHERE id_restia_token = 1
                LIMIT 1';

        $res = $conn->query($sql);
        if ($res === false) {
            return ['ok' => 0, 'chyba' => 'DB: nelze načíst restia_token.'];
        }

        $row = $res->fetch_assoc();
        if (!is_array($row)) {
            return ['ok' => 0, 'chyba' => 'DB: restia_token neobsahuje řádek id=1.'];
        }

        $access = (string)($row['access_token'] ?? '');
        $expires = (string)($row['expires_at'] ?? '');

        if ($access === '' || $expires === '') {
            return ['ok' => 0, 'chyba' => 'DB: restia_token je prázdný (token/expirace).'];
        }

        try {
            $exp = new DateTimeImmutable($expires, new DateTimeZone('UTC'));
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

            // Rezerva 60 s proti hraniční expiraci.
            if ($exp <= $now->modify('+60 seconds')) {
                return ['ok' => 0, 'chyba' => 'Token je expirovaný nebo na hraně expirace.'];
            }
        } catch (Throwable $e) {
            return ['ok' => 0, 'chyba' => 'DB: neplatný čas expirace tokenu.'];
        }

        return [
            'ok' => 1,
            'access_token' => $access,
            'expires_at' => $expires
        ];
    }
}

if (!function_exists('cb_restia_headers_to_assoc')) {
    function cb_restia_headers_to_assoc(array $headers): array
    {
        $out = [];
        foreach ($headers as $h) {
            if (!is_string($h)) {
                continue;
            }
            $p = strpos($h, ':');
            if ($p === false) {
                continue;
            }
            $k = strtolower(trim(substr($h, 0, $p)));
            $v = trim(substr($h, $p + 1));
            if ($k !== '') {
                $out[$k] = $v;
            }
        }
        return $out;
    }
}

if (!function_exists('cb_restia_buffer_add')) {
    function cb_restia_buffer_add(array $row): void
    {
        if (!isset($_SESSION['api_restia_buffer']) || !is_array($_SESSION['api_restia_buffer'])) {
            $_SESSION['api_restia_buffer'] = [];
        }
        $_SESSION['api_restia_buffer'][] = $row;
    }
}

if (!function_exists('cb_restia_get')) {

    /**
     * GET request na Restii.
     *
     * @param string $endpoint Např. '/api/orders'
     * @param array $query Query parametry (bez ?)
     * @param ?string $activePosId Restia identifikátor pobočky (pro log)
     * @param ?string $poznamka Krátká poznámka do logu
     * @return array ok, http_status, headers, body, total_count, ms, chyba
     */
    function cb_restia_get(string $endpoint, array $query, ?string $activePosId, ?string $poznamka = null): array
    {
        $kdyStart = cb_restia_dt6_utc_now();
        $t0 = microtime(true);

        $conn = db();
        $tok = cb_restia_db_get_access_token($conn);

        if ((int)($tok['ok'] ?? 0) !== 1) {
            cb_restia_buffer_add([
                'kdy_start' => $kdyStart,
                'ms' => (int)round((microtime(true) - $t0) * 1000),
                'metoda' => 'GET',
                'endpoint' => $endpoint,
                'url' => null,
                'active_pos_id' => $activePosId,
                'http_status' => null,
                'bytes_out' => 0,
                'bytes_in' => 0,
                'pocet_zaznamu' => null,
                'total_count' => null,
                'chyba' => (string)($tok['chyba'] ?? 'Neznámá chyba tokenu.'),
                'poznamka' => $poznamka,
                'ok' => 0
            ]);

            return [
                'ok' => 0,
                'chyba' => (string)($tok['chyba'] ?? 'Neznámá chyba tokenu.')
            ];
        }

        $base = 'https://apilite.restia.cz';
        $qs = http_build_query($query);
        $url = $base . $endpoint . (($qs !== '') ? ('?' . $qs) : '');

        $headersIn = [];
        $headersFn = static function ($ch, string $line) use (&$headersIn): int {
            $len = strlen($line);
            $line = trim($line);
            if ($line !== '' && stripos($line, 'HTTP/') !== 0) {
                $headersIn[] = $line;
            }
            return $len;
        };

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_HTTPGET         => true,
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_HEADERFUNCTION  => $headersFn,
            CURLOPT_HTTPHEADER      => [
                'Accept: application/json',
                'Authorization: Bearer ' . (string)$tok['access_token'],
            ],
            CURLOPT_TIMEOUT         => 30,
            CURLOPT_SSL_VERIFYPEER  => true,
            CURLOPT_SSL_VERIFYHOST  => 2,
        ]);

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($body === false) {
            $err = curl_error($ch);
            curl_close($ch);

            $ms = (int)round((microtime(true) - $t0) * 1000);

            cb_restia_buffer_add([
                'kdy_start' => $kdyStart,
                'ms' => $ms,
                'metoda' => 'GET',
                'endpoint' => $endpoint,
                'url' => $url,
                'active_pos_id' => $activePosId,
                'http_status' => $http,
                'bytes_out' => 0,
                'bytes_in' => 0,
                'pocet_zaznamu' => null,
                'total_count' => null,
                'chyba' => 'cURL chyba: ' . $err,
                'poznamka' => $poznamka,
                'ok' => 0
            ]);

            return [
                'ok' => 0,
                'http_status' => $http,
                'chyba' => 'cURL chyba: ' . $err
            ];
        }

        curl_close($ch);

        $ms = (int)round((microtime(true) - $t0) * 1000);
        $bytesIn = strlen($body);

        $hAssoc = cb_restia_headers_to_assoc($headersIn);
        $totalCount = null;
        if (isset($hAssoc['x-total-count'])) {
            $v = trim((string)$hAssoc['x-total-count']);
            if ($v !== '' && ctype_digit($v)) {
                $totalCount = (int)$v;
            }
        }

        $pocetZaznamu = null;
        $json = json_decode($body, true);
        if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
            $pocetZaznamu = count($json['data']);
        } elseif (is_array($json)) {
            // někdy může být pole objednávek přímo na root
            if (array_is_list($json)) {
                $pocetZaznamu = count($json);
            }
        }

        $chyba = null;
        $ok = 0;
        if ($http >= 200 && $http <= 299) {
            $ok = 1;
        } else {
            $chyba = 'HTTP ' . $http;
        }

        cb_restia_buffer_add([
            'kdy_start' => $kdyStart,
            'ms' => $ms,
            'metoda' => 'GET',
            'endpoint' => $endpoint,
            'url' => $url,
            'active_pos_id' => $activePosId,
            'http_status' => $http,
            'bytes_out' => 0,
            'bytes_in' => $bytesIn,
            'pocet_zaznamu' => $pocetZaznamu,
            'total_count' => $totalCount,
            'chyba' => $chyba,
            'poznamka' => $poznamka,
            'ok' => $ok
        ]);

        return [
            'ok' => $ok,
            'http_status' => $http,
            'headers' => $hAssoc,
            'body' => $body,
            'total_count' => $totalCount,
            'ms' => $ms,
            'bytes_in' => $bytesIn
        ];
    }
}

// lib/restia_client.php * Verze: V1 * Aktualizace: 22.2.2026
// Počet řádků: 275
// Konec souboru