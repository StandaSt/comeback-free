<?php
// index.php * Verze: V25 * Aktualizace: 28.04.2026


declare(strict_types=1);

require_once __DIR__ . '/lib/session_boot.php';
require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/system.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/post_prg_redirect.php';
require_once __DIR__ . '/lib/asset_url.php';

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}

$cbTitle = 'Comeback - IS';
$cbFavicon = cb_url('img/favicon_comeback.png');

$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cb2faPending = !empty($_SESSION['cb_2fa_token']);
$cbSystemLocked = false;
$cbUserRoleId = (int)($_SESSION['cb_user']['id_role'] ?? 0);
if (!empty($_SESSION['login_ok']) && (int)($_SESSION['cb_system']['zamek'] ?? 0) === 1 && $cbUserRoleId !== 1) {
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
$cbIsPartialRequest = isset($_SERVER['HTTP_X_COMEBACK_PARTIAL']);
$cbIsCardRequest = isset($_SERVER['HTTP_X_COMEBACK_CARD']);
$cbIsMaxFormRequest = isset($_SERVER['HTTP_X_COMEBACK_MAX_FORM']);
$cbStartupLoaderText = '';
if (!empty($_SESSION['login_ok']) && !$cbSystemLocked) {
    $cbStartupLoaderText = trim((string)($_SESSION['cb_initial_loader_text'] ?? ''));
    if ($cbStartupLoaderText === '') {
        $cbStartupLoaderText = 'Aktualizuji data ...';
    }
}
unset($_SESSION['cb_initial_loader_text']);

if (!empty($_SESSION['login_ok']) && !$cbSystemLocked) {
    require_once __DIR__ . '/lib/pobocky_vyber.php';
    require_once __DIR__ . '/lib/card_json_response.php';
    require_once __DIR__ . '/lib/handle_set_period.php';
    require_once __DIR__ . '/lib/handle_set_card_mode.php';
    require_once __DIR__ . '/lib/handle_unlock_all_card_pos.php';
    require_once __DIR__ . '/lib/handle_set_card_position.php';

    cb_pobocky_bootstrap_session();
}

require_once __DIR__ . '/lib/detektuj_neplatnou_url.php';
require_once __DIR__ . '/lib/logout_handler.php';
require_once __DIR__ . '/lib/json_registrace.php';
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
    require_once __DIR__ . '/lib/uloz_akci.php';
}

$pageKey = 'dashboard';
$file = __DIR__ . '/includes/dashboard.php';
$cbPage = trim((string)($_GET['page'] ?? 'dashboard'));
if ($cbPage === '') {
    $cbPage = 'dashboard';
}

require_once __DIR__ . '/includes/log_a_404.php';

if (!empty($_SESSION['login_ok']) && !$cbSystemLocked) {
    require_once __DIR__ . '/lib/request_dispatch.php';
} elseif ($cbHasComebackHeader) {
    http_response_code(401);
    exit;
}

if (!empty($_SESSION['login_ok']) && !$cbSystemLocked && !$cbHasComebackHeader) {
    require_once __DIR__ . '/lib/restia_online_kontrola.php';
    if (function_exists('cb_restia_online_kontrola')) {
        cb_restia_online_kontrola();
    }
}
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($cbTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= h($cbFavicon) ?>">

    <?php require_once __DIR__ . '/lib/nacti_styly.php'; ?>
</head>
<body>

<?php if (!empty($_SESSION['login_ok']) && !$cbSystemLocked && $cbStartupLoaderText !== ''): ?>
<div id="cb-startup-loader" class="dash_box bg_modra sirka100 is-dashboard-loading" data-cb-startup-text="<?= h($cbStartupLoaderText) ?>" style="position:fixed;left:0;top:0;right:0;bottom:0;z-index:12000;padding:0 12px;background-clip:content-box;overflow:hidden;">
  <?php require __DIR__ . '/includes/loaders/dashboard.php'; ?>
</div>
<script src="<?= h(cb_url('js/loader_show.js')) ?>"></script>
<script src="<?= h(cb_url('js/loader_timer.js')) ?>"></script>
<?php endif; ?>

<div class="container bg_modra displ_flex sirka100">
<?php

if (!empty($_SESSION['login_ok']) && $cbSystemLocked) {
    ?>
    <div style="width:100vw;height:100vh;display:flex;align-items:center;justify-content:center;background:#0f172a;overflow:hidden;">
      <img src="<?= h(cb_url('img/udrzba.png')) ?>" alt="Údržba systému" style="width:100vw;height:100vh;object-fit:contain;display:block;">
    </div>
      <?php
  } elseif (!empty($_SESSION['login_ok'])) {
    require_once __DIR__ . '/includes/hlavicka.php';
    require_once __DIR__ . '/modaly/modal_overeni.php';
    require_once __DIR__ . '/lib/kontrola_registrace.php';

    $cb_page_exists = $cbPageExists;
    $cb_page_file = $file;

    require_once __DIR__ . '/includes/main.php';
    require_once __DIR__ . '/includes/paticka.php';
} elseif ($cb2faPending) {
    require_once __DIR__ . '/modaly/modal_overeni.php';
} elseif ($cbAuthOk) {
    require_once __DIR__ . '/lib/kontrola_registrace.php';
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

    require_once __DIR__ . '/modaly/modal_login.php';
}

?>
</div>


<?php if (!empty($_SESSION['login_ok']) && !$cbSystemLocked): ?>
<script src="<?= h(cb_asset_url('js/echarts.min.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/ajax_core.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/ajax_karta_max.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_max_loader.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_min_max.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_top_report.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_grafy.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/tooltip_pozice.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_min_nano.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_hlavicka.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_restia.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_form.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_person.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/select_pobocky.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/filtry.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/prehled_smen_export.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/rozbalovaci_detail.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/casovac_odhlaseni.js')) ?>"></script>
<?php endif; ?>

<?php
if (!empty($cbInvalidUrl)) {
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
</body>
</html>
<?php
/* index.php * Verze: V25 * Aktualizace: 28.04.2026 */
// Konec souboru
