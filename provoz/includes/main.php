<?php
// includes/main.php * Verze: V7 * Aktualizace: 03.06.2026
// Počet řádků: 36
// Předchozí počet řádků: 36

declare(strict_types=1);

/*
 * MAIN (obsah) – kostra mezi hlavičkou a patičkou
 *
 * Cíl:
 * - pokud je obsah vyšší než okno, scrolluje běžně okno prohlížeče
 * - uvnitř main se renderuje obsah z aktuální include/page
 * - dashboard nepoužívá vlastní vnitřní scroll
 */
?>

<!-- MAIN START -->
<?php
?>
<main class="dash_box bg_modra sirka100">
  <div data-cb-dash-content="1">
    <?php
    /*
     * Obsah renderuje zvolená include/page.
     * Rozložení dashboard karet řeší dashboard.php přes .dash_grid.
     */
    if (isset($file) && is_string($file) && $file !== '' && is_file($file)) {
        try {
            require $file;
        } catch (Throwable $e) {
            cb_chyba_oznam($e, [
                'module' => 'PROVOZ',
                'action' => 'Načtení obsahu stránky',
            ]);

            echo '<section class="card_box ram_normal bg_bila zaobleni_12 odstup_vnitrni_14">';
            echo '<p class="card_text txt_cervena text_tucny odstup_vnejsi_0">' . h(cb_chyba_verejna_zprava()) . '</p>';
            echo '</section>';
        }
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
