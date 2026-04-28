<?php
// priprav_kartu_mini.php * Verze: V6 * Aktualizace: 15.04.2026
declare(strict_types=1);

function cb_priprav_kartu_mini(
    array $karta,
    array $userCardHeaderColorById,
    array $userCardIconFileById,
    array $userCardPosById,
    int $dashGridCols
): array {
    // DOCASNE MERENI CASU KARET
    $cbTmpMeasureStart = microtime(true);
    $cardId = (int)($karta['id_karta'] ?? 0);
    $title = (string)($karta['nazev'] ?? '');
    $subtitleMin = (string)($karta['subtitle_min'] ?? '');
    $subtitleMax = (string)($karta['subtitle_max'] ?? '');
    $cols = ($dashGridCols > 0) ? $dashGridCols : 3;

    $renderPos = (int)($karta['__render_pos'] ?? 0);
    $renderCol = 0;
    $renderLine = 0;
    if ($renderPos > 0) {
        $renderCol = (($renderPos - 1) % $cols) + 1;
        $renderLine = (int)floor(($renderPos - 1) / $cols) + 1;
    }

    $storedPos = ($cardId > 0 && isset($userCardPosById[$cardId]))
        ? (array)$userCardPosById[$cardId]
        : ['col' => null, 'line' => null];

    $isPosLocked = (($storedPos['col'] ?? null) !== null && ($storedPos['line'] ?? null) !== null);
    if ($isPosLocked) {
        $storedCol = (int)($storedPos['col'] ?? 0);
        $storedLine = (int)($storedPos['line'] ?? 0);
        if ($storedCol > 0 && $storedLine > 0) {
            $renderCol = $storedCol;
            $renderLine = $storedLine;
        }
    }

    $card_min_html = '';
    $card_max_html = '';
    $legacy_html = '';
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
            $legacy_html = '';
            $requireError = $e->getMessage();
        }
        if ($requireOk) {
            $legacy_html = (string)ob_get_clean();
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
                'Očekávaná data' => 'card_min_html nebo legacy HTML output',
            ]
        );
        $card_max_html = $card_min_html;
        $renderErrorHtml = $card_min_html;
    }

    if ($card_min_html === '' && $card_max_html === '' && trim($legacy_html) !== '') {
        $card_min_html = $legacy_html;
    }

    if ($card_min_html === '' && $card_max_html === '' && trim($legacy_html) === '') {
        $card_min_html = cb_dashboard_render_card_error(
            'Chyba karty',
            'Mini obsah se nepodařilo načíst.',
            [
                'Soubor' => $soubor,
                'Cesta' => ($fullPath !== null) ? $fullPath : cb_dashboard_card_source_path($soubor),
                'Očekávaná data' => 'card_min_html nebo legacy HTML output',
                'Chybějící data' => 'žádný HTML výstup z include',
                'Include chyba' => isset($requireError) ? $requireError : '',
            ]
        );
        $card_max_html = $card_min_html;
        $renderErrorHtml = $card_min_html;
    }

    $result = [
        'mode' => 'mini',
        'cardId' => $cardId,
        'cardPoradi' => (int)($karta['poradi'] ?? 0),
        'title' => $title,
        'soubor' => $soubor,
        'refreshOp' => (int)($karta['refresh_op'] ?? 0),
        'subtitleMin' => $subtitleMin,
        'subtitleMax' => $subtitleMax,
        'color' => ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) ? (string)$userCardHeaderColorById[$cardId] : '',
        'iconFile' => ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '',
        'role' => (int)($karta['min_role'] ?? 3),
        'col' => $renderCol,
        'line' => $renderLine,
        'isPosLocked' => $isPosLocked ? 1 : 0,
        'minHtml' => $card_min_html,
        'maxHtml' => '',
        'renderErrorHtml' => $renderErrorHtml,
        'startExpanded' => $startExpanded ? 1 : 0,
        'maxFill' => ($cardId === 12) ? 1 : 0,
        'cardColorUrl' => cb_url('/includes/select_card_color.php?id_karta=' . (string)$cardId),
        'cardIconUrl' => cb_url('/includes/select_card_ikon.php?id_karta=' . (string)$cardId),
    ];

    // DOCASNE MERENI CASU KARET
    if (function_exists('cb_tmp_measure_card_time_log')) {
        cb_tmp_measure_card_time_log($cardId, $title, 'mini', 'priprava', $cbTmpMeasureStart);
    }

    return $result;
}
