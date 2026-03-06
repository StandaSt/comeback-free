<?php
// includes/main.php * Verze: V6 * Aktualizace: 06.03.2026
// Počet řádků: 36
// Předchozí počet řádků: 36

declare(strict_types=1);

/*
 * MAIN (obsah) – kostra mezi hlavičkou a patičkou
 *
 * Cíl:
 * - scroll je pouze uvnitř <main class="dash_box">
 * - uvnitř main se renderuje obsah z aktuální include/page
 * - dashboard už nepoužívá žádný další vnořený obal pro scroll
 */
?>

<!-- MAIN START -->
<main class="dash_box">
  <?php
  /*
   * Obsah renderuje zvolená include/page.
   * Rozložení dashboard karet řeší dashboard.php přes .dash_grid.
   */
  if (isset($file) && is_string($file) && $file !== '' && is_file($file)) {
      require $file;
  } else {
      echo '<section class="card_box"><p>Obsah stránky nebyl nalezen.</p></section>';
  }
  ?>
</main>
<!-- MAIN END -->

<?php
/* includes/main.php * Verze: V6 * Aktualizace: 06.03.2026 * Počet řádků: 36 */
// Konec souboru
