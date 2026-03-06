<?php
// includes/dashboard.php * Verze: V4 * Aktualizace: 06.03.2026
// Počet řádků: 52
// Předchozí počet řádků: 52

declare(strict_types=1);

/*
 * DASHBOARD
 * - skládá bloky z /blocks
 * - bez dalšího vnořeného .dash_box / .dash_wrap
 * - rozložení řídí pouze .dash_grid a .dash_col_*
 */
?>

<div class="dash_grid">
  <section class="dash_col_4 dash_card card_blue">
    <?php require __DIR__ . '/../blocks/blok_02_trzba.php'; ?>
  </section>

  <section class="dash_col_4 dash_card card_green">
    <?php require __DIR__ . '/../blocks/blok_04_zisk.php'; ?>
  </section>

  <section class="dash_col_4 dash_card card_cyan">
    <?php require __DIR__ . '/../blocks/blok_05_stav_systemu.php'; ?>
  </section>

  <section class="dash_col_8 dash_card card_orange">
    <?php require __DIR__ . '/../blocks/blok_06_report.php'; ?>
  </section>

  <section class="dash_col_4 dash_card card_purple">
    <?php require __DIR__ . '/../blocks/blok_07_trend.php'; ?>
  </section>

  <section class="dash_col_12 dash_card card_blue">
    <?php require __DIR__ . '/../blocks/blok_12_top_polozky.php'; ?>
  </section>

  <section class="dash_col_4 dash_card card_orange">
    <?php require __DIR__ . '/../blocks/blok_16_kategorie.php'; ?>
  </section>

  <section class="dash_col_8 dash_card card_green">
    <?php require __DIR__ . '/../blocks/blok_23_tabulka_check.php'; ?>
  </section>
</div>

<?php
/* includes/dashboard.php * Verze: V4 * Aktualizace: 06.03.2026 * Počet řádků: 52 */
// Konec souboru
