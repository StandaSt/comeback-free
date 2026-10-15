<?php
declare(strict_types=1);

/**
 * DB helper pro nacitani povolenych ciselniku pouzivanych v HR formularich.
 * Pohlavi se vybira a overuje podle ID; Neuvedeno (ID 0) se nabizi posledni.
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
    if ($table === 'hr_cis_pracovni_vztah_typ' || $table === 'hr_cis_pohlavi') {
        $where = ' WHERE aktivni = 1';
    }
    if ($table === 'pobocka' || $table === 'cis_slot') {
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

/** Nacte aktivni pohlavi; ID 0 je vyhrazena volba Neuvedeno, nikoli chybejici hodnota. */
function hr_fetch_genders(mysqli $db): array
{
    return hr_fetch_lookup($db, 'hr_cis_pohlavi', 'id_pohlavi', 'nazev', '(id_pohlavi = 0), id_pohlavi');
}

/** Overi ciselne ID z formulare proti aktivnimu ciselniku, vcetne platne nuly. */
function hr_employee_gender_id(mysqli $db, mixed $value): int
{
    if ((!is_string($value) && !is_int($value)) || !preg_match('/^\d+$/D', (string)$value) || (float)$value > 4294967295) {
        throw new CbUserVisibleException('Vyberte pohlaví z číselníku.');
    }
    $id = (int)$value;
    foreach (hr_fetch_genders($db) as $gender) {
        if ($gender['id'] === $id) return $id;
    }
    throw new CbUserVisibleException('Vybrané pohlaví není aktivní v číselníku.');
}

/**
 * Nacte tituly pro pozici pred nebo za jmenem.
 */
function hr_fetch_employee_titles(mysqli $db, int $placement): array
{
    $stmt = $db->prepare('SELECT id_titul, zkratka FROM hr_cis_tituly WHERE umisteni = ? ORDER BY zkratka');
    $stmt->bind_param('i', $placement);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int)$row['id_titul'],
            'label' => (string)$row['zkratka'],
        ];
    }
    $stmt->close();

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
