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
require_once __DIR__ . '/admin_db/admin_firma_db.php';
require_once __DIR__ . '/admin_includes/admin_individualni_prava_detail.php';
require_once __DIR__ . '/admin_lib/admin_pages.php';
require_once __DIR__ . '/admin_lib/admin_smeny_plan_doplnit.php';
require_once __DIR__ . '/admin_lib/admin_firma_ares.php';
require_once __DIR__ . '/admin_lib/admin_firma_pridat.php';

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

if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(100)) {
    http_response_code(403);
    ?>
    <section class="pp admin_pp" data-module="administrace" data-page="zakazano">
        <header class="pp_header">
            <h1>Administrace</h1>
        </header>
        <div class="blok">
            <h2 class="blok_title">Přístup zamítnut</h2>
            <p>Nemáte povolený přístup do modulu Administrace.</p>
        </div>
    </section>
    <?php
    return;
}

cb_admin_firma_pridat_handle();
cb_admin_smeny_plan_doplnit_handle();

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_POST['cb_action'] ?? '') === 'admin_hr_import_user'
) {
    $returnUrl = cb_root_url('index.php?m=administrace&page=spousteni_scriptu');

    try {
        if ((string)($_POST['admin_hr_import_confirm'] ?? '') !== '1') {
            throw new RuntimeException('Potvrďte odstranění testovacích HR dat.');
        }
        $environment = (($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL') ? 'local' : 'server';
        $scriptPath = realpath(__DIR__ . '/../common/tmp/hr_import_user_do_person.php');
        if ($scriptPath === false) {
            throw new RuntimeException('Importní skript nebyl nalezen.');
        }

        if (!defined('CB_HR_IMPORT_DIRECT')) {
            define('CB_HR_IMPORT_DIRECT', true);
        }
        $GLOBALS['CB_HR_IMPORT_ENVIRONMENT'] = $environment;
        unset($GLOBALS['CB_HR_IMPORT_OUTPUT']);
        require $scriptPath;
        $output = trim((string)($GLOBALS['CB_HR_IMPORT_OUTPUT'] ?? ''));
        unset($GLOBALS['CB_HR_IMPORT_ENVIRONMENT'], $GLOBALS['CB_HR_IMPORT_OUTPUT']);

        $_SESSION['cb_admin_script_result'] = [
            'script' => 'hr',
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
            'script' => 'hr',
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
        $adminPravaAction = (string)($_POST['admin_prava_action'] ?? 'role');
        if ($adminPravaAction === 'aplikovano') {
            $idPravo = (int)($_POST['id_pravo'] ?? 0);
            $aplikovano = (int)($_POST['aplikovano'] ?? 0) === 1;
            $result = cb_admin_pravo_aplikovano_uloz($idPravo, $aplikovano);
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14,
                'modul' => 'administrace',
                'objekt' => 'admin_prava_on_off',
                'id_objektu' => $idPravo,
                'pole' => 'aplikovano',
                'hodnota_old' => !empty($result['aplikovano_pred']) ? '1' : '0',
                'hodnota_new' => $aplikovano ? '1' : '0',
                'vysledek' => 1,
                'zdroj' => 'administrace',
                'detail' => [
                    'id_pravo' => $idPravo,
                    'nazev' => (string)($result['nazev'] ?? ''),
                ],
            ]);
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($adminPravaAction === 'aktivni') {
            $idPravo = (int)($_POST['id_pravo'] ?? 0);
            $aktivni = (int)($_POST['aktivni'] ?? 0) === 1;
            if (!$aktivni && (string)($_POST['potvrzeno'] ?? '') !== '1') {
                throw new RuntimeException('Vypnutí hlídání práva nebylo potvrzeno.');
            }

            $result = cb_admin_pravo_aktivni_uloz($idPravo, $aktivni);
            cb_user_akce_zapis([
                'id_user_akce_typ' => 14,
                'modul' => 'administrace',
                'objekt' => 'cis_prava',
                'id_objektu' => $idPravo,
                'pole' => 'aktivni',
                'hodnota_old' => !empty($result['aktivni_pred']) ? '1' : '0',
                'hodnota_new' => $aktivni ? '1' : '0',
                'vysledek' => 1,
                'zdroj' => 'administrace',
                'detail' => [
                    'id_pravo' => $idPravo,
                    'nazev' => (string)($result['nazev'] ?? ''),
                ],
            ]);
            echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if ($adminPravaAction !== 'role') {
            throw new RuntimeException('Neznámá akce globálních práv.');
        }

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
<?php if (!defined('CB_PP_ONLY') || CB_PP_ONLY !== true): ?>
    <?php require __DIR__ . '/admin_includes/admin_menu.php'; ?>
<?php endif; ?>

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
