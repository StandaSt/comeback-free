<?php
// includes/main.php * Verze: V4 * Aktualizace: 05.03.2026
// Počet řádků: 44
// Předchozí počet řádků: 45

/*
 * MAIN (obsah) – KOSTRA DASHBOARDU
 *
 * Cíl:
 * - Hlavička a patička se nehýbou (řeší body flex + dash_wrap scroll).
 * - Scroll je pouze uvnitř <main class="dash_wrap">.
 * - Uvnitř main je jen layoutový obal <div class="dash_grid">, ve kterém stránky typicky renderují karty.
 *
 * Volá / závisí na:
 * - proměnné z index.php: $cb_page_exists, $cb_page_file
 */

declare(strict_types=1);
?>

<!-- MAIN START: dashboard kostra -->
<main class="dash_wrap">
  <?php
  /*
   * dash_grid = layoutový obal pro rozložení karet.
   * Pozn.: Neřeší vzhled karet, jen jejich umístění.
   */
  ?>
  <div class="dash_grid">
    <?php
    if (!empty($cb_page_exists) && !empty($cb_page_file) && is_file($cb_page_file)) {
        require $cb_page_file;
    } else {
        echo '<div class="page-head"><h2>Stránka nenalezena</h2></div>';
        echo '<section class="card"><p>Požadovaná stránka neexistuje.</p></section>';
    }
    ?>
  </div>
</main>
<!-- main END -->

<?php
/* includes/main.php * Verze: V4 * Aktualizace: 05.03.2026 * Počet řádků: 45 */
// Konec souboru
