<?php
// lib/api_smeny_log.php * Verze: V2 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * LOGOVÁNÍ VOLÁNÍ NA SMĚNY (API) – SBĚR DAT DO SESSION (bez DB)
 *
 * Co dělá:
 * - vytvoří záznam pro jeden API request (kdy_start, ms, bytes_out, bytes_in, ok, chyba)
 * - uloží ho do $_SESSION['api_smeny_buffer'] (pole záznamů)
 *
 * Co to NEDĚLÁ:
 * - nesahá do DB
 * - neřeší id_user / id_login (doplní se až při flush po úspěšném loginu)
 */

if (!function_exists('smeny_api_buffer_add')) {

    /**
     * Přidá 1 záznam do session bufferu.
     *
     * @param array<string,mixed> $row
     */
    function smeny_api_buffer_add(array $row): void
    {
        if (!isset($_SESSION['api_smeny_buffer']) || !is_array($_SESSION['api_smeny_buffer'])) {
            $_SESSION['api_smeny_buffer'] = [];
        }
        $_SESSION['api_smeny_buffer'][] = $row;
    }
}

if (!function_exists('smeny_api_make_row')) {

    /**
     * Vytvoří řádek pro api_smeny (bez id_user/id_login).
     *
     * @return array<string,mixed>
     */
    function smeny_api_make_row(float $startTs, string $payloadJson, string $responseBody, bool $ok, ?string $chyba): array
    {
        $endTs = microtime(true);
        $ms = (int)round(($endTs - $startTs) * 1000);

        $kdyStart = smeny_api_dt_from_ts($startTs);

        $chyba = is_string($chyba) ? trim($chyba) : null;
        if ($chyba === '') {
            $chyba = null;
        }

        return [
            'kdy_start' => $kdyStart,
            'ms' => $ms,
            'bytes_out' => strlen($payloadJson),
            'bytes_in' => strlen($responseBody),
            'ok' => $ok ? 1 : 0,
            'chyba' => $chyba,
        ];
    }
}

if (!function_exists('smeny_api_dt_from_ts')) {

    /**
     * Převede timestamp (microtime) na DATETIME(6) string: Y-m-d H:i:s.u
     */
    function smeny_api_dt_from_ts(float $ts): string
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

// lib/api_smeny_log.php * Verze: V2 * Aktualizace: 21.2.2026
// Počet řádků: 93
// Konec souboru