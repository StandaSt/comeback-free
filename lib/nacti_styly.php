<?php
// lib/nacti_styly.php * Verze: V5 * Aktualizace: 08.03.2026
// PoÄŤet Ĺ™ĂˇdkĹŻ: 50
// PĹ™edchozĂ­ poÄŤet Ĺ™ĂˇdkĹŻ: 45

/*
 * NaÄŤtenĂ­ CSS stylĹŻ do <head>
 *
 * ZĂˇsady:
 * - NENAÄŚĂŤTAT "page" CSS globĂˇlnÄ› (style/1/pages/*). Tyto soubory mohou rozbĂ­jet jinĂ© strĂˇnky.
 * - NENAÄŚĂŤTAT style/1/layout.css (legacy). NovĂ˝ layout je v global.css + hlavicka.css + main.css + paticka.css.
 *
 * VolĂˇ / zĂˇvisĂ­ na:
 * - cb_url() (sestavenĂ­ URL cesty)
 * - h() (HTML escape)
 */

declare(strict_types=1);

?>
<!-- styly -->
<!-- 1) promÄ›nnĂ© -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/nastaveni.css')) ?>">

<!-- 2) skelet strĂˇnky -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/global.css')) ?>">

<!-- 3) ÄŤĂˇsti layoutu -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/hlavicka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/main.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/paticka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/karty.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/karty/admin_karty.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/karty/zadani_reportu.css')) ?>">


<!-- moduly (globĂˇlnÄ› pouĹľĂ­vanĂ©) -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/menu.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/tabulky.css')) ?>">
<!-- <link rel="stylesheet" href="<?= h(cb_url('style/1/menu_tlac.css')) ?>">  -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/ikony_svg.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/modal_alert.css')) ?>">

<?php
/*
 * POZOR:
 * - style/1/pages/*.css se naÄŤĂ­tajĂ­ jen v konkrĂ©tnĂ­ch pages/*.php (pokud je potĹ™eba).
 * - style/1/layout.css je legacy a nesmĂ­ se naÄŤĂ­tat.
 */

/* lib/nacti_styly.php * Verze: V5 * Aktualizace: 08.03.2026 * PoÄŤet Ĺ™ĂˇdkĹŻ: 51
   konec souboru */
?>
