<?php
declare(strict_types=1);

/**
 * DB helper pro nacitani povolenych ciselniku pouzivanych v HR formularich.
 */

/**
 * Nacte povoleny ciselnik pro HR formular.
 */
function hr_fetch_lookup(mysqli $db, string $table, string $idColumn, string $labelColumn, string $orderColumn = ''): array
{
    $orderBy = $orderColumn !== '' ? $orderColumn : $idColumn;
    $where = '';
    if ($table === 'hr_cis_pracovni_vztah_typ') {
        $where = ' WHERE aktivni = 1';
    }

    $rows = [];
    $result = $db->query("SELECT {$idColumn} AS id, {$labelColumn} AS label FROM {$table}{$where} ORDER BY {$orderBy}");
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id'],
            'label' => (string)$row['label'],
        ];
    }

    return $rows;
}
