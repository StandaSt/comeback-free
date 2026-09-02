<?php
/*
 * Ucel souboru: Vypise konfiguraci pro prohlizec a JS soubory spolecne HTML kostry.
 * Poradi skriptu je zavisle na soucasne aplikaci a odpovida puvodnimu rozlozeni.
 */
declare(strict_types=1);
?>
<?php // Koncovy bod pro komunikaci klienta se spolecnym shellem. ?>
<script>
window.CB_ENDPOINT = <?= json_encode($cbShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php // Knihovna grafu. ?>
<script src="<?= h(cb_asset_url('js/echarts.min.js')) ?>"></script>
<?php // Zakladni AJAX funkce aplikace. ?>
<script src="<?= h(cb_asset_url('js/ajax_core.js')) ?>"></script>
<?php // Data grafu modulu Provoz. ?>
<script src="<?= h(cb_public_url('js/data_grafu.js')) ?>"></script>
<?php // Spolecne tooltipy aplikace. ?>
<script src="<?= h(cb_public_url('js/tooltip.js') . '?v=' . (is_file(__DIR__ . '/../js/tooltip.js') ? (string)filemtime(__DIR__ . '/../js/tooltip.js') : '1')) ?>"></script>
<?php // Zmena velikosti grafu. ?>
<script src="<?= h(cb_public_url('js/resize_graf.js')) ?>"></script>
<?php // Online graf objednavek. ?>
<script src="<?= h(cb_asset_url('js/objednavky_online_graf.js')) ?>"></script>
<?php // Vykresleni grafu modulu Provoz. ?>
<script src="<?= h(cb_public_url('js/vykresleni_grafu.js')) ?>"></script>
<?php // Online data Restia. ?>
<script src="<?= h(cb_asset_url('js/restia_online.js')) ?>"></script>
<?php // Denni report Restia. ?>
<script src="<?= h(cb_asset_url('js/denni_report_restia.js')) ?>"></script>
<?php // Formular denniho reportu. ?>
<script src="<?= h(cb_asset_url('js/denni_report_form.js')) ?>"></script>
<?php // Osoby denniho reportu. ?>
<script src="<?= h(cb_asset_url('js/denni_report_osoby.js')) ?>"></script>
<?php // Vyber pobocky. ?>
<script src="<?= h($cbSelectPobockyJsUrl) ?>"></script>
<?php // Vyber obdobi. ?>
<script src="<?= h($cbObdobiJsUrl) ?>"></script>
<?php // Nastaveni prodlevy. ?>
<script src="<?= h($cbSetProdlevaJsUrl) ?>"></script>
<?php // Obnoveni bloku nacitanych postupne. ?>
<script src="<?= h($cbGnRefreshJsUrl) ?>"></script>
<?php // Prehled objednavek. ?>
<script src="<?= h(cb_asset_url('js/objednavky_prehled.js')) ?>"></script>
<?php // Uroven barevneho tematu. ?>
<script src="<?= h($cbThemeJsUrl) ?>"></script>
<?php // Filtry. ?>
<script src="<?= h(cb_asset_url('js/filtry.js')) ?>"></script>
<?php // Export prehledu smen. ?>
<script src="<?= h(cb_asset_url('js/prehled_smen_export.js')) ?>"></script>
<?php // E-mailovy export nezadanych dennich reportu. ?>
<script src="<?= h(cb_asset_url('js/nezadane_reporty_export.js')) ?>"></script>
<?php // Rozbaleni detailu. ?>
<script src="<?= h(cb_asset_url('js/rozbalovaci_detail.js')) ?>"></script>
<?php // Casovac odhlaseni. ?>
<script src="<?= h(cb_asset_url('js/casovac_odhlaseni.js')) ?>"></script>
<?php // Jednotne ovladani rucne zadanych dat. ?>
<script src="<?= h($cbDateInputJsUrl) ?>"></script>
<?php // Chovani modulu HR v prohlizeci. ?>
<script src="<?= h($cbHrJsUrl) ?>"></script>
<?php // Chovani modulu Helpdesk v prohlizeci. ?>
<script src="<?= h(cb_root_url('helpdesk/hl_js/hl_helpdesk.js')) ?>"></script>
<?php // Ulozeni prav role v Administraci. ?>
<script src="<?= h($cbAdministracePravaSaveJsUrl) ?>"></script>
<?php // Bloky prav role v Administraci. ?>
<script src="<?= h($cbAdministracePravaBlocksJsUrl) ?>"></script>
<?php // Vyhledani individualnich prav v Administraci. ?>
<script src="<?= h($cbAdministraceIndividualSearchJsUrl) ?>"></script>
<?php // Ulozeni individualnich prav v Administraci. ?>
<script src="<?= h($cbAdministraceIndividualSaveJsUrl) ?>"></script>
<?php // Pridani, editace a razeni ciselniku prav v Administraci. ?>
<script src="<?= h($cbAdministraceEditacePravJsUrl) ?>"></script>
<?php // AI analytik modulu Provoz. ?>
<script src="<?= h(cb_root_url('provoz/js/ai_analytik.js') . '?v=' . (is_file(__DIR__ . '/../../provoz/js/ai_analytik.js') ? (string)filemtime(__DIR__ . '/../../provoz/js/ai_analytik.js') : '1')) ?>"></script>
<?php // Konfigurace navigace mezi hlavni moduly. ?>
<script>
window.CB_MODULY_NAVIGACE = {
  shellUrl: <?= json_encode($cbShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  publicShellUrl: <?= json_encode($cbPublicShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  activeMainModule: <?= json_encode($cbInitialModule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  adminFirmaPridat: <?= function_exists('cb_pravo_ma') && cb_pravo_ma(105) ? 'true' : 'false' ?>,
  aiAnalytikAllowed: <?= function_exists('cb_pravo_ma') && is_array($_SESSION['prava_stav'] ?? null) && array_key_exists(210, $_SESSION['prava_stav']) && cb_pravo_ma(210) ? 'true' : 'false' ?>,
  initialAutoLoad: true
};
</script>
<?php // Klientska navigace mezi moduly. ?>
<script src="<?= h($cbModulyNavigaceJsUrl) ?>"></script>
