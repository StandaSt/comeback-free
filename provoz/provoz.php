<?php
declare(strict_types=1);

/*
 * Modulový vstup Provoz.
 * Sem nepatří SQL dotazy, HTML bloky, AJAX handlery ani pomocné funkce.
 * Soubor má pouze připravit modul, předat akce dispatcheru, vybrat stránku/pohled a načíst modulový layout.
 */

require_once __DIR__ . '/../common/lib/session_boot.php';
require_once __DIR__ . '/../common/lib/app.php';
require_once __DIR__ . '/lib/mereni_vykonu.php';
require_once __DIR__ . '/../common/lib/system.php';
require_once __DIR__ . '/../common/config/secrets.php';
require_once __DIR__ . '/lib/post_prg_redirect.php';
require_once __DIR__ . '/lib/asset_url.php';
require_once __DIR__ . '/lib/provoz_pages.php';

cb_session_guard_entry();

$cbEmbeddedModule = defined('CB_EMBEDDED_MODULE') && CB_EMBEDDED_MODULE === 'provoz';
if (!$cbEmbeddedModule) {
    http_response_code(500);
    throw new RuntimeException('Modul Provoz lze načíst pouze přes společný index.php.');
}
$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cb2faPending = !empty($_SESSION['cb_2fa_token']);
$cbSystemLocked = false;
if (!empty($_SESSION['login_ok']) && (int)($_SESSION['cb_system']['zamek'] ?? 0) === 1 && !cb_user_ma_roli(1)) {
    try {
        $cbLockConn = db();
        $cbLockRes = $cbLockConn->query('SELECT zamek FROM set_system WHERE id_set = 1 LIMIT 1');
        if ($cbLockRes instanceof mysqli_result) {
            $cbLockRow = $cbLockRes->fetch_assoc();
            $cbLockRes->free();
            $_SESSION['cb_system']['zamek'] = ((int)($cbLockRow['zamek'] ?? 0) === 1) ? 1 : 0;
        }
    } catch (Throwable $e) {
    }
    if ((int)($_SESSION['cb_system']['zamek'] ?? 0) === 1) {
        $cbSystemLocked = true;
    }
}
$cbHasComebackHeader = false;
foreach (array_keys($_SERVER) as $cbServerKey) {
    if (strncmp((string)$cbServerKey, 'HTTP_X_COMEBACK_', 16) === 0) {
        $cbHasComebackHeader = true;
        break;
    }
}
$cbPpOnly = (defined('CB_PP_ONLY') && CB_PP_ONLY === true)
    || (string)($_SERVER['HTTP_X_COMEBACK_PP_ONLY'] ?? '') === '1';

if (empty($_SESSION['login_ok'])) {
    if ($cbHasComebackHeader) {
        http_response_code(401);
        exit;
    }

    header('Location: ' . cb_login_url());
    exit;
}

if (!empty($_SESSION['login_ok']) && !$cbSystemLocked) {
    require_once __DIR__ . '/../common/lib/pobocky_vyber.php';
    require_once __DIR__ . '/../common/lib/handle_set_period.php';
    require_once __DIR__ . '/../common/lib/handle_set_pobocky.php';

    cb_pobocky_bootstrap_session();
}

require_once __DIR__ . '/lib/detektuj_neplatnou_url.php';
require_once __DIR__ . '/../common/lib/json_registrace.php';
if (!empty($_SESSION['login_ok']) && $cbSystemLocked && isset($_GET['cb_lock_check']) && (string)$_GET['cb_lock_check'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    $cbLockedNow = 1;
    try {
        $cbLockConn = db();
        $cbLockRes = $cbLockConn->query('SELECT zamek FROM set_system WHERE id_set = 1 LIMIT 1');
        if ($cbLockRes instanceof mysqli_result) {
            $cbLockRow = $cbLockRes->fetch_assoc();
            $cbLockRes->free();
            $cbLockedNow = ((int)($cbLockRow['zamek'] ?? 0) === 1) ? 1 : 0;
            $_SESSION['cb_system']['zamek'] = $cbLockedNow;
        }
    } catch (Throwable $e) {
    }
    echo json_encode(['ok' => true, 'locked' => $cbLockedNow], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!empty($_SESSION['login_ok']) && !$cbSystemLocked) {
    require_once __DIR__ . '/lib/post_akce.php';
    require_once __DIR__ . '/lib/uloz_dr_pracovni.php';
    require_once __DIR__ . '/lib/uloz_reporty_is.php';
    require_once __DIR__ . '/lib/report_promenne.php';
    require_once __DIR__ . '/../common/lib/uloz_akci.php';
}

$cbProvozCurrentPage = cb_provoz_current_page();
$cbPage = $cbProvozCurrentPage['key'];
$pageKey = $cbPage;
$file = $cbProvozCurrentPage['file'];
$cbPageExists = (bool)$cbProvozCurrentPage['exists'];
$cbProvozPageTitle = $cbProvozCurrentPage['title'];
$cbArchiveBackUrl = '';
$cbAiAnalytikPristup = [];

if ($cbPage === 'ai_analytik' && $cbPageExists) {
    require_once __DIR__ . '/lib/ai_analytik_pravidla.php';
    require_once __DIR__ . '/db/db_ai_analytik_audit.php';
    try {
        if (cb_ai_analytik_ma_pravo()) {
            $cbAiAnalytikPristup = cb_ai_analytik_prehled_pristupu(db());
        }
    } catch (Throwable $error) {
        $cbAiAnalytikPristup = [];
    }
}

$cbAiAnalytikPristupRender = static function (array $rows, bool $showGuide = false): void {
    if ($rows === [] && !$showGuide) {
        return;
    }
    ?>
    <div class="ai_analytik_header_controls">
        <?php if ($showGuide): ?>
            <button
                type="button"
                class="ai_analytik_header_guide_toggle"
                aria-controls="ai_analytik_guide"
                aria-expanded="false"
                data-ai-analytik-guide-toggle
            >AI analytik</button>
        <?php endif; ?>
        <?php if ($rows !== []): ?>
            <button
                type="button"
                class="ai_analytik_header_access_toggle"
                aria-controls="ai_analytik_access"
                aria-expanded="false"
                data-ai-analytik-access-toggle
            >Kdo má přístup</button>
        <?php endif; ?>
    </div>
    <?php
};

if (
    (($cbPage === 'denni_report' && (string)($_GET['zr_archive'] ?? '') === '1') || $cbPage === 'porovnani_reportu')
) {
    $cbArchiveBackParams = [
        'm' => 'provoz',
        'page' => 'archiv_reportu',
    ];
    foreach (['ar_month', 'ar_year', 'ar_branch', 'ar_status', 'ar_sort', 'ar_dir', 'ar_p'] as $cbArchiveBackKey) {
        if (isset($_GET[$cbArchiveBackKey]) && is_scalar($_GET[$cbArchiveBackKey])) {
            $cbArchiveBackParams[$cbArchiveBackKey] = (string)$_GET[$cbArchiveBackKey];
        }
    }
    $cbArchiveBackUrl = cb_root_url('index.php') . '?' . http_build_query($cbArchiveBackParams, '', '&', PHP_QUERY_RFC3986);
}

if (
    ($cbPage === 'denni_report' && (string)($_GET['zr_archive'] ?? '') === '1' && (string)($_GET['zr_archive_edit'] ?? '') !== '1')
    || $cbPage === 'porovnani_reportu'
) {
    $isComparisonReport = $cbPage === 'porovnani_reportu';
    $archiveBranchId = (int)($_GET[$isComparisonReport ? 'compare_branch' : 'zr_id_pob'] ?? 0);
    $archiveDateRaw = trim((string)($_GET[$isComparisonReport ? 'compare_date' : 'datum_reportu'] ?? ''));
    $archiveDate = DateTimeImmutable::createFromFormat('!Y-m-d', $archiveDateRaw, new DateTimeZone('Europe/Prague'));
    $archiveUser = $_SESSION['cb_user'] ?? [];
    $archiveUserId = is_array($archiveUser) ? (int)($archiveUser['id_user'] ?? 0) : 0;
    if (
        $archiveBranchId > 0
        && $archiveUserId > 0
        && $archiveDate instanceof DateTimeImmutable
        && $archiveDate->format('Y-m-d') === $archiveDateRaw
    ) {
        $archiveBranchName = '';
        $archiveTitleStmt = db()->prepare('
            SELECT p.nazev
            FROM user_pobocka up
            INNER JOIN pobocka p ON p.id_pob = up.id_pob
            WHERE up.id_user = ? AND p.id_pob = ? AND p.aktivni = 1
            LIMIT 1
        ');
        if ($archiveTitleStmt !== false) {
            $archiveTitleStmt->bind_param('ii', $archiveUserId, $archiveBranchId);
            $archiveTitleStmt->execute();
            $archiveTitleResult = $archiveTitleStmt->get_result();
            $archiveTitleRow = $archiveTitleResult instanceof mysqli_result ? ($archiveTitleResult->fetch_assoc() ?: []) : [];
            if ($archiveTitleResult instanceof mysqli_result) {
                $archiveTitleResult->free();
            }
            $archiveTitleStmt->close();
            $archiveBranchName = trim((string)($archiveTitleRow['nazev'] ?? ''));
        }
        if ($archiveBranchName !== '') {
            $cbProvozPageTitle = ($isComparisonReport ? 'Porovnání reportu ' : 'Detail reportu ') . $archiveBranchName . ' ' . $archiveDate->format('j.n.Y');
        }
    }
}

if (!empty($_SESSION['login_ok']) && !$cbSystemLocked && function_exists('cb_report_promenne_handle_post')) {
    cb_report_promenne_handle_post();
}

require_once __DIR__ . '/includes/log_a_404.php';

if (!empty($_SESSION['login_ok']) && !$cbSystemLocked) {
    require_once __DIR__ . '/lib/request_dispatch.php';
} elseif ($cbHasComebackHeader) {
    http_response_code(401);
    exit;
}

if (
    !empty($_SESSION['login_ok'])
    && !$cbSystemLocked
    && isset($_REQUEST['open_kontrola_email'])
    && (string)$_REQUEST['open_kontrola_email'] === '1'
    && (
        (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['ajax'] ?? '') === '1')
        || (string)($_GET['export'] ?? '') === 'pdf'
    )
) {
    if (!defined('CB_KONTROLA_EMAIL_EMBED')) {
        define('CB_KONTROLA_EMAIL_EMBED', true);
    }
    require __DIR__ . '/includes/kontrola_email.php';
    exit;
}

if ($cbPpOnly && !empty($_SESSION['login_ok']) && !$cbSystemLocked) {
    header('Content-Type: text/html; charset=utf-8');
    if ($cbPage === 'uprava_profilu') {
        require __DIR__ . '/../common/pages/uprava_profilu.php';
    } else {
        ?>
        <section class="pp" data-module="provoz" data-page="<?= h($cbPage) ?>">
            <header class="pp_header">
                <?php if ($cbArchiveBackUrl !== ''): ?>
                    <div class="provoz_archive_header_title"><a class="provoz_archive_back_btn" href="<?= h($cbArchiveBackUrl) ?>" title="Zpět do archivu" aria-label="Zpět do archivu">←</a><h1><?= h($cbProvozPageTitle) ?></h1></div>
                <?php else: ?>
                    <h1><?= h($cbProvozPageTitle) ?></h1>
                <?php endif; ?>
                <?php if ($cbPage === 'denni_report' && function_exists('cb_pravo_ma') && cb_pravo_ma(CB_REPORT_PROMENNE_PRAVO)): ?>
                    <div class="pp_header_control">
                        <a class="head_task_btn" href="<?= h(cb_root_url('index.php?m=provoz&page=nastaveni_reportu')) ?>">Nastavení reportu</a>
                    </div>
                <?php endif; ?>
                <?php $cbAiAnalytikPristupRender($cbAiAnalytikPristup, $cbPage === 'ai_analytik'); ?>
            </header>
            <?php
            if ($cbPageExists) {
                require $file;
            }
            ?>
        </section>
        <?php
    }
    exit;
}
?>
<?php

if (!empty($_SESSION['login_ok']) && $cbSystemLocked) {
    ?>
    <div style="width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;overflow:hidden;">
      <img src="<?= h(cb_url('img/udrzba.png')) ?>" alt="Údržba systému" style="width:100vw;height:100vh;object-fit:contain;display:block;">
    </div>
      <?php
  } elseif (!empty($_SESSION['login_ok'])) {
    if (!$cbEmbeddedModule) {
        require_once __DIR__ . '/../common/includes/hlavicka.php';
    }
    require_once __DIR__ . '/../common/modaly/modal_overeni.php';
    require_once __DIR__ . '/../common/lib/kontrola_registrace.php';

    ?>
    <?php require __DIR__ . '/includes/menu.php'; ?>

    <?php if ($cbPage === 'uprava_profilu'): ?>
        <?php require __DIR__ . '/../common/pages/uprava_profilu.php'; ?>
    <?php else: ?>
    <section class="pp" data-module="provoz" data-page="<?= h($cbPage) ?>">
        <header class="pp_header">
            <?php if ($cbArchiveBackUrl !== ''): ?>
                <div class="provoz_archive_header_title"><a class="provoz_archive_back_btn" href="<?= h($cbArchiveBackUrl) ?>" title="Zpět do archivu" aria-label="Zpět do archivu">←</a><h1><?= h($cbProvozPageTitle) ?></h1></div>
            <?php else: ?>
                <h1><?= h($cbProvozPageTitle) ?></h1>
            <?php endif; ?>
            <?php if ($cbPage === 'denni_report' && function_exists('cb_pravo_ma') && cb_pravo_ma(CB_REPORT_PROMENNE_PRAVO)): ?>
                <div class="pp_header_control">
                    <a class="head_task_btn" href="<?= h(cb_root_url('index.php?m=provoz&page=nastaveni_reportu')) ?>">Nastavení reportu</a>
                </div>
            <?php endif; ?>
            <?php $cbAiAnalytikPristupRender($cbAiAnalytikPristup, $cbPage === 'ai_analytik'); ?>
        </header>
        <?php
        if ($cbPageExists) {
            require $file;
        }
        ?>
    </section>
    <?php endif; ?>
    <?php
} elseif ($cb2faPending) {
    require_once __DIR__ . '/../common/modaly/modal_overeni.php';
} elseif ($cbAuthOk) {
    require_once __DIR__ . '/../common/lib/kontrola_registrace.php';
} else {
    $cbLoginDbOk = false;
    $cbLoginDbName = '---';

    if (isset($SECRETS['db']) && is_array($SECRETS['db'])) {
        $cbLoginDbCfg = ($PROSTREDI === 'LOCAL')
            ? ($SECRETS['db']['local'] ?? null)
            : ($SECRETS['db']['server'] ?? null);

        if (is_array($cbLoginDbCfg)) {
            $cbLoginDbName = trim((string)($cbLoginDbCfg['name'] ?? ''));
            if ($cbLoginDbName === '') {
                $cbLoginDbName = '---';
            }
        }
    }

    try {
        $cbLoginDbConn = db();
        $cbLoginDbResult = $cbLoginDbConn->query('SELECT DATABASE() AS db_name');
        if ($cbLoginDbResult instanceof mysqli_result) {
            $cbLoginDbRow = $cbLoginDbResult->fetch_assoc();
            $cbLoginDbResult->free();
            $cbLoginDbRealName = trim((string)($cbLoginDbRow['db_name'] ?? ''));
            if ($cbLoginDbRealName !== '') {
                $cbLoginDbName = $cbLoginDbRealName;
            }
        }
        $cbLoginDbOk = true;
    } catch (Throwable $e) {
        $cbLoginDbOk = false;
    }

    require_once __DIR__ . '/../common/modaly/modal_login.php';
}

?>

<?php
if (!$cbEmbeddedModule && !empty($cbInvalidUrl)) {
    $cbUserForAlert = $_SESSION['cb_user'] ?? [];
    $cbUserName = trim((string)($cbUserForAlert['name'] ?? ''));
    $cbUserSurname = trim((string)($cbUserForAlert['surname'] ?? ''));
    $cbAlertUserName = trim($cbUserName . ' ' . $cbUserSurname);
    if ($cbAlertUserName === '') {
        $cbAlertUserName = 'Neznámý uživatel';
    }

    $fullRequestUrl =
        ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http')
        . '://'
        . (string)($_SERVER['HTTP_HOST'] ?? 'localhost')
        . (string)($_SERVER['REQUEST_URI'] ?? '/');

    $cbAlertInvalidUrl = $fullRequestUrl;

    require_once __DIR__ . '/modaly/modal_alert_url.php';
}
?>
<?php
/* provoz.php * Verze: V25 * Aktualizace: 28.04.2026 */
// Konec souboru
