<?php
declare(strict_types=1);

/**
 * Nacte povoleny ciselnik pro HR formular.
 */
function hr_fetch_lookup(mysqli $db, string $table, string $idColumn, string $labelColumn, string $orderColumn = ''): array
{
    $allowed = [
        'hr_pracovni_vztah_typ' => ['id_pracovni_vztah_typ', 'nazev', 'poradi'],
        'pobocka' => ['id_pob', 'nazev', 'id_pob'],
        'cis_slot' => ['id_slot', 'slot', 'id_slot'],
    ];

    if (!isset($allowed[$table])) {
        return [];
    }

    [$safeId, $safeLabel, $safeOrder] = $allowed[$table];
    if ($idColumn !== $safeId || $labelColumn !== $safeLabel) {
        return [];
    }

    $orderBy = $orderColumn !== '' ? $safeOrder : $safeId;
    $where = '';
    if ($table === 'hr_pracovni_vztah_typ') {
        $where = ' WHERE aktivni = 1';
    }

    $rows = [];
    $result = $db->query("SELECT {$safeId} AS id, {$safeLabel} AS label FROM {$table}{$where} ORDER BY {$orderBy}");
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'label' => (string)$row['label'],
        ];
    }

    return $rows;
}
