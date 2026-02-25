<?php
// lib/smeny_graphql.php * Verze: V3 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * Pomocník pro volání Směny (GraphQL)
 *
 * V3:
 * - nic nezapisuje do DB
 * - jen sbírá metriky do session bufferu (lib/api_smeny_log.php)
 */

require_once __DIR__ . '/api_smeny_log.php';

/**
 * @return array<string, mixed>  data část odpovědi GraphQL
 */
function cb_smeny_graphql(string $url, string $query, array $vars = [], ?string $token = null): array
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

    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HTTPHEADER      => $headers,
        CURLOPT_POSTFIELDS      => $payloadJson,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_TIMEOUT         => 20,
    ]);

    $out = '';
    $ok = true;
    $chyba = null;

    try {
        $out = curl_exec($ch);
        if ($out === false) {
            $ok = false;
            $err = curl_error($ch);
            $chyba = 'cURL chyba';
            throw new RuntimeException('cURL chyba: ' . $err);
        }

        $json = json_decode($out, true);
        if (!is_array($json)) {
            $ok = false;
            $chyba = 'Neplatná odpověď z API';
            throw new RuntimeException('Neplatná odpověď z API.');
        }

        if (!empty($json['errors'])) {
            $ok = false;

            // pro DB chceme krátký text, ne JSON román
            $chyba = 'neplatné přihlášení';

            // ale výjimku necháme původní, ať to UI/diagnostika vidí
            $m = $json['errors'][0]['message'] ?? 'Neznámá chyba.';
            if (is_array($m)) {
                $m = json_encode($m, JSON_UNESCAPED_UNICODE);
            }
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

// lib/smeny_graphql.php * Verze: V3 * Aktualizace: 21.2.2026
// Počet řádků: 98
// Konec souboru