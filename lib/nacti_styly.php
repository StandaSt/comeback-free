<?php
// lib/nacti_styly.php * Verze: V6 * Aktualizace: 09.03.2026
// Pocet radku: 51
// Predchozi pocet radku: 51

/*
 * Nacteni CSS stylu do <head>
 *
 * Zasady:
 * - Nenacitat "page" CSS globalne (style/1/pages/*). Tyto soubory mohou rozbijet jine stranky.
 * - Nenacitat style/1/layout.css (legacy). Novy layout je v global.css + hlavicka.css + main.css + paticka.css.
 *
 * Zavislosti:
 * - cb_url() (sestaveni URL cesty)
 * - h() (HTML escape)
 */

declare(strict_types=1);

?>
<!-- styly -->
<!-- 1) skelet stranky -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/global.css')) ?>">

<!-- 2) casti layoutu -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/hlavicka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/main.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/paticka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/karty/karty.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/karty/admin_karty.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/karty/zadani_reportu.css')) ?>">

<!-- moduly (globalne pouzivane) -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/menu.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/tabulky.css')) ?>">
<!-- <link rel="stylesheet" href="<?= h(cb_url('style/1/menu_tlac.css')) ?>">  -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/ikony_svg.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/modal_alert.css')) ?>">

<?php
/*
 * POZOR:
 * - style/1/pages/*.css se nacitaji jen v konkretnich pages/*.php (pokud je potreba).
 * - style/1/layout.css je legacy a nesmi se nacitat.
 */

/* lib/nacti_styly.php * Verze: V6 * Aktualizace: 09.03.2026 * Pocet radku: 51
   konec souboru */
?>
