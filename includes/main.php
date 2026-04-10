<?php
// includes/main.php * Verze: V6 * Aktualizace: 06.03.2026
// Počet řádků: 36
// Předchozí počet řádků: 36

declare(strict_types=1);

/*
 * MAIN (obsah) – kostra mezi hlavičkou a patičkou
 *
 * Cíl:
 * - scroll je pouze uvnitř <main class="dash_box bg_modra">
 * - uvnitř main se renderuje obsah z aktuální include/page
 * - dashboard už nepoužívá žádný další vnořený obal pro scroll
 */
?>

<!-- MAIN START -->
<main class="dash_box bg_modra sirka100">
  <div class="dash_loader is-hidden" data-cb-dash-loader="1" aria-hidden="true">
    <div class="dash_loader_inner">
      <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback" class="dash_loader_logo">
      <p class="dash_loader_text">Aktualizuji obsah karet ...</p>
      <div class="dash_loader_time" data-cb-dash-loader-time>0.00 s</div>
      <div class="dash_loader_step" data-cb-dash-loader-step>0 / 0 uloženo: 0</div>
    </div>
  </div>

  <div data-cb-dash-content="1">
    <?php
    /*
     * Obsah renderuje zvolená include/page.
     * Rozložení dashboard karet řeší dashboard.php přes .dash_grid.
     */
    if (isset($file) && is_string($file) && $file !== '' && is_file($file)) {
        require $file;
    } else {
        echo '<section class="card_box ram_normal bg_bila zaobleni_12 odstup_vnitrni_14"><p>Obsah stránky nebyl nalezen.</p></section>';
    }
    ?>
  </div>
</main>
<!-- MAIN END -->

<?php
/* includes/main.php * Verze: V6 * Aktualizace: 06.03.2026 * Počet řádků: 36 */
// Konec souboru
