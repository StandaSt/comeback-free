<?php
// inicializace/google_data.php * Verze: V13 * Aktualizace: 05.05.2026

declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__ . '/../db/db_connect.php';

const BASE_DIR = __DIR__ . '/../admin_testy/reporty_google_testy/google_data/Pobočky';
const REPORT_LOG_FILE = __DIR__ . '/../log/google_reporty.txt';
const REPORT_IMPORT_LOG_FILE = __DIR__ . '/../log/reporty_import.txt';
const REPORT_IMPORT_MATCH_LOG_FILE = __DIR__ . '/../log/reporty_import_match.txt';
const REPORT_OPEN_CLOSE_LOG_FILE = __DIR__ . '/../log/reporty_open_close.txt';
const GOOGLE_SOURCE = 1;
const MONTHS_LOOKUP = [
    'january' => 1,
    'february' => 2,
    'march' => 3,
    'april' => 4,
    'may' => 5,
    'june' => 6,
    'july' => 7,
    'august' => 8,
    'september' => 9,
    'october' => 10,
    'november' => 11,
    'december' => 12,
];
const GOOGLE_BRANCH_MAP = [
    'Malešice' => 1,
    'Chodov' => 2,
    'Zličín' => 3,
    'Prosek' => 4,
    'Libuš' => 5,
    'Bolevec' => 6,
    'Výroba' => 0,
];

if (isset($_POST['run_google_data']) && (string)$_POST['run_google_data'] === '1') {
    main();
} else {
    renderGoogleDataPreview();
}

function renderGoogleDataPreview(): void
{
    $branches = orderBranchesForPreview(getBranchFolders(BASE_DIR));
    $db = db_connect();
    $db->set_charset('utf8mb4');
    $statusByBranch = getGoogleImportStatusByBranch();
    $importUntil = getGoogleReportImportUntilDate();
    $resumeFrom = getGoogleResumeFromDate($statusByBranch);
    $actionUrl = function_exists('cb_url') ? (string)cb_url('/index.php') : '/index.php';
    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
      <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Inicializace reportů Google</h2>
      <p class="card_text txt_seda">Script načte historické denní reporty z Google sešitů a zapíše je do nových tabulek reportů.</p>

      <?php if ($branches === []): ?>
        <p class="card_text txt_seda">Nenalezeny dostupné zdroje ve složce Google dat.</p>
      <?php else: ?>
        <?php foreach ($branches as $branchName => $branchPath): ?>
          <?php
            $idPob = getBranchSortId($branchName);
            $status = $statusByBranch[$idPob] ?? ['count' => 0, 'last' => null];
            $nextImportDate = getNextGoogleReportImportDate($db, $idPob);
            $workbooks = findWorkbooksForBranch($branchPath, $branchName);
          ?>
          <p class="card_text txt_seda text_tucny odstup_vnejsi_0">
            <?= htmlspecialchars($branchName, ENT_QUOTES, 'UTF-8') ?>
            | staženo (<?= number_format((int)$status['count'], 0, ',', ' ') ?> reportů), poslední report:
            <?= $status['last'] !== null ? htmlspecialchars((string)$status['last'], ENT_QUOTES, 'UTF-8') : 'bez importu' ?>
          </p>
          <?php if (!isset(GOOGLE_BRANCH_MAP[$branchName])): ?>
            <p class="card_text txt_seda odstup_vnejsi_0">- složka není součástí importu reportů poboček</p>
          <?php elseif ($workbooks === []): ?>
            <p class="card_text txt_seda odstup_vnejsi_0">- bez dostupných sešitů</p>
          <?php else: ?>
            <?php $shownWorkbook = false; ?>
            <?php foreach ($workbooks as $workbook): ?>
              <?php
                $workbookName = basename($workbook);
                $rok = extractYearFromWorkbookName($workbookName, $branchName);
                if ($rok === null || !isWorkbookInImportRange($rok, $nextImportDate, $importUntil)) {
                    continue;
                }
                $sheets = getRelevantWorkbookSheetsForImport($workbook, $rok, $nextImportDate, $importUntil);
                if ($sheets === []) {
                    continue;
                }
                $shownWorkbook = true;
              ?>
              <p class="card_text txt_seda odstup_vnejsi_0">- <?= htmlspecialchars($workbookName, ENT_QUOTES, 'UTF-8') ?></p>
              <p class="card_text txt_seda odstup_vnejsi_0" style="padding-left:18px;">
                <?= htmlspecialchars(implode(', ', $sheets), ENT_QUOTES, 'UTF-8') ?>
              </p>
            <?php endforeach; ?>
            <?php if (!$shownWorkbook): ?>
              <p class="card_text txt_seda odstup_vnejsi_0">- není co doplnit</p>
            <?php endif; ?>
          <?php endif; ?>
          <div style="height:12px;"></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div style="height:26px;"></div>

      <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
        <p class="card_text txt_seda odstup_vnejsi_0">
          importovat chybějící reporty od <?= htmlspecialchars($resumeFrom, ENT_QUOTES, 'UTF-8') ?> do <?= htmlspecialchars(formatDateForPreview($importUntil), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="odstup_vnejsi_0" data-cb-max-form="1">
          <input type="hidden" name="run_google_data" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex" data-cb-loader-text="Probíhá import ...">Pokračovat v importu</button>
        </form>
      </div>

      <div style="margin-top:16px; text-align:right;">
        <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="odstup_vnejsi_0 displ_inline_flex">
          <input type="hidden" name="back_admin_init" value="1">
          <button type="submit" class="card_btn cursor_ruka ram_btn zaobleni_6 vyska_28 displ_inline_flex" style="background:var(--clr_ruzova_4); border-color:var(--clr_ruzova_1); color:var(--clr_cervena);">Zpět</button>
        </form>
      </div>
    </div>
    <?php
}

function main(): void
{
    $scriptStart = microtime(true);

    if (!is_dir(BASE_DIR)) {
        fail('Nenalezena slozka: ' . BASE_DIR);
    }

    initReportLog();
    initReportImportLog();
    initReportImportMatchLog();
    initReportOpenCloseLog();
    logLine('Import denních reportů Google');
    logLine('cas behu: 00:00');

    $db = db_connect();
    $db->set_charset('utf8mb4');
    $userMap = getUserMap($db);
    $importUntil = getGoogleReportImportUntilDate();

    $pocetZpracovanychDni = 0;
    $pocetUlozenychReportu = 0;
    $pocetUlozenychOsob = 0;
    $pocetChyb = 0;
    $summary = [];

    foreach (orderBranchesForPreview(getBranchFolders(BASE_DIR)) as $branchName => $branchPath) {
        logLine('========================================');
        logLine('pobocka: ' . $branchName);
        logLine('========================================');

        if (!isset(GOOGLE_BRANCH_MAP[$branchName])) {
            logLine('přeskakuji složku bez pobočky v DB: ' . $branchName);
            logLine('');
            continue;
        }

        $idPob = (int)GOOGLE_BRANCH_MAP[$branchName];
        $nextImportDate = getNextGoogleReportImportDate($db, $idPob);
        logLine('import od: ' . ($nextImportDate !== '' ? $nextImportDate : 'zacatek zdroju'));
        logLine('import do: ' . $importUntil);

        if ($nextImportDate !== '' && $nextImportDate > $importUntil) {
            logLine('neni co doplnit');
            logLine('');
            continue;
        }

        foreach (findWorkbooksForBranch($branchPath, $branchName) as $workbook) {
            $workbookName = basename($workbook);
            $year = extractYearFromWorkbookName($workbookName, $branchName);

            if ($year === null) {
                continue;
            }

            if (!isWorkbookInImportRange($year, $nextImportDate, $importUntil)) {
                continue;
            }

            logLine('ctu sesit: ' . $workbookName);
            $sheetTargets = getWorkbookSheetTargetsByCalendar($workbook);
            if ($sheetTargets === []) {
                fail('Nepodařilo se načíst listy sešitu: ' . $workbookName . '. Zkontroluj, jestli soubor není otevřený nebo zamčený.');
            }

            $pocetListuSesitu = 0;
            foreach ($sheetTargets as $sheetName => $sheetPath) {
                if (!isMonthlySheetNameForYear($sheetName, $year)) {
                    continue;
                }
                if (!isSheetInImportRange($sheetName, $nextImportDate, $importUntil)) {
                    continue;
                }

                logLine('- list: ' . $sheetName);
                $pocetListuSesitu++;
                $rows = getSheetRows($workbook, $sheetPath);
                if ($rows === []) {
                    fail('Nepodařilo se načíst řádky listu ' . $sheetName . ' v sešitu: ' . $workbookName . '. Import nesmí potichu přeskočit část historie.');
                }
                $reports = $idPob === 0
                    ? createProductionReportsFromRows($rows, $idPob, $userMap)
                    : createReportsFromRows($rows, $idPob, $userMap);
                $ulozenoMesic = 0;

                foreach ($reports as $report) {
                    $datum = (string)$report['datum_reportu'];
                    if (!isDateInImportRange($datum, $nextImportDate, $importUntil)) {
                        continue;
                    }

                    $pocetZpracovanychDni++;
                    try {
                        $result = saveReport($db, $report);
                        $pocetUlozenychReportu++;
                        $pocetUlozenychOsob += (int)$result['persons'];
                        $ulozenoMesic++;
                        addImportSummary($summary, $branchName, $year);
                    } catch (Throwable $e) {
                        $pocetChyb++;
                        logLine('DB chyba ' . $branchName . ' ' . $datum . ': ' . $e->getMessage());
                    }
                }

                if ($ulozenoMesic > 0) {
                    reportLogLine($branchName . ' ' . (string)$year . ' ' . $sheetName . ': ' . (string)$ulozenoMesic);
                }
            }

            if ($pocetListuSesitu === 0) {
                fail('Sešit je v rozsahu importu, ale nemá žádný měsíční list k importu: ' . $workbookName);
            }

            logLine('cas behu: ' . formatElapsedRuntime($scriptStart));
        }

        logLine('');
    }

    logLine('zpracovane dny: ' . $pocetZpracovanychDni);
    logLine('ulozene reporty: ' . $pocetUlozenychReportu);
    logLine('ulozene osoby: ' . $pocetUlozenychOsob);
    logLine('pocet chyb: ' . $pocetChyb);
    logLine('celkova doba behu: ' . formatElapsedRuntime($scriptStart));
    renderImportSummary($summary);
    renderImportFinishedInfo($db);
    renderBackButton();
}

function getGoogleImportStatusByBranch(): array
{
    $map = [];
    foreach (GOOGLE_BRANCH_MAP as $idPob) {
        $map[(int)$idPob] = ['count' => 0, 'last' => null];
    }

    $db = db_connect();
    $result = $db->query('
        SELECT id_pob, COUNT(*) AS cnt, MAX(datum_reportu) AS last_datum
        FROM reporty
        WHERE zdroj = ' . GOOGLE_SOURCE . ' AND platny = 1
        GROUP BY id_pob
    ');

    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            $lastRaw = trim((string)($row['last_datum'] ?? ''));
            $last = null;
            if ($lastRaw !== '') {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d', $lastRaw);
                $last = $dt instanceof DateTimeImmutable ? $dt->format('d.m.Y') : $lastRaw;
            }
            $map[$idPob] = [
                'count' => (int)($row['cnt'] ?? 0),
                'last' => $last,
            ];
        }
        $result->free();
    }

    return $map;
}

function getGoogleResumeFromDate(array $statusByBranch): string
{
    $lastMax = '';
    foreach ($statusByBranch as $status) {
        $last = trim((string)($status['last'] ?? ''));
        $dt = DateTimeImmutable::createFromFormat('d.m.Y', $last);
        if (!($dt instanceof DateTimeImmutable)) {
            continue;
        }
        $ymd = $dt->format('Y-m-d');
        if ($ymd > $lastMax) {
            $lastMax = $ymd;
        }
    }

    if ($lastMax === '') {
        return 'od začátku zdrojů';
    }

    $next = DateTimeImmutable::createFromFormat('Y-m-d', $lastMax);
    return $next instanceof DateTimeImmutable ? $next->modify('+1 day')->format('d.m.Y') : 'od začátku zdrojů';
}

function getGoogleReportImportUntilDate(): string
{
    return (new DateTimeImmutable('yesterday'))->format('Y-m-d');
}

function formatDateForPreview(string $date): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTimeImmutable ? $dt->format('d.m.Y') : $date;
}

function getNextGoogleReportImportDate(mysqli $db, int $idPob): string
{
    $stmt = $db->prepare('
        SELECT MAX(datum_reportu) AS last_datum
        FROM reporty
        WHERE zdroj = ? AND id_pob = ? AND platny = 1
    ');
    if ($stmt === false) {
        return '';
    }

    $source = GOOGLE_SOURCE;
    $stmt->bind_param('ii', $source, $idPob);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result instanceof mysqli_result) ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    $last = trim((string)($row['last_datum'] ?? ''));
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $last);
    return $dt instanceof DateTimeImmutable ? $dt->modify('+1 day')->format('Y-m-d') : '';
}

function createReportsFromRows(array $rows, int $idPob, array $userMap): array
{
    $reports = [];

    foreach ($rows as $rowNo => $row) {
        if ($rowNo < 2) {
            continue;
        }

        $datum = normalizeDate((string)($row['A'] ?? ''));
        if ($datum === null) {
            continue;
        }

        if (!isset($reports[$datum])) {
            $reports[$datum] = emptyReport($datum, $idPob);
        }

        $instor = createPersonRecord($row, 'instor', $userMap, $datum, $idPob);
        if ($instor !== null) {
            $reports[$datum]['persons'][] = $instor;
            applyOpenCloseCandidates($reports[$datum], $instor);
        }

        $kuryr = createPersonRecord($row, 'kuryr', $userMap, $datum, $idPob);
        if ($kuryr !== null) {
            $reports[$datum]['persons'][] = $kuryr;
        }

        if (isSummaryRow($row)) {
            applySummaryRow($reports[$datum], $row, $userMap);
        }
    }

    ksort($reports);
    foreach ($reports as &$report) {
        applyFinalOpenCloseFallback($report);
    }
    unset($report);

    return array_values($reports);
}

function createProductionReportsFromRows(array $rows, int $idPob, array $userMap): array
{
    $reports = [];

    foreach ($rows as $rowNo => $row) {
        if ($rowNo < 2) {
            continue;
        }

        $datum = normalizeDate((string)($row['A'] ?? ''));
        if ($datum === null) {
            continue;
        }

        $person = createProductionPersonRecord($row, $userMap, $datum, $idPob);
        if ($person === null) {
            continue;
        }

        if (!isset($reports[$datum])) {
            $reports[$datum] = emptyReport($datum, $idPob);
        }
        $reports[$datum]['persons'][] = $person;
    }

    ksort($reports);
    return array_values($reports);
}

function createProductionPersonRecord(array $row, array $userMap, string $datum, int $idPob): ?array
{
    $fullName = (string)($row['C'] ?? '');
    $start = normalizeTime((string)($row['D'] ?? ''));
    $end = normalizeTime((string)($row['E'] ?? ''));
    $pause = normalizeHoursDecimal($row['F'] ?? '');
    $worked = normalizeHoursDecimal($row['G'] ?? '');

    if ($fullName === '' || ($start === null && $end === null && $worked === null)) {
        return null;
    }

    if ($worked === null) {
        $worked = computeWorkedHours($start, $end, $pause);
    }
    if ($pause === null) {
        $pause = '0.00';
    }
    if ($worked === null) {
        $worked = '0.00';
    }

    $resolved = resolveUserName($fullName, $userMap, $idPob);
    logMissingReportUser($datum, $idPob, 'vyroba', $resolved);
    logReportUserMatch($datum, $idPob, 'vyroba', $resolved);

    return [
        'id_user' => $resolved['id_user'],
        'jmeno' => $resolved['jmeno'],
        'prijmeni' => $resolved['prijmeni'],
        'full_name' => $resolved['full_name'],
        'match_code' => $resolved['match_code'] ?? '',
        'user_full_name' => $resolved['user_full_name'] ?? '',
        'slot' => 'vyroba',
        'smena_od' => $start,
        'smena_do' => $end,
        'pauza' => $pause,
        'odpracovano' => $worked,
        'rozvozu_restia' => 0,
        'rozvozu_manual' => 0,
        'rozvozu_celkem' => 0,
        'vlastni_vuz' => 0,
        'vyplatit_phm' => '0.00',
    ];
}

function emptyReport(string $datum, int $idPob): array
{
    return [
        'datum_reportu' => $datum,
        'id_pob' => $idPob,
        'oteviral' => null,
        'zaviral' => null,
        'oteviral_text' => null,
        'zaviral_text' => null,
        'oteviral_candidate' => null,
        'zaviral_candidate' => null,
        'oteviral_raw_empty' => false,
        'zaviral_raw_empty' => false,
        'pokladna' => [
            'hotovost' => '0.00',
            'terminal' => '0.00',
            'stravenky' => '0.00',
            'rozdil' => '0.00',
            'vydaje_benzin' => '0.00',
            'vydaje_auta' => '0.00',
            'vydaje_suroviny' => '0.00',
            'vydaje_ostatni' => '0.00',
            'vydaje_phm_soukrome' => '0.00',
            'vydaje_doklady_ks' => 0,
        ],
        'restia' => [
            'trzba' => '0.00',
            'wolt' => '0.00',
            'bolt' => '0.00',
            'damejidlo' => '0.00',
            'web' => '0.00',
            'wolt_cash' => '0.00',
            'dj_cash' => '0.00',
            'col_pomer' => null,
            'zrusene_obj_ks' => 0,
            'zrusene_obj_kc' => '0.00',
            'zpozdene_rozvozy_5_min' => 0,
            'make_time_prumer_sec' => null,
            'objednavky_nezrusene_ks' => 0,
            'nase_rozvozy_ks' => 0,
            'woltdrive_ks' => 0,
            'woltdrive_pozde_5_min' => 0,
            'woltdrive_pozde_nase_vina' => 0,
            'nase_rozvozy_pozde_pomer' => null,
            'woltdrive_zpozdene_ks' => 0,
            'doruceno_vcas_pomer' => null,
            'woltdrive_zpozdene_pomer' => null,
        ],
        'persons' => [],
    ];
}

function applyOpenCloseCandidates(array &$report, array $person): void
{
    if ((string)($person['slot'] ?? '') !== 'instor') {
        return;
    }

    if (($person['smena_od'] ?? null) === '10:00:00' && $report['oteviral_candidate'] === null) {
        $report['oteviral_candidate'] = $person;
    }

    $datum = (string)($report['datum_reportu'] ?? '');
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $datum);
    if (!($dt instanceof DateTimeImmutable)) {
        return;
    }

    $dayOfWeek = (int)$dt->format('N');
    $expectedEnd = null;
    if ($dayOfWeek >= 1 && $dayOfWeek <= 4) {
        $expectedEnd = '01:00:00';
    } elseif ($dayOfWeek === 5 || $dayOfWeek === 6) {
        $expectedEnd = '03:00:00';
    } elseif ($dayOfWeek === 7) {
        $expectedEnd = '00:00:00';
    }

    if ($expectedEnd !== null && ($person['smena_do'] ?? null) === $expectedEnd && $report['zaviral_candidate'] === null) {
        $report['zaviral_candidate'] = $person;
    }
}

function isSummaryRow(array $row): bool
{
    foreach (['P','Q','R','S','T','U','V','W','X','Y','Z','AA','AB','AC','AD','AE','AF','AG','AH','AI','AJ','AK','AL','AM','AN','AO','AP','AQ','AR','AS','AT','AU','AV'] as $col) {
        if (trim((string)($row[$col] ?? '')) !== '') {
            return true;
        }
    }
    return false;
}

function applySummaryRow(array &$report, array $row, array $userMap): void
{
    $idPob = (int)($report['id_pob'] ?? 0);
    $datum = (string)($report['datum_reportu'] ?? '');

    $oteviralRaw = (string)($row['Q'] ?? '');
    $zaviralRaw = (string)($row['R'] ?? '');

    $report['oteviral_raw_empty'] = $oteviralRaw === '';
    $report['zaviral_raw_empty'] = $zaviralRaw === '';

    if ($oteviralRaw !== '') {
        $oteviral = resolveUserName($oteviralRaw, $userMap, $idPob);
        $report['oteviral'] = $oteviral['id_user'];
        $report['oteviral_text'] = $oteviral['full_name'];
        logMissingReportUser($datum, $idPob, 'oteviral', $oteviral);
        logReportUserMatch($datum, $idPob, 'oteviral', $oteviral);
    } else {
        $report['oteviral'] = null;
        $report['oteviral_text'] = null;
    }

    if ($zaviralRaw !== '') {
        $zaviral = resolveUserName($zaviralRaw, $userMap, $idPob);
        $report['zaviral'] = $zaviral['id_user'];
        $report['zaviral_text'] = $zaviral['full_name'];
        logMissingReportUser($datum, $idPob, 'zaviral', $zaviral);
        logReportUserMatch($datum, $idPob, 'zaviral', $zaviral);
    } else {
        $report['zaviral'] = null;
        $report['zaviral_text'] = null;
    }

    $report['pokladna'] = [
        'hotovost' => normalizeMoney($row['AA'] ?? ''),
        'terminal' => normalizeMoney($row['Y'] ?? ''),
        'stravenky' => normalizeMoney($row['Z'] ?? ''),
        'rozdil' => normalizeMoney($row['AC'] ?? ''),
        'vydaje_benzin' => normalizeMoney($row['AE'] ?? ''),
        'vydaje_auta' => normalizeMoney($row['AF'] ?? ''),
        'vydaje_suroviny' => normalizeMoney($row['AG'] ?? ''),
        'vydaje_ostatni' => normalizeMoney($row['AH'] ?? ''),
        'vydaje_phm_soukrome' => normalizeMoney($row['AI'] ?? ''),
        'vydaje_doklady_ks' => normalizeInt($row['AN'] ?? ''),
    ];

    $report['restia'] = [
        'trzba' => normalizeMoney($row['AB'] ?? ''),
        'wolt' => normalizeMoney($row['S'] ?? ''),
        'bolt' => normalizeMoney($row['T'] ?? ''),
        'damejidlo' => normalizeMoney($row['U'] ?? ''),
        'web' => normalizeMoney($row['V'] ?? ''),
        'wolt_cash' => normalizeMoney($row['W'] ?? ''),
        'dj_cash' => normalizeMoney($row['X'] ?? ''),
        'col_pomer' => normalizeNullableDecimal($row['AD'] ?? ''),
        'zrusene_obj_ks' => normalizeInt($row['AJ'] ?? ''),
        'zrusene_obj_kc' => normalizeMoney($row['AK'] ?? ''),
        'zpozdene_rozvozy_5_min' => normalizeInt($row['AL'] ?? ''),
        'make_time_prumer_sec' => normalizeMakeTimeSeconds($row['AM'] ?? ''),
        'objednavky_nezrusene_ks' => normalizeInt($row['AO'] ?? ''),
        'nase_rozvozy_ks' => normalizeInt($row['AP'] ?? ''),
        'woltdrive_ks' => normalizeInt($row['P'] ?? ''),
        'woltdrive_pozde_5_min' => normalizeInt($row['AQ'] ?? ''),
        'woltdrive_pozde_nase_vina' => normalizeInt($row['AR'] ?? ''),
        'nase_rozvozy_pozde_pomer' => normalizeNullableDecimal($row['AS'] ?? ''),
        'woltdrive_zpozdene_ks' => normalizeInt($row['AT'] ?? ''),
        'doruceno_vcas_pomer' => normalizeNullableDecimal($row['AU'] ?? ''),
        'woltdrive_zpozdene_pomer' => normalizeNullableDecimal($row['AV'] ?? ''),
    ];
}

function applyFinalOpenCloseFallback(array &$report): void
{
    $datum = (string)($report['datum_reportu'] ?? '');
    $idPob = (int)($report['id_pob'] ?? 0);

    if (($report['oteviral_text'] ?? null) === null || (string)($report['oteviral_text'] ?? '') === '') {
        $selected = selectOpenPerson($report);
        $oteviral = personCandidateToResolved($selected['person']);
        $report['oteviral'] = $oteviral['id_user'];
        $report['oteviral_text'] = $oteviral['full_name'];
        logMissingReportUser($datum, $idPob, 'oteviral', $oteviral);
        logReportUserMatch($datum, $idPob, 'oteviral', $oteviral);
        logOpenCloseFallback($datum, $idPob, 'oteviral', $selected['reason'], $oteviral, $selected['person']);
    }

    if (($report['zaviral_text'] ?? null) === null || (string)($report['zaviral_text'] ?? '') === '') {
        $selected = selectClosePerson($report);
        $zaviral = personCandidateToResolved($selected['person']);
        $report['zaviral'] = $zaviral['id_user'];
        $report['zaviral_text'] = $zaviral['full_name'];
        logMissingReportUser($datum, $idPob, 'zaviral', $zaviral);
        logReportUserMatch($datum, $idPob, 'zaviral', $zaviral);
        logOpenCloseFallback($datum, $idPob, 'zaviral', $selected['reason'], $zaviral, $selected['person']);
    }
}

function selectOpenPerson(array $report): array
{
    if (is_array($report['oteviral_candidate'] ?? null)) {
        return ['person' => $report['oteviral_candidate'], 'reason' => 'instor od 10:00'];
    }

    $selected = null;
    $selectedMinutes = null;
    foreach ((array)($report['persons'] ?? []) as $person) {
        if ((string)($person['slot'] ?? '') !== 'instor') {
            continue;
        }
        $minutes = parseNullableTimeToMinutes($person['smena_od'] ?? null, false);
        if ($minutes === null) {
            continue;
        }
        if ($selected === null || $minutes < $selectedMinutes) {
            $selected = $person;
            $selectedMinutes = $minutes;
        }
    }

    return [
        'person' => $selected,
        'reason' => $selected === null ? 'nenalezen žádný instor se začátkem směny' : 'nejdřívější začátek instora',
    ];
}

function selectClosePerson(array $report): array
{
    if (is_array($report['zaviral_candidate'] ?? null)) {
        return ['person' => $report['zaviral_candidate'], 'reason' => 'instor s očekávaným koncem'];
    }

    $selected = null;
    $selectedMinutes = null;
    foreach ((array)($report['persons'] ?? []) as $person) {
        if ((string)($person['slot'] ?? '') !== 'instor') {
            continue;
        }
        $minutes = parseNullableTimeToMinutes($person['smena_do'] ?? null, true);
        if ($minutes === null) {
            continue;
        }
        if ($selected === null || $minutes > $selectedMinutes) {
            $selected = $person;
            $selectedMinutes = $minutes;
        }
    }

    return [
        'person' => $selected,
        'reason' => $selected === null ? 'nenalezen žádný instor s koncem směny' : 'nejpozdější konec instora',
    ];
}

function parseNullableTimeToMinutes(mixed $time, bool $overnight): ?int
{
    if (!is_string($time) || $time === '') {
        return null;
    }

    $minutes = parseTimeToMinutes($time);
    if ($minutes === null) {
        return null;
    }

    return $overnight && $minutes < 6 * 60 ? $minutes + 24 * 60 : $minutes;
}

function personCandidateToResolved(mixed $candidate): array
{
    if (!is_array($candidate)) {
        return ['id_user' => null, 'jmeno' => null, 'prijmeni' => null, 'full_name' => null];
    }

    $jmeno = $candidate['jmeno'] ?? null;
    $prijmeni = $candidate['prijmeni'] ?? null;
    $fullName = $candidate['full_name'] ?? null;

    return [
        'id_user' => $candidate['id_user'] ?? null,
        'jmeno' => $jmeno,
        'prijmeni' => $prijmeni,
        'full_name' => $fullName,
        'match_code' => $candidate['match_code'] ?? '',
        'user_full_name' => $candidate['user_full_name'] ?? '',
    ];
}

function createPersonRecord(array $row, string $slot, array $userMap, string $datum, int $idPob): ?array
{
    if ($slot === 'instor') {
        $fullName = (string)($row['C'] ?? '');
        $start = normalizeTime((string)($row['D'] ?? ''));
        $end = normalizeTime((string)($row['E'] ?? ''));
        $pause = normalizeHoursDecimal($row['F'] ?? '');
        $worked = normalizeHoursDecimal($row['G'] ?? '');
        $deliveries = 0;
        $ownCar = 0;
        $phm = '0.00';
    } else {
        $fullName = (string)($row['H'] ?? '');
        $start = normalizeTime((string)($row['I'] ?? ''));
        $end = normalizeTime((string)($row['J'] ?? ''));
        $pause = normalizeHoursDecimal($row['K'] ?? '');
        $worked = normalizeHoursDecimal($row['L'] ?? '');
        $deliveries = normalizeInt($row['M'] ?? '');
        $ownCar = normalizeBool($row['N'] ?? '');
        $phm = normalizeMoney($row['O'] ?? '');
    }

    if ($fullName === '' || ($start === null && $end === null)) {
        return null;
    }

    if ($worked === null) {
        $worked = computeWorkedHours($start, $end, $pause);
    }
    if ($pause === null) {
        $pause = '0.00';
    }
    if ($worked === null) {
        $worked = '0.00';
    }

    $resolved = resolveUserName($fullName, $userMap, $idPob);
    logMissingReportUser($datum, $idPob, $slot, $resolved);
    logReportUserMatch($datum, $idPob, $slot, $resolved);

    return [
        'id_user' => $resolved['id_user'],
        'jmeno' => $resolved['jmeno'],
        'prijmeni' => $resolved['prijmeni'],
        'full_name' => $resolved['full_name'],
        'match_code' => $resolved['match_code'] ?? '',
        'user_full_name' => $resolved['user_full_name'] ?? '',
        'slot' => $slot,
        'smena_od' => $start,
        'smena_do' => $end,
        'pauza' => $pause,
        'odpracovano' => $worked,
        'rozvozu_restia' => $slot === 'kuryr' ? $deliveries : 0,
        'rozvozu_manual' => 0,
        'rozvozu_celkem' => $slot === 'kuryr' ? $deliveries : 0,
        'vlastni_vuz' => $slot === 'kuryr' ? $ownCar : 0,
        'vyplatit_phm' => $slot === 'kuryr' ? $phm : '0.00',
    ];
}

function saveReport(mysqli $db, array $report): array
{
    $db->begin_transaction();
    try {
        $idReportu = upsertReportHeader($db, $report);
        deleteReportChildren($db, $idReportu);
        insertReportPokladna($db, $idReportu, (array)$report['pokladna']);
        insertReportRestia($db, $idReportu, (array)$report['restia']);

        $persons = 0;
        foreach ((array)$report['persons'] as $person) {
            insertReportPerson($db, $idReportu, (array)$person);
            $persons++;
        }

        $db->commit();
        return ['id_reportu' => $idReportu, 'persons' => $persons];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

function upsertReportHeader(mysqli $db, array $report): int
{
    $sql = '
        INSERT INTO reporty (
            datum_reportu, id_pob, oteviral, zaviral, oteviral_text, zaviral_text, zdroj, stav, platny
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)
        ON DUPLICATE KEY UPDATE
            oteviral = VALUES(oteviral),
            zaviral = VALUES(zaviral),
            oteviral_text = VALUES(oteviral_text),
            zaviral_text = VALUES(zaviral_text),
            zdroj = VALUES(zdroj),
            stav = 1,
            platny = 1
    ';
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Prepare reporty selhal.');
    }

    $datum = (string)$report['datum_reportu'];
    $idPob = (int)$report['id_pob'];
    $oteviral = $report['oteviral'];
    $zaviral = $report['zaviral'];
    $oteviralText = $report['oteviral_text'];
    $zaviralText = $report['zaviral_text'];
    $source = GOOGLE_SOURCE;

    $stmt->bind_param('siiissi', $datum, $idPob, $oteviral, $zaviral, $oteviralText, $zaviralText, $source);
    $stmt->execute();
    $stmt->close();

    $stmt = $db->prepare('SELECT id_reportu FROM reporty WHERE id_pob = ? AND datum_reportu = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('Prepare select reporty selhal.');
    }
    $stmt->bind_param('is', $idPob, $datum);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = ($result instanceof mysqli_result) ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    $idReportu = (int)($row['id_reportu'] ?? 0);
    if ($idReportu <= 0) {
        throw new RuntimeException('Nepodařilo se najít uložený report.');
    }

    return $idReportu;
}

function deleteReportChildren(mysqli $db, int $idReportu): void
{
    foreach (['reporty_osoby', 'reporty_pokladna', 'reporty_restia'] as $table) {
        $stmt = $db->prepare('DELETE FROM `' . $table . '` WHERE id_reportu = ?');
        if ($stmt === false) {
            throw new RuntimeException('Prepare delete ' . $table . ' selhal.');
        }
        $stmt->bind_param('i', $idReportu);
        $stmt->execute();
        $stmt->close();
    }
}

function insertReportPokladna(mysqli $db, int $idReportu, array $p): void
{
    $stmt = $db->prepare('
        INSERT INTO reporty_pokladna (
            id_reportu, hotovost, terminal, stravenky, rozdil, vydaje_benzin, vydaje_auta,
            vydaje_suroviny, vydaje_ostatni, vydaje_phm_soukrome, vydaje_doklady_ks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    if ($stmt === false) {
        throw new RuntimeException('Prepare reporty_pokladna selhal.');
    }
    $hotovost = (string)$p['hotovost'];
    $terminal = (string)$p['terminal'];
    $stravenky = (string)$p['stravenky'];
    $rozdil = (string)$p['rozdil'];
    $vydajeBenzin = (string)$p['vydaje_benzin'];
    $vydajeAuta = (string)$p['vydaje_auta'];
    $vydajeSuroviny = (string)$p['vydaje_suroviny'];
    $vydajeOstatni = (string)$p['vydaje_ostatni'];
    $vydajePhmSoukrome = (string)$p['vydaje_phm_soukrome'];
    $vydajeDokladyKs = (int)$p['vydaje_doklady_ks'];
    $stmt->bind_param(
        'idddddddddi',
        $idReportu,
        $hotovost,
        $terminal,
        $stravenky,
        $rozdil,
        $vydajeBenzin,
        $vydajeAuta,
        $vydajeSuroviny,
        $vydajeOstatni,
        $vydajePhmSoukrome,
        $vydajeDokladyKs
    );
    $stmt->execute();
    $stmt->close();
}

function insertReportRestia(mysqli $db, int $idReportu, array $r): void
{
    $stmt = $db->prepare('
        INSERT INTO reporty_restia (
            id_reportu, trzba, wolt, bolt, damejidlo, web, wolt_cash, dj_cash, col_pomer,
            zrusene_obj_ks, zrusene_obj_kc, zpozdene_rozvozy_5_min, make_time_prumer_sec,
            objednavky_nezrusene_ks, nase_rozvozy_ks, woltdrive_ks, woltdrive_pozde_5_min,
            woltdrive_pozde_nase_vina, nase_rozvozy_pozde_pomer, woltdrive_zpozdene_ks,
            doruceno_vcas_pomer, woltdrive_zpozdene_pomer
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    if ($stmt === false) {
        throw new RuntimeException('Prepare reporty_restia selhal.');
    }
    $trzba = (string)$r['trzba'];
    $wolt = (string)$r['wolt'];
    $bolt = (string)$r['bolt'];
    $damejidlo = (string)$r['damejidlo'];
    $web = (string)$r['web'];
    $woltCash = (string)$r['wolt_cash'];
    $djCash = (string)$r['dj_cash'];
    $colPomer = $r['col_pomer'];
    $zruseneObjKs = (int)$r['zrusene_obj_ks'];
    $zruseneObjKc = (string)$r['zrusene_obj_kc'];
    $zpozdeneRozvozy5Min = (int)$r['zpozdene_rozvozy_5_min'];
    $makeTimePrumerSec = $r['make_time_prumer_sec'];
    $objednavkyNezruseneKs = (int)$r['objednavky_nezrusene_ks'];
    $naseRozvozyKs = (int)$r['nase_rozvozy_ks'];
    $woltdriveKs = (int)$r['woltdrive_ks'];
    $woltdrivePozde5Min = (int)$r['woltdrive_pozde_5_min'];
    $woltdrivePozdeNaseVina = (int)$r['woltdrive_pozde_nase_vina'];
    $naseRozvozyPozdePomer = $r['nase_rozvozy_pozde_pomer'];
    $woltdriveZpozdeneKs = (int)$r['woltdrive_zpozdene_ks'];
    $dorucenoVcasPomer = $r['doruceno_vcas_pomer'];
    $woltdriveZpozdenePomer = $r['woltdrive_zpozdene_pomer'];
    $stmt->bind_param(
        'iddddddddidiidiiiididd',
        $idReportu,
        $trzba,
        $wolt,
        $bolt,
        $damejidlo,
        $web,
        $woltCash,
        $djCash,
        $colPomer,
        $zruseneObjKs,
        $zruseneObjKc,
        $zpozdeneRozvozy5Min,
        $makeTimePrumerSec,
        $objednavkyNezruseneKs,
        $naseRozvozyKs,
        $woltdriveKs,
        $woltdrivePozde5Min,
        $woltdrivePozdeNaseVina,
        $naseRozvozyPozdePomer,
        $woltdriveZpozdeneKs,
        $dorucenoVcasPomer,
        $woltdriveZpozdenePomer
    );
    $stmt->execute();
    $stmt->close();
}

function insertReportPerson(mysqli $db, int $idReportu, array $p): void
{
    $stmt = $db->prepare('
        INSERT INTO reporty_osoby (
            id_reportu, id_user, jmeno, prijmeni, slot, smena_od, smena_do, pauza, odpracovano,
            rozvozu_restia, rozvozu_manual, rozvozu_celkem, vlastni_vuz, vyplatit_phm
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    if ($stmt === false) {
        throw new RuntimeException('Prepare reporty_osoby selhal.');
    }
    $idUser = $p['id_user'];
    $jmeno = $p['jmeno'];
    $prijmeni = $p['prijmeni'];
    $slot = reportPersonSlotId((string)$p['slot']);
    $smenaOd = $p['smena_od'];
    $smenaDo = $p['smena_do'];
    $pauza = (string)$p['pauza'];
    $odpracovano = (string)$p['odpracovano'];
    $rozvozuRestia = (int)$p['rozvozu_restia'];
    $rozvozuManual = (int)$p['rozvozu_manual'];
    $rozvozuCelkem = (int)$p['rozvozu_celkem'];
    $vlastniVuz = (int)$p['vlastni_vuz'];
    $vyplatitPhm = (string)$p['vyplatit_phm'];
    $stmt->bind_param(
        'iississddiiiid',
        $idReportu,
        $idUser,
        $jmeno,
        $prijmeni,
        $slot,
        $smenaOd,
        $smenaDo,
        $pauza,
        $odpracovano,
        $rozvozuRestia,
        $rozvozuManual,
        $rozvozuCelkem,
        $vlastniVuz,
        $vyplatitPhm
    );
    $stmt->execute();
    $stmt->close();
}

function reportPersonSlotId(string $slot): int
{
    return match ($slot) {
        'instor' => 1,
        'kuryr' => 2,
        'vyroba' => 3,
        default => 0,
    };
}

function getUserMap(mysqli $db): array
{
    $usersById = [];
    $result = $db->query('
        SELECT u.id_user, u.jmeno, u.prijmeni, u.aktivni, up.id_pob
        FROM `user` u
        LEFT JOIN user_pobocka up ON up.id_user = u.id_user
        ORDER BY u.id_user
    ');
    if (!$result instanceof mysqli_result) {
        return [];
    }

    while ($row = $result->fetch_assoc()) {
        $idUser = (int)($row['id_user'] ?? 0);
        $jmeno = (string)($row['jmeno'] ?? '');
        $prijmeni = (string)($row['prijmeni'] ?? '');
        $idPob = (int)($row['id_pob'] ?? 0);
        if ($idUser <= 0 || $jmeno === '' || $prijmeni === '') {
            continue;
        }

        if (!isset($usersById[$idUser])) {
            $usersById[$idUser] = [
                'id_user' => $idUser,
                'jmeno' => $jmeno,
                'prijmeni' => $prijmeni,
                'aktivni' => (int)($row['aktivni'] ?? 0),
                'pobocky' => [],
            ];
        }
        if ($idPob > 0) {
            $usersById[$idUser]['pobocky'][$idPob] = true;
        }
    }
    $result->free();

    $map = [
        'exact' => [],
        'plain' => [],
        'surname' => [],
        'surname_plain' => [],
    ];

    foreach ($usersById as $user) {
        $fullPrijmeniJmeno = (string)$user['prijmeni'] . ' ' . (string)$user['jmeno'];
        addUserMapCandidate($map['exact'], $fullPrijmeniJmeno, $user);
        addUserMapCandidate($map['plain'], plainNameKey($fullPrijmeniJmeno), $user);
        addUserMapCandidate($map['surname'], (string)$user['prijmeni'], $user);
        addUserMapCandidate($map['surname_plain'], plainNameKey((string)$user['prijmeni']), $user);
    }

    return $map;
}

function addUserMapCandidate(array &$index, string $key, array $user): void
{
    if ($key === '') {
        return;
    }
    if (!isset($index[$key])) {
        $index[$key] = [];
    }
    $index[$key][(int)$user['id_user']] = $user;
}

function resolveUserName(string $fullName, array $userMap, int $idPob): array
{
    if ($fullName === '') {
        return ['id_user' => null, 'jmeno' => null, 'prijmeni' => null, 'full_name' => null];
    }

    $attempts = buildUserResolveAttempts($fullName);
    $alias = reportUserNameAlias($fullName);
    if ($alias !== null) {
        $attempts = array_merge($attempts, buildUserResolveAttempts($alias));
    }

    foreach ($attempts as $attempt) {
        $index = $userMap[(string)$attempt['index']] ?? [];
        $matches = $index[(string)$attempt['key']] ?? [];
        $selected = selectUserCandidate(array_values($matches), $idPob, (string)$attempt['code']);
        if ($selected !== null) {
            return resolvedUserFromCandidate($fullName, $selected['user'], (string)$selected['code']);
        }
    }

    $split = splitNameFallback($fullName);
    return [
        'id_user' => null,
        'jmeno' => $split['jmeno'],
        'prijmeni' => $split['prijmeni'],
        'full_name' => $fullName,
    ];
}

function reportUserNameAlias(string $fullName): ?string
{
    static $aliases = [
        'Čonka Ludovít' => 'Čonka Ludovíd',
        'Chyský David' => 'Chyský žl David',
        'Hofmanová Jana' => 'Hofmanová žl Jana',
    ];

    return $aliases[$fullName] ?? null;
}

function buildUserResolveAttempts(string $fullName): array
{
    $attempts = [
        ['index' => 'exact', 'key' => $fullName, 'code' => ''],
        ['index' => 'plain', 'key' => plainNameKey($fullName), 'code' => '2'],
    ];

    $swapped = swapFirstLastName($fullName);
    if ($swapped !== null) {
        $attempts[] = ['index' => 'exact', 'key' => $swapped, 'code' => '3'];
        $attempts[] = ['index' => 'plain', 'key' => plainNameKey($swapped), 'code' => '4'];
    }

    foreach (surnameCandidatesFromReportName($fullName) as $surname) {
        $attempts[] = ['index' => 'surname', 'key' => $surname, 'code' => '5'];
        $attempts[] = ['index' => 'surname_plain', 'key' => plainNameKey($surname), 'code' => '6'];
    }

    $out = [];
    $seen = [];
    foreach ($attempts as $attempt) {
        $key = (string)$attempt['index'] . "\t" . (string)$attempt['key'] . "\t" . (string)$attempt['code'];
        if ((string)$attempt['key'] === '' || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $attempt;
    }
    return $out;
}

function selectUserCandidate(array $matches, int $idPob, string $baseCode): ?array
{
    if (count($matches) === 0) {
        return null;
    }

    if (count($matches) === 1) {
        return ['user' => $matches[0], 'code' => $baseCode];
    }

    $active = [];
    foreach ($matches as $m) {
        if ((int)($m['aktivni'] ?? 0) === 1) {
            $active[] = $m;
        }
    }
    if (count($active) === 1) {
        return ['user' => $active[0], 'code' => $baseCode === '' ? '1' : $baseCode . 'A'];
    }

    $pool = count($active) > 0 ? $active : $matches;
    if ($idPob > 0) {
        $branchMatches = [];
        foreach ($pool as $m) {
            $pobocky = $m['pobocky'] ?? [];
            if (is_array($pobocky) && isset($pobocky[$idPob])) {
                $branchMatches[] = $m;
            }
        }
        if (count($branchMatches) === 1) {
            return ['user' => $branchMatches[0], 'code' => ($baseCode === '' ? 'P' : $baseCode . 'P')];
        }
    }

    return null;
}

function resolvedUserFromCandidate(string $fullName, array $user, string $matchCode): array
{
    return [
        'id_user' => (int)$user['id_user'],
        'jmeno' => (string)$user['jmeno'],
        'prijmeni' => (string)$user['prijmeni'],
        'full_name' => $fullName,
        'match_code' => $matchCode,
        'user_full_name' => (string)$user['jmeno'] . ' ' . (string)$user['prijmeni'],
    ];
}

function swapFirstLastName(string $fullName): ?string
{
    $parts = preg_split('/\s+/u', $fullName) ?: [];
    if (count($parts) < 2) {
        return null;
    }

    $last = (string)array_pop($parts);
    return $last . ' ' . implode(' ', $parts);
}

function surnameCandidatesFromReportName(string $fullName): array
{
    $parts = preg_split('/\s+/u', $fullName) ?: [];
    if (count($parts) < 1) {
        return [];
    }

    $candidates = [];
    $first = (string)reset($parts);
    $last = (string)end($parts);
    if ($first !== '') {
        $candidates[$first] = true;
    }
    if ($last !== '') {
        $candidates[$last] = true;
    }
    return array_keys($candidates);
}

function splitNameFallback(string $fullName): array
{
    $parts = preg_split('/\s+/u', trim($fullName)) ?: [];
    if (count($parts) <= 1) {
        return ['jmeno' => null, 'prijmeni' => mb_substr($fullName, 0, 50, 'UTF-8')];
    }

    $prijmeni = (string)array_shift($parts);
    $jmeno = trim(implode(' ', $parts));

    return [
        'jmeno' => mb_substr($jmeno, 0, 50, 'UTF-8'),
        'prijmeni' => mb_substr($prijmeni, 0, 50, 'UTF-8'),
    ];
}

function plainNameKey(string $value): string
{
    $value = mb_strtolower($value, 'UTF-8');
    $value = strtr($value, [
        'á' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e', 'í' => 'i',
        'ň' => 'n', 'ó' => 'o', 'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u',
        'ů' => 'u', 'ý' => 'y', 'ž' => 'z',
        'Á' => 'a', 'Č' => 'c', 'Ď' => 'd', 'É' => 'e', 'Ě' => 'e', 'Í' => 'i',
        'Ň' => 'n', 'Ó' => 'o', 'Ř' => 'r', 'Š' => 's', 'Ť' => 't', 'Ú' => 'u',
        'Ů' => 'u', 'Ý' => 'y', 'Ž' => 'z',
        'ä' => 'a', 'ö' => 'o', 'ő' => 'o', 'ü' => 'u', 'ű' => 'u',
        'Ä' => 'a', 'Ö' => 'o', 'Ő' => 'o', 'Ü' => 'u', 'Ű' => 'u',
    ]);
    return $value;
}

function normalizeDate(string $value): ?string
{
    $v = trim($value);
    if ($v === '') {
        return null;
    }

    if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $v, $m) === 1) {
        return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
    }

    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m) === 1) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }

    if (is_numeric($v)) {
        $days = (int)floor((float)$v);
        if ($days > 0) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+' . $days . ' days')->format('Y-m-d');
        }
    }

    return null;
}

function normalizeTime(string $value): ?string
{
    $v = trim($value);
    if ($v === '') {
        return null;
    }

    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $v, $m) === 1) {
        $h = (int)$m[1];
        $i = (int)$m[2];
        $s = isset($m[3]) ? (int)$m[3] : 0;
        if ($h === 24 && $i === 0 && $s === 0) {
            $h = 0;
        }
        if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59 && $s >= 0 && $s <= 59) {
            return sprintf('%02d:%02d:%02d', $h, $i, $s);
        }
        return null;
    }

    if (is_numeric($v)) {
        $num = (float)$v;
        if ($num >= 0 && $num < 1) {
            $total = (int)round($num * 86400);
            if ($total >= 86400) {
                $total = 0;
            }
            return sprintf('%02d:%02d:%02d', intdiv($total, 3600), intdiv($total % 3600, 60), $total % 60);
        }
    }

    return null;
}

function normalizeMoney(mixed $value): string
{
    $v = trim((string)$value);
    if ($v === '') {
        return '0.00';
    }
    $v = str_replace(["\xc2\xa0", ' ', 'Kč'], '', $v);
    $v = str_replace(',', '.', $v);
    $v = preg_replace('/[^0-9.\-]/', '', $v) ?? '';
    if ($v === '' || $v === '-' || $v === '.') {
        return '0.00';
    }
    return number_format((float)$v, 2, '.', '');
}

function normalizeInt(mixed $value): int
{
    $v = trim((string)$value);
    if ($v === '') {
        return 0;
    }
    return max(0, (int)round((float)str_replace(',', '.', $v)));
}

function normalizeBool(mixed $value): int
{
    $v = trim((string)$value);
    if ($v === '') {
        return 0;
    }
    $lower = mb_strtolower($v, 'UTF-8');
    return in_array($lower, ['1', 'true', 'ano', 'yes'], true) ? 1 : 0;
}

function normalizeNullableDecimal(mixed $value): ?string
{
    $v = trim((string)$value);
    if ($v === '' || strtoupper($v) === '#DIV/0!') {
        return null;
    }
    $v = str_replace(["\xc2\xa0", ' ', '%'], '', $v);
    $v = str_replace(',', '.', $v);
    if (!is_numeric($v)) {
        return null;
    }
    return number_format((float)$v, 6, '.', '');
}

function normalizeHoursDecimal(mixed $value): ?string
{
    $v = trim((string)$value);
    if ($v === '') {
        return null;
    }

    $time = normalizeTime($v);
    if ($time !== null) {
        [$h, $m, $s] = array_map('intval', explode(':', $time));
        return number_format($h + ($m / 60) + ($s / 3600), 2, '.', '');
    }

    if (is_numeric(str_replace(',', '.', $v))) {
        return number_format((float)str_replace(',', '.', $v), 2, '.', '');
    }

    return null;
}

function normalizeMakeTimeSeconds(mixed $value): ?int
{
    $v = trim((string)$value);
    if ($v === '' || strtoupper($v) === '#DIV/0!') {
        return null;
    }

    if (preg_match('/(\d+)\s*min(?:ut)?\s*(\d+)?\s*s?/iu', $v, $m) === 1) {
        return ((int)$m[1] * 60) + (isset($m[2]) ? (int)$m[2] : 0);
    }

    if (is_numeric($v)) {
        return (int)round((float)$v * 60);
    }

    return null;
}

function computeWorkedHours(?string $start, ?string $end, ?string $pauseHours): ?string
{
    if ($start === null || $end === null) {
        return null;
    }
    $startMin = parseTimeToMinutes($start);
    $endMin = parseTimeToMinutes($end);
    if ($startMin === null || $endMin === null) {
        return null;
    }
    if ($endMin < $startMin) {
        $endMin += 24 * 60;
    }
    $pause = $pauseHours === null ? 0.0 : (float)$pauseHours;
    $hours = (($endMin - $startMin) / 60) - $pause;
    return number_format(max(0, $hours), 2, '.', '');
}

function parseTimeToMinutes(string $time): ?int
{
    if (preg_match('/^(\d{2}):(\d{2}):(\d{2})$/', $time, $m) !== 1) {
        return null;
    }
    return ((int)$m[1] * 60) + (int)$m[2] + (int)round(((int)$m[3]) / 60);
}

function isDateInImportRange(string $datum, string $nextImportDate, string $importUntil): bool
{
    if ($datum > $importUntil) {
        return false;
    }
    return $nextImportDate === '' || $datum >= $nextImportDate;
}

function orderBranchesForPreview(array $branches): array
{
    $pairs = [];
    foreach ($branches as $name => $path) {
        $pairs[] = ['name' => (string)$name, 'path' => (string)$path, 'id' => getBranchSortId((string)$name)];
    }

    usort($pairs, static function (array $a, array $b): int {
        if ($a['id'] === $b['id']) {
            return strcmp((string)$a['name'], (string)$b['name']);
        }
        return (int)$a['id'] <=> (int)$b['id'];
    });

    $ordered = [];
    foreach ($pairs as $pair) {
        $ordered[(string)$pair['name']] = (string)$pair['path'];
    }
    return $ordered;
}

function getBranchSortId(string $branchName): int
{
    if (isset(GOOGLE_BRANCH_MAP[$branchName])) {
        return (int)GOOGLE_BRANCH_MAP[$branchName];
    }
    return $branchName === 'Výroba' ? 999 : 998;
}

function getBranchFolders(string $baseDir): array
{
    $out = [];
    if (!is_dir($baseDir)) {
        return [];
    }

    $items = scandir($baseDir);
    if ($items === false) {
        return [];
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $full = $baseDir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($full)) {
            $out[$item] = $full;
        }
    }

    ksort($out, SORT_NATURAL | SORT_FLAG_CASE);
    return $out;
}

function findWorkbooksForBranch(string $branchDir, string $branchName): array
{
    if (!is_dir($branchDir)) {
        return [];
    }

    $items = scandir($branchDir);
    if ($items === false) {
        return [];
    }

    $regex = '/^Databaze\s+' . preg_quote($branchName, '/') . '\s+([0-9]{4})\.xlsx$/iu';
    $matches = [];
    foreach ($items as $item) {
        $full = $branchDir . DIRECTORY_SEPARATOR . $item;
        if (!is_file($full) || preg_match($regex, $item, $m) !== 1) {
            continue;
        }
        $matches[] = ['year' => (int)$m[1], 'path' => $full];
    }

    usort($matches, static fn(array $a, array $b): int => $a['year'] <=> $b['year']);
    return array_values(array_map(static fn(array $row): string => (string)$row['path'], $matches));
}

function extractYearFromWorkbookName(string $fileName, string $branchName): ?int
{
    $regex = '/^Databaze\s+' . preg_quote($branchName, '/') . '\s+([0-9]{4})\.xlsx$/iu';
    return preg_match($regex, $fileName, $m) === 1 ? (int)$m[1] : null;
}

function getRelevantWorkbookSheets(string $filePath, int $year): array
{
    $out = [];
    foreach (array_keys(getWorkbookSheetTargets($filePath)) as $sheetName) {
        if (isMonthlySheetNameForYear((string)$sheetName, $year)) {
            $out[] = (string)$sheetName;
        }
    }
    return $out;
}

function getRelevantWorkbookSheetsForImport(string $filePath, int $year, string $nextImportDate, string $importUntil): array
{
    $out = [];
    foreach (getRelevantWorkbookSheets($filePath, $year) as $sheetName) {
        if (isSheetInImportRange($sheetName, $nextImportDate, $importUntil)) {
            $out[] = $sheetName;
        }
    }
    return $out;
}

function isMonthlySheetNameForYear(string $sheetName, int $year): bool
{
    $month = getSheetMonthStart($sheetName);
    return $month !== null && str_starts_with($month, (string)$year . '-');
}

function isWorkbookInImportRange(int $year, string $nextImportDate, string $importUntil): bool
{
    if ($year > (int)substr($importUntil, 0, 4)) {
        return false;
    }
    return $nextImportDate === '' || $year >= (int)substr($nextImportDate, 0, 4);
}

function isSheetInImportRange(string $sheetName, string $nextImportDate, string $importUntil): bool
{
    $monthStart = getSheetMonthStart($sheetName);
    if ($monthStart === null) {
        return false;
    }

    $monthEnd = (new DateTimeImmutable($monthStart))->modify('last day of this month')->format('Y-m-d');
    if ($monthStart > $importUntil) {
        return false;
    }
    return $nextImportDate === '' || $monthEnd >= $nextImportDate;
}

function getSheetMonthStart(string $sheetName): ?string
{
    $parts = preg_split('/\s+/', trim($sheetName));
    if (!is_array($parts) || count($parts) !== 2) {
        return null;
    }

    $month = MONTHS_LOOKUP[strtolower($parts[0])] ?? null;
    $year = (int)$parts[1];
    if (!is_int($month) || $year < 2000 || $year > 2100) {
        return null;
    }

    return sprintf('%04d-%02d-01', $year, $month);
}

function getWorkbookSheetTargets(string $filePath): array
{
    $workbookXml = getZipEntryContent($filePath, 'xl/workbook.xml');
    $relsXml = getZipEntryContent($filePath, 'xl/_rels/workbook.xml.rels');
    if ($workbookXml === null || $relsXml === null) {
        return [];
    }

    $workbook = simplexml_load_string($workbookXml);
    $rels = simplexml_load_string($relsXml);
    if ($workbook === false || $rels === false) {
        return [];
    }

    $workbook->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $rels->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $relMap = [];
    $relNodes = $rels->xpath('/p:Relationships/p:Relationship');
    if (is_array($relNodes)) {
        foreach ($relNodes as $node) {
            $attrs = $node->attributes();
            if ($attrs === null) {
                continue;
            }
            $id = trim((string)$attrs['Id']);
            $target = trim((string)$attrs['Target']);
            if ($id === '' || $target === '') {
                continue;
            }
            if (!str_starts_with($target, 'xl/')) {
                $target = 'xl/' . ltrim($target, '/');
            }
            $relMap[$id] = $target;
        }
    }

    $out = [];
    $sheetNodes = $workbook->xpath('/x:workbook/x:sheets/x:sheet');
    if (is_array($sheetNodes)) {
        foreach ($sheetNodes as $sheet) {
            $attrs = $sheet->attributes();
            $attrsR = $sheet->attributes('r', true);
            if ($attrs === null || $attrsR === null) {
                continue;
            }
            $name = trim((string)$attrs['name']);
            $rid = trim((string)$attrsR['id']);
            if ($name !== '' && $rid !== '' && isset($relMap[$rid])) {
                $out[$name] = $relMap[$rid];
            }
        }
    }

    return $out;
}

function getWorkbookSheetTargetsByCalendar(string $filePath): array
{
    $targets = getWorkbookSheetTargets($filePath);
    uksort($targets, static function (string $a, string $b): int {
        $monthA = getSheetMonthStart($a) ?? '9999-12-01';
        $monthB = getSheetMonthStart($b) ?? '9999-12-01';
        if ($monthA === $monthB) {
            return strcmp($a, $b);
        }
        return strcmp($monthA, $monthB);
    });
    return $targets;
}

function getSheetRows(string $filePath, string $sheetPath): array
{
    $sheetXml = getZipEntryContent($filePath, $sheetPath);
    if ($sheetXml === null || $sheetXml === '') {
        return [];
    }

    $sharedStrings = getSharedStrings($filePath);
    $sheet = simplexml_load_string($sheetXml);
    if ($sheet === false) {
        return [];
    }

    $sheet->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rowNodes = $sheet->xpath('/x:worksheet/x:sheetData/x:row');
    if (!is_array($rowNodes)) {
        return [];
    }

    $rows = [];
    foreach ($rowNodes as $rowNode) {
        $rowAttrs = $rowNode->attributes();
        if ($rowAttrs === null) {
            continue;
        }
        $rowNo = (int)$rowAttrs['r'];
        if ($rowNo <= 0) {
            continue;
        }
        $rows[$rowNo] = [];

        foreach ($rowNode->c as $cell) {
            $cellAttrs = $cell->attributes();
            if ($cellAttrs === null) {
                continue;
            }
            $ref = trim((string)$cellAttrs['r']);
            if (preg_match('/^([A-Z]+)\d+$/', $ref, $m) !== 1) {
                continue;
            }
            $col = $m[1];
            $type = isset($cellAttrs['t']) ? (string)$cellAttrs['t'] : '';
            if ($type === 's') {
                $idx = (int)$cell->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = trim((string)$cell->is->t);
            } else {
                $value = trim((string)$cell->v);
            }
            $rows[$rowNo][$col] = $value;
        }
    }

    return $rows;
}

function getSharedStrings(string $filePath): array
{
    $xml = getZipEntryContent($filePath, 'xl/sharedStrings.xml');
    if ($xml === null || $xml === '') {
        return [];
    }

    $doc = simplexml_load_string($xml);
    if ($doc === false) {
        return [];
    }
    $doc->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $items = $doc->xpath('/x:sst/x:si');
    if (!is_array($items)) {
        return [];
    }

    $out = [];
    foreach ($items as $si) {
        $txt = '';
        if (isset($si->t)) {
            $txt = (string)$si->t;
        } elseif (isset($si->r)) {
            foreach ($si->r as $run) {
                $txt .= (string)$run->t;
            }
        }
        $out[] = trim($txt);
    }
    return $out;
}

function getZipEntryContent(string $filePath, string $entryPath): ?string
{
    if (!is_file($filePath)) {
        return null;
    }

    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        $ok = $zip->open($filePath);
        if ($ok === true) {
            $content = $zip->getFromName($entryPath);
            $zip->close();
            if (is_string($content)) {
                return $content;
            }
        }
    }

    $escapedFile = str_replace("'", "''", $filePath);
    $escapedEntry = str_replace("'", "''", $entryPath);
$script = <<<PS
\$ErrorActionPreference='Stop'
\$ProgressPreference='SilentlyContinue'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
\$fs=[System.IO.File]::Open('$escapedFile',[System.IO.FileMode]::Open,[System.IO.FileAccess]::Read,[System.IO.FileShare]::ReadWrite)
\$zip=New-Object System.IO.Compression.ZipArchive(\$fs,[System.IO.Compression.ZipArchiveMode]::Read,\$false)
try{
  \$entry=\$zip.GetEntry('$escapedEntry')
  if(\$null -eq \$entry){''; exit 0}
  \$stream=\$entry.Open()
  \$ms=New-Object System.IO.MemoryStream
  try{
    \$stream.CopyTo(\$ms)
    [Convert]::ToBase64String(\$ms.ToArray())
  } finally {
    \$stream.Close()
    \$ms.Dispose()
  }
} finally {
  \$zip.Dispose()
  \$fs.Close()
}
PS;

    $encodedScript = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
    $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . $encodedScript;
    $output = shell_exec($cmd);
    if (!is_string($output)) {
        return null;
    }

    $base64 = trim($output);
    if ($base64 === '') {
        return null;
    }

    $decoded = base64_decode($base64, true);
    return $decoded === false ? null : $decoded;
}

function addImportSummary(array &$summary, string $branchName, int $year): void
{
    if (!isset($summary[$branchName])) {
        $summary[$branchName] = [];
    }
    if (!isset($summary[$branchName][$year])) {
        $summary[$branchName][$year] = 0;
    }
    $summary[$branchName][$year]++;
}

function renderImportSummary(array $summary): void
{
    if ($summary === []) {
        logLine('Souhrn: 0 ulozenych reportu.');
        return;
    }

    logLine('');
    logLine('Souhrn ulozenych reportu:');
    $total = 0;
    foreach ($summary as $branchName => $years) {
        ksort($years, SORT_NUMERIC);
        foreach ($years as $year => $count) {
            $total += (int)$count;
            logLine((string)$branchName . ' ' . (string)$year . ': ' . number_format((int)$count, 0, ',', ' '));
        }
    }
    logLine('Celkem ulozeno: ' . number_format($total, 0, ',', ' '));
}

function renderImportFinishedInfo(mysqli $db): void
{
    $counts = getReportTableCounts($db);
    ?>
    <div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10" style="margin-top:18px;">
      <p class="card_text txt_zelena text_tucny odstup_vnejsi_0">Import dokončen</p>
      <table class="table ram_normal bg_bila radek_1_35 sirka100" style="margin-top:8px;">
        <tbody>
          <?php foreach ($counts as $table => $count): ?>
            <tr>
              <td><?= htmlspecialchars((string)$table, ENT_QUOTES, 'UTF-8') ?></td>
              <td class="txt_r"><strong><?= htmlspecialchars(number_format((int)$count, 0, ',', ' '), ENT_QUOTES, 'UTF-8') ?></strong> záznamů</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
}

function getReportTableCounts(mysqli $db): array
{
    $counts = [];
    foreach (['reporty', 'reporty_osoby', 'reporty_pokladna', 'reporty_restia'] as $table) {
        $sql = 'SELECT COUNT(*) AS cnt FROM `' . str_replace('`', '``', $table) . '`';
        $result = $db->query($sql);
        $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $counts[$table] = (int)($row['cnt'] ?? 0);
    }
    return $counts;
}

function renderBackButton(): void
{
    $actionUrl = function_exists('cb_url') ? (string)cb_url('/index.php') : '/index.php';
    ?>
    <div style="margin-top:16px; text-align:right;">
      <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" class="odstup_vnejsi_0 displ_inline_flex">
        <input type="hidden" name="back_admin_init" value="1">
        <button type="submit" class="card_btn cursor_ruka ram_btn zaobleni_6 vyska_28 displ_inline_flex" style="background:var(--clr_ruzova_4); border-color:var(--clr_ruzova_1); color:var(--clr_cervena);">Zpět</button>
      </form>
    </div>
    <?php
}

function initReportLog(): void
{
    @file_put_contents(REPORT_LOG_FILE, '', LOCK_EX);
}

function initReportImportLog(): void
{
    @file_put_contents(REPORT_IMPORT_LOG_FILE, '', LOCK_EX);
}

function initReportImportMatchLog(): void
{
    @file_put_contents(REPORT_IMPORT_MATCH_LOG_FILE, '', LOCK_EX);
}

function initReportOpenCloseLog(): void
{
    @file_put_contents(REPORT_OPEN_CLOSE_LOG_FILE, '', LOCK_EX);
}

function logMissingReportUser(string $datum, int $idPob, string $role, array $resolved): void
{
    if (($resolved['id_user'] ?? null) !== null) {
        return;
    }

    $fullName = (string)($resolved['full_name'] ?? '');
    if ($fullName === '') {
        return;
    }

    $line = implode("\t", [
        $datum,
        'pobocka=' . (string)$idPob,
        'role=' . $role,
        'jmeno=' . $fullName,
    ]);
    @file_put_contents(REPORT_IMPORT_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
}

function logReportUserMatch(string $datum, int $idPob, string $role, array $resolved): void
{
    $idUser = $resolved['id_user'] ?? null;
    $matchCode = (string)($resolved['match_code'] ?? '');
    if ($idUser === null || $matchCode === '') {
        return;
    }

    $line = implode("\t", [
        $datum,
        'pobocka=' . (string)$idPob,
        'role=' . $role,
        'jmeno=' . (string)($resolved['full_name'] ?? ''),
        'id_user=' . (string)$idUser,
        'user=' . (string)($resolved['user_full_name'] ?? ''),
        'kod=' . $matchCode,
    ]);
    @file_put_contents(REPORT_IMPORT_MATCH_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
}

function logOpenCloseFallback(string $datum, int $idPob, string $role, string $reason, array $resolved, mixed $person): void
{
    $fullName = (string)($resolved['full_name'] ?? '');
    $smenaOd = '';
    $smenaDo = '';
    if (is_array($person)) {
        $smenaOd = (string)($person['smena_od'] ?? '');
        $smenaDo = (string)($person['smena_do'] ?? '');
    }

    $line = implode("\t", [
        $datum,
        'pobocka=' . (string)$idPob,
        'chybi=' . $role,
        'dohledano_podle=' . $reason,
        'jmeno=' . $fullName,
        'id_user=' . (string)($resolved['id_user'] ?? ''),
        'smena_od=' . $smenaOd,
        'smena_do=' . $smenaDo,
    ]);
    @file_put_contents(REPORT_OPEN_CLOSE_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
}

function reportLogLine(string $line): void
{
    @file_put_contents(REPORT_LOG_FILE, $line . "\n", FILE_APPEND | LOCK_EX);
}

function logLine(string $text): void
{
    echo $text . "<br>\n";
    @ob_flush();
    flush();
}

function formatElapsedRuntime(float $scriptStart): string
{
    $elapsed = (int)max(0, floor(microtime(true) - $scriptStart));
    return sprintf('%02d:%02d', intdiv($elapsed, 60), $elapsed % 60);
}

function fail(string $message): void
{
    echo 'Chyba: ' . htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}

// inicializace/google_data.php * Verze: V13 * Aktualizace: 05.05.2026
