<?php
// priprav_kartu_max.php * Verze: V3 * Aktualizace: 15.04.2026
declare(strict_types=1);

if (!function_exists('cb_priprav_kartu_max')) {
    function cb_priprav_kartu_max(array $karta): string
    {
        // DOCASNE MERENI CASU KARET
        $cbTmpMeasureStart = microtime(true);
        $card_max_html = '';
        $card_min_html = '';
        $legacy_html = '';
        $requireError = '';

        $cardId = (int)($karta['id_karta'] ?? 0);
        $title = trim((string)($karta['nazev'] ?? ''));
        $soubor = (string)($karta['soubor'] ?? '');
        $fullPath = cb_dashboard_resolve_file($soubor);
        if ($fullPath === null) {
            $result = cb_dashboard_render_card_error(
                'Chyba karty',
                'File not found: ' . $soubor,
                [
                    'OÄŤekĂˇvanĂˇ cesta' => cb_dashboard_card_source_path($soubor),
                    'OÄŤekĂˇvanĂˇ data' => 'card_max_html nebo legacy HTML output',
                ]
            );

            // DOCASNE MERENI CASU KARET
            if (function_exists('cb_tmp_measure_card_time_log')) {
                cb_tmp_measure_card_time_log($cardId, $title, 'max', 'priprava', $cbTmpMeasureStart);
            }

            return $result;
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
            $result = (string)$card_max_html;
            // DOCASNE MERENI CASU KARET
            if (function_exists('cb_tmp_measure_card_time_log')) {
                cb_tmp_measure_card_time_log($cardId, $title, 'max', 'priprava', $cbTmpMeasureStart);
            }
            return $result;
        }

        if (trim($legacy_html) !== '') {
            $result = (string)$legacy_html;
            // DOCASNE MERENI CASU KARET
            if (function_exists('cb_tmp_measure_card_time_log')) {
                cb_tmp_measure_card_time_log($cardId, $title, 'max', 'priprava', $cbTmpMeasureStart);
            }
            return $result;
        }

        $result = cb_dashboard_render_card_error(
            'Chyba karty',
            'Max obsah se nepodaĹ™ilo naÄŤĂ­st.',
            [
                'Soubor' => $soubor,
                'Cesta' => $fullPath,
                'OÄŤekĂˇvanĂˇ data' => 'card_max_html nebo legacy HTML output',
                'ChybÄ›jĂ­cĂ­ data' => 'ĹľĂˇdnĂ˝ HTML vĂ˝stup z include',
                'Include chyba' => isset($requireError) ? $requireError : '',
            ]
        );

        // DOCASNE MERENI CASU KARET
        if (function_exists('cb_tmp_measure_card_time_log')) {
            cb_tmp_measure_card_time_log($cardId, $title, 'max', 'priprava', $cbTmpMeasureStart);
        }

        return $result;
    }
}

if (!function_exists('cb_zobraz_karty_max')) {
    function cb_zobraz_karty_max(array $karta): string
    {
        return cb_priprav_kartu_max($karta);
    }
}
