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
    $cardId = (int)($karta['id_karta'] ?? 0);
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
    $startExpanded = false;

    $soubor = (string)($karta['soubor'] ?? '');
    $fullPath = cb_dashboard_resolve_file($soubor);
    if ($fullPath !== null) {
        $cbDashboardRenderMode = 'mini';
        ob_start();
        require $fullPath;
        $legacy_html = (string)ob_get_clean();
        unset($cbDashboardRenderMode);
    }

    if ($card_min_html === '' && $card_max_html === '' && $legacy_html !== '') {
        $card_min_html = $legacy_html;
    }

    return [
        'mode' => 'mini',
        'cardId' => $cardId,
        'cardPoradi' => (int)($karta['poradi'] ?? 0),
        'title' => (string)($karta['nazev'] ?? ''),
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
        'startExpanded' => $startExpanded ? 1 : 0,
        'cardColorUrl' => cb_url('/includes/select_card_color.php?id_karta=' . (string)$cardId),
        'cardIconUrl' => cb_url('/includes/select_card_ikon.php?id_karta=' . (string)$cardId),
    ];
}
