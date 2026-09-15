<?php
declare(strict_types=1);

/*
 * Účel souboru: Ověří serverový požadavek, vytvoří dočasný SQL export
 * provozních dat a nabídne jej přihlášenému uživateli přes Uložit jako.
 */

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';
require_once __DIR__ . '/../../common/db/db_prava.php';
require_once __DIR__ . '/../admin_lib/admin_server_data_export.php';

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
function cb_admin_server_data_export_download_error(Throwable $error, int $status = 400): never
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Export dat ze serveru na lokál se nepodařilo vytvořit: ' . $error->getMessage();
    exit;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('Export lze spustit pouze odesláním formuláře.');
    }
    if (($GLOBALS['PROSTREDI'] ?? '') !== 'SERVER') {
        throw new RuntimeException('Tento export je dostupný pouze na serveru.');
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

    $downloadName = 'export_server_data_pro_lokal.sql';
    $tempDirectory = dirname(__DIR__, 3) . '/data/tmp';
    if (!is_dir($tempDirectory) && !mkdir($tempDirectory, 0770, true) && !is_dir($tempDirectory)) {
        throw new RuntimeException('Adresář data/tmp nelze vytvořit.');
    }

    $lockHandle = fopen($tempDirectory . '/db_export.lock', 'c');
    if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Jiný export databáze právě probíhá. Zkuste to znovu po jeho dokončení.');
    }

    $tempPath = tempnam($tempDirectory, 'server_data_export_');
    if ($tempPath === false) {
        $tempPath = '';
        throw new RuntimeException('Dočasný soubor exportu nelze vytvořit.');
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }
    ignore_user_abort(true);

    $export = cb_admin_server_data_export_create(db(), $tempPath, 'server');
    cb_user_akce_zapis([
        'id_user_akce_typ' => 14,
        'modul' => 'administrace',
        'objekt' => 'server_data_export',
        'pole' => 'stazeni',
        'hodnota_new' => implode(',', $export['tables']),
        'vysledek' => 1,
        'zdroj' => 'administrace',
        'detail' => [
            'prostredi' => 'server',
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

    if (readfile($tempPath) === false) {
        throw new RuntimeException('Odeslání exportu do prohlížeče selhalo.');
    }
} catch (Throwable $e) {
    try {
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'server_data_export',
            'pole' => 'stazeni',
            'vysledek' => 0,
            'err_msg' => $e->getMessage(),
            'zdroj' => 'administrace',
        ]);
    } catch (Throwable) {
        // Selhání pomocného auditu nesmí překrýt skutečnou chybu exportu.
    }
    $forbidden = str_contains($e->getMessage(), 'právo')
        || str_contains($e->getMessage(), 'pouze na serveru');
    cb_admin_server_data_export_download_error($e, $forbidden ? 403 : 400);
}

exit;
