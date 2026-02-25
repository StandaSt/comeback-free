<?php
// lib/api_restia_log.php * Verze: V1 * Aktualizace: 22.2.2026
declare(strict_types=1);

/*
 * LOGOVÁNÍ VOLÁNÍ NA RESTII (API) – SBĚR DAT DO SESSION (bez DB)
 *
 * Princip je stejný jako u Směn:
 * - během requestů sbíráme metriky do $_SESSION['api_restia_buffer']
 * - do DB se to flushne až ve chvíli, kdy už známe id_user + id_login
 *
 * Co to dělá:
 * - vytvoří záznam pro jeden REST request (kdy_start, ms, metoda, endpoint, url, status, bytes_out, bytes_in, ok, chyba, ... )
 * - uloží ho do $_SESSION['api_restia_buffer'] (pole záznamů)
 *
 * Co to NEDĚLÁ:
 * - nesahá do DB
 * - neřeší stránkování ani business logiku
 */

if (!function_exists('restia_api_buffer_add')) {

    /**
     * Přidá 1 záznam do session bufferu.
     *
     * @param array<string,mixed> $row
     */
    function restia_api_buffer_add(array $row): void
    {
        if (!isset($_SESSION['api_restia_buffer']) || !is_array($_SESSION['api_restia_buffer'])) {
            $_SESSION['api_restia_buffer'] = [];
        }
        $_SESSION['api_restia_buffer'][] = $row;
    }
}

if (!function_exists('restia_api_make_row')) {

    /**
     * Vytvoří řádek pro api_restia (bez id_user/id_login).
     *
     * Povinné:
     * - metoda, endpoint, kdy_start, ms
     *
     * @return array<string,mixed>
     */
    function restia_api_make_row(
        float $startTs,
        string $metoda,
        string $endpoint,
        ?string $url,
        ?string $activePosId,
        ?int $httpStatus,
        ?int $bytesOut,
        ?int $bytesIn,
        ?int $pocetZaznamu,
        ?int $totalCount,
        bool $ok,
        ?string $chyba,
        ?string $poznamka
    ): array {
        $endTs = microtime(true);
        $ms = (int)round(($endTs - $startTs) * 1000);

        $kdyStart = restia_api_dt_from_ts($startTs);

        $metoda = strtoupper(trim($metoda));
        if ($metoda === '') {
            $metoda = 'GET';
        }

        $endpoint = trim($endpoint);

        $url = is_string($url) ? trim($url) : null;
        if ($url === '') {
            $url = null;
        }

        $activePosId = is_string($activePosId) ? trim($activePosId) : null;
        if ($activePosId === '') {
            $activePosId = null;
        }

        $chyba = is_string($chyba) ? trim($chyba) : null;
        if ($chyba === '') {
            $chyba = null;
        }

        $poznamka = is_string($poznamka) ? trim($poznamka) : null;
        if ($poznamka === '') {
            $poznamka = null;
        }

        return [
            'kdy_start' => $kdyStart,
            'ms' => $ms,

            'metoda' => $metoda,
            'endpoint' => $endpoint,
            'url' => $url,

            'active_pos_id' => $activePosId,
            'http_status' => $httpStatus,

            'bytes_out' => $bytesOut,
            'bytes_in' => $bytesIn,

            'pocet_zaznamu' => $pocetZaznamu,
            'total_count' => $totalCount,

            'ok' => $ok ? 1 : 0,
            'chyba' => $chyba,
            'poznamka' => $poznamka,
        ];
    }
}

if (!function_exists('restia_api_dt_from_ts')) {

    /**
     * Převede timestamp (microtime) na DATETIME(6) string: Y-m-d H:i:s.u
     */
    function restia_api_dt_from_ts(float $ts): string
    {
        $sec = (int)floor($ts);
        $micro = (int)round(($ts - $sec) * 1000000);

        $dt = DateTimeImmutable::createFromFormat('U u', $sec . ' ' . $micro);
        if ($dt === false) {
            return date('Y-m-d H:i:s') . '.000000';
        }

        return $dt->format('Y-m-d H:i:s.u');
    }
}

// lib/api_restia_log.php * Verze: V1 * Aktualizace: 22.2.2026
// Počet řádků: 139
// Konec souboru