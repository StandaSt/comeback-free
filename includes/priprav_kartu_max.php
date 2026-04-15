<?php
// priprav_kartu_max.php * Verze: V2 * Aktualizace: 15.04.2026
declare(strict_types=1);

if (!function_exists('cb_priprav_kartu_max')) {
    function cb_priprav_kartu_max(string $cardMaxHtml): string
    {
        return $cardMaxHtml;
    }
}

if (!function_exists('cb_zobraz_karty_max')) {
    function cb_zobraz_karty_max(string $cardMaxHtml): string
    {
        return cb_priprav_kartu_max($cardMaxHtml);
    }
}
