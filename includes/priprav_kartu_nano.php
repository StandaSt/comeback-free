<?php
// priprav_kartu_nano.php * Verze: V2 * Aktualizace: 15.04.2026
declare(strict_types=1);

function cb_priprav_kartu_nano(
    array $karta,
    array $userCardHeaderColorById,
    array $userCardIconFileById,
    array $userCardPosById,
    int $dashGridCols
): array {
    // DOCASNE MERENI CASU KARET
    $cbTmpMeasureStart = microtime(true);
    $cardId = (int)($karta['id_karta'] ?? 0);
    $title = trim((string)($karta['nazev'] ?? ''));

    if ($cardId <= 0 || $title === '') {
        $errorHtml = cb_dashboard_render_card_error(
            'Chyba karty',
            'Nano karta nemá potřebná data.',
            [
                'Očekávaná data' => 'id_karta a název karty',
                'Chybějící data' => $cardId <= 0 ? 'id_karta' : 'nazev',
            ]
        );

        $result = [
            'mode' => 'nano',
            'cardId' => $cardId,
            'cardPoradi' => (int)($karta['poradi'] ?? 0),
            'title' => $title,
            'soubor' => (string)($karta['soubor'] ?? ''),
            'refreshOp' => (int)($karta['refresh_op'] ?? 0),
            'color' => ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) ? (string)$userCardHeaderColorById[$cardId] : '',
            'iconFile' => ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '',
            'role' => (int)($karta['min_role'] ?? 3),
            'col' => 0,
            'line' => 0,
            'isPosLocked' => 0,
            'minHtml' => $errorHtml,
            'renderErrorHtml' => $errorHtml,
            'cardColorUrl' => cb_url('/includes/select_card_color.php?id_karta=' . (string)$cardId),
            'cardIconUrl' => cb_url('/includes/select_card_ikon.php?id_karta=' . (string)$cardId),
        ];

        // DOCASNE MERENI CASU KARET
        if (function_exists('cb_tmp_measure_card_time_log')) {
            cb_tmp_measure_card_time_log($cardId, $title, 'nano', 'priprava', $cbTmpMeasureStart);
        }

        return $result;
    }

    $result = [
        'mode' => 'nano',
        'cardId' => $cardId,
        'cardPoradi' => (int)($karta['poradi'] ?? 0),
        'title' => $title,
        'soubor' => (string)($karta['soubor'] ?? ''),
        'refreshOp' => (int)($karta['refresh_op'] ?? 0),
        'color' => ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) ? (string)$userCardHeaderColorById[$cardId] : '',
        'iconFile' => ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '',
        'role' => (int)($karta['min_role'] ?? 3),
        'col' => 0,
        'line' => 0,
        'isPosLocked' => 0,
        'renderErrorHtml' => '',
        'cardColorUrl' => cb_url('/includes/select_card_color.php?id_karta=' . (string)$cardId),
        'cardIconUrl' => cb_url('/includes/select_card_ikon.php?id_karta=' . (string)$cardId),
    ];

    // DOCASNE MERENI CASU KARET
    if (function_exists('cb_tmp_measure_card_time_log')) {
        cb_tmp_measure_card_time_log($cardId, $title, 'nano', 'priprava', $cbTmpMeasureStart);
    }

    return $result;
}
