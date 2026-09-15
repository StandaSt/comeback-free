<?php
declare(strict_types=1);

/*
 * Účel souboru: Ověří požadavek na export DB, vytvoří dočasný SQL soubor
 * a nabídne jej přihlášenému uživateli ke stažení.
 */

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';
require_once __DIR__ . '/../../common/db/db_prava.php';
require_once __DIR__ . '/../admin_lib/admin_db_export.php';

cb_session_guard_entry();

$tempPath = '';
$lockHandle = null;

register_shutdown_function(static function () use (&$tempPath, &$lockHandle): void {
    if ($tempPath !== '' && is_file($tempPath)) {
        @unlink($tempPath);
    }
    if (is_resource($lockHandle)) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
});

/** @return never */
function cb_admin_db_export_download_error(Throwable $error, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Export databáze se nepodařilo vytvořit: ' . $error->getMessage();
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('Export lze spustit pouze odesláním formuláře.');
    }
    if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
        cb_session_forget_auth();
    }
    if (empty($_SESSION['login_ok'])) {
        throw new RuntimeException('Přihlášení vypršelo.');
    }

    cb_crf_vyzaduj();

    $user = $_SESSION['cb_user'] ?? [];
    $idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
    cb_db_prava_nacti_do_session(db(), $idUser);
    if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(109)) {
        throw new RuntimeException('Nemáte právo exportovat databázi.');
    }

    $selectedGroups = cb_admin_db_export_selected_groups($_POST['groups'] ?? null);
    $environment = (($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL') ? 'local' : 'server';
    $downloadName = 'export_' . $environment . '.sql';
    $tempDirectory = dirname(__DIR__, 3) . '/data/tmp';
    if (!is_dir($tempDirectory) && !mkdir($tempDirectory, 0770, true) && !is_dir($tempDirectory)) {
        throw new RuntimeException('Adresář data/tmp nelze vytvořit.');
    }

    $lockHandle = fopen($tempDirectory . '/db_export.lock', 'c');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Jiný export databáze právě probíhá. Zkuste to znovu po jeho dokončení.');
    }

    $tempPath = tempnam($tempDirectory, 'db_export_');
    if ($tempPath === false) {
        $tempPath = '';
        throw new RuntimeException('Dočasný soubor exportu nelze vytvořit.');
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    ignore_user_abort(true);

    $export = cb_admin_db_export_create(db(), $selectedGroups, $tempPath, $environment);
    cb_user_akce_zapis([
        'id_user_akce_typ' => 14,
        'modul' => 'administrace',
        'objekt' => 'db_export',
        'pole' => 'stazeni',
        'hodnota_new' => implode(',', $selectedGroups),
        'vysledek' => 1,
        'zdroj' => 'administrace',
        'detail' => [
            'prostredi' => $environment,
            'tabulek' => (int)$export['table_count'],
            'radku' => (int)$export['row_count'],
            'velikost_b' => (int)$export['size'],
        ],
    ]);

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $downloadName . '"');
    header('Content-Length: ' . (string)$export['size']);
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    $streamed = readfile($tempPath);
    if ($streamed === false) {
        throw new RuntimeException('Odeslání exportu do prohlížeče selhalo.');
    }
} catch (Throwable $e) {
    try {
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'db_export',
            'pole' => 'stazeni',
            'vysledek' => 0,
            'err_msg' => $e->getMessage(),
            'zdroj' => 'administrace',
        ]);
    } catch (Throwable) {
        // Selhání pomocného auditu nesmí překrýt skutečnou chybu exportu.
    }
    if ($tempPath !== '' && is_file($tempPath)) {
        @unlink($tempPath);
    }
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
    cb_admin_db_export_download_error($e, str_contains($e->getMessage(), 'právo') ? 403 : 400);
}

if ($tempPath !== '' && is_file($tempPath)) {
    @unlink($tempPath);
}
if (is_resource($lockHandle)) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
}
exit;
