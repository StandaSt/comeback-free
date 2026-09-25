<?php
declare(strict_types=1);

/*
 * Jednoúčelové spuštění ručně stažených reportů Google z administrace.
 * Nejnovější ZIP Pobočky-YYYYMMDD*.zip se rozbalí do pracovní složky jen pro import.
 */

function cb_admin_google_reporty_import_handle(): void
{
    if (
        ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    ) {
        return;
    }

    $action = (string)($_POST['cb_action'] ?? '');
    if ($action === 'admin_google_reporty_preview') {
        cb_admin_google_reporty_preview_handle();
        return;
    }
    if ($action !== 'admin_google_reporty_import') {
        return;
    }

    $returnUrl = cb_root_url('index.php?m=administrace&page=spousteni_scriptu');

    try {
        if ((string)($_POST['admin_google_reporty_confirm'] ?? '') !== '1') {
            throw new CbUserVisibleException('Potvrďte přípravu zdroje a import reportů.');
        }

        $prepared = cb_admin_google_reporty_priprav_zdroj();
        $importResult = cb_admin_google_reporty_spust_import();
        $summary = cb_admin_google_reporty_shrnuti_importu(
            (string)($importResult['output'] ?? ''),
            (array)($importResult['days'] ?? [])
        );
        $success = $summary['errors'] === 0;
        if ($success) {
            cb_admin_google_reporty_smaz_pracovni_zdroj();
        }

        $_SESSION['cb_admin_script_result'] = [
            'script' => 'google_reporty',
            'success' => $success,
            'message' => $success
                ? $prepared['message'] . ' ' . $summary['message']
                : $prepared['message'] . ' Import skončil s chybami. ' . $summary['message'],
        ];
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'google_reporty_import',
            'pole' => 'spusteni',
            'hodnota_new' => 'xlsx=' . $prepared['xlsx_count'] . ';reporty=' . $summary['reports'] . ';chyby=' . $summary['errors'],
            'vysledek' => $success ? 1 : 0,
            'zdroj' => 'administrace',
        ]);
    } catch (Throwable $e) {
        $publicMessage = cb_admin_chyba_text($e, 'Import Google reportů');
        $_SESSION['cb_admin_script_result'] = [
            'script' => 'google_reporty',
            'success' => false,
            'message' => $publicMessage,
        ];
        cb_admin_chyba_audit(static function () use ($e): void {
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14,
                'modul' => 'administrace',
                'objekt' => 'google_reporty_import',
                'pole' => 'spusteni',
                'vysledek' => 0,
                'err_msg' => $e->getMessage(),
                'zdroj' => 'administrace',
                'detail' => ['chyba' => $e->getMessage()],
            ]);
        });
    }

    header('Location: ' . $returnUrl, true, 303);
    exit;
}

function cb_admin_google_reporty_preview_handle(): void
{
    $returnUrl = cb_root_url('index.php?m=administrace&page=spousteni_scriptu');

    try {
        $prepared = cb_admin_google_reporty_priprav_zdroj();
        $branches = cb_admin_google_reporty_nahled_importu();
        $_SESSION['cb_admin_script_result'] = [
            'script' => 'google_reporty_preview',
            'success' => true,
            'message' => $prepared['message'] . ' Zápis do databáze neproběhl.',
            'branches' => $branches,
        ];
    } catch (Throwable $e) {
        $_SESSION['cb_admin_script_result'] = [
            'script' => 'google_reporty_preview',
            'success' => false,
            'message' => cb_admin_chyba_text($e, 'Náhled importu Google reportů'),
        ];
    }

    header('Location: ' . $returnUrl, true, 303);
    exit;
}

function cb_admin_google_reporty_priprav_zdroj(): array
{
    $googleDataDir = dirname(__DIR__, 3) . '/data/google_data';
    $zipFiles = cb_admin_google_reporty_najdi_zipy($googleDataDir);
    $zipPath = (string)($zipFiles[0]['path'] ?? '');
    $runtimeDir = dirname(__DIR__, 2) . '/common/tmp/google_reporty';
    $workingDir = $runtimeDir . '/Pobočky';

    if ($zipPath === '') {
        throw new RuntimeException('Nebyl nalezen ZIP Pobočky-YYYYMMDD*.zip.');
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Na tomto serveru není dostupná podpora ZIP (ZipArchive).');
    }

    $stageDir = $runtimeDir . '/.Pobočky_rozbaleni';
    $backupDir = $runtimeDir . '/.Pobočky_predchozi';

    cb_admin_google_reporty_smaz_strom($stageDir);
    cb_admin_google_reporty_smaz_strom($backupDir);
    if (!mkdir($stageDir, 0775, true) && !is_dir($stageDir)) {
        throw new RuntimeException('Nelze vytvořit pracovní složku pro rozbalení ZIPu.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        cb_admin_google_reporty_smaz_strom($stageDir);
        throw new RuntimeException('Soubor ' . basename($zipPath) . ' nelze otevřít.');
    }

    try {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if (
                $name === ''
                || !str_starts_with($name, 'Pobočky/')
                || preg_match('#(^|/)\.\.?(/|$)#', $name) === 1
            ) {
                throw new RuntimeException('ZIP nemá očekávanou strukturu složky Pobočky.');
            }
        }

        if (!$zip->extractTo($stageDir)) {
            throw new RuntimeException('ZIP se nepodařilo rozbalit.');
        }
    } finally {
        $zip->close();
    }

    $preparedDir = $stageDir . '/Pobočky';
    $xlsxCount = count(glob($preparedDir . '/*/Databaze *.xlsx') ?: []);
    if (!is_dir($preparedDir) || $xlsxCount === 0) {
        cb_admin_google_reporty_smaz_strom($stageDir);
        throw new RuntimeException('ZIP neobsahuje očekávané sešity Databaze poboček.');
    }

    try {
        if (is_dir($workingDir) && !rename($workingDir, $backupDir)) {
            throw new RuntimeException('Nelze nahradit starou rozbalenou složku Pobočky.');
        }
        if (!rename($preparedDir, $workingDir)) {
            throw new RuntimeException('Nelze uložit nově rozbalenou složku Pobočky.');
        }
        cb_admin_google_reporty_smaz_strom($backupDir);
        cb_admin_google_reporty_smaz_strom($stageDir);
    } catch (Throwable $e) {
        if (!is_dir($workingDir) && is_dir($backupDir)) {
            rename($backupDir, $workingDir);
        }
        cb_admin_google_reporty_smaz_strom($stageDir);
        throw $e;
    }

    $removedZipCount = cb_admin_google_reporty_smaz_starsi_zipy($zipFiles, $zipPath);

    return [
        'xlsx_count' => $xlsxCount,
        'message' => 'Použit ZIP ' . basename($zipPath) . ' (' . $xlsxCount . ' XLSX).'
            . ($removedZipCount > 0 ? ' Odstraněno starších ZIPů: ' . $removedZipCount . '.' : ''),
    ];
}

function cb_admin_google_reporty_spust_import(): array
{
    cb_admin_google_reporty_nacti_importer();

    ob_start();
    try {
        $result = main();
        $output = (string)ob_get_clean();

        return [
            'output' => $output,
            'days' => (array)($result['days'] ?? []),
        ];
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    }
}

function cb_admin_google_reporty_nahled_importu(): array
{
    cb_admin_google_reporty_nacti_importer();

    $db = db_connect();
    $db->set_charset('utf8mb4');
    $importUntil = getGoogleReportImportUntilDate();
    $branches = [];

    foreach (orderBranchesForPreview(getBranchFolders(BASE_DIR)) as $branchName => $branchPath) {
        if (!isset(GOOGLE_BRANCH_MAP[$branchName])) {
            continue;
        }

        $nextImportDate = getNextGoogleReportImportDate($db, (int)GOOGLE_BRANCH_MAP[$branchName]);
        if ($nextImportDate !== '' && $nextImportDate > $importUntil) {
            continue;
        }

        $workbooks = [];
        foreach (findWorkbooksForBranch($branchPath, $branchName) as $workbook) {
            $year = extractYearFromWorkbookName(basename($workbook), $branchName);
            if ($year === null || !isWorkbookInImportRange($year, $nextImportDate, $importUntil)) {
                continue;
            }
            if (getRelevantWorkbookSheetsForImport($workbook, $year, $nextImportDate, $importUntil) !== []) {
                $workbooks[] = basename($workbook);
            }
        }

        if ($workbooks !== []) {
            $branches[] = [
                'name' => $branchName,
                'from' => $nextImportDate !== '' ? $nextImportDate : 'od začátku zdrojů',
                'until' => $importUntil,
                'workbooks' => $workbooks,
            ];
        }
    }

    return $branches;
}

function cb_admin_google_reporty_stav_zdroje(): string
{
    $googleDataDir = dirname(__DIR__, 3) . '/data/google_data';
    $zipFiles = cb_admin_google_reporty_najdi_zipy($googleDataDir);
    $zipPath = (string)($zipFiles[0]['path'] ?? '');
    $zipDate = (string)($zipFiles[0]['date'] ?? '');
    if ($zipPath === '' || $zipDate === '') {
        return 'ZIP nebyl nalezen.';
    }

    $date = DateTimeImmutable::createFromFormat('!Ymd', $zipDate);
    $message = 'Nejnovější ZIP ' . basename($zipPath);
    if ($date instanceof DateTimeImmutable) {
        $message .= ' ze dne ' . $date->format('j. n. Y');
    }

    try {
        cb_admin_google_reporty_nacti_importer();
        $lastReportDate = cb_admin_google_reporty_posledni_datum_zipu($zipPath);

        if ($lastReportDate !== '') {
            $lastDate = DateTimeImmutable::createFromFormat('Y-m-d', $lastReportDate);
            $message .= ' s reporty do '
                . ($lastDate instanceof DateTimeImmutable ? $lastDate->format('j. n. Y') : $lastReportDate)
                . '.';
        } else {
            $message .= '. V ZIPu nebyly nalezeny reporty.';
        }
    } catch (Throwable $e) {
        $message .= '. Datum reportů se nepodařilo načíst.';
    }

    return $message;
}

function cb_admin_google_reporty_posledni_datum_zipu(string $zipPath): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Na tomto serveru není dostupná podpora ZIP (ZipArchive).');
    }

    $tmpRoot = dirname(__DIR__, 2) . '/common/tmp';
    if (!is_dir($tmpRoot) && !mkdir($tmpRoot, 0775, true) && !is_dir($tmpRoot)) {
        throw new RuntimeException('Nelze vytvořit pomocnou složku pro kontrolu ZIPu.');
    }

    $tmpDir = $tmpRoot . '/google_reporty_zip_stav_' . bin2hex(random_bytes(8));
    if (!mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
        throw new RuntimeException('Nelze vytvořit pomocnou složku pro kontrolu ZIPu.');
    }

    $zip = new ZipArchive();
    $opened = false;
    try {
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('ZIP nelze otevřít.');
        }
        $opened = true;

        $xlsxEntries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if (preg_match('#^Pobočky/[^/]+/Databaze [^/]+ [0-9]{4}\.xlsx$#iu', $name) === 1) {
                $xlsxEntries[] = $name;
            }
        }
        if ($xlsxEntries === [] || !$zip->extractTo($tmpDir, $xlsxEntries)) {
            throw new RuntimeException('ZIP neobsahuje očekávané sešity Databaze poboček.');
        }

        return cb_admin_google_reporty_posledni_datum_adresare($tmpDir . '/Pobočky');
    } finally {
        if ($opened) {
            $zip->close();
        }
        cb_admin_google_reporty_smaz_strom($tmpDir);
    }
}

function cb_admin_google_reporty_posledni_datum_adresare(string $baseDir): string
{
    $lastReportDate = '';
    foreach (orderBranchesForPreview(getBranchFolders($baseDir)) as $branchName => $branchPath) {
        if (!isset(GOOGLE_BRANCH_MAP[$branchName])) {
            continue;
        }

        $workbooks = findWorkbooksForBranch($branchPath, $branchName);
        for ($i = count($workbooks) - 1; $i >= 0; $i--) {
            $workbook = (string)$workbooks[$i];
            $year = extractYearFromWorkbookName(basename($workbook), $branchName);
            if ($year === null) {
                continue;
            }

            $branchLastDate = '';
            $sheetTargets = getWorkbookSheetTargetsByCalendar($workbook);
            foreach (getRelevantWorkbookSheets($workbook, $year) as $sheetName) {
                $sheetPath = (string)($sheetTargets[$sheetName] ?? '');
                if ($sheetPath === '') {
                    continue;
                }
                foreach (getSheetRows($workbook, $sheetPath) as $row) {
                    $reportDate = normalizeDate((string)($row['A'] ?? ''));
                    if ($reportDate !== null && $reportDate > $branchLastDate) {
                        $branchLastDate = $reportDate;
                    }
                }
            }

            if ($branchLastDate !== '') {
                if ($branchLastDate > $lastReportDate) {
                    $lastReportDate = $branchLastDate;
                }
                break;
            }
        }
    }

    return $lastReportDate;
}

function cb_admin_google_reporty_najdi_zipy(string $googleDataDir): array
{
    $zipFiles = [];
    foreach (glob($googleDataDir . '/Pobočky-*.zip') ?: [] as $path) {
        $name = basename($path);
        if (!is_file($path) || preg_match('/^Pobočky-(\d{8}).*\.zip$/u', $name, $matches) !== 1) {
            continue;
        }

        $date = DateTimeImmutable::createFromFormat('!Ymd', $matches[1]);
        if (!$date instanceof DateTimeImmutable || $date->format('Ymd') !== $matches[1]) {
            continue;
        }

        $zipFiles[] = [
            'path' => $path,
            'name' => $name,
            'date' => $matches[1],
        ];
    }

    usort($zipFiles, static function (array $a, array $b): int {
        return [$b['date'], $b['name']] <=> [$a['date'], $a['name']];
    });

    return $zipFiles;
}

function cb_admin_google_reporty_smaz_starsi_zipy(array $zipFiles, string $selectedZipPath): int
{
    $removed = 0;
    foreach ($zipFiles as $zipFile) {
        $path = (string)($zipFile['path'] ?? '');
        if ($path === '' || $path === $selectedZipPath) {
            continue;
        }
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Nelze odstranit starší ZIP ' . basename($path) . '.');
        }
        $removed++;
    }

    return $removed;
}

function cb_admin_google_reporty_smaz_pracovni_zdroj(): void
{
    cb_admin_google_reporty_smaz_strom(dirname(__DIR__, 2) . '/common/tmp/google_reporty/Pobočky');
}

function cb_admin_google_reporty_nacti_importer(): void
{
    $scriptPath = __DIR__ . '/admin_google_reporty_importer.php';
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Importní skript Google reportů nebyl nalezen.');
    }

    if (!defined('CB_GOOGLE_DATA_LIBRARY')) {
        define('CB_GOOGLE_DATA_LIBRARY', true);
    }
    require_once $scriptPath;
}

function cb_admin_google_reporty_shrnuti_importu(string $output, array $days): array
{
    $reports = cb_admin_google_reporty_cislo_z_vystupu($output, 'ulozene reporty');
    $errors = cb_admin_google_reporty_cislo_z_vystupu($output, 'pocet chyb');
    $dayLines = [];
    foreach ($days as $day) {
        $dateValue = (string)($day['date'] ?? '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
        $dateLabel = $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $dateValue
            ? $date->format('j. n. Y')
            : $dateValue;
        $branches = array_values(array_filter(array_map('strval', (array)($day['branches'] ?? []))));
        if ($dateLabel !== '' && $branches !== []) {
            $dayLines[] = $dateLabel . ' – ' . implode(', ', $branches);
        }
    }

    $message = 'Zpracované dny: ' . count($dayLines) . ', uloženo reportů: ' . $reports . ', chyb: ' . $errors . '.';
    if ($dayLines !== []) {
        $message .= "\n" . implode("\n", $dayLines);
    }

    return [
        'reports' => $reports,
        'errors' => $errors,
        'message' => $message,
    ];
}

function cb_admin_google_reporty_cislo_z_vystupu(string $output, string $label): int
{
    $pattern = '/' . preg_quote($label, '/') . ':\s*([0-9]+)/iu';
    return preg_match($pattern, $output, $matches) === 1 ? (int)$matches[1] : 0;
}

function cb_admin_google_reporty_smaz_strom(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        $itemPath = $item->getPathname();
        if ($item->isDir() && !$item->isLink()) {
            if (!rmdir($itemPath)) {
                throw new RuntimeException('Nelze odstranit pracovní složku ZIPu.');
            }
        } elseif (!unlink($itemPath)) {
            throw new RuntimeException('Nelze odstranit pracovní soubor ZIPu.');
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException('Nelze odstranit pracovní složku ZIPu.');
    }
}
