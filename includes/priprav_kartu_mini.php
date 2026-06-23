<?php
// priprav_kartu_mini.php * Verze: V6 * Aktualizace: 15.04.2026
declare(strict_types=1);

function cb_priprav_kartu_mini(
    array $karta,
    array $userCardHeaderColorById,
    array $userCardIconFileById
): array {
    // DOCASNE MERENI CASU KARET
    $cbTmpMeasureStart = microtime(true);
    $cardId = (int)($karta['id_karta'] ?? 0);
    $title = (string)($karta['nazev'] ?? '');
    $subtitleMin = (string)($karta['subtitle_min'] ?? '');
    $subtitleMax = (string)($karta['subtitle_max'] ?? '');

    $card_min_html = '';
    $card_max_html = '';
    $renderErrorHtml = '';
    $startExpanded = false;

    $soubor = (string)($karta['soubor'] ?? '');
    $fullPath = cb_dashboard_resolve_file($soubor);
    if ($fullPath !== null) {
        $cbDashboardRenderMode = 'mini';
        ob_start();
        $requireOk = true;
        try {
            require $fullPath;
        } catch (Throwable $e) {
            $requireOk = false;
            $requireError = $e->getMessage();
        }
        if ($requireOk) {
            ob_end_clean();
        } else {
            ob_end_clean();
        }
        unset($cbDashboardRenderMode);
    } else {
        $card_min_html = cb_dashboard_render_card_error(
            'Chyba karty',
            'File not found: ' . $soubor,
            [
                'Očekávaná cesta' => cb_dashboard_card_source_path($soubor),
                'Očekávaná data' => 'card_min_html',
            ]
        );
        $card_max_html = $card_min_html;
        $renderErrorHtml = $card_min_html;
    }

    if ($card_min_html === '' && $card_max_html === '') {
        $card_min_html = cb_dashboard_render_card_error(
            'Chyba karty',
            'Mini obsah se nepodařilo načíst.',
            [
                'Soubor' => $soubor,
                'Cesta' => ($fullPath !== null) ? $fullPath : cb_dashboard_card_source_path($soubor),
                'Očekávaná data' => 'card_min_html',
                'Chybějící data' => 'card_min_html je prázdné',
                'Include chyba' => isset($requireError) ? $requireError : '',
            ]
        );
        $card_max_html = $card_min_html;
        $renderErrorHtml = $card_min_html;
    }

    $result = [
        'mode' => 'mini',
        'cardId' => $cardId,
        'cardPoradi' => (int)($karta['__dash_order'] ?? ($karta['poradi'] ?? 0)),
        'title' => $title,
        'soubor' => $soubor,
        'refreshOp' => (int)($karta['refresh_op'] ?? 0),
        'subtitleMin' => $subtitleMin,
        'subtitleMax' => $subtitleMax,
        'color' => ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) ? (string)$userCardHeaderColorById[$cardId] : '',
        'iconFile' => ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '',
        'role' => (int)($karta['min_role'] ?? 3),
        'col' => 0,
        'line' => 0,
        'isPosLocked' => 0,
        'minHtml' => $card_min_html,
        'maxHtml' => '',
        'renderErrorHtml' => $renderErrorHtml,
        'startExpanded' => $startExpanded ? 1 : 0,
        // NEMENIT: Vybrane karty maji v max rezimu zabrat cely povoleny prostor dashboardu podle obsahu.
        'maxFill' => in_array($cardId, [1, 12, 15, 19], true) ? 1 : 0,
        'cardColorUrl' => cb_url('/includes/select_card_color.php?id_karta=' . (string)$cardId),
        'cardIconUrl' => cb_url('/includes/select_card_ikon.php?id_karta=' . (string)$cardId),
    ];

    // DOCASNE MERENI CASU KARET
    if (function_exists('cb_tmp_measure_card_detail_log')) {
        cb_tmp_measure_card_detail_log($cardId, $soubor, 'mini', 'priprava', $cbTmpMeasureStart);
    }

    return $result;
}
