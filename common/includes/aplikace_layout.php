<?php
declare(strict_types=1);

$cbAplikaceRoot = dirname(__DIR__, 2);
$cbModulyNavigaceJsPath = $cbAplikaceRoot . '/common/js/moduly_navigace.js';
$cbModulyNavigaceJsUrl = cb_public_url('js/moduly_navigace.js') . '?v=' . (is_file($cbModulyNavigaceJsPath) ? (string)filemtime($cbModulyNavigaceJsPath) : '1');
?><!doctype html>
<html lang="cs" data-theme-level="<?= h((string)$cbThemeLevel) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($cbTitle) ?></title>
  <link rel="icon" type="image/png" href="<?= h($cbFavicon) ?>">
  <?php require_once $cbAplikaceRoot . '/provoz/lib/nacti_styly.php'; ?>
  <link rel="stylesheet" href="<?= h($cbHrCssUrl) ?>">
  <link rel="stylesheet" href="<?= h($cbSmenyCssUrl) ?>">
  <link rel="stylesheet" href="<?= h($cbUkolyCssUrl) ?>">
  <link rel="stylesheet" href="<?= h($cbHelpdeskCssUrl) ?>">
  <link rel="stylesheet" href="<?= h($cbAdministraceCssUrl) ?>">
  <link rel="stylesheet" href="<?= h($cbLoaderCssUrl) ?>">
</head>
<body class="cb-context--<?= h($cbVisualModule) ?>">
<main id="obal_main" class="obal_main cb-context--<?= h($cbVisualModule) ?>" data-obal-main="1">
<?php
    require_once __DIR__ . '/hlavicka.php';
?>
<?php
    cb_modul_nacti($cbInitialModule);
?>
</main>
<?php require __DIR__ . '/loader.php'; ?>
<script>
window.CB_ENDPOINT = <?= json_encode($cbShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= h($cbLoaderJsUrl) ?>"></script>
<script src="<?= h(cb_asset_url('js/echarts.min.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/ajax_core.js')) ?>"></script>
<script src="<?= h(cb_public_url('js/data_grafu.js')) ?>"></script>
<script src="<?= h(cb_public_url('js/tooltip.js')) ?>"></script>
<script src="<?= h(cb_public_url('js/resize_graf.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/objednavky_online_graf.js')) ?>"></script>
<script src="<?= h(cb_public_url('js/vykresleni_grafu.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/restia_online.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/denni_report_restia.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/denni_report_form.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/denni_report_osoby.js')) ?>"></script>
<script src="<?= h($cbSelectPobockyJsUrl) ?>"></script>
<script src="<?= h($cbObdobiJsUrl) ?>"></script>
<script src="<?= h($cbSetProdlevaJsUrl) ?>"></script>
<script src="<?= h($cbGnRefreshJsUrl) ?>"></script>
<script src="<?= h(cb_asset_url('js/objednavky_prehled.js')) ?>"></script>
<script src="<?= h($cbThemeJsUrl) ?>"></script>
<script src="<?= h(cb_asset_url('js/filtry.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/prehled_smen_export.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/rozbalovaci_detail.js')) ?>"></script>
<script src="<?= h(cb_asset_url('js/casovac_odhlaseni.js')) ?>"></script>
<script src="<?= h($cbHrJsUrl) ?>"></script>
<script src="<?= h(cb_root_url('helpdesk/hl_js/hl_helpdesk.js')) ?>"></script>
<script src="<?= h($cbAdministraceJsUrl) ?>"></script>
<script>
window.CB_MODULY_NAVIGACE = {
  shellUrl: <?= json_encode($cbShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  publicShellUrl: <?= json_encode($cbPublicShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  activeMainModule: <?= json_encode($cbInitialModule === 'helpdesk' ? ($_SESSION['cb_helpdesk_source_module'] ?? 'provoz') : $cbInitialModule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
};
</script>
<script src="<?= h($cbModulyNavigaceJsUrl) ?>"></script>
</body>
</html>
