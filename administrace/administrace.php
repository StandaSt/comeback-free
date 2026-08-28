<?php
declare(strict_types=1);

/*
 * Modulovy vstup administrace.
 * Pripravi modul, preda akce dispatcheru a nacte vybrany pohled.
 */

/*
 * Modulový vstup Administrace.
 * Sem nepatří SQL dotazy, HTML bloky, AJAX handlery ani pomocné funkce.
 * Soubor má pouze připravit modul, předat akce dispatcheru, vybrat stránku/pohled a načíst modulový layout.
 */

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/../common/lib/system.php';
require_once __DIR__ . '/../common/lib/uloz_akci.php';
require_once __DIR__ . '/admin_db/admin_prava_roli_db.php';
require_once __DIR__ . '/admin_db/admin_individualni_prava_db.php';
require_once __DIR__ . '/admin_includes/admin_individualni_prava_detail.php';
require_once __DIR__ . '/admin_lib/admin_pages.php';

cb_session_guard_entry();

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'administrace';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul Administrace lze načíst pouze přes společný index.php.');
}

if (empty($_SESSION['login_ok'])) {
    header('Location: ' . cb_login_url());
    exit;
}

$adminUser = $_SESSION['cb_user'] ?? [];
$adminRoleId = is_array($adminUser) ? (int)($adminUser['id_role'] ?? 0) : 0;
if ($adminRoleId !== 1) {
    http_response_code(403);
    ?>
    <section class="pp admin_pp" data-module="administrace" data-page="zakazano">
        <header class="pp_header">
            <h1>Administrace</h1>
        </header>
        <div class="blok">
            <h2 class="blok_title">Přístup zamítnut</h2>
            <p>Administrace je dostupná pouze pro roli Admin.</p>
        </div>
    </section>
    <?php
    return;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_POST['cb_action'] ?? '') === 'admin_hr_import_user'
) {
    $returnUrl = cb_root_url('index.php?m=administrace&page=spousteni_scriptu');

    try {
        if ((string)($_POST['admin_hr_import_confirm'] ?? '') !== '1') {
            throw new RuntimeException('Potvrďte odstranění testovacích HR dat.');
        }
        if (!function_exists('proc_open')) {
            throw new RuntimeException('Server nepovoluje spuštění importního skriptu.');
        }

        $environment = (($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL') ? 'local' : 'server';
        $scriptPath = realpath(__DIR__ . '/../../tmp/hr_import_user_do_person.php');
        if ($scriptPath === false) {
            throw new RuntimeException('Importní skript nebyl nalezen.');
        }

        $phpCliName = DIRECTORY_SEPARATOR === '\\' ? 'php.exe' : 'php';
        $phpCliCandidates = [
            PHP_BINDIR . DIRECTORY_SEPARATOR . $phpCliName,
            dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . $phpCliName,
            dirname(dirname(PHP_BINARY)) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $phpCliName,
            dirname(dirname(PHP_BINDIR)) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $phpCliName,
        ];
        if ($environment === 'local') {
            array_unshift(
                $phpCliCandidates,
                dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'php' . DIRECTORY_SEPARATOR . $phpCliName
            );
        }
        $phpCliPath = '';
        foreach ($phpCliCandidates as $phpCliCandidate) {
            if (is_file($phpCliCandidate)) {
                $phpCliPath = $phpCliCandidate;
                break;
            }
        }
        if ($phpCliPath === '') {
            throw new RuntimeException('CLI PHP pro spuštění importu nebylo nalezeno.');
        }

        $command = escapeshellarg($phpCliPath)
            . ' ' . escapeshellarg($scriptPath)
            . ' --db=' . $environment
            . ' --reset';
        $pipes = [];
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, dirname($scriptPath));

        if (!is_resource($process)) {
            throw new RuntimeException('Importní skript se nepodařilo spustit.');
        }

        $output = trim((string)stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        $errorOutput = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException($errorOutput !== '' ? $errorOutput : 'Importní skript skončil s chybou.');
        }

        $_SESSION['cb_admin_script_result'] = [
            'success' => true,
            'message' => $output !== '' ? $output : 'Import byl dokončen.',
        ];
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'hr_import_user_do_person',
            'pole' => 'spusteni',
            'hodnota_new' => $environment,
            'vysledek' => 1,
            'zdroj' => 'administrace',
        ]);
    } catch (Throwable $e) {
        $_SESSION['cb_admin_script_result'] = [
            'success' => false,
            'message' => $e->getMessage(),
        ];
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'hr_import_user_do_person',
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

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_SERVER['HTTP_X_COMEBACK_ADMIN_INDIVIDUAL'] ?? '') === '1'
) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'exception_users') {
            echo json_encode([
                'ok' => true,
                'users' => cb_admin_individualni_prava_uzivatele_s_vyjimkami(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'search') {
            $users = cb_admin_individualni_prava_hledej_uzivatele((string)($_POST['q'] ?? ''));
            $detailHtml = '';
            if (count($users) === 1) {
                $detailHtml = cb_admin_individualni_prava_html(
                    cb_admin_individualni_prava_data((int)$users[0]['id_user'])
                );
            }
            echo json_encode(['ok' => true, 'users' => $users, 'detail_html' => $detailHtml], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'detail') {
            $detailHtml = cb_admin_individualni_prava_html(
                cb_admin_individualni_prava_data((int)($_POST['id_user'] ?? 0))
            );
            echo json_encode(['ok' => true, 'detail_html' => $detailHtml], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($action === 'save') {
            $result = cb_admin_individualni_prava_uloz(
                (int)($_POST['id_user'] ?? 0),
                (int)($_POST['id_pravo'] ?? 0),
                (int)($_POST['vyjimka'] ?? 0) === 1
            );
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14,
                'modul' => 'administrace',
                'objekt' => 'prava_vyjimky',
                'id_objektu' => (int)($_POST['id_pravo'] ?? 0),
                'pole' => 'povoleno',
                'hodnota_new' => $result['vyjimka'] ? (string)$result['povoleno'] : '',
                'vysledek' => 1,
                'zdroj' => 'administrace',
                'detail' => [
                    'id_user' => (int)($_POST['id_user'] ?? 0),
                    'id_pravo' => (int)($_POST['id_pravo'] ?? 0),
                    'vyjimka' => $result['vyjimka'] ? 1 : 0,
                ],
            ]);
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
            exit;
        }

        throw new RuntimeException('Neznámá akce.');
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_SERVER['HTTP_X_COMEBACK_ADMIN_PRAVA'] ?? '') === '1'
) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        cb_admin_prava_roli_uloz(
            (int)($_POST['id_role'] ?? 0),
            (int)($_POST['id_pravo'] ?? 0),
            (int)($_POST['allowed'] ?? 0) === 1
        );
        cb_user_akce_zapis([
            'id_user_akce_typ' => 14,
            'modul' => 'administrace',
            'objekt' => 'prava_global',
            'id_objektu' => (int)($_POST['id_pravo'] ?? 0),
            'pole' => 'pristup',
            'hodnota_new' => (string)((int)($_POST['allowed'] ?? 0) === 1 ? 1 : 0),
            'vysledek' => 1,
            'zdroj' => 'administrace',
            'detail' => [
                'id_role' => (int)($_POST['id_role'] ?? 0),
                'id_pravo' => (int)($_POST['id_pravo'] ?? 0),
            ],
        ]);
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$adminCurrentPage = cb_admin_current_page();
$adminPage = $adminCurrentPage['key'];
$adminPageTitle = $adminCurrentPage['title'];
$adminPageFile = $adminCurrentPage['file'];

?>
<?php require __DIR__ . '/admin_includes/admin_menu.php'; ?>

<?php if ($adminPage === 'uprava_profilu'): ?>
    <?php require __DIR__ . '/../common/pages/uprava_profilu.php'; ?>
<?php else: ?>
<section class="pp admin_pp" data-module="administrace" data-page="<?= h($adminPage) ?>">
    <header class="pp_header">
        <h1><?= h($adminPageTitle) ?></h1>
    </header>
    <main class="admin_content">
        <?php require $adminPageFile; ?>
    </main>
</section>
<?php endif; ?>
