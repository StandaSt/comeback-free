<?php
// lib/nacti_styly.php * Verze: V4 * Aktualizace: 03.03.2026
// Počet řádků: 50
// Předchozí počet řádků: 45

/*
 * Načtení CSS stylů do <head>
 *
 * Zásady:
 * - NENAČÍTAT "page" CSS globálně (style/1/pages/*). Tyto soubory mohou rozbíjet jiné stránky.
 * - NENAČÍTAT style/1/layout.css (legacy). Nový layout je v global.css + hlavicka.css + central.css + paticka.css.
 *
 * Volá / závisí na:
 * - cb_url() (sestavení URL cesty)
 * - h() (HTML escape)
 */

declare(strict_types=1);

?>
<!-- styly -->
<!-- 1) proměnné -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/nastaveni.css')) ?>">

<!-- 2) skelet stránky -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/global.css')) ?>">

<!-- 3) části layoutu -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/hlavicka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/central.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/karty.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/paticka.css')) ?>">

<!-- moduly (globálně používané) -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/menu.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/tabulky.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/menu_tlac.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/ikony_svg.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/modal_alert.css')) ?>">

<?php
/*
 * POZOR:
 * - style/1/pages/*.css se načítají jen v konkrétních pages/*.php (pokud je potřeba).
 * - style/1/layout.css je legacy a nesmí se načítat.
 */

/* lib/nacti_styly.php * Verze: V4 * Aktualizace: 03.03.2026 * Počet řádků: 50
   konec souboru */
?>
