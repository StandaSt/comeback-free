<?php
// index.php * Spolecny login Comeback
declare(strict_types=1);

require_once __DIR__ . '/lib/session_boot.php';
require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/system.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/json_registrace.php';

cb_session_guard_entry();

$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cb2faPending = !empty($_SESSION['cb_2fa_token']);

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
    header('Location: ' . cb_login_target_url());
    exit;
}

$cbLoginBackgroundFiles = glob(__DIR__ . '/img/pozadi_login/login_pozadi_*.png');
$cbLoginBackgroundCount = is_array($cbLoginBackgroundFiles) ? count($cbLoginBackgroundFiles) : 0;
$cbLoginBackgroundUrl = cb_url('img/login_pozadi.png');
if ($cbLoginBackgroundCount > 0) {
    $cbLoginBackgroundIndex = random_int(1, $cbLoginBackgroundCount);
    $cbLoginBackgroundUrl = cb_url('img/pozadi_login/login_pozadi_' . $cbLoginBackgroundIndex . '.png');
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback - přihlášení</title>
  <link rel="icon" type="image/png" href="<?= h(cb_url('img/logo_comeback.png')) ?>">
  <link rel="stylesheet" href="<?= h(cb_url('style/1/modal_alert.css')) ?>">
</head>
<body class="modal-page modal-login-page" style="--cb-login-bg: url('<?= h($cbLoginBackgroundUrl) ?>');">
<div class="modal-login-container">
<?php
if ($cb2faPending) {
    require_once __DIR__ . '/modaly/modal_overeni.php';
} elseif ($cbAuthOk) {
    require_once __DIR__ . '/lib/kontrola_registrace.php';
    if (!empty($_SESSION['login_ok'])) {
        echo '<script>window.location.href=' . json_encode(cb_login_target_url(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    }
} else {
    require_once __DIR__ . '/modaly/modal_login.php';
}
?>
</div>
</body>
</html>
