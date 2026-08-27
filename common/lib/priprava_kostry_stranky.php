<?php
/*
 * Ucel souboru: Pripravi data pro spolecnou HTML kostru prihlasene aplikace.
 * Soubor nema zadny HTML vystup. Ocekava pripraveny aktivni modul a funkce pro sestaveni URL.
 */
declare(strict_types=1);

/* Zakladni identita aplikace a adresy spolecneho shellu. */
$cbTitle = 'Comeback - IS';
$cbFavicon = cb_module_asset_url('img/favicon_comeback.png', 'provoz');
$cbShellUrl = cb_root_url('index.php');
$cbPublicShellUrl = cb_root_url('');
$cbAplikaceRoot = dirname(__DIR__, 2);

/* Spolecne skripty s verzi podle casu posledni zmeny souboru. */
$cbSelectPobockyJsPath = $cbAplikaceRoot . '/common/js/select_pobocky.js';
$cbSelectPobockyJsUrl = cb_public_url('js/select_pobocky.js') . '?v=' . (is_file($cbSelectPobockyJsPath) ? (string)filemtime($cbSelectPobockyJsPath) : '1');
$cbObdobiJsPath = $cbAplikaceRoot . '/common/js/obdobi.js';
$cbObdobiJsUrl = cb_public_url('js/obdobi.js') . '?v=' . (is_file($cbObdobiJsPath) ? (string)filemtime($cbObdobiJsPath) : '1');
$cbSetProdlevaJsPath = $cbAplikaceRoot . '/common/js/set_prodleva.js';
$cbSetProdlevaJsUrl = cb_public_url('js/set_prodleva.js') . '?v=' . (is_file($cbSetProdlevaJsPath) ? (string)filemtime($cbSetProdlevaJsPath) : '1');
$cbGnRefreshJsPath = $cbAplikaceRoot . '/common/js/gn_refresh.js';
$cbGnRefreshJsUrl = cb_public_url('js/gn_refresh.js') . '?v=' . (is_file($cbGnRefreshJsPath) ? (string)filemtime($cbGnRefreshJsPath) : '1');
$cbThemeJsPath = $cbAplikaceRoot . '/common/js/theme_level.js';
$cbThemeJsUrl = cb_public_url('js/theme_level.js') . '?v=' . (is_file($cbThemeJsPath) ? (string)filemtime($cbThemeJsPath) : '1');
$cbModulyNavigaceJsPath = $cbAplikaceRoot . '/common/js/moduly_navigace.js';
$cbModulyNavigaceJsUrl = cb_public_url('js/moduly_navigace.js') . '?v=' . (is_file($cbModulyNavigaceJsPath) ? (string)filemtime($cbModulyNavigaceJsPath) : '1');
$cbDateInputJsPath = $cbAplikaceRoot . '/common/js/date_input.js';
$cbDateInputJsUrl = cb_public_url('js/date_input.js') . '?v=' . (is_file($cbDateInputJsPath) ? (string)filemtime($cbDateInputJsPath) : '1');
$cbLoaderCssPath = $cbAplikaceRoot . '/common/style/loader.css';
$cbLoaderCssUrl = cb_public_url('style/loader.css') . '?v=' . (is_file($cbLoaderCssPath) ? (string)filemtime($cbLoaderCssPath) : '1');
$cbLoaderJsPath = $cbAplikaceRoot . '/common/js/loader.js';
$cbLoaderJsUrl = cb_public_url('js/loader.js') . '?v=' . (is_file($cbLoaderJsPath) ? (string)filemtime($cbLoaderJsPath) : '1');

/* Modulove styly a skripty s verzi podle casu posledni zmeny souboru. */
$cbHrCssPath = $cbAplikaceRoot . '/hr/style/hr.css';
$cbHrCssUrl = cb_root_url('hr/style/hr.css') . '?v=' . (is_file($cbHrCssPath) ? (string)filemtime($cbHrCssPath) : '1');
$cbHrJsPath = $cbAplikaceRoot . '/hr/hr_js/hr.js';
$cbHrJsUrl = cb_root_url('hr/hr_js/hr.js') . '?v=' . (is_file($cbHrJsPath) ? (string)filemtime($cbHrJsPath) : '1');
$cbSmenyCssPath = $cbAplikaceRoot . '/smeny/style/smeny.css';
$cbSmenyCssUrl = cb_root_url('smeny/style/smeny.css') . '?v=' . (is_file($cbSmenyCssPath) ? (string)filemtime($cbSmenyCssPath) : '1');
$cbUkolyCssPath = $cbAplikaceRoot . '/ukoly/style/ukoly.css';
$cbUkolyCssUrl = cb_root_url('ukoly/style/ukoly.css') . '?v=' . (is_file($cbUkolyCssPath) ? (string)filemtime($cbUkolyCssPath) : '1');
$cbHelpdeskCssPath = $cbAplikaceRoot . '/helpdesk/hl_style/helpdesk.css';
$cbHelpdeskCssUrl = cb_root_url('helpdesk/hl_style/helpdesk.css') . '?v=' . (is_file($cbHelpdeskCssPath) ? (string)filemtime($cbHelpdeskCssPath) : '1');
$cbAdministraceCssPath = $cbAplikaceRoot . '/administrace/style/administrace.css';
$cbAdministraceCssUrl = cb_root_url('administrace/style/administrace.css') . '?v=' . (is_file($cbAdministraceCssPath) ? (string)filemtime($cbAdministraceCssPath) : '1');
$cbAdministracePravaSaveJsPath = $cbAplikaceRoot . '/administrace/admin_js/admin_prava_roli_save.js';
$cbAdministracePravaSaveJsUrl = cb_root_url('administrace/admin_js/admin_prava_roli_save.js') . '?v=' . (is_file($cbAdministracePravaSaveJsPath) ? (string)filemtime($cbAdministracePravaSaveJsPath) : '1');
$cbAdministracePravaBlocksJsPath = $cbAplikaceRoot . '/administrace/admin_js/admin_prava_roli_blocks.js';
$cbAdministracePravaBlocksJsUrl = cb_root_url('administrace/admin_js/admin_prava_roli_blocks.js') . '?v=' . (is_file($cbAdministracePravaBlocksJsPath) ? (string)filemtime($cbAdministracePravaBlocksJsPath) : '1');
$cbAdministraceIndividualSearchJsPath = $cbAplikaceRoot . '/administrace/admin_js/admin_individualni_prava_search.js';
$cbAdministraceIndividualSearchJsUrl = cb_root_url('administrace/admin_js/admin_individualni_prava_search.js') . '?v=' . (is_file($cbAdministraceIndividualSearchJsPath) ? (string)filemtime($cbAdministraceIndividualSearchJsPath) : '1');
$cbAdministraceIndividualSaveJsPath = $cbAplikaceRoot . '/administrace/admin_js/admin_individualni_prava_save.js';
$cbAdministraceIndividualSaveJsUrl = cb_root_url('administrace/admin_js/admin_individualni_prava_save.js') . '?v=' . (is_file($cbAdministraceIndividualSaveJsPath) ? (string)filemtime($cbAdministraceIndividualSaveJsPath) : '1');

/* Hodnoty pro vizualni kontext a tema celeho shellu. */
$cbVisualModule = $cbInitialModule === 'helpdesk' ? 'helpdesk' : $cbInitialModule;
$cbThemeLevel = max(0, min(6, (int)cb_user_setting('dark', 0)));
