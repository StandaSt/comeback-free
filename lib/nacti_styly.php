<?php
// lib/nacti_styly.php * Verze: V2 * Aktualizace: 28.2.2026

/*
 * Načtení CSS stylů do <head>
 *
 * Pořadí:
 * 1) nastavení proměnných (:root)
 * 2) layout skelet (nový obal)
 * 3) globální styly + existující moduly
 *
 * Volá / závisí na:
 * - cb_url() (sestavení URL cesty)
 * - h() (HTML escape)
 */

declare(strict_types=1);

?>
<!-- styly -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/nastaveni.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/layout.css')) ?>">

<link rel="stylesheet" href="<?= h(cb_url('style/1/global.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/hlavicka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/central.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/paticka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/menu.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/tabulky.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/menu_tlac.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/ikony_svg.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/modal_alert.css')) ?>">

<!-- page styly (globálně) -->
<link rel="stylesheet" href="<?= h(cb_url('style/1/pages/admin_ukazka.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/pages/hr_uzivatele.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/pages/obj_zakaznici.css')) ?>">
<link rel="stylesheet" href="<?= h(cb_url('style/1/pages/a_ukazka.css')) ?>">

<?php
/* lib/nacti_styly.php * Verze: V2 * Aktualizace: 28.2.2026 * Počet řádků: 43 */
// Konec souboru
