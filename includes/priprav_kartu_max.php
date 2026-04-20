<?php
// priprav_kartu_max.php * Verze: V3 * Aktualizace: 15.04.2026
declare(strict_types=1);

if (!function_exists('cb_priprav_kartu_max')) {
    function cb_priprav_kartu_max(array $karta): string
    {
        $card_max_html = '';
        $card_min_html = '';
        $legacy_html = '';
        $requireError = '';

        $soubor = (string)($karta['soubor'] ?? '');
        $fullPath = cb_dashboard_resolve_file($soubor);
        if ($fullPath === null) {
            return cb_dashboard_render_card_error(
                'Chyba karty',
                'File not found: ' . $soubor,
                [
                    'Očekávaná cesta' => cb_dashboard_card_source_path($soubor),
                    'Očekávaná data' => 'card_max_html nebo legacy HTML output',
                ]
            );
        }

        $cbDashboardRenderMode = 'max';
        ob_start();
        $requireOk = true;
        try {
            require $fullPath;
        } catch (Throwable $e) {
            $requireOk = false;
            $legacy_html = '';
            $requireError = $e->getMessage();
        }
        if ($requireOk) {
            $legacy_html = (string)ob_get_clean();
        } else {
            ob_end_clean();
        }
        unset($cbDashboardRenderMode);

        if (trim($card_max_html) !== '') {
            return (string)$card_max_html;
        }

        if (trim($legacy_html) !== '') {
            return (string)$legacy_html;
        }

        return cb_dashboard_render_card_error(
            'Chyba karty',
            'Max obsah se nepodařilo načíst.',
            [
                'Soubor' => $soubor,
                'Cesta' => $fullPath,
                'Očekávaná data' => 'card_max_html nebo legacy HTML output',
                'Chybějící data' => 'žádný HTML výstup z include',
                'Include chyba' => isset($requireError) ? $requireError : '',
            ]
        );
    }
}

if (!function_exists('cb_zobraz_karty_max')) {
    function cb_zobraz_karty_max(array $karta): string
    {
        return cb_priprav_kartu_max($karta);
    }
}
