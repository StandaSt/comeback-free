<?php
// priprav_kartu_max.php * Verze: V3 * Aktualizace: 15.04.2026
declare(strict_types=1);

if (!function_exists('cb_priprav_kartu_max')) {
    function cb_priprav_kartu_max(array $karta): string
    {
        $card_max_html = '';
        $card_min_html = '';
        $legacy_html = '';

        $soubor = (string)($karta['soubor'] ?? '');
        $fullPath = cb_dashboard_resolve_file($soubor);
        if ($fullPath === null) {
            return '';
        }

        ob_start();
        require $fullPath;
        $legacy_html = (string)ob_get_clean();

        if ($card_max_html !== '') {
            return (string)$card_max_html;
        }

        if ($legacy_html !== '') {
            return (string)$legacy_html;
        }

        return '';
    }
}

if (!function_exists('cb_zobraz_karty_max')) {
    function cb_zobraz_karty_max(array $karta): string
    {
        return cb_priprav_kartu_max($karta);
    }
}
