<?php
// includes/dashboard.php * Verze: V2 * Aktualizace: 05.03.2026
declare(strict_types=1);

/*
 * DASHBOARD (makety pro layout + barvy)
 * - sklada bloky z /blocks
 * - bez titulku "Dashboard" (byl navic)
 */
?>

<div class="dash_wrap">
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
</div>
