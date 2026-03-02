<?php
// includes/central.php * Verze: V2 * Aktualizace: 1.3.2026

/*
 * CENTRAL (obsah)
 *
 * Nově:
 * - menu je součástí hlavičky (includes/hlavicka.php)
 * - žádný sidebar
 * - central drží jen obal + <main> (renderuje pages/<pageKey>.php)
 *
 * Volá / závisí na:
 * - proměnné z index.php: $cb_page_exists, $cb_page_file
 */

declare(strict_types=1);
?>

<div class="central">
  <div class="central-content">
    <main>
      <?php
      if (!empty($cb_page_exists) && !empty($cb_page_file) && is_file($cb_page_file)) {
          require $cb_page_file;
      } else {
          echo '<div class="page-head"><h2>Stránka nenalezena</h2></div>';
          echo '<section class="card"><p>Požadovaná stránka neexistuje.</p></section>';
      }
      ?>
    </main>
  </div>
</div>

<?php
/* includes/central.php * Verze: V2 * Aktualizace: 1.3.2026 * Počet řádků: 35 */
// Konec souboru
