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
    $cardId = (int)($karta['id_karta'] ?? 0);

    return [
        'mode' => 'nano',
        'cardId' => $cardId,
        'cardPoradi' => (int)($karta['poradi'] ?? 0),
        'title' => (string)($karta['nazev'] ?? ''),
        'color' => ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) ? (string)$userCardHeaderColorById[$cardId] : '',
        'iconFile' => ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '',
        'role' => (int)($karta['min_role'] ?? 3),
        'col' => 0,
        'line' => 0,
        'isPosLocked' => 0,
        'cardColorUrl' => cb_url('/includes/select_card_color.php?id_karta=' . (string)$cardId),
        'cardIconUrl' => cb_url('/includes/select_card_ikon.php?id_karta=' . (string)$cardId),
    ];
}
