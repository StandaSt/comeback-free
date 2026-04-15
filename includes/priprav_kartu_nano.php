<?php
// priprav_kartu_nano.php * Verze: V1 * Aktualizace: 15.04.2026
declare(strict_types=1);

function cb_priprav_kartu_role_class(int $minRole): string
{
    if ($minRole === 1) {
        return ' card_top_role_1';
    }
    if ($minRole === 2) {
        return ' card_top_role_2';
    }
    return '';
}

function cb_priprav_kartu_nano(
    array $karta,
    array $userCardHeaderColorById,
    array $userCardIconFileById,
    array $userCardPosById,
    int $dashGridCols
): array {
    $cardId = (int)($karta['id_karta'] ?? 0);
    return [
        'cardId' => $cardId,
        'cardPoradi' => (int)($karta['poradi'] ?? 0),
        'title' => (string)($karta['nazev'] ?? ''),
        'color' => ($cardId > 0 && isset($userCardHeaderColorById[$cardId])) ? (string)$userCardHeaderColorById[$cardId] : '',
        'iconFile' => ($cardId > 0 && isset($userCardIconFileById[$cardId])) ? (string)$userCardIconFileById[$cardId] : '',
        'role' => (int)($karta['min_role'] ?? 3),
        // NANO nemá subtitle, max ani HTML
    ];
}
