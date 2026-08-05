<?php
// index.php * Spolecny login Comeback
declare(strict_types=1);

require_once __DIR__ . '/common/lib/session_boot.php';
require_once __DIR__ . '/common/lib/app.php';
require_once __DIR__ . '/common/lib/system.php';
require_once __DIR__ . '/common/config/secrets.php';
require_once __DIR__ . '/common/lib/json_registrace.php';

cb_session_guard_entry();
require_once __DIR__ . '/provoz/lib/logout_handler.php';

$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cb2faPending = !empty($_SESSION['cb_2fa_token']);

if (!empty($_SESSION['login_ok'])) {
    require_once __DIR__ . '/common/lib/pobocky_vyber.php';
    require_once __DIR__ . '/common/lib/handle_set_period.php';
    require_once __DIR__ . '/common/lib/handle_set_pobocky.php';
}

if (!empty($_SESSION['login_ok']) && (isset($_GET['helpdesk_action']) || isset($_POST['helpdesk_action']))) {
    if (isset($_GET['cb_helpdesk_module']) || isset($_POST['cb_helpdesk_module'])) {
        $cbHelpdeskSourceModule = strtolower(trim((string)($_GET['cb_helpdesk_module'] ?? $_POST['cb_helpdesk_module'] ?? '')));
        if (in_array($cbHelpdeskSourceModule, ['provoz', 'hr', 'smeny', 'ukoly'], true)) {
            $_SESSION['cb_helpdesk_source_module'] = $cbHelpdeskSourceModule;
        }
    }

    $cbHelpdeskAction = trim((string)($_GET['helpdesk_action'] ?? $_POST['helpdesk_action'] ?? ''));
    $cbHelpdeskRoutes = [
        'detail' => __DIR__ . '/helpdesk/hl_ajax/hl_detail.php',
        'notifikace_nacist' => __DIR__ . '/helpdesk/hl_ajax/hl_notifikace_nacist.php',
        'notifikace_precteno' => __DIR__ . '/helpdesk/hl_ajax/hl_notifikace_precteno.php',
        'priloha_nahrat' => __DIR__ . '/helpdesk/hl_ajax/hl_priloha_nahrat.php',
        'sledovat' => __DIR__ . '/helpdesk/hl_ajax/hl_sledovat.php',
        'stav_tiketu' => __DIR__ . '/helpdesk/hl_ajax/hl_stav_tiketu.php',
        'stav_zmenit' => __DIR__ . '/helpdesk/hl_ajax/hl_stav_zmenit.php',
        'vytvorit' => __DIR__ . '/helpdesk/hl_ajax/hl_vytvorit.php',
        'zprava_pridat' => __DIR__ . '/helpdesk/hl_ajax/hl_zprava_pridat.php',
    ];
    if (!isset($cbHelpdeskRoutes[$cbHelpdeskAction])) {
        http_response_code(404);
        exit;
    }

    $GLOBALS['CURRENT_MODULE'] = 'helpdesk';
    define('CB_EMBEDDED_MODULE', 'helpdesk');
    require $cbHelpdeskRoutes[$cbHelpdeskAction];
    exit;
}

if (!empty($_SESSION['login_ok']) && isset($_SERVER['HTTP_X_COMEBACK_SHELL_MODULE'])) {
    $cbShellModule = strtolower(trim((string)($_POST['cb_shell_module'] ?? 'provoz')));
    if (!in_array($cbShellModule, ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk'], true)) {
        $cbShellModule = 'provoz';
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
        if (!in_array($cbHelpdeskSourceModule, ['provoz', 'hr', 'smeny', 'ukoly'], true)) {
            $cbHelpdeskSourceModule = 'provoz';
        }
        $_SESSION['cb_helpdesk_source_module'] = $cbHelpdeskSourceModule;
    }

    $GLOBALS['CURRENT_MODULE'] = $cbShellModule;
    define('CB_EMBEDDED_MODULE', $cbShellModule);
    header('Content-Type: text/html; charset=utf-8');

    if ($cbShellModule === 'hr') {
        require __DIR__ . '/hr/hr.php';
    } elseif ($cbShellModule === 'smeny') {
        require __DIR__ . '/smeny/smeny.php';
    } elseif ($cbShellModule === 'ukoly') {
        require __DIR__ . '/ukoly/ukoly.php';
    } elseif ($cbShellModule === 'helpdesk') {
        require __DIR__ . '/helpdesk/helpdesk.php';
    } else {
        if ((int)($_SESSION['cb_system']['zamek'] ?? 0) !== 1) {
            try {
                require_once __DIR__ . '/provoz/lib/restia_online_kontrola.php';
                if (function_exists('cb_restia_online_kontrola')) {
                    cb_restia_online_kontrola(false);
                }
                if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }
            } catch (Throwable $e) {
            }
        }
        require __DIR__ . '/provoz/provoz.php';
    }
    exit;
}

if (!empty($_SESSION['login_ok']) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $cbPostedModule = strtolower(trim((string)($_GET['m'] ?? '')));
    if ($cbPostedModule === 'hr') {
        $GLOBALS['CURRENT_MODULE'] = 'hr';
        define('CB_EMBEDDED_MODULE', 'hr');
        require __DIR__ . '/hr/hr.php';
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

    $cbIsProvozRequest =
        $cbHasAsyncHeader
        || isset($_GET['cb_card_id'])
        || isset($_GET['cb_load_max'])
        || isset($_GET['cb_restia_import_max'])
        || isset($_GET['helpdesk_action'])
        || isset($_POST['helpdesk_action']);

    if ($cbIsProvozRequest) {
        $GLOBALS['CURRENT_MODULE'] = 'provoz';
        define('CB_EMBEDDED_MODULE', 'provoz');
        require __DIR__ . '/provoz/provoz.php';
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
    $cbInitialModule = 'provoz';

    $GLOBALS['CURRENT_MODULE'] = $cbInitialModule;
    require_once __DIR__ . '/provoz/lib/asset_url.php';
    require_once __DIR__ . '/common/lib/pobocky_vyber.php';
    cb_pobocky_bootstrap_session();

    $cbTitle = 'Comeback - IS';
    $cbFavicon = cb_module_asset_url('img/favicon_comeback.png', 'provoz');
    $cbShellUrl = cb_root_url('index.php');
    $cbPublicShellUrl = cb_root_url('');
    $cbSelectPobockyJsPath = __DIR__ . '/common/js/select_pobocky.js';
    $cbSelectPobockyJsUrl = cb_public_url('js/select_pobocky.js') . '?v=' . (is_file($cbSelectPobockyJsPath) ? (string)filemtime($cbSelectPobockyJsPath) : '1');
    $cbHrCssPath = __DIR__ . '/hr/style/hr.css';
    $cbHrCssUrl = cb_root_url('hr/style/hr.css') . '?v=' . (is_file($cbHrCssPath) ? (string)filemtime($cbHrCssPath) : '1');
    $cbHrJsPath = __DIR__ . '/hr/hr_js/hr.js';
    $cbHrJsUrl = cb_root_url('hr/hr_js/hr.js') . '?v=' . (is_file($cbHrJsPath) ? (string)filemtime($cbHrJsPath) : '1');
    $cbSmenyCssPath = __DIR__ . '/smeny/style/smeny.css';
    $cbSmenyCssUrl = cb_root_url('smeny/style/smeny.css') . '?v=' . (is_file($cbSmenyCssPath) ? (string)filemtime($cbSmenyCssPath) : '1');
    $cbHelpdeskCssPath = __DIR__ . '/helpdesk/hl_style/helpdesk.css';
    $cbHelpdeskCssUrl = cb_root_url('helpdesk/hl_style/helpdesk.css') . '?v=' . (is_file($cbHelpdeskCssPath) ? (string)filemtime($cbHelpdeskCssPath) : '1');
    if ((int)($_SESSION['cb_system']['zamek'] ?? 0) !== 1) {
        try {
            require_once __DIR__ . '/provoz/lib/restia_online_kontrola.php';
            if (function_exists('cb_restia_online_kontrola')) {
                cb_restia_online_kontrola(false);
            }
            if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
        } catch (Throwable $e) {
        }
    }
    ?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($cbTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= h($cbFavicon) ?>">
  <?php require_once __DIR__ . '/provoz/lib/nacti_styly.php'; ?>
  <link rel="stylesheet" href="<?= h($cbHrCssUrl) ?>">
  <link rel="stylesheet" href="<?= h($cbSmenyCssUrl) ?>">
  <link rel="stylesheet" href="<?= h($cbHelpdeskCssUrl) ?>">
</head>
<body class="cb-context--<?= h($cbInitialModule === 'helpdesk' ? ($_SESSION['cb_helpdesk_source_module'] ?? 'provoz') : $cbInitialModule) ?>">
<?php
    require_once __DIR__ . '/common/includes/hlavicka.php';
?>
<main id="cb-module-root" class="cb-module-root--<?= h($cbInitialModule) ?> cb-context--<?= h($cbInitialModule === 'helpdesk' ? ($_SESSION['cb_helpdesk_source_module'] ?? 'provoz') : $cbInitialModule) ?>" data-cb-module-root="1">
<?php
    define('CB_EMBEDDED_MODULE', $cbInitialModule);
    if ($cbInitialModule === 'hr') {
        require __DIR__ . '/hr/hr.php';
    } elseif ($cbInitialModule === 'smeny') {
        require __DIR__ . '/smeny/smeny.php';
    } elseif ($cbInitialModule === 'ukoly') {
        require __DIR__ . '/ukoly/ukoly.php';
    } elseif ($cbInitialModule === 'helpdesk') {
        require __DIR__ . '/helpdesk/helpdesk.php';
    } else {
        require __DIR__ . '/provoz/provoz.php';
    }
?>
</main>
<script>
window.CB_ENDPOINT = <?= json_encode($cbShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
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
<script src="<?= h($cbSelectPobockyJsUrl) ?>"></script>
<script src="<?= h(cb_asset_url('js/filtry.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/prehled_smen_export.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/rozbalovaci_detail.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/casovac_odhlaseni.js')) ?>"></script>
<script src="<?= h($cbHrJsUrl) ?>"></script>
<script src="<?= h(cb_root_url('helpdesk/hl_js/hl_helpdesk.js')) ?>"></script>
<script>
(function(){
  'use strict';

  var root = document.querySelector('[data-cb-module-root="1"]');
  var shellUrl = <?= json_encode($cbShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var publicShellUrl = <?= json_encode($cbPublicShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  var activeMainModule = <?= json_encode($cbInitialModule === 'helpdesk' ? ($_SESSION['cb_helpdesk_source_module'] ?? 'provoz') : $cbInitialModule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  window.CB_ACTIVE_MAIN_MODULE = activeMainModule;
  if (!root) return;
  history.replaceState(null, '', publicShellUrl);

  function setActive(moduleName){
    if (moduleName === 'helpdesk') return;

    var links = Array.prototype.slice.call(document.querySelectorAll('[data-cb-module-link="1"]'));
    links.forEach(function(link){
      var active = link.getAttribute('data-cb-module') === moduleName;
      link.classList.toggle('is-active', active);
    });
  }

  function setRootModule(moduleName){
    root.classList.remove('cb-module-root--provoz', 'cb-module-root--hr', 'cb-module-root--smeny', 'cb-module-root--ukoly', 'cb-module-root--helpdesk');
    root.classList.add('cb-module-root--' + moduleName);

    if (moduleName !== 'helpdesk') {
      activeMainModule = moduleName;
      window.CB_ACTIVE_MAIN_MODULE = activeMainModule;
    }
    root.classList.remove('cb-context--provoz', 'cb-context--hr', 'cb-context--smeny', 'cb-context--ukoly');
    root.classList.add('cb-context--' + activeMainModule);
    document.body.classList.remove('cb-context--provoz', 'cb-context--hr', 'cb-context--smeny', 'cb-context--ukoly');
    document.body.classList.add('cb-context--' + activeMainModule);
  }

  function loadModule(moduleName, pushState, params){
    if (!moduleName) return;

    var body = new URLSearchParams();
    body.set('cb_shell_module', moduleName);
    if (params instanceof URLSearchParams) {
      params.forEach(function(value, key){
        if (key !== 'm') {
          body.set(key, value);
        }
      });
    }
    if (moduleName === 'helpdesk') {
      body.set('cb_helpdesk_source_module', activeMainModule);
      window.CB_HELPDESK_SOURCE_MODULE = activeMainModule;
    }

    fetch(shellUrl, {
      method: 'POST',
      headers: {
        'X-Comeback-Shell-Module': '1',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function(response){
        if (!response.ok) throw new Error('HTTP ' + response.status);
        return response.text();
      })
      .then(function(html){
        if (typeof window.__CB_HELPDESK_CLEANUP__ === 'function') {
          window.__CB_HELPDESK_CLEANUP__();
          window.__CB_HELPDESK_CLEANUP__ = null;
        }
        setRootModule(moduleName);
        root.innerHTML = html;
        if (moduleName === 'hr' && window.CB_HR_INIT) {
          window.CB_HR_INIT(root);
        }
        if (moduleName === 'helpdesk' && window.CB_HELPDESK_INIT) {
          window.CB_HELPDESK_INIT(root);
        }
        setActive(moduleName);
      })
      .catch(function(error){
        root.innerHTML = '<div class="cb-module-load-error">Modul se nepodařilo načíst: ' + String(error.message || error) + '</div>';
      });
  }

  window.CB_LOAD_MODULE = loadModule;

  document.addEventListener('click', function(event){
    var link = event.target.closest ? event.target.closest('[data-cb-module-link="1"]') : null;
    if (!link) return;

    var moduleName = link.getAttribute('data-cb-module') || '';
    if (!moduleName) return;

    event.preventDefault();
    loadModule(moduleName, true);
  });

  root.addEventListener('click', function(event){
    var link = event.target.closest ? event.target.closest('a[href]') : null;
    if (!link || link.getAttribute('data-cb-module-link') === '1') return;
    if (link.target && link.target !== '_self') return;
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;

    var url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (e) {
      return;
    }

    if (url.origin !== window.location.origin) return;
    if (url.pathname !== new URL(shellUrl, window.location.href).pathname) return;

    var moduleName = url.searchParams.get('m') || '';
    if (!['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk'].includes(moduleName)) return;

    event.preventDefault();
    loadModule(moduleName, true, url.searchParams);
  });

})();
</script>
</body>
</html>
<?php
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
