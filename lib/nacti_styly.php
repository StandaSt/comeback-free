<?php
// lib/nacti_styly.php * Verze: V1 * Aktualizace: 24.2.2026

/*
 * Načtení CSS stylů do <head>
 *
 * Co dělá:
 * - vypíše <link rel="stylesheet"> pro základní styly projektu (style/1)
 * - obsahuje i vybrané page styly načítané globálně
 *
 * Volá / závisí na:
 * - cb_url() (sestavení URL cesty)
 * - h() (HTML escape)
 *
 * Pozn.:
 * - soubor se includuje z includes/hlavicka.php uvnitř <head>
 */

declare(strict_types=1);

?>
<!-- styly -->
 <link rel="stylesheet" href="<?= h(cb_url('style/1/pages/a_ukazka.css')) ?>">
 <link rel="stylesheet" href="<?= h(cb_url('style/1/pages/admin_ukazka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/nastaveni.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/global.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/hlavicka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/central.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/paticka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/menu.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/tabulky.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/pages/hr_uzivatele.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/pages/obj_zakaznici.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/menu_tlac.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/ikony_svg.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/modal_alert.css')) ?>">

<?php
/* lib/nacti_styly.php * Verze: V1 * Aktualizace: 24.2.2026 * Počet řádků: 37 */
// Konec souboru