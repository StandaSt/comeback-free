<?php
// lib/smeny_graphql.php * Verze: V4 * Aktualizace: 13.05.2026
declare(strict_types=1);

/*
 * Pomocnik pro volani Smeny (GraphQL)
 *
 * V4:
 * - samotne volani cb_smeny_graphql nic nezapisuje do DB
 * - jen sbira metriky do session bufferu (lib/api_smeny_log.php)
 */

require_once __DIR__ . '/api_smeny_log.php';

/**
 * @return array<string, mixed>
 */
function cb_smeny_graphql(string $url, string $query, array $vars = [], ?string $token = null, ?int $timeoutSec = null): array
{
    $startTs = microtime(true);

    $payloadJson = (string)json_encode(
        ['query' => $query, 'variables' => $vars],
        JSON_UNESCAPED_UNICODE
    );

    $ch = curl_init($url);

    $headers = ['Content-Type: application/json'];
    if ($token !== null && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    $timeout = (int)($timeoutSec ?? 20);
    if ($timeout <= 0) {
        $timeout = 20;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payloadJson,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => $timeout,
    ]);

    $out = '';
    $ok = true;
    $chyba = null;
    $httpCode = 0;
    $curlError = '';

    try {
        $out = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($out === false) {
            $ok = false;
            $curlError = curl_error($ch);
            $chyba = 'cURL chyba';
            throw new RuntimeException('cURL chyba: ' . $curlError);
        }

        $json = json_decode($out, true);
        if (!is_array($json)) {
            $ok = false;
            $chyba = 'Neplatna odpoved z API';
            throw new RuntimeException('Neplatna odpoved z API.');
        }

        if (!empty($json['errors'])) {
            $ok = false;

            $m = $json['errors'][0]['message'] ?? 'Neznama chyba.';
            if (is_array($m)) {
                $m = json_encode($m, JSON_UNESCAPED_UNICODE);
            }
            $chyba = (string)$m;
            throw new RuntimeException((string)$m);
        }

        $data = $json['data'] ?? [];
        return is_array($data) ? $data : [];
    } finally {
        $row = smeny_api_make_row($startTs, $payloadJson, (string)$out, $ok, $chyba);
        smeny_api_buffer_add($row);

        curl_close($ch);
    }
}
