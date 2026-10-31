<?php
/*
 * Ucel souboru: Vypise konfiguraci pro prohlizec a JS soubory spolecne HTML kostry.
 * E-mail se inicializuje pouze ve vlastni PP a chrani rozepsane odpovedi.
 * Poradi skriptu je zavisle na soucasne aplikaci a odpovida puvodnimu rozlozeni.
 * Historie Franty ma oddelenou obsluhu zobrazeni a navratu do stranky.
 * Menu a jeho oprávnění dodává společná PHP příprava HTML šablon; konfigurace neobsahuje druhý seznam položek.
 * Směny a E-mail při plném otevření použijí hotový serverový obsah; ostatní moduly si zachovávají úvodní obnovu dat.
 * Vlastní profil používá společnou delegovanou obsluhu formulářů a zobrazení hesel.
 * Sdělení správce mají společnou kartu a obsluhu náhledu příjemců i archivu.
 * Klient Restie dostava priznak jednorazove kontroly az po nacteni prihlaseneho IS.
 * Přehled členů rolí má vlastní delegovaný skript, nezávislý na ukládání práv.
 */
declare(strict_types=1);

// MAIN se cte ze samostatne historie; povoleni pobocek zustava v hr_pracoviste.
require_once __DIR__ . '/../lib/hr_pracoviste_main.php';
$cbNavUser = $_SESSION['cb_user'] ?? [];
$cbNavUserId = is_array($cbNavUser) ? (int)($cbNavUser['id_user'] ?? 0) : 0;
$cbNavMainBranchId = 0;
if ($cbNavUserId > 0) {
    $cbNavMainBranchId = cb_hr_main_id(db(), $cbNavUserId);
}
?>
<?php // Koncovy bod pro komunikaci klienta se spolecnym shellem. ?>
<script>
window.CB_ENDPOINT = <?= json_encode($cbShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.CB_CRF_TOKEN = <?= json_encode(function_exists('cb_crf_token') ? cb_crf_token() : '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.CB_PC_SESSION_TOKEN = <?= json_encode((string)($_SESSION['cb_pc_session_token'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
window.CB_RESTIA_AFTER_LOGIN = <?= !empty($_SESSION['cb_restia_po_loginu']) ? 'true' : 'false' ?>;
// Fronta patří skutečnému loginu, nikoliv převzatému profilu práv.
window.CB_AKTIVITA = <?= json_encode($cbNavUserId>0 ? ['url'=>cb_root_url('index.php?cb_aktivita=1'),'owner'=>(string)(int)($_SESSION['cb_id_login'] ?? 0).':'.$cbNavUserId] : null,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) ?>;
</script>
<?php // Jednotná ochrana zápisových požadavků. ?>
<script src="<?= h(cb_public_url('js/profil.js') . '?v=' . (string)filemtime(__DIR__ . '/../js/profil.js')) ?>"></script>
<script src="<?= h(cb_public_url('js/ochrana_crf.js') . '?v=' . (is_file(__DIR__ . '/../js/ochrana_crf.js') ? (string)filemtime(__DIR__ . '/../js/ochrana_crf.js') : '1')) ?>"></script>
<script src="<?= h(cb_public_url('js/admin_sdeleni.js').'?v='.(string)filemtime(__DIR__.'/../js/admin_sdeleni.js')) ?>" defer></script>
<?php // Společné dávkování činnosti; moduly dodávají významové události ze svého ovládání. ?>
<script src="<?= h(cb_public_url('js/user_aktivita.js').'?v='.(string)filemtime(__DIR__.'/../js/user_aktivita.js')) ?>"></script>
<?php // Jednotné zpracování chybových odpovědí v prohlížeči. ?>
<script src="<?= h($cbChybyJsUrl) ?>"></script>
<?php // Knihovna grafu. ?>
<script src="<?= h(cb_asset_url('js/echarts.min.js')) ?>"></script>
<?php // Zakladni AJAX funkce aplikace. ?>
<script src="<?= h(cb_asset_url('js/ajax_core.js')) ?>"></script>
<?php // Pravidelne potvrzeni zive session tohoto okna. ?>
<script src="<?= h(cb_public_url('js/pc_session.js') . '?v=' . (is_file(__DIR__ . '/../js/pc_session.js') ? (string)filemtime(__DIR__ . '/../js/pc_session.js') : '1')) ?>"></script>
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
<?php // Směny ve formuláři denního reportu výroby sdílí formát času s reportem restaurace. ?>
<script src="<?= h(cb_asset_url('js/denni_report_vyroba.js')) ?>"></script>
<?php // Společný panel místního období. ?>
<script src="<?= h($cbObdobiJsUrl) ?>"></script>
<?php // Obnoveni bloku nacitanych postupne. ?>
<script src="<?= h($cbGnRefreshJsUrl) ?>"></script>
<?php // Prehled objednavek. ?>
<script src="<?= h(cb_asset_url('js/objednavky_prehled.js')) ?>"></script>
<?php // Uroven barevneho tematu. ?>
<script src="<?= h($cbThemeJsUrl) ?>"></script>
<?php // Filtry. ?>
<?php // Kontrola trzeb se inicializuje i po vymene pracovni plochy Provozu. ?>
<script src="<?= h(cb_module_asset_url('js/kontrola_trzeb.js', 'provoz') . '?v=' . (string)filemtime(__DIR__ . '/../../provoz/js/kontrola_trzeb.js')) ?>"></script>
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
<script src="<?= h(cb_root_url('helpdesk/hl_js/hl_helpdesk.js') . '?v=' . (is_file(__DIR__ . '/../../helpdesk/hl_js/hl_helpdesk.js') ? (string)filemtime(__DIR__ . '/../../helpdesk/hl_js/hl_helpdesk.js') : '1')) ?>"></script>
<?php // Ovládání týdenních požadavků v modulu Směny. ?>
<script src="<?= h(cb_root_url('smeny/js/pozadavky.js') . '?v=' . (is_file(__DIR__ . '/../../smeny/js/pozadavky.js') ? (string)filemtime(__DIR__ . '/../../smeny/js/pozadavky.js') : '1')) ?>"></script>
<?php // Ovládání editoru šablon směn. ?>
<script src="<?= h(cb_root_url('smeny/js/sablony.js') . '?v=' . (is_file(__DIR__ . '/../../smeny/js/sablony.js') ? (string)filemtime(__DIR__ . '/../../smeny/js/sablony.js') : '1')) ?>"></script>
<?php // Společná preference vzhledu a klasická denní mřížka pro šablony i plánování. ?>
<script src="<?= h(cb_root_url('smeny/js/styly.js') . '?v=' . (string)filemtime(__DIR__ . '/../../smeny/js/styly.js')) ?>"></script>
<?php // Životnost zámku, aktivita a bezpečný odchod ze stránky plánování. ?>
<script src="<?= h(cb_root_url('smeny/js/planovani_zamek.js') . '?v=' . (string)filemtime(__DIR__ . '/../../smeny/js/planovani_zamek.js')) ?>"></script>
<?php // Klikací plánování zaměstnanců do směn. ?>
<script src="<?= h(cb_root_url('smeny/js/planovani.js') . '?v=' . (is_file(__DIR__ . '/../../smeny/js/planovani.js') ? (string)filemtime(__DIR__ . '/../../smeny/js/planovani.js') : '1')) ?>"></script>
<?php // Adaptér běží po směnových editorech a čte jejich výsledný DOM i hodnoty. ?>
<script src="<?= h(cb_root_url('smeny/js/aktivita.js').'?v='.(string)filemtime(__DIR__.'/../../smeny/js/aktivita.js')) ?>"></script>
<?php // Otevření oznámení směn a souhlasu v modálu. ?>
<script src="<?= h(cb_root_url('smeny/js/oznameni.js') . '?v=' . (string)filemtime(__DIR__ . '/../../smeny/js/oznameni.js')) ?>"></script>
<?php // Jednotné uživatelské texty chyb Administrace. ?>
<script src="<?= h($cbAdministraceChybyJsUrl) ?>"></script>
<?php // Ulozeni prav role v Administraci. ?>
<script src="<?= h($cbAdministracePravaSaveJsUrl) ?>"></script>
<?php // Bloky prav role v Administraci. ?>
<script src="<?= h($cbAdministracePravaBlocksJsUrl) ?>"></script>
<script src="<?= h($cbAdministraceRoleUzivateleJsUrl) ?>"></script>
<?php // Vyhledani individualnich prav v Administraci. ?>
<script src="<?= h($cbAdministraceIndividualSearchJsUrl) ?>"></script>
<?php // Ulozeni individualnich prav v Administraci. ?>
<script src="<?= h($cbAdministraceIndividualSaveJsUrl) ?>"></script>
<?php // Pridani, editace a razeni ciselniku prav v Administraci. ?>
<script src="<?= h($cbAdministraceEditacePravJsUrl) ?>"></script>
<?php // Správa uživatelů v Administraci. ?>
<script src="<?= h($cbAdministraceUzivateleJsUrl) ?>"></script>
<?php // Průběh ručního načítání katalogu Restia v Administraci. ?>
<script src="<?= h($cbAdministraceRestiaKatalogJsUrl) ?>"></script>
<?php // Průběh jednorázového převodu historických Google reportů. ?>
<script src="<?= h($cbAdministraceGoogleHistorieJsUrl) ?>"></script>
<?php // AI analytik modulu Provoz. ?>
<?php // Puvodni zadani a upresneni patri do jednoho vizualniho bloku se stejnym cislem. ?>
<script src="<?= h(cb_root_url('provoz/js/ai_analytik_vysledky.js') . '?v=' . filemtime(__DIR__ . '/../../provoz/js/ai_analytik_vysledky.js')) ?>"></script>
<script src="<?= h(cb_root_url('provoz/js/ai_analytik.js') . '?v=' . (is_file(__DIR__ . '/../../provoz/js/ai_analytik.js') ? (string)filemtime(__DIR__ . '/../../provoz/js/ai_analytik.js') : '1')) ?>"></script>
<?php // Historie Franty sdili renderer; stav pro navrat se inicializuje az po nacteni obou. ?>
<script src="<?= h(cb_root_url('provoz/js/ai_analytik_historie.js') . '?v=' . filemtime(__DIR__ . '/../../provoz/js/ai_analytik_historie.js')) ?>"></script>
<script src="<?= h(cb_root_url('provoz/js/ai_analytik_historie_stav.js') . '?v=' . filemtime(__DIR__ . '/../../provoz/js/ai_analytik_historie_stav.js')) ?>"></script>
<?php // Konfigurace navigace mezi hlavni moduly. ?>
<script>
window.CB_MODULY_NAVIGACE = {
  shellUrl: <?= json_encode($cbShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  publicShellUrl: <?= json_encode($cbPublicShellUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  activeMainModule: <?= json_encode($cbInitialModule, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
  denniReportMainBranchId: <?= $cbNavMainBranchId ?>,
  <?php // Směny už byly vykreslené serverem, včetně výsledku POST. Druhý GET by zahodil flash a znovu přestavěl PP. ?>
  initialAutoLoad: <?= in_array($cbInitialModule, ['smeny','email'], true) ? 'false' : 'true' ?>
};
</script>
<?php // E-mail reaguje take na vymenu pracovni plochy. ?>
<script src="<?= h(cb_root_url('email/email.js').'?v='.filemtime(__DIR__.'/../../email/email.js')) ?>" defer></script>
<?php // Klientska navigace mezi moduly. ?>
<script src="<?= h($cbModulyNavigaceJsUrl) ?>"></script>
<?php // Responzivni chovani spolecne hlavicky a menu. ?>
<script src="<?= h(cb_public_url('js/responzivni_shell.js') . '?v=' . (is_file(__DIR__ . '/../js/responzivni_shell.js') ? (string)filemtime(__DIR__ . '/../js/responzivni_shell.js') : '1')) ?>"></script>
