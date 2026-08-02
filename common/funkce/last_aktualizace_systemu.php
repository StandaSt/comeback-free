<?php
// funkce/last_aktualizace_systemu.php * Verze: V1 * Aktualizace: 30.06.2026
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    if (!isset($_SESSION) || !is_array($_SESSION)) {
        $_SESSION = [];
    }
} else {
    require_once __DIR__ . '/../lib/session_boot.php';
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../db/db_connect.php';
require_once __DIR__ . '/../db/zapis_log_chyby.php';

if (PHP_SAPI === 'cli') {
    $PROSTREDI = 'SERVER';
}

function cb_last_aktualizace_systemu_log_error(Throwable $e): void
{
    try {
        $conn = db_connect();
        $dataJson = json_encode([
            'script' => __FILE__,
            'php_sapi' => PHP_SAPI,
            'prostredi' => (string)($GLOBALS['PROSTREDI'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        db_zapis_log_chyby(
            $conn,
            null,
            'CRON',
            'LAST_AKTUALIZACE_SYSTEMU',
            'LAST_AKTUALIZACE_SYSTEMU_CHYBA',
            $e->getMessage(),
            null,
            $e->getFile(),
            $e->getLine(),
            null,
            $dataJson === false ? null : $dataJson
        );
    } catch (Throwable $logError) {
    }
}

function cb_last_aktualizace_systemu(): void
{
    try {
        $root = dirname(__DIR__);
        $nejnovejsiCas = 0;

        $soubory = [];
        $indexSoubor = $root . '/index.php';

        if (is_file($indexSoubor)) {
            $soubory[] = $indexSoubor;
        }

        $masky = [
            $root . '/karty/*.php',
            $root . '/includes/*.php',
            $root . '/lib/*.php',
        ];

        foreach ($masky as $maska) {
            $nalezeno = glob($maska);

            if ($nalezeno === false) {
                continue;
            }

            foreach ($nalezeno as $soubor) {
                if (is_file($soubor)) {
                    $soubory[] = $soubor;
                }
            }
        }

        foreach ($soubory as $soubor) {
            $cas = filemtime($soubor);

            if ($cas === false) {
                continue;
            }

            if ($cas > $nejnovejsiCas) {
                $nejnovejsiCas = $cas;
            }
        }

        if ($nejnovejsiCas <= 0) {
            return;
        }

        $upravaSouboru = date('Y-m-d H:i:s', $nejnovejsiCas);

        $conn = db_connect();

        $result = $conn->query('SELECT uprava_souboru FROM set_system LIMIT 1');
        $row = $result->fetch_assoc();
        $result->free();

        if ($row === null) {
            throw new RuntimeException('Tabulka set_system neobsahuje zadny zaznam.');
        }

        $dbUpravaSouboru = (string)($row['uprava_souboru'] ?? '');

        if ($dbUpravaSouboru === $upravaSouboru) {
            return;
        }

        $stmt = $conn->prepare('UPDATE set_system SET uprava_souboru = ?, verze = verze + 1 LIMIT 1');
        $stmt->bind_param('s', $upravaSouboru);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        cb_last_aktualizace_systemu_log_error($e);
    }
}

if (!defined('CB_LAST_AKTUALIZACE_SYSTEMU_AUTO_RUN') || CB_LAST_AKTUALIZACE_SYSTEMU_AUTO_RUN !== false) {
    cb_last_aktualizace_systemu();
}

/* funkce/last_aktualizace_systemu.php V1 * počet řádků: 89 * aktualizace: 30.06.2026 */
// Konec souboru
