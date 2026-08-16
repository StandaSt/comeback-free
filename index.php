<?php
// index.php * Spolecny login Comeback
declare(strict_types=1);

require_once __DIR__ . '/common/lib/session_boot.php';
require_once __DIR__ . '/common/lib/app.php';
require_once __DIR__ . '/common/lib/system.php';
require_once __DIR__ . '/common/config/secrets.php';
require_once __DIR__ . '/common/lib/json_registrace.php';
require_once __DIR__ . '/common/lib/moduly.php';
require_once __DIR__ . '/common/lib/nastaveni_uzivatele.php';
require_once __DIR__ . '/common/lib/local_login_sync.php';

cb_session_guard_entry();
require_once __DIR__ . '/provoz/lib/logout_handler.php';

$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cb2faPending = !empty($_SESSION['cb_2fa_token']);

if (!empty($_SESSION['login_ok'])) {
    require_once __DIR__ . '/common/lib/pobocky_vyber.php';
    require_once __DIR__ . '/common/lib/handle_set_period.php';
    require_once __DIR__ . '/common/lib/handle_set_pobocky.php';
}

cb_nastaveni_uzivatele_vyrid_post();
cb_local_login_sync_vyrid();

if (!empty($_SESSION['login_ok']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_SERVER['HTTP_X_COMEBACK_GN_BLOCK'])) {
    $cbGnModule = strtolower(trim((string)($_POST['module'] ?? '')));
    $cbGnPage = strtolower(trim((string)($_POST['page'] ?? '')));
    $cbGnBlock = strtolower(trim((string)($_POST['block'] ?? '')));
    if ($cbGnPage === 'dashboard') {
        $cbGnPage = 'prehled';
    }

    if ($cbGnModule === 'provoz' && $cbGnPage === 'prehled' && $cbGnBlock === 'top_report') {
        require_once __DIR__ . '/common/lib/pobocky_vyber.php';
        cb_pobocky_bootstrap_session();

        $GLOBALS['CURRENT_MODULE'] = 'provoz';
        define('CB_EMBEDDED_MODULE', 'provoz');
        header('Content-Type: text/html; charset=utf-8');
        echo '<div class="provoz_prehled_cell" data-pp-block="top_report" data-gn="1">';
        require __DIR__ . '/provoz/bloky/top_report.php';
        echo '</div>';
        exit;
    }

    if ($cbGnModule === 'provoz' && $cbGnPage === 'objednavky' && $cbGnBlock === 'objednavky_prehled') {
        require_once __DIR__ . '/common/lib/pobocky_vyber.php';
        cb_pobocky_bootstrap_session();

        $GLOBALS['CURRENT_MODULE'] = 'provoz';
        define('CB_EMBEDDED_MODULE', 'provoz');
        header('Content-Type: text/html; charset=utf-8');
        echo '<div class="provoz_objednavky_block" data-pp-block="objednavky_prehled" data-gn="1">';
        require __DIR__ . '/provoz/bloky/objednavky_prehled.php';
        echo '</div>';
        exit;
    }

    http_response_code(404);
    exit;
}

if (!empty($_SESSION['login_ok']) && (isset($_GET['helpdesk_action']) || isset($_POST['helpdesk_action']))) {
    cb_modul_nacti('helpdesk');
    exit;
}

if (
    !empty($_SESSION['login_ok'])
    && (
        isset($_SERVER['HTTP_X_COMEBACK_ADMIN_PRAVA'])
        || isset($_SERVER['HTTP_X_COMEBACK_ADMIN_INDIVIDUAL'])
    )
) {
    cb_modul_nacti('administrace');
    exit;
}

if (!empty($_SESSION['login_ok']) && isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE'])) {
    $cbShellModule = strtolower(trim((string)($_POST['cb_shell_module'] ?? 'provoz')));
    $cbShellModule = cb_modul_normalizuj($cbShellModule);
    foreach ($_POST as $cbShellParamKey => $cbShellParamValue) {
        $cbShellParamKey = (string)$cbShellParamKey;
        if (str_starts_with($cbShellParamKey, 'cb_shell_') || $cbShellParamKey === 'cb_helpdesk_source_module') {
            continue;
        }
        if (is_scalar($cbShellParamValue) || $cbShellParamValue === null) {
            $_GET[$cbShellParamKey] = (string)$cbShellParamValue;
        }
    }
    if ($cbShellModule === 'helpdesk') {
        $cbHelpdeskSourceModule = strtolower(trim((string)($_POST['cb_helpdesk_source_module'] ?? $_SESSION['cb_helpdesk_source_module'] ?? 'provoz')));
        $cbHelpdeskSourceModule = in_array($cbHelpdeskSourceModule, ['provoz', 'hr', 'smeny', 'ukoly'], true) ? $cbHelpdeskSourceModule : 'provoz';
        $_SESSION['cb_helpdesk_source_module'] = $cbHelpdeskSourceModule;
    }

    header('Content-Type: text/html; charset=utf-8');

    $GLOBALS['CURRENT_MODULE'] = $cbShellModule;
    require __DIR__ . '/common/includes/hlavicka.php';
    cb_modul_nacti($cbShellModule);
    exit;
}

if (!empty($_SESSION['login_ok']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $cbPostedModule = strtolower(trim((string)($_GET['m'] ?? '')));
    if ($cbPostedModule === 'hr') {
        cb_modul_nacti('hr');
        exit;
    }
}

if (!empty($_SESSION['login_ok'])) {
    $cbHasAsyncHeader = false;
    foreach (array_keys($_SERVER) as $cbServerKey) {
        if (strncmp((string)$cbServerKey, 'HTTP_X_COMEBACK_', 16) === 0) {
            $cbHasAsyncHeader = true;
            break;
        }
    }

    $cbIsProvozRequest = $cbHasAsyncHeader;

    if ($cbIsProvozRequest) {
        cb_modul_nacti('provoz');
        exit;
    }
}

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

if (!empty($_SESSION['login_ok'])) {
    $cbInitialModuleRaw = isset($_GET['m'])
        ? (string)$_GET['m']
        : (string)cb_user_setting('aktivni_modul', 'provoz');
    $cbInitialModule = strtolower(trim($cbInitialModuleRaw));
    $cbInitialModule = cb_modul_normalizuj($cbInitialModule);

    $GLOBALS['CURRENT_MODULE'] = $cbInitialModule;
    require_once __DIR__ . '/provoz/lib/asset_url.php';
    require_once __DIR__ . '/common/lib/pobocky_vyber.php';
    cb_pobocky_bootstrap_session();

    if ((string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL' && !empty($_SESSION['cb_local_after_login_sync'])) {
        $cbLoaderCssPath = __DIR__ . '/common/style/loader.css';
        $cbLoaderCssUrl = cb_public_url('style/loader.css') . '?v=' . (is_file($cbLoaderCssPath) ? (string)filemtime($cbLoaderCssPath) : '1');
        $cbLoaderJsPath = __DIR__ . '/common/js/loader.js';
        $cbLoaderJsUrl = cb_public_url('js/loader.js') . '?v=' . (is_file($cbLoaderJsPath) ? (string)filemtime($cbLoaderJsPath) : '1');
        ?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback - načítání</title>
  <link rel="icon" type="image/png" href="<?= h(cb_public_url('img/logo_comeback.png')) ?>">
  <link rel="stylesheet" href="<?= h($cbLoaderCssUrl) ?>">
  <style>
    html,
    body{margin:0;min-height:100%;background:#0f172a;}
  </style>
</head>
<body>
<?php require __DIR__ . '/common/includes/loader.php'; ?>
<script>
window.CB_ENDPOINT = <?= json_encode(cb_root_url(''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= h($cbLoaderJsUrl) ?>"></script>
<script src="<?= h(cb_asset_url('js/restia_online.js')) ?>"></script>
<script>
(function(){
  'use strict';
  var targetUrl = <?= json_encode(cb_login_target_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

  function localStep(name){
    return fetch(window.CB_ENDPOINT, {
      method: 'POST',
      headers: {
        'X-Comeback-Local-Login-Sync': name,
        'Accept': 'application/json'
      },
      credentials: 'same-origin'
    }).then(function(response){
      if (!response.ok) throw new Error('HTTP ' + response.status);
      return response.json();
    });
  }

  function finish(){
    localStep('done')
      .catch(function(){})
      .finally(function(){
        window.location.replace(targetUrl);
      });
  }

  if (window.CB_LOADER && typeof window.CB_LOADER.show === 'function') {
    window.CB_LOADER.show('Aktualizuji uživatele ze Směn ...');
  }

  localStep('users')
    .catch(function(error){
      if (window.console && window.console.warn) window.console.warn(error);
    })
    .then(function(){
      if (window.CB_RESTIA && typeof window.CB_RESTIA.run === 'function') {
        window.CB_ACTIVE_MAIN_MODULE = 'provoz';
        return window.CB_RESTIA.run({
          loaderText: 'Aktualizuji data z Restie ...'
        });
      }
      return null;
    })
    .catch(function(error){
      if (window.console && window.console.warn) window.console.warn(error);
    })
    .finally(finish);
})();
</script>
</body>
</html>
<?php
        exit;
    }

    $cbTitle = 'Comeback - IS';
    $cbFavicon = cb_module_asset_url('img/favicon_comeback.png', 'provoz');
    $cbShellUrl = cb_root_url('index.php');
    $cbPublicShellUrl = cb_root_url('');
    $cbSelectPobockyJsPath = __DIR__ . '/common/js/select_pobocky.js';
    $cbSelectPobockyJsUrl = cb_public_url('js/select_pobocky.js') . '?v=' . (is_file($cbSelectPobockyJsPath) ? (string)filemtime($cbSelectPobockyJsPath) : '1');
    $cbObdobiJsPath = __DIR__ . '/common/js/obdobi.js';
    $cbObdobiJsUrl = cb_public_url('js/obdobi.js') . '?v=' . (is_file($cbObdobiJsPath) ? (string)filemtime($cbObdobiJsPath) : '1');
    $cbSetProdlevaJsPath = __DIR__ . '/common/js/set_prodleva.js';
    $cbSetProdlevaJsUrl = cb_public_url('js/set_prodleva.js') . '?v=' . (is_file($cbSetProdlevaJsPath) ? (string)filemtime($cbSetProdlevaJsPath) : '1');
    $cbGnRefreshJsPath = __DIR__ . '/common/js/gn_refresh.js';
    $cbGnRefreshJsUrl = cb_public_url('js/gn_refresh.js') . '?v=' . (is_file($cbGnRefreshJsPath) ? (string)filemtime($cbGnRefreshJsPath) : '1');
    $cbThemeJsPath = __DIR__ . '/common/js/theme_level.js';
    $cbThemeJsUrl = cb_public_url('js/theme_level.js') . '?v=' . (is_file($cbThemeJsPath) ? (string)filemtime($cbThemeJsPath) : '1');
    $cbHrCssPath = __DIR__ . '/hr/style/hr.css';
    $cbHrCssUrl = cb_root_url('hr/style/hr.css') . '?v=' . (is_file($cbHrCssPath) ? (string)filemtime($cbHrCssPath) : '1');
    $cbHrJsPath = __DIR__ . '/hr/hr_js/hr.js';
    $cbHrJsUrl = cb_root_url('hr/hr_js/hr.js') . '?v=' . (is_file($cbHrJsPath) ? (string)filemtime($cbHrJsPath) : '1');
    $cbSmenyCssPath = __DIR__ . '/smeny/style/smeny.css';
    $cbSmenyCssUrl = cb_root_url('smeny/style/smeny.css') . '?v=' . (is_file($cbSmenyCssPath) ? (string)filemtime($cbSmenyCssPath) : '1');
    $cbUkolyCssPath = __DIR__ . '/ukoly/style/ukoly.css';
    $cbUkolyCssUrl = cb_root_url('ukoly/style/ukoly.css') . '?v=' . (is_file($cbUkolyCssPath) ? (string)filemtime($cbUkolyCssPath) : '1');
    $cbHelpdeskCssPath = __DIR__ . '/helpdesk/hl_style/helpdesk.css';
    $cbHelpdeskCssUrl = cb_root_url('helpdesk/hl_style/helpdesk.css') . '?v=' . (is_file($cbHelpdeskCssPath) ? (string)filemtime($cbHelpdeskCssPath) : '1');
    $cbAdministraceCssPath = __DIR__ . '/administrace/style/administrace.css';
    $cbAdministraceCssUrl = cb_root_url('administrace/style/administrace.css') . '?v=' . (is_file($cbAdministraceCssPath) ? (string)filemtime($cbAdministraceCssPath) : '1');
    $cbAdministracePravaSaveJsPath = __DIR__ . '/administrace/admin_js/admin_prava_roli_save.js';
    $cbAdministracePravaSaveJsUrl = cb_root_url('administrace/admin_js/admin_prava_roli_save.js') . '?v=' . (is_file($cbAdministracePravaSaveJsPath) ? (string)filemtime($cbAdministracePravaSaveJsPath) : '1');
    $cbAdministracePravaBlocksJsPath = __DIR__ . '/administrace/admin_js/admin_prava_roli_blocks.js';
    $cbAdministracePravaBlocksJsUrl = cb_root_url('administrace/admin_js/admin_prava_roli_blocks.js') . '?v=' . (is_file($cbAdministracePravaBlocksJsPath) ? (string)filemtime($cbAdministracePravaBlocksJsPath) : '1');
    $cbAdministraceIndividualSearchJsPath = __DIR__ . '/administrace/admin_js/admin_individualni_prava_search.js';
    $cbAdministraceIndividualSearchJsUrl = cb_root_url('administrace/admin_js/admin_individualni_prava_search.js') . '?v=' . (is_file($cbAdministraceIndividualSearchJsPath) ? (string)filemtime($cbAdministraceIndividualSearchJsPath) : '1');
    $cbAdministraceIndividualSaveJsPath = __DIR__ . '/administrace/admin_js/admin_individualni_prava_save.js';
    $cbAdministraceIndividualSaveJsUrl = cb_root_url('administrace/admin_js/admin_individualni_prava_save.js') . '?v=' . (is_file($cbAdministraceIndividualSaveJsPath) ? (string)filemtime($cbAdministraceIndividualSaveJsPath) : '1');
    $cbLoaderCssPath = __DIR__ . '/common/style/loader.css';
    $cbLoaderCssUrl = cb_public_url('style/loader.css') . '?v=' . (is_file($cbLoaderCssPath) ? (string)filemtime($cbLoaderCssPath) : '1');
    $cbLoaderJsPath = __DIR__ . '/common/js/loader.js';
    $cbLoaderJsUrl = cb_public_url('js/loader.js') . '?v=' . (is_file($cbLoaderJsPath) ? (string)filemtime($cbLoaderJsPath) : '1');
    $cbVisualModule = $cbInitialModule === 'helpdesk' ? 'helpdesk' : $cbInitialModule;
    $cbThemeLevel = max(0, min(6, (int)cb_user_setting('dark', 0)));
    require __DIR__ . '/common/includes/aplikace_layout.php';
    exit;
}

$cbLoginBackgroundFiles = glob(__DIR__ . '/common/img/pozadi_login/login_pozadi_*.png');
$cbLoginBackgroundCount = is_array($cbLoginBackgroundFiles) ? count($cbLoginBackgroundFiles) : 0;
$cbLoginBackgroundUrl = cb_public_url('img/login_pozadi.png');
$cbLoginBackgroundLabel = '';
if ($cbLoginBackgroundCount > 0) {
    $cbLoginBackgroundIndex = random_int(1, $cbLoginBackgroundCount);
    $cbLoginBackgroundFile = __DIR__ . '/common/img/pozadi_login/login_pozadi_' . $cbLoginBackgroundIndex . '.png';
    $cbLoginBackgroundUrl = cb_public_url('img/pozadi_login/login_pozadi_' . $cbLoginBackgroundIndex . '.png?v=' . filemtime($cbLoginBackgroundFile));
    $cbLoginBackgroundLabel = $cbLoginBackgroundIndex . '/' . $cbLoginBackgroundCount;
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback - přihlášení</title>
  <link rel="icon" type="image/png" href="<?= h(cb_public_url('img/logo_comeback.png')) ?>">
<link rel="stylesheet" href="<?= h(cb_public_url('style/modal_alert.css?v=' . filemtime(__DIR__ . '/common/style/modal_alert.css'))) ?>">
</head>
<body class="modal-page modal-login-page" style="--cb-login-bg: url('<?= h($cbLoginBackgroundUrl) ?>');">
<div class="modal-login-container">
<?php
if ($cb2faPending) {
    require_once __DIR__ . '/common/modaly/modal_overeni.php';
} elseif ($cbAuthOk) {
    require_once __DIR__ . '/common/lib/kontrola_registrace.php';
    if (!empty($_SESSION['login_ok'])) {
        echo '<script>window.location.href=' . json_encode(cb_login_target_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    }
} else {
    require_once __DIR__ . '/common/modaly/modal_login.php';
}
?>
</div>
</body>
</html>
