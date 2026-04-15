<?php
// priprav_kartu_mini.php * Verze: V4 * Aktualizace: 15.04.2026
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
    $pos = ($cardId > 0 && isset($userCardPosById[$cardId])) ? $userCardPosById[$cardId] : ['col'=>null, 'line'=>null];
    
    // Pozice pro grid (col/row, pokud uložené)
    $col = isset($pos['col']) ? (int)$pos['col'] : null;
    $line = isset($pos['line']) ? (int)$pos['line'] : null;
    
    // Pokud má soubor, stáhni min HTML jako string (pouze miniverze)
    $minHtml = '';
    $soubor = (string)($karta['soubor'] ?? '');
    if ($soubor !== '') {
        $fullPath = cb_dashboard_resolve_file($soubor);
        if ($fullPath !== null) {
            ob_start();
            require $fullPath; // očekává jednoduchý min obsah
            $minHtml = (string)ob_get_clean();
        }
    }
    return [
        'cardId' => $cardId,
        'cardPoradi' => (int)($karta['poradi'] ?? 0),
        'title' => (string)($karta['nazev'] ?? ''),
        'subtitleMin' => $subtitleMin,
        'subtitleMax' => $subtitleMax,
        'color' => ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) ? (string)$userCardHeaderColorById[$cardId] : '',
        'iconFile' => ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '',
        'role' => (int)($karta['min_role'] ?? 3),
        'col' => $col,
        'line' => $line,
        'minHtml' => $minHtml,
        // MINI nemá max ani legacy html
    ];
}
