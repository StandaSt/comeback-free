<?php
// index.php * Verze: V25 * Aktualizace: 28.04.2026

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$time_count = 1;
require_once __DIR__ . '/lib/app.php';
require_once __DIR__ . '/lib/system.php';
require_once __DIR__ . '/config/secrets.php';
require_once __DIR__ . '/lib/pobocky_vyber.php';
require_once __DIR__ . '/lib/card_json_response.php';
require_once __DIR__ . '/lib/handle_set_period.php';
require_once __DIR__ . '/lib/handle_set_card_mode.php';
require_once __DIR__ . '/lib/handle_unlock_all_card_pos.php';
require_once __DIR__ . '/lib/handle_set_card_position.php';
require_once __DIR__ . '/lib/post_prg_redirect.php';
require_once __DIR__ . '/lib/asset_url.php';

$cbTitle = 'Comeback - IS';
$cbFavicon = cb_url('img/favicon_comeback.png');

cb_pobocky_bootstrap_session();

$cbLoginOk = !empty($_SESSION['login_ok']);

require_once __DIR__ . '/lib/detektuj_neplatnou_url.php';
require_once __DIR__ . '/lib/logout_handler.php';
require_once __DIR__ . '/lib/json_registrace.php';
require_once __DIR__ . '/lib/post_akce.php';

$pageKey = 'dashboard';
$file = __DIR__ . '/includes/dashboard.php';
$cbStartupLoaderText = trim((string)($_SESSION['cb_initial_loader_text'] ?? ''));
if ($cbStartupLoaderText !== '') {
    unset($_SESSION['cb_initial_loader_text']);
}

$cbStartupLoaderHtml = '';
$cbShowStartupLoader = !empty($_SESSION['login_ok']);
if ($cbShowStartupLoader) {
    ob_start();
    require __DIR__ . '/includes/loaders/dashboard.php';
    $cbStartupLoaderHtml = trim((string)ob_get_clean());
    if ($cbStartupLoaderHtml !== '') {
        $cbStartupLoaderHtml = preg_replace(
            '~<div class="dash_loader is-hidden" data-cb-loader-mode="dashboard" aria-hidden="true">~',
            '<div class="dash_loader" data-cb-loader-mode="dashboard" aria-hidden="false" data-cb-loader-visible="1">',
            $cbStartupLoaderHtml,
            1
        ) ?? $cbStartupLoaderHtml;
        if ($cbStartupLoaderText !== '') {
            $cbStartupLoaderHtml = preg_replace(
                '~<p class="dash_loader_text">.*?</p>~s',
                '<p class="dash_loader_text">' . h($cbStartupLoaderText) . '</p>',
                $cbStartupLoaderHtml,
                1
            ) ?? $cbStartupLoaderHtml;
        }
    }
}

require_once __DIR__ . '/includes/log_a_404.php';

require_once __DIR__ . '/lib/request_dispatch.php';
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

<?php if ($cbShowStartupLoader && $cbStartupLoaderHtml !== ''): ?>
<div id="cb-startup-loader" class="dash_box bg_modra sirka100 is-dashboard-loading" data-cb-startup-text="<?= h($cbStartupLoaderText) ?>" style="position:fixed;left:0;top:0;right:0;bottom:0;z-index:12000;padding:0 12px;background-clip:content-box;overflow:hidden;">
  <?= $cbStartupLoaderHtml ?>
</div>
<script src="<?= h(cb_url('js/loader_show.js')) ?>"></script>
<script src="<?= h(cb_url('js/loader_timer.js')) ?>"></script>
<?php endif; ?>
<div class="container bg_modra displ_flex sirka100">
<?php

if ($cbLoginOk) {
    require_once __DIR__ . '/includes/hlavicka.php';
    require_once __DIR__ . '/modaly/modal_overeni.php';
    require_once __DIR__ . '/lib/kontrola_registrace.php';
    require_once __DIR__ . '/lib/restia_online_kontrola.php';

    $cb_page_exists = $cbPageExists;
    $cb_page_file = $file;

    require_once __DIR__ . '/includes/main.php';
    require_once __DIR__ . '/includes/paticka.php';
} else {
    require_once __DIR__ . '/modaly/modal_login.php';
}

?>
</div>


<?php if ($cbLoginOk): ?>
<script src="<?= h(cb_asset_url('js/echarts.min.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/ajax_core.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/ajax_karta_max.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_min_max.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_grafy.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_min_nano.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_hlavicka.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_restia.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_form.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/karty_report_person.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/select_pobocky.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/filtry.js')) ?>"></script>
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
