<?php
// lib/smeny_graphql.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 63
declare(strict_types=1);

/*
 * Pomocník pro volání Směny (GraphQL)
 *
 * Účel:
 * - jedna funkce cb_smeny_graphql(), aby se stejný cURL kód neopakoval v různých souborech
 *
 * Pozn.:
 * - ověřování SSL/TLS je zatím vypnuté (čeká v úkolech projektu)
 * - token se předává jako string a posílá se v hlavičce Authorization: Bearer <token>
 */

/**
 * @return array<string, mixed>  data část odpovědi GraphQL
 */
function cb_smeny_graphql(string $url, string $query, array $vars = [], ?string $token = null): array
{
    $ch = curl_init($url);

    $headers = ['Content-Type: application/json'];
    if ($token !== null && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HTTPHEADER      => $headers,
        CURLOPT_POSTFIELDS      => json_encode(['query' => $query, 'variables' => $vars], JSON_UNESCAPED_UNICODE),
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_SSL_VERIFYHOST  => 0,
        CURLOPT_TIMEOUT         => 20,
    ]);

    $out = curl_exec($ch);
    if ($out === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL chyba: ' . $err);
    }
    curl_close($ch);

    $json = json_decode($out, true);
    if (!is_array($json)) {
        throw new RuntimeException('Neplatná odpověď z API.');
    }

    if (!empty($json['errors'])) {
        $m = $json['errors'][0]['message'] ?? 'Neznámá chyba.';
        if (is_array($m)) {
            $m = json_encode($m, JSON_UNESCAPED_UNICODE);
        }
        throw new RuntimeException((string)$m);
    }

    $data = $json['data'] ?? [];
    return is_array($data) ? $data : [];
}

// lib/smeny_graphql.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 63