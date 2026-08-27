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
    if ($table === 'hr_cis_pracovni_vztah_typ' && $labelColumn === 'nazev') {
        $labelColumn = "CONCAT(nazev, ' – ', CASE nazev WHEN 'HPP' THEN 'hlavní pracovní poměr' WHEN 'DPP' THEN 'dohoda o provedení práce' WHEN 'DPČ' THEN 'dohoda o pracovní činnosti' ELSE nazev END)";
    }
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

/**
 * Nacte aktivni zdravotni pojistovny pro formular osobnich udaju.
 */
function hr_fetch_health_insurers(mysqli $db): array
{
    $result = $db->query('SELECT kod, zkratka FROM hr_cis_pojistovny WHERE aktivni = 1 ORDER BY kod');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'kod' => (int)$row['kod'],
            'label' => (string)$row['kod'] . ' – ' . (string)$row['zkratka'],
        ];
    }
    $result->free();

    return $rows;
}

/**
 * Nacte aktivni benefity serazene podle poradi ciselniku.
 */
function hr_fetch_active_benefits(mysqli $db): array
{
    $result = $db->query('SELECT id_cis_benefit, nazev FROM hr_cis_benefit WHERE aktivni = 1 ORDER BY poradi, nazev');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id_cis_benefit'],
            'label' => (string)$row['nazev'],
        ];
    }
    $result->free();

    return $rows;
}

/**
 * Nacte dva povolene typy mzdy pro pracovni vztah.
 */
function hr_fetch_work_salary_types(mysqli $db): array
{
    $result = $db->query('SELECT id_mzda_typ, nazev FROM cis_mzda_typ WHERE id_mzda_typ IN (1, 2) ORDER BY id_mzda_typ');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id_mzda_typ'],
            'label' => (string)$row['nazev'],
        ];
    }
    $result->free();

    return $rows;
}
