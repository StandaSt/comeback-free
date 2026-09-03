<?php
/*
 * Ucel souboru: Hlavni vstup aplikace. Ridi prihlaseni, session, smerovani modulu
 * a casne zpracovani pozadavku pred jakymkoli HTML vystupem.
 */
declare(strict_types=1);

require_once __DIR__ . '/common/lib/session_boot.php';
require_once __DIR__ . '/common/lib/ochrana_crf.php';
require_once __DIR__ . '/common/lib/app.php';
require_once __DIR__ . '/common/lib/system.php';
require_once __DIR__ . '/common/config/secrets.php';
require_once __DIR__ . '/common/lib/json_registrace.php';
require_once __DIR__ . '/common/lib/prvni_vstup.php';
require_once __DIR__ . '/common/lib/moduly.php';
require_once __DIR__ . '/common/lib/nastaveni_uzivatele.php';
require_once __DIR__ . '/common/lib/local_login_sync.php';

cb_session_guard_entry();
require_once __DIR__ . '/provoz/lib/logout_handler.php';

if (!empty($_SESSION['login_ok'])) {
    require_once __DIR__ . '/common/db/db_prava.php';
    $cbRightsUser = $_SESSION['cb_user'] ?? [];
    cb_db_prava_nacti_do_session(
        db(),
        is_array($cbRightsUser) ? (int)($cbRightsUser['id_user'] ?? 0) : 0,
        is_array($cbRightsUser) ? (int)($cbRightsUser['id_role'] ?? 0) : 0
    );
}

if (!empty($_SESSION['login_ok']) && cb_crf_zapisova_metoda()) {
    cb_crf_vyzaduj();
}

$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cb2faPending = !empty($_SESSION['cb_2fa_token']);

if (!empty($_SESSION['login_ok'])) {
    require_once __DIR__ . '/common/lib/pobocky_vyber.php';
    require_once __DIR__ . '/common/lib/handle_set_period.php';
    require_once __DIR__ . '/common/lib/handle_set_pobocky.php';
}

cb_nastaveni_uzivatele_vyrid_post();
cb_local_login_sync_vyrid();

if (empty($_SESSION['login_ok']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['cb_action'] ?? '') === 'zapomenute_heslo') {
    try {
        $cbResetEmailSent = cb_prvni_vstup_obnoveni_hesla_odeslat(db(), trim((string)($_POST['email'] ?? '')));
        $_SESSION['cb_flash'] = $cbResetEmailSent
            ? 'E-mail byl odeslán'
            : "Neznámý E-mail,\nkontaktujte admina IS";
    } catch (Throwable $e) {
        $_SESSION['cb_flash'] = $e->getMessage();
    }
    header('Location: ' . cb_root_url('?zapomenute_heslo=1'), true, 303);
    exit;
}

if (empty($_SESSION['login_ok']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['cb_action'] ?? '') === 'prvni_vstup_ulozit') {
    try {
        cb_prvni_vstup_uloz(db(), $_POST);
        header('Location: ' . cb_login_target_url(), true, 303);
        exit;
    } catch (Throwable $e) {
        $_SESSION['cb_flash'] = $e->getMessage();
        header('Location: ' . cb_root_url(''), true, 303);
        exit;
    }
}

if (empty($_SESSION['login_ok']) && isset($_GET['prvni_vstup'])) {
    if (!cb_prvni_vstup_over_token(db(), trim((string)$_GET['prvni_vstup']))) {
        $_SESSION['cb_flash'] = 'Odkaz pro první vstup není platný nebo již vypršel.';
        header('Location: ' . cb_root_url(''), true, 303);
        exit;
    }
    header('Location: ' . cb_root_url(''), true, 303);
    exit;
}

if (
    !empty($_SESSION['login_ok'])
    && ($_SERVER['REQUEST_METHOD'] ?? '') === 'PUT'
    && isset($_SERVER['HTTP_X_COMEBACK_REPORT_PROMENNE'])
) {
    $GLOBALS['CURRENT_MODULE'] = 'provoz';
    define('CB_EMBEDDED_MODULE', 'provoz');
    require_once __DIR__ . '/provoz/lib/report_promenne.php';
    cb_report_promenne_handle_json_request();
    exit;
}

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
    if ((string)($_SERVER['HTTP_X_COMEBACK_PP_ONLY'] ?? '') === '1' && !defined('CB_PP_ONLY')) {
        define('CB_PP_ONLY', true);
    }
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
    $cbShellOutputLevel = ob_get_level();
    ob_start();
    try {
        cb_modul_nacti($cbShellModule);
        ob_end_flush();
    } catch (Throwable $e) {
        while (ob_get_level() > $cbShellOutputLevel) {
            ob_end_clean();
        }

        $cbShellError = get_class($e)
            . ': ' . $e->getMessage()
            . ' in ' . $e->getFile()
            . ':' . (string)$e->getLine();
        error_log('[shell_module] ' . $cbShellError);

        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $cbShellError;
    }
    exit;
}

if (!empty($_SESSION['login_ok']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $cbPostedModule = strtolower(trim((string)($_GET['m'] ?? '')));
    if (in_array($cbPostedModule, ['hr', 'administrace'], true)) {
        cb_modul_nacti($cbPostedModule);
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
        // Lokální synchronizace používá stejný střízlivý loader jako pracovní plocha PP.
        $cbPageLoaderCssPath = __DIR__ . '/common/style/page_loader.css';
        $cbPageLoaderCssUrl = cb_public_url('style/page_loader.css') . '?v=' . (is_file($cbPageLoaderCssPath) ? (string)filemtime($cbPageLoaderCssPath) : '1');
        ?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback - načítání</title>
  <link rel="icon" type="image/png" href="<?= h(cb_public_url('img/logo_comeback.png')) ?>">
  <link rel="stylesheet" href="<?= h(cb_asset_url('style/global.css')) ?>">
  <link rel="stylesheet" href="<?= h($cbPageLoaderCssUrl) ?>">
  <style>
    html,
    body{margin:0;min-height:100%;background:var(--cb-bg-page);}
  </style>
</head>
<body>
<main class="pp is-page-loading" style="min-height:100vh;">
  <div class="cb_page_loader" role="status" aria-live="polite" aria-atomic="true">
    <span class="cb_page_loader_text">Inicializuji systém ...</span>
    <span class="cb_page_loader_detail">Aktualizuji uživatele a data</span>
  </div>
</main>
<script>
window.CB_ENDPOINT = <?= json_encode(cb_root_url(''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
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

  localStep('users')
    .catch(function(error){
      if (window.console && window.console.warn) window.console.warn(error);
    })
    .then(function(){
      if (window.CB_RESTIA && typeof window.CB_RESTIA.run === 'function') {
        window.CB_ACTIVE_MAIN_MODULE = 'provoz';
        return window.CB_RESTIA.run();
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

    require __DIR__ . '/common/lib/priprava_kostry_stranky.php';
    require __DIR__ . '/common/includes/kostra_stranky.php';
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
} elseif (cb_prvni_vstup_zbyva() > 0) {
    require_once __DIR__ . '/common/modaly/modal_prvni_vstup.php';
} elseif ($cbAuthOk) {
    require_once __DIR__ . '/common/lib/kontrola_registrace.php';
    if (!empty($_SESSION['login_ok'])) {
        echo '<script>window.location.href=' . json_encode(cb_login_target_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    }
} elseif (isset($_GET['zapomenute_heslo'])) {
    require_once __DIR__ . '/common/modaly/modal_ztracene_heslo.php';
} else {
    require_once __DIR__ . '/common/modaly/modal_login.php';
}
?>
</div>
</body>
</html>
