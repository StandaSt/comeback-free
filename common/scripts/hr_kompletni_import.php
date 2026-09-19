<?php
declare(strict_types=1);

/*
 * Lokální první naplnění HR z data/google_data/HR.zip.
 * Veřejné funkce: cb_hr_kompletni_import_preview(), cb_hr_kompletni_import_proved().
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/db/db_cis_slot.php';

function cb_hr_kompletni_import_preview(mysqli $db): array
{
    set_time_limit(0);
    cb_hr_kompletni_over_schema($db);
    $source = cb_hr_kompletni_priprav_zdroj();
    try {
        $employees = cb_hr_kompletni_nacti_zamestnance($source['zamestnanci']);
        $matched = cb_hr_kompletni_spocitej_shody($db, $employees);
        $payroll = cb_hr_kompletni_nahled_mezd($db, $source['mzdy']);
        $documents = cb_hr_kompletni_nahled_dokumentu($source['root']);

        return [
            'formular' => count($employees) . ' podání, ' . $matched['people'] . ' bezpečně přiřazených zaměstnanců, '
                . $matched['rejected'] . ' nepřiřazených nebo rozporných podání.',
            'mzdy' => $payroll['rows'] . ' řádků v ' . $payroll['months'] . ' uzavřených měsících, '
                . $payroll['from'] . ' až ' . $payroll['to'] . '.',
            'dokumenty' => $documents['files'] . ' souborů pro ' . $documents['people'] . ' zaměstnanců, '
                . $documents['metadata'] . ' souborů s připraveným vytěžením.',
        ];
    } finally {
        cb_hr_kompletni_smaz_strom($source['runtime']);
    }
}

function cb_hr_kompletni_import_proved(mysqli $db): array
{
    set_time_limit(0);
    cb_hr_kompletni_over_schema($db);
    $source = cb_hr_kompletni_priprav_zdroj();
    $employeeRows = cb_hr_kompletni_nacti_zamestnance($source['zamestnanci']);
    $matchedBeforeReset = cb_hr_kompletni_prirad_zamestnance($db, $employeeRows);
    $payrollRows = cb_hr_kompletni_nacti_mzdy($db, $source['mzdy']);

    if (!defined('CB_HR_IMPORT_DIRECT')) {
        define('CB_HR_IMPORT_DIRECT', true);
    }
    $GLOBALS['CB_HR_IMPORT_ENVIRONMENT'] = 'local';
    $GLOBALS['CB_HR_RESET_SCOPE'] = 'all';
    $GLOBALS['CB_HR_IMPORT_USERS'] = true;
    unset($GLOBALS['CB_HR_IMPORT_OUTPUT']);
    require __DIR__ . '/hr_import_user_do_person.php';
    unset(
        $GLOBALS['CB_HR_IMPORT_ENVIRONMENT'],
        $GLOBALS['CB_HR_RESET_SCOPE'],
        $GLOBALS['CB_HR_IMPORT_USERS'],
        $GLOBALS['CB_HR_IMPORT_OUTPUT']
    );

    $employeeResult = cb_hr_kompletni_import_zamestnancu($db, $matchedBeforeReset);
    $payrollResult = cb_hr_kompletni_import_mezd($db, $payrollRows);
    $rateResult = cb_hr_kompletni_vytvor_sazby($db);
    cb_hr_kompletni_smaz_strom(dirname(__DIR__, 3) . '/sklad/hr_dokumenty');
    $documentResult = cb_hr_kompletni_import_dokumentu($db, $source['root']);
    cb_hr_kompletni_smaz_strom($source['runtime']);

    $message = 'Kompletní naplnění HR dokončeno.' . PHP_EOL
        . 'USER → PERSON: ' . (int)$db->query('SELECT COUNT(*) FROM hr_person')->fetch_row()[0] . ' osob.' . PHP_EOL
        . 'Dotazníky: ' . $employeeResult['people'] . ' osob, odmítnuto ' . $employeeResult['rejected'] . ' podání.' . PHP_EOL
        . 'Mzdy: ' . $payrollResult['inserted'] . ' řádků, sazby: ' . $rateResult . ' intervalů.' . PHP_EOL
        . 'Dokumenty: ' . $documentResult['documents'] . ' souborů pro ' . $documentResult['people'] . ' osob.';

    return [
        'message' => $message,
        'audit' => 'person=' . $employeeResult['people'] . ';mzdy=' . $payrollResult['inserted']
            . ';sazby=' . $rateResult . ';dokumenty=' . $documentResult['documents'],
    ];
}

function cb_hr_kompletni_over_schema(mysqli $db): void
{
    $required = [
        'hr_zdravotni_pojisteni' => ['id_person', 'platnost_od', 'platnost_do', 'platny'],
        'hr_dokument_udaje' => ['id_dokument', 'verze'],
        'hr_osobni_udaje' => ['vzdelani', 'cizinec', 'zahranicni_identifikator', 'prvni_zamestnani_cr'],
        'hr_mzdy_mesic' => ['id_slot'],
    ];
    foreach ($required as $table => $columns) {
        $safeTable = str_replace('`', '``', $table);
        $found = [];
        $result = $db->query('SHOW COLUMNS FROM `' . $safeTable . '`');
        while ($row = $result->fetch_assoc()) {
            $found[(string)$row['Field']] = true;
        }
        foreach ($columns as $column) {
            if (!isset($found[$column])) {
                throw new RuntimeException('Databáze není připravená. Chybí ' . $table . '.' . $column . '.');
            }
        }
    }
}

function cb_hr_kompletni_priprav_zdroj(): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Není dostupná podpora ZIP.');
    }
    $root = dirname(__DIR__, 3);
    $zipPath = $root . '/data/google_data/HR.zip';
    if (!is_file($zipPath)) {
        throw new RuntimeException('Chybí data/google_data/HR.zip.');
    }
    $runtime = dirname(__DIR__) . '/tmp/hr_kompletni_import';
    cb_hr_kompletni_smaz_strom($runtime);
    if (!mkdir($runtime, 0775, true) && !is_dir($runtime)) {
        throw new RuntimeException('Nelze vytvořit pracovní složku importu.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        throw new RuntimeException('HR.zip nelze otevřít.');
    }
    try {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($index));
            if ($name === '' || !str_starts_with($name, 'HR/') || preg_match('#(^|/)\.\.?(/|$)#', $name) === 1) {
                throw new RuntimeException('HR.zip nemá očekávanou bezpečnou strukturu.');
            }
        }
        if (!$zip->extractTo($runtime)) {
            throw new RuntimeException('HR.zip se nepodařilo rozbalit.');
        }
    } finally {
        $zip->close();
    }

    return cb_hr_kompletni_pripraveny_zdroj();
}

function cb_hr_kompletni_pripraveny_zdroj(): array
{
    $runtime = dirname(__DIR__) . '/tmp/hr_kompletni_import';
    $sourceRoot = $runtime . '/HR';
    if (!is_dir($sourceRoot)) {
        throw new RuntimeException('Rozbalené podklady importu nebyly nalezeny. Spusťte kontrolu znovu.');
    }
    $employees = cb_hr_kompletni_najdi_soubor($sourceRoot, 'Zaměstnanci.xlsx');
    $payroll = cb_hr_kompletni_najdi_soubor($sourceRoot, 'HR 2024.xlsx');
    if ($employees === null || $payroll === null) {
        throw new RuntimeException('ZIP neobsahuje Zaměstnanci.xlsx a HR 2024.xlsx.');
    }
    return ['runtime' => $runtime, 'root' => $sourceRoot, 'zamestnanci' => $employees, 'mzdy' => $payroll];
}

function cb_hr_kompletni_najdi_soubor(string $root, string $basename): ?string
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getBasename() === $basename) {
            return $file->getPathname();
        }
    }
    return null;
}

function cb_hr_kompletni_nacti_zamestnance(string $path): array
{
    $book = cb_hr_kompletni_nacti_excel($path, ['Jména z dotazniku']);
    $sheet = $book->getSheetByName('Jména z dotazniku');
    if ($sheet === null) {
        throw new RuntimeException('V Zaměstnanci.xlsx chybí list Jména z dotazniku.');
    }
    $rows = [];
    for ($row = 2; $row <= $sheet->getHighestDataRow(); $row++) {
        $values = [];
        for ($column = 1; $column <= 34; $column++) {
            $values[$column] = cb_hr_kompletni_cell_value($sheet->getCell([$column, $row]));
        }
        if (trim((string)($values[2] ?? '')) === '' || trim((string)($values[3] ?? '')) === '') {
            continue;
        }
        $rows[] = [
            'row' => $row,
            'submitted_at' => cb_hr_kompletni_excel_datetime($values[1] ?? null),
            'first_name' => trim((string)$values[2]), 'last_name' => trim((string)$values[3]),
            'previous_name' => trim((string)$values[4]), 'birth_date' => cb_hr_kompletni_excel_date($values[5] ?? null),
            'birth_place' => trim((string)$values[6]), 'personal_id' => trim((string)$values[7]),
            'citizenship' => trim((string)$values[8]), 'phone' => trim((string)$values[9]),
            'email' => trim((string)$values[10]), 'id_card' => trim((string)$values[11]),
            'position' => trim((string)$values[12]), 'driver_to' => cb_hr_kompletni_excel_date($values[13] ?? null),
            'tax_discount' => trim((string)$values[14]), 'marital_status' => trim((string)$values[15]),
            'address' => trim((string)$values[16]), 'education' => trim((string)$values[17]),
            'start_date' => cb_hr_kompletni_excel_date($values[18] ?? null), 'insurer' => trim((string)$values[19]),
            'relation' => trim((string)$values[20]), 'workplace' => trim((string)$values[21]),
            'bank' => trim((string)$values[22]), 'children' => trim((string)$values[23]),
            'pension' => trim((string)$values[24]), 'foreigner' => trim((string)$values[25]),
            'cz_address' => trim((string)$values[26]), 'first_job_cz' => trim((string)$values[28]),
            'ico' => trim((string)$values[32]), 'starting_salary' => trim((string)$values[34]),
        ];
    }
    $book->disconnectWorksheets();
    return $rows;
}

function cb_hr_kompletni_spocitej_shody(mysqli $db, array $rows): array
{
    $matched = cb_hr_kompletni_prirad_zamestnance($db, $rows);
    return ['people' => count($matched['accepted']), 'rejected' => count($matched['rejected'])];
}

function cb_hr_kompletni_prirad_zamestnance(mysqli $db, array $rows): array
{
    $maps = cb_hr_kompletni_user_mapy($db);
    $aliases = cb_hr_kompletni_zamestnanecke_aliasy($db);
    $accepted = [];
    $rejected = [];
    foreach ($rows as $row) {
        $signals = [];
        $email = mb_strtolower(trim((string)$row['email']), 'UTF-8');
        $phone = cb_hr_kompletni_digits((string)$row['phone']);
        $name = cb_hr_kompletni_name_key($row['first_name'] . ' ' . $row['last_name']);
        foreach ([['email', $email], ['phone', $phone], ['name', $name]] as [$type, $key]) {
            $ids = $key !== '' ? (array)($maps[$type][$key] ?? []) : [];
            if (count($ids) === 1) {
                $signals[(int)$ids[0]] = true;
            }
        }
        $aliasId = $aliases[$name] ?? null;
        if ($aliasId !== null) {
            $signals = [(int)$aliasId => true];
        }
        if (count($signals) !== 1) {
            $rejected[] = $row;
            continue;
        }
        $idUser = (int)array_key_first($signals);
        if (!isset($accepted[$idUser]) || (string)$row['submitted_at'] > (string)$accepted[$idUser]['submitted_at']) {
            $accepted[$idUser] = $row;
        }
    }
    return ['accepted' => $accepted, 'rejected' => $rejected];
}

function cb_hr_kompletni_zamestnanecke_aliasy(mysqli $db): array
{
    $path = dirname(__DIR__, 3) . '/data/google_data/zamestnanci_jmena_import.json';
    if (!is_file($path)) {
        throw new RuntimeException('Chybí data/google_data/zamestnanci_jmena_import.json.');
    }
    $data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $validUsers = [];
    $result = $db->query('SELECT id_user FROM user');
    while ($row = $result->fetch_assoc()) {
        $validUsers[(int)$row['id_user']] = true;
    }
    $out = [];
    foreach ((array)($data['aliases'] ?? []) as $alias) {
        $idUser = (int)($alias['id_user'] ?? 0);
        $key = cb_hr_kompletni_name_key((string)($alias['source'] ?? ''));
        if ($idUser <= 0 || $key === '' || !isset($validUsers[$idUser])) {
            throw new RuntimeException('Neplatné mapování jména v zamestnanci_jmena_import.json.');
        }
        $out[$key] = $idUser;
    }
    return $out;
}

function cb_hr_kompletni_user_mapy(mysqli $db): array
{
    $maps = ['email' => [], 'phone' => [], 'name' => []];
    $result = $db->query('SELECT id_user,jmeno,prijmeni,email,telefon FROM `user`');
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id_user'];
        $values = [
            'email' => mb_strtolower(trim((string)$row['email']), 'UTF-8'),
            'phone' => cb_hr_kompletni_digits((string)$row['telefon']),
            'name' => cb_hr_kompletni_name_key((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
        ];
        foreach ($values as $type => $key) {
            if ($key !== '') {
                $maps[$type][$key] ??= [];
                $maps[$type][$key][] = $id;
            }
        }
        $swapped = cb_hr_kompletni_name_key((string)$row['prijmeni'] . ' ' . (string)$row['jmeno']);
        if ($swapped !== '') {
            $maps['name'][$swapped] ??= [];
            $maps['name'][$swapped][] = $id;
        }
    }
    return $maps;
}

function cb_hr_kompletni_import_zamestnancu(mysqli $db, array $matched): array
{
    $db->begin_transaction();
    try {
        foreach ($matched['accepted'] as $idUser => $row) {
            $person = $db->query('SELECT id_person,id_firma FROM hr_person WHERE id_user=' . (int)$idUser . ' LIMIT 1')->fetch_assoc();
            if ($person === null) {
                throw new RuntimeException('Po resetu chybí osoba pro user #' . $idUser . '.');
            }
            cb_hr_kompletni_uloz_osobu($db, (int)$person['id_person'], $row);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return ['people' => count($matched['accepted']), 'rejected' => count($matched['rejected'])];
}

function cb_hr_kompletni_uloz_osobu(mysqli $db, int $idPerson, array $row): void
{
    $isForeigner = cb_hr_kompletni_bool($row['foreigner']) || !cb_hr_kompletni_is_czech_citizenship($row['citizenship']);
    $personalId = trim((string)$row['personal_id']);
    $personal = [
        'rodne_prijmeni' => $row['previous_name'] ?: null,
        'datum_narozeni' => $row['birth_date'],
        'rodne_cislo' => $isForeigner ? null : ($personalId ?: null),
        'cislo_obcanskeho_prukazu' => $row['id_card'] ?: null,
        'statni_obcanstvi' => $row['citizenship'] ?: null,
        'misto_narozeni' => $row['birth_place'] ?: null,
        'vzdelani' => $row['education'] ?: null,
        'rodinny_stav' => $row['marital_status'] ?: null,
        'sleva_na_poplatnika' => cb_hr_kompletni_nullable_bool($row['tax_discount']),
        'vyzivovane_deti' => $row['children'] ?: null,
        'duchodce' => cb_hr_kompletni_pension_flag($row['pension']),
        'duchod_typ' => $row['pension'] ?: null,
        'ico' => $row['ico'] ?: null,
        'ridicsky_prukaz_platnost_do' => $row['driver_to'],
        'cizinec' => $isForeigner ? 1 : 0,
        'zahranicni_identifikator' => $isForeigner ? ($personalId ?: null) : null,
        'zahranicni_identifikator_typ' => $isForeigner && $personalId !== '' ? 'osobní identifikátor ze vstupního dotazníku' : null,
        'prvni_zamestnani_cr' => cb_hr_kompletni_nullable_bool($row['first_job_cz']),
    ];
    cb_hr_kompletni_update($db, 'hr_osobni_udaje', 'id_person', $idPerson, $personal, 'platny=1');

    cb_hr_kompletni_uloz_kontakty($db, $idPerson, $row);

    $db->query('DELETE FROM hr_adresa WHERE id_person=' . $idPerson);
    if ($row['address'] !== '') {
        $address = cb_hr_kompletni_rozdel_adresu($row['address']);
        cb_hr_kompletni_insert($db, 'hr_adresa', ['id_person' => $idPerson] + $address + ['typ' => 0, 'platny' => 1]);
    }
    if ($isForeigner && $row['cz_address'] !== '') {
        $address = cb_hr_kompletni_rozdel_adresu($row['cz_address']);
        cb_hr_kompletni_insert($db, 'hr_adresa', ['id_person' => $idPerson] + $address + ['typ' => 2, 'platny' => 1]);
    }

    $db->query('DELETE FROM hr_bankovni_ucet WHERE id_person=' . $idPerson);
    $bank = cb_hr_kompletni_bankovni_ucet($row['bank']);
    if ($bank !== null) {
        cb_hr_kompletni_insert($db, 'hr_bankovni_ucet', [
            'id_person' => $idPerson, 'cislo_uctu' => $bank['account'], 'kod_banky' => $bank['bank'],
            'hlavni' => 1, 'poznamka' => 'Import z HR.zip', 'zadano' => date('Y-m-d H:i:s'),
            'zmena' => date('Y-m-d H:i:s'), 'platny' => 1,
        ]);
    }

    $db->query('DELETE FROM hr_zdravotni_pojisteni WHERE id_person=' . $idPerson);
    if ($row['insurer'] !== '') {
        $insurer = cb_hr_kompletni_pojistovna($row['insurer']);
        cb_hr_kompletni_insert($db, 'hr_zdravotni_pojisteni', [
            'id_person' => $idPerson, 'stat' => $insurer['state'], 'kod_pojistovny' => $insurer['code'],
            'nazev_pojistovny' => $insurer['name'], 'platnost_od' => $row['start_date'],
            'poznamka' => 'Import z HR.zip', 'platny' => 1,
        ]);
        if ($insurer['code'] !== null && ctype_digit($insurer['code'])) {
            cb_hr_kompletni_update($db, 'hr_osobni_udaje', 'id_person', $idPerson, ['zdr_poj' => (int)$insurer['code']], 'platny=1');
        }
    }

    cb_hr_kompletni_uloz_pracovni_vztah($db, $idPerson, $row);
}

function cb_hr_kompletni_uloz_pracovni_vztah(mysqli $db, int $idPerson, array $row): void
{
    $start = $row['start_date'] ?: date('Y-m-d');
    $relationText = cb_hr_kompletni_plain((string)$row['relation']);
    $types = [];
    foreach (['hpp' => 1, 'dpp' => 2, 'dpc' => 3, 'ico' => 5] as $code => $id) {
        if (str_contains($relationText, $code)) {
            $types[] = $id;
        }
    }
    if ($types === []) {
        $types[] = 4;
    }
    $db->query('DELETE FROM hr_mzda WHERE id_pracovni_vztah IN (SELECT id_pracovni_vztah FROM hr_pracovni_vztah WHERE id_person=' . $idPerson . ')');
    $db->query('DELETE FROM hr_pracovni_vztah WHERE id_person=' . $idPerson);
    foreach (array_values(array_unique($types)) as $index => $type) {
        cb_hr_kompletni_insert($db, 'hr_pracovni_vztah', [
            'id_person' => $idPerson, 'id_pracovni_vztah_typ' => $type, 'datum_nastupu' => $start,
            'poznamka' => cb_hr_kompletni_pracovni_poznamka($row), 'platny' => 1,
        ]);
        $relationId = (int)$db->insert_id;
        $salary = cb_hr_kompletni_decimal($row['starting_salary']);
        if ($index === 0 && $salary !== null && $salary > 0) {
            cb_hr_kompletni_insert($db, 'hr_mzda', [
                'id_pracovni_vztah' => $relationId, 'id_mzda_typ' => 1, 'castka' => (int)round($salary),
                'platnost_od' => $start, 'platny' => 1,
            ]);
        }
    }
}

function cb_hr_kompletni_nahled_mezd(mysqli $db, string $path): array
{
    $rows = cb_hr_kompletni_nacti_mzdy($db, $path);
    $months = [];
    foreach ($rows as $row) {
        $months[(string)$row['datum_od']] = true;
    }
    $dates = array_keys($months);
    sort($dates);
    return [
        'rows' => count($rows), 'months' => count($dates),
        'from' => $dates[0] ?? 'nenalezeno', 'to' => $dates !== [] ? end($dates) : 'nenalezeno',
    ];
}

function cb_hr_kompletni_nacti_mzdy(mysqli $db, string $path): array
{
    $maps = cb_hr_kompletni_user_mapy($db);
    $aliases = cb_hr_kompletni_mzdove_aliasy($db);
    $sloty = cb_hr_kompletni_mzdove_sloty($db);
    $book = cb_hr_kompletni_nacti_excel($path);
    $out = [];
    foreach ($book->getWorksheetIterator() as $sheet) {
        $period = cb_hr_kompletni_mzdovy_mesic($sheet);
        if ($period === null) {
            continue;
        }
        $newFormat = isset($sloty[cb_hr_kompletni_slot_klic((string)cb_hr_kompletni_cell_value($sheet->getCell('T4')))]);
        $idSlot = null;
        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $name = trim((string)cb_hr_kompletni_cell_value($sheet->getCell('B' . $row)));
            $header = cb_hr_kompletni_plain($name);
            $headerSlotKey = cb_hr_kompletni_slot_klic($name);
            if (isset($sloty[$headerSlotKey])) {
                $idSlot = $sloty[$headerSlotKey];
                continue;
            }
            if ($idSlot === null || cb_hr_kompletni_mzdovy_nadpis($header, $sloty) || !cb_hr_kompletni_je_jmeno($name, $sloty)) {
                continue;
            }
            $ids = (array)($maps['name'][cb_hr_kompletni_name_key($name)] ?? []);
            $idUser = count(array_unique($ids)) === 1 ? (int)$ids[0] : null;
            if ($idUser === null) {
                $idUser = $aliases[cb_hr_kompletni_name_key($name)] ?? null;
            }
            $g = cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell('G' . $row)));
            $h = cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell('H' . $row)));
            $isFix = $g !== null && abs($g - 1.0) < 0.0001;
            $out[] = [
                'id_user' => $idUser, 'import_jmeno' => $idUser === null ? $name : null,
                'rok' => (int)substr($period['from'], 0, 4), 'mesic' => (int)substr($period['from'], 5, 2),
                'datum_od' => $period['from'], 'datum_do' => $period['to'], 'mzda_typ' => $isFix ? 'fix' : 'hodinova',
                'id_slot' => $idSlot, 'hodiny' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell(($newFormat ? 'U' : 'M') . $row))),
                'hodinova_sazba' => $isFix ? null : $h, 'mesicni_fix' => $isFix ? $h : null,
                'isk' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell('I' . $row))),
                'bonus_1' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell('J' . $row))),
                'bonus_2' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell('K' . $row))),
                'bonus_cista' => $newFormat ? cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell('L' . $row))) : null,
                'cista_mzda' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell(($newFormat ? 'V' : 'N') . $row))),
                'hruba_mzda' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell(($newFormat ? 'W' : 'O') . $row))),
                'superhruba_mzda' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell(($newFormat ? 'X' : 'P') . $row))),
                'naklad_col_hod' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell(($newFormat ? 'AC' : 'U') . $row))),
                'naklad_col_den' => cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell(($newFormat ? 'AD' : 'V') . $row))),
                'je_manager_col' => (int)(cb_hr_kompletni_decimal(cb_hr_kompletni_cell_value($sheet->getCell('A' . $row))) ?? 0),
                'import_list' => $sheet->getTitle(),
            ];
        }
    }
    $book->disconnectWorksheets();
    return $out;
}

function cb_hr_kompletni_import_mezd(mysqli $db, array $rows): array
{
    $db->begin_transaction();
    try {
        foreach ($rows as $row) {
            cb_hr_kompletni_insert($db, 'hr_mzdy_mesic', $row + [
                'zdroj' => 'HR 2024.xlsx', 'stav' => 'import',
                'poznamka' => $row['id_user'] === null ? 'Čeká na ruční spárování s user.' : null,
            ]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return ['inserted' => count($rows)];
}

function cb_hr_kompletni_vytvor_sazby(mysqli $db): int
{
    $rows = [];
    $result = $db->query('SELECT id_user,import_jmeno,datum_od,datum_do,mzda_typ,hodinova_sazba,mesicni_fix,je_manager_col,naklad_col_hod,naklad_col_den FROM hr_mzdy_mesic ORDER BY COALESCE(id_user,0),import_jmeno,datum_od,id_hr_mzda_mesic');
    while ($row = $result->fetch_assoc()) {
        $entity = $row['id_user'] !== null ? 'u:' . $row['id_user'] : 'n:' . $row['import_jmeno'];
        $key = $entity . '|' . $row['datum_od'];
        if (!isset($rows[$key]) || ($row['hodinova_sazba'] !== null || $row['mesicni_fix'] !== null)) {
            $rows[$key] = $row + ['entity' => $entity];
        }
    }
    $byEntity = [];
    foreach ($rows as $row) {
        $byEntity[$row['entity']][] = $row;
    }
    $inserted = 0;
    foreach ($byEntity as $entityRows) {
        $interval = null;
        foreach ($entityRows as $row) {
            $signature = implode('|', [$row['mzda_typ'], $row['hodinova_sazba'], $row['mesicni_fix'], $row['je_manager_col'], $row['naklad_col_hod'], $row['naklad_col_den']]);
            if ($interval !== null && $interval['signature'] === $signature) {
                $interval['platnost_do'] = $row['datum_do'];
                continue;
            }
            if ($interval !== null) {
                cb_hr_kompletni_insert($db, 'hr_sazby', $interval['data'] + ['platnost_do' => $interval['platnost_do']]);
                $inserted++;
            }
            $interval = [
                'signature' => $signature, 'platnost_do' => $row['datum_do'],
                'data' => [
                    'id_user' => $row['id_user'], 'import_jmeno' => $row['import_jmeno'], 'platnost_od' => $row['datum_od'],
                    'id_mzda_typ' => match ($row['mzda_typ']) { 'fix' => 2, 'kombinovana' => 3, 'bez_mzdy' => 4, default => 1 },
                    'hodinova_sazba' => $row['hodinova_sazba'], 'mesicni_fix' => $row['mesicni_fix'],
                    'je_manager_col' => $row['je_manager_col'], 'naklad_col_hod' => $row['naklad_col_hod'],
                    'naklad_col_den' => $row['naklad_col_den'], 'zdroj' => 'HR 2024.xlsx', 'poznamka' => 'Vytvořeno při prvním naplnění HR',
                ],
            ];
        }
        if ($interval !== null) {
            cb_hr_kompletni_insert($db, 'hr_sazby', $interval['data'] + ['platnost_do' => null]);
            $inserted++;
        }
    }
    return $inserted;
}

function cb_hr_kompletni_nahled_dokumentu(string $sourceRoot): array
{
    $manifest = cb_hr_kompletni_manifest();
    $metadata = cb_hr_kompletni_dokument_udaje_manifest();
    $files = 0;
    $metadataFiles = 0;
    $people = [];
    foreach ((array)($manifest['documents'] ?? []) as $document) {
        $people[(int)$document['id_user']] = true;
        foreach ((array)$document['files'] as $relative) {
            if (is_file($sourceRoot . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$relative))) {
                $files++;
                if (isset($metadata[(string)$relative])) {
                    $metadataFiles++;
                }
            }
        }
    }
    if ($metadataFiles !== $files) {
        throw new RuntimeException('Pro některé dokumenty chybí připravené vytěžení údajů.');
    }
    return ['files' => $files, 'people' => count($people), 'metadata' => $metadataFiles];
}

function cb_hr_kompletni_import_dokumentu(mysqli $db, string $sourceRoot): array
{
    $manifest = cb_hr_kompletni_manifest();
    $metadata = cb_hr_kompletni_dokument_udaje_manifest();
    $typeMap = ['contract' => 2, 'passport' => 8, 'residence_permit' => 9, 'insurance_card' => 10, 'id_card' => 3, 'other' => 4];
    $targetRoot = dirname(__DIR__, 2) . '/hr/hr_dokumenty';
    cb_hr_kompletni_smaz_strom($targetRoot);
    if (!mkdir($targetRoot, 0775, true) && !is_dir($targetRoot)) {
        throw new RuntimeException('Nelze vytvořit www/hr/hr_dokumenty.');
    }
    file_put_contents($targetRoot . '/.htaccess', "Require all denied\n");
    file_put_contents($targetRoot . '/.gitignore', "*\n!.htaccess\n!.gitignore\n");
    $documents = 0;
    $people = [];
    foreach ((array)($manifest['documents'] ?? []) as $item) {
        $idUser = (int)$item['id_user'];
        $person = $db->query('SELECT id_person,id_firma FROM hr_person WHERE id_user=' . $idUser . ' LIMIT 1')->fetch_assoc();
        if ($person === null) {
            continue;
        }
        foreach ((array)$item['files'] as $relative) {
            $source = $sourceRoot . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$relative);
            if (!is_file($source)) {
                continue;
            }
            $type = (string)($item['type'] ?? 'other');
            $typeId = $typeMap[$type] ?? 4;
            $title = match ($type) {
                'contract' => 'Pracovní smlouva',
                'passport' => 'Cestovní pas', 'residence_permit' => 'Pobytové oprávnění nebo vízum',
                'insurance_card' => 'Kartička zdravotní pojišťovny', 'id_card' => 'Občanský průkaz',
                default => 'Ostatní dokument',
            };
            $documentMetadata = (array)($metadata[(string)$relative] ?? []);
            cb_hr_kompletni_insert($db, 'hr_dokument', [
                'verze' => 1, 'id_firma' => (int)$person['id_firma'], 'id_person' => (int)$person['id_person'],
                'id_dokument_typ' => $typeId, 'id_dokument_stav' => 1, 'nazev' => $title,
                'platnost_od' => $documentMetadata['platnost_od'] ?? null,
                'platnost_do' => $documentMetadata['platnost_do'] ?? null,
                'platny' => 1,
            ]);
            $idDocument = (int)$db->insert_id;
            $extension = strtolower((string)pathinfo($source, PATHINFO_EXTENSION));
            $extension = $extension === 'jpeg' ? 'jpg' : $extension;
            if (!in_array($extension, ['jpg', 'png', 'pdf', 'docx'], true)) {
                $db->query('DELETE FROM hr_dokument WHERE id_dokument=' . $idDocument . ' AND verze=1');
                continue;
            }
            $personDir = $targetRoot . '/' . (int)$person['id_firma'] . '/' . (int)$person['id_person'];
            if (!is_dir($personDir) && !mkdir($personDir, 0775, true) && !is_dir($personDir)) {
                throw new RuntimeException('Nelze vytvořit složku dokumentů zaměstnance.');
            }
            $stored = $idDocument . '_1.' . $extension;
            $target = $personDir . '/' . $stored;
            if (!copy($source, $target)) {
                throw new RuntimeException('Dokument se nepodařilo uložit.');
            }
            cb_hr_kompletni_insert($db, 'hr_dokument_soubor', [
                'id_dokument' => $idDocument, 'verze' => 1, 'puvodni_nazev' => basename($source),
                'ulozeny_nazev' => $stored,
                'relativni_cesta' => 'hr/hr_dokumenty/' . (int)$person['id_firma'] . '/' . (int)$person['id_person'] . '/' . $stored,
                'mime_typ' => mime_content_type($source) ?: null, 'velikost' => filesize($source),
                'sha256' => hash_file('sha256', $source), 'poradi' => 1,
            ]);
            cb_hr_kompletni_uloz_dokument_udaje($db, $idDocument, (int)$person['id_person'], $type, $documentMetadata);
            $documents++;
            $people[(int)$person['id_person']] = true;
        }
    }
    return ['documents' => $documents, 'people' => count($people)];
}

function cb_hr_kompletni_manifest(): array
{
    $path = dirname(__DIR__, 3) . '/data/google_data/doklady_import.json';
    if (!is_file($path)) {
        throw new RuntimeException('Chybí data/google_data/doklady_import.json.');
    }
    return json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

function cb_hr_kompletni_dokument_udaje_manifest(): array
{
    $path = dirname(__DIR__, 3) . '/data/google_data/doklady_udaje_import.json';
    if (!is_file($path)) {
        throw new RuntimeException('Chybí data/google_data/doklady_udaje_import.json.');
    }
    $data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $out = [];
    foreach ((array)($data['documents'] ?? []) as $document) {
        $source = (string)($document['source'] ?? '');
        if ($source !== '') {
            $out[$source] = $document;
        }
    }
    return $out;
}

function cb_hr_kompletni_uloz_dokument_udaje(mysqli $db, int $idDocument, int $idPerson, string $type, array $metadata): void
{
    if ($metadata === []) {
        return;
    }
    $allowed = [
        'jmeno_na_dokladu', 'cislo_dokladu', 'stat_vydani', 'vydal', 'datum_vydani', 'datum_narozeni',
        'misto_narozeni', 'statni_obcanstvi', 'pohlavi', 'mrz', 'vizum_typ', 'vizum_pocet_vstupu',
        'vizum_delka_pobytu_dni', 'navazane_cislo_dokladu', 'pojistovna_kod', 'pojistovna_nazev',
        'cislo_pojistence', 'ocr_text', 'ocr_jazyk', 'ocr_duvera', 'overeno_clovekem', 'poznamka',
    ];
    $values = ['id_dokument' => $idDocument, 'verze' => 1];
    foreach ($allowed as $column) {
        if (array_key_exists($column, $metadata)) {
            $values[$column] = $metadata[$column];
        }
    }
    cb_hr_kompletni_insert($db, 'hr_dokument_udaje', $values);

    if ($type === 'id_card' && !empty($metadata['cislo_dokladu'])) {
        $number = (string)$metadata['cislo_dokladu'];
        $stmt = $db->prepare("UPDATE hr_osobni_udaje SET cislo_obcanskeho_prukazu=? WHERE id_person=? AND platny=1 AND (cislo_obcanskeho_prukazu IS NULL OR cislo_obcanskeho_prukazu='')");
        $stmt->bind_param('si', $number, $idPerson);
        $stmt->execute();
        $stmt->close();
    }
}

function cb_hr_kompletni_mzdove_aliasy(mysqli $db): array
{
    $path = dirname(__DIR__, 3) . '/data/google_data/mzdy_jmena_import.json';
    if (!is_file($path)) {
        throw new RuntimeException('Chybí data/google_data/mzdy_jmena_import.json.');
    }
    $data = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    $validUsers = [];
    $result = $db->query('SELECT id_user FROM user');
    while ($row = $result->fetch_assoc()) {
        $validUsers[(int)$row['id_user']] = true;
    }
    $out = [];
    foreach ((array)($data['aliases'] ?? []) as $alias) {
        $idUser = (int)($alias['id_user'] ?? 0);
        $key = cb_hr_kompletni_name_key((string)($alias['source'] ?? ''));
        if ($idUser <= 0 || $key === '' || !isset($validUsers[$idUser])) {
            throw new RuntimeException('Neplatné mapování jména v mzdy_jmena_import.json.');
        }
        $out[$key] = $idUser;
    }
    return $out;
}

/** @return array<string,int> */
function cb_hr_kompletni_mzdove_sloty(mysqli $db): array
{
    $sloty = [];
    foreach (cb_cis_slot_nazvy($db, true) as $idSlot => $nazev) {
        $klic = cb_hr_kompletni_slot_klic($nazev);
        if ($klic !== '') {
            $sloty[$klic] = $idSlot;
        }
    }

    if (isset($sloty['pizzar'])) {
        $sloty['instor'] = $sloty['pizzar'];
        $sloty['instore'] = $sloty['pizzar'];
    }

    return $sloty;
}

function cb_hr_kompletni_mzdovy_nadpis(string $plain, array $sloty): bool
{
    $compact = str_replace(' ', '', $plain);
    return preg_match('/^(celkem|suma)( |$)/', $plain) === 1 || isset($sloty[$compact]);
}

function cb_hr_kompletni_mzdovy_mesic($sheet): ?array
{
    $title = (string)$sheet->getTitle();
    if (!str_starts_with($title, 'Mzdy ') || $title === 'Mzdy' || $title === '__TEMP_SORT_MZDY__') {
        return null;
    }
    $start = cb_hr_kompletni_excel_date(cb_hr_kompletni_cell_value($sheet->getCell('M1')))
        ?? cb_hr_kompletni_excel_date(cb_hr_kompletni_cell_value($sheet->getCell('U1')));
    if ($start === null) {
        return null;
    }
    $end = cb_hr_kompletni_excel_date(cb_hr_kompletni_cell_value($sheet->getCell('S1')))
        ?? cb_hr_kompletni_excel_date(cb_hr_kompletni_cell_value($sheet->getCell('AA1')))
        ?? (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');
    $lastClosed = (new DateTimeImmutable('first day of this month'))->modify('-1 day')->format('Y-m-d');
    return $end <= $lastClosed ? ['from' => $start, 'to' => $end] : null;
}

function cb_hr_kompletni_excel_date(mixed $value): ?string
{
    if (!is_numeric($value) || (float)$value <= 0) {
        return null;
    }
    return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
}

function cb_hr_kompletni_cell_value(object $cell): mixed
{
    $value = $cell->getValue();
    if (is_string($value) && str_starts_with($value, '=')) {
        return $cell->getOldCalculatedValue();
    }
    return $value;
}

function cb_hr_kompletni_nacti_excel(string $path, ?array $sheets = null): object
{
    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(true);
    if (method_exists($reader, 'setReadEmptyCells')) {
        $reader->setReadEmptyCells(false);
    }
    if ($sheets !== null) {
        $reader->setLoadSheetsOnly($sheets);
    }
    return $reader->load($path);
}

function cb_hr_kompletni_excel_datetime(mixed $value): ?string
{
    if (!is_numeric($value) || (float)$value <= 0) {
        return null;
    }
    return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d H:i:s');
}

function cb_hr_kompletni_rozdel_adresu(string $value): array
{
    $text = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    $parts = array_values(array_filter(array_map('trim', preg_split('/[,;]/u', $text) ?: []), static fn(string $v): bool => $v !== ''));
    $street = $parts[0] ?? $text;
    $city = $parts[1] ?? null;
    $state = count($parts) > 2 ? end($parts) : null;
    $psc = null;
    if (preg_match('/\b(\d{3})\s?(\d{2})\b/u', $text, $match) === 1) {
        $psc = $match[1] . ' ' . $match[2];
        if ($city !== null) {
            $city = trim(str_replace($match[0], '', $city));
        }
    }
    $cp = null;
    if (preg_match('/^(.*?)[\s,]+(\d+[a-zA-Z]?(?:\/\d+[a-zA-Z]?)?)$/u', $street, $match) === 1) {
        $street = trim($match[1]);
        $cp = $match[2];
    }
    return ['ulice' => $street ?: null, 'cp' => $cp, 'mesto' => $city ?: null, 'psc' => $psc, 'stat' => $state ?: null];
}

function cb_hr_kompletni_bankovni_ucet(string $value): ?array
{
    $value = preg_replace('/\s+/u', '', trim($value)) ?? '';
    if (preg_match('/^(?:(\d{1,6})-)?(\d{1,10})\/(\d{4})$/', $value, $match) !== 1) {
        return null;
    }
    return ['account' => ($match[1] ?? '') !== '' ? $match[1] . '-' . $match[2] : $match[2], 'bank' => $match[3]];
}

function cb_hr_kompletni_pojistovna(string $value): array
{
    $plain = cb_hr_kompletni_plain($value);
    $code = preg_match('/^\s*(\d{3})\s*$/', $value, $m) === 1
        ? $m[1]
        : (preg_match('/(?:^|\D)(111|201|205|207|209|211|213)(?:\D|$)/', $value, $m) === 1 ? $m[1] : null);
    $map = ['vzp' => '111', 'vseobecni' => '111', 'vozp' => '201', 'voz' => '201', 'cpzp' => '205', 'ozp' => '207', 'zps' => '209', 'zpmv' => '211', 'zmpv' => '211', 'rbp' => '213'];
    if ($code === null) {
        foreach ($map as $name => $mapped) {
            if (str_contains($plain, $name)) {
                $code = $mapped;
                break;
            }
        }
    }
    $foreign = mb_stripos($value, 'dôver', 0, 'UTF-8') !== false
        || str_contains($plain, 'dovera')
        || str_contains($plain, 'union')
        || str_contains($plain, 'slovensk');
    if ($code === null && preg_match('/^(111|201|205|207|209|211|213)\d+$/', preg_replace('/\D+/', '', $value) ?? '', $m) === 1) {
        $code = $m[1];
    }
    if ($foreign && preg_match('/(?:^|\D)(24|25|27)(?:\D|$)/', $value, $m) === 1) {
        $code = $m[1];
    }
    return ['state' => $foreign ? 'SK' : 'CZ', 'code' => $code, 'name' => trim($value)];
}

function cb_hr_kompletni_insert(mysqli $db, string $table, array $values): void
{
    $columns = array_keys($values);
    $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($values), '?')) . ')';
    $stmt = $db->prepare($sql);
    cb_hr_kompletni_bind($stmt, array_values($values));
    $stmt->execute();
    $stmt->close();
}

function cb_hr_kompletni_update(mysqli $db, string $table, string $keyColumn, int $key, array $values, string $extra = ''): void
{
    $sets = array_map(static fn(string $column): string => '`' . $column . '`=?', array_keys($values));
    $sql = 'UPDATE `' . str_replace('`', '``', $table) . '` SET ' . implode(',', $sets) . ' WHERE `' . $keyColumn . '`=?' . ($extra !== '' ? ' AND ' . $extra : '');
    $stmt = $db->prepare($sql);
    cb_hr_kompletni_bind($stmt, [...array_values($values), $key]);
    $stmt->execute();
    $stmt->close();
}

function cb_hr_kompletni_bind(mysqli_stmt $stmt, array $values): void
{
    $types = '';
    foreach ($values as $value) {
        $types .= is_int($value) ? 'i' : (is_float($value) ? 'd' : 's');
    }
    $params = [$types];
    foreach ($values as $index => $value) {
        $values[$index] = $value;
        $params[] = &$values[$index];
    }
    $stmt->bind_param(...$params);
}

function cb_hr_kompletni_name_key(string $value): string
{
    return cb_hr_kompletni_plain($value);
}

function cb_hr_kompletni_plain(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower(trim($value), 'UTF-8'));
    return trim((string)preg_replace('/[^a-z0-9]+/', ' ', (string)$ascii));
}

function cb_hr_kompletni_slot_klic(string $value): string
{
    return str_replace(' ', '', cb_hr_kompletni_plain($value));
}

function cb_hr_kompletni_digits(string $value): string
{
    $digits = preg_replace('/\D+/', '', $value) ?? '';
    return str_starts_with($digits, '420') && strlen($digits) === 12 ? substr($digits, 3) : $digits;
}

function cb_hr_kompletni_bool(string $value): bool
{
    return in_array(cb_hr_kompletni_plain($value), ['ano', 'yes', '1', 'true'], true);
}

function cb_hr_kompletni_nullable_bool(string $value): ?int
{
    $plain = cb_hr_kompletni_plain($value);
    if ($plain === '') {
        return null;
    }
    return in_array($plain, ['ano', 'yes', '1', 'true'], true) ? 1 : 0;
}

function cb_hr_kompletni_pension_flag(string $value): ?int
{
    $plain = cb_hr_kompletni_plain($value);
    if ($plain === '') {
        return null;
    }
    return in_array($plain, ['ne', 'no', '0', 'false', 'nepobiram', 'nejsem'], true) ? 0 : 1;
}

function cb_hr_kompletni_uloz_kontakty(mysqli $db, int $idPerson, array $row): void
{
    $email = mb_strtolower(trim((string)$row['email']), 'UTF-8');
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $db->prepare('SELECT 1 FROM hr_email WHERE id_person=? AND LOWER(email)=? LIMIT 1');
        $stmt->bind_param('is', $idPerson, $email);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if (!$exists) {
            cb_hr_kompletni_insert($db, 'hr_email', [
                'id_person' => $idPerson, 'id_email_typ' => 1, 'email' => $email,
                'hlavni' => 0, 'platny' => 1,
            ]);
        }
    }

    $phone = trim((string)$row['phone']);
    $normalized = cb_hr_kompletni_digits($phone);
    if ($normalized !== '') {
        $stmt = $db->prepare('SELECT 1 FROM hr_telefon WHERE id_person=? AND telefon_normalizovany=? LIMIT 1');
        $stmt->bind_param('is', $idPerson, $normalized);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if (!$exists) {
            cb_hr_kompletni_insert($db, 'hr_telefon', [
                'id_person' => $idPerson, 'id_telefon_typ' => 1, 'telefon' => $phone,
                'telefon_normalizovany' => $normalized, 'hlavni' => 0, 'platny' => 1,
            ]);
        }
    }
}

function cb_hr_kompletni_pracovni_poznamka(array $row): string
{
    $parts = ['Import z HR.zip'];
    foreach (['relation' => 'Vztah', 'position' => 'Pozice', 'workplace' => 'Pracoviště'] as $key => $label) {
        $value = trim((string)($row[$key] ?? ''));
        if ($value !== '') {
            $parts[] = $label . ': ' . $value;
        }
    }
    return implode('; ', $parts);
}

function cb_hr_kompletni_is_czech_citizenship(string $value): bool
{
    $plain = cb_hr_kompletni_plain($value);
    $compact = str_replace(' ', '', $plain);
    return $plain === ''
        || str_contains($plain, 'cesk')
        || in_array($compact, ['cr', 'cz', 'cech'], true);
}

function cb_hr_kompletni_decimal(mixed $value): ?float
{
    $text = trim(str_replace(',', '.', (string)$value));
    return $text !== '' && is_numeric($text) ? (float)$text : null;
}

function cb_hr_kompletni_je_jmeno(string $value, array $sloty): bool
{
    $plain = cb_hr_kompletni_plain($value);
    $compact = str_replace(' ', '', $plain);

    return $plain !== ''
        && count(preg_split('/\s+/', $plain) ?: []) >= 2
        && !isset($sloty[$plain])
        && !isset($sloty[$compact]);
}

function cb_hr_kompletni_smaz_strom(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) {
        $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}
