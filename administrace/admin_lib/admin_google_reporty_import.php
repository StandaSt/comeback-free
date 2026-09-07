<?php
declare(strict_types=1);

/*
 * Jednoúčelové spuštění ručně stažených reportů Google z administrace.
 * Je-li přítomný ZIP, má přednost a aktualizuje pracovní složku Pobočky.
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
            throw new RuntimeException('Potvrďte přípravu zdroje a import reportů.');
        }

        $prepared = cb_admin_google_reporty_priprav_zdroj();
        $output = cb_admin_google_reporty_spust_import();
        $summary = cb_admin_google_reporty_shrnuti_importu($output);
        $success = $summary['errors'] === 0;

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
        $_SESSION['cb_admin_script_result'] = [
            'script' => 'google_reporty',
            'success' => false,
            'message' => $e->getMessage(),
        ];
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
            'message' => $e->getMessage(),
        ];
    }

    header('Location: ' . $returnUrl, true, 303);
    exit;
}

function cb_admin_google_reporty_priprav_zdroj(): array
{
    $googleDataDir = dirname(__DIR__, 3) . '/data/google_data';
    $zipPath = $googleDataDir . '/Pobočky.zip';
    $workingDir = $googleDataDir . '/Pobočky';

    if (!is_file($zipPath)) {
        $xlsxCount = count(glob($workingDir . '/*/Databaze *.xlsx') ?: []);
        if (!is_dir($workingDir) || $xlsxCount === 0) {
            throw new RuntimeException('Chybí data/google_data/Pobočky.zip i rozbalená složka Pobočky s XLSX sešity.');
        }

        return [
            'xlsx_count' => $xlsxCount,
            'message' => 'Použita existující složka Pobočky (' . $xlsxCount . ' XLSX).',
        ];
    }

    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Na tomto serveru není dostupná podpora ZIP (ZipArchive).');
    }

    $stageDir = $googleDataDir . '/.Pobočky_rozbaleni';
    $backupDir = $googleDataDir . '/.Pobočky_predchozi';

    cb_admin_google_reporty_smaz_strom($stageDir);
    cb_admin_google_reporty_smaz_strom($backupDir);
    if (!mkdir($stageDir, 0775, true) && !is_dir($stageDir)) {
        throw new RuntimeException('Nelze vytvořit pracovní složku pro rozbalení ZIPu.');
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        cb_admin_google_reporty_smaz_strom($stageDir);
        throw new RuntimeException('Soubor Pobočky.zip nelze otevřít.');
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

    return [
        'xlsx_count' => $xlsxCount,
        'message' => 'ZIP nahradil složku Pobočky (' . $xlsxCount . ' XLSX).',
    ];
}

function cb_admin_google_reporty_spust_import(): string
{
    cb_admin_google_reporty_nacti_importer();

    ob_start();
    try {
        main();
        return (string)ob_get_clean();
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

function cb_admin_google_reporty_nacti_importer(): void
{
    $scriptPath = dirname(__DIR__, 3) . '/data/google_data/google_data.php';
    if (!is_file($scriptPath)) {
        throw new RuntimeException('Importní skript Google reportů nebyl nalezen.');
    }

    if (!defined('CB_GOOGLE_DATA_LIBRARY')) {
        define('CB_GOOGLE_DATA_LIBRARY', true);
    }
    require_once $scriptPath;
}

function cb_admin_google_reporty_shrnuti_importu(string $output): array
{
    $reports = cb_admin_google_reporty_cislo_z_vystupu($output, 'ulozene reporty');
    $errors = cb_admin_google_reporty_cislo_z_vystupu($output, 'pocet chyb');
    $days = cb_admin_google_reporty_cislo_z_vystupu($output, 'zpracovane dny');

    return [
        'reports' => $reports,
        'errors' => $errors,
        'message' => 'Zpracováno dnů: ' . $days . ', uloženo reportů: ' . $reports . ', chyb: ' . $errors . '.',
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
