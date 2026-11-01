<?php
declare(strict_types=1);
/* Co-Link: hlavni sablona komunikatoru sklada jednoucelove casti pri zachovani puvodniho HTML. */
if(!defined('CO_LINK_VIEW')) { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="cs"><head>
<?php require __DIR__ . '/../co_includes/co_hlavicka.php'; ?>
<?php require __DIR__ . '/../co_includes/co_styly.php'; ?>
</head><body>
<?php require __DIR__ . '/../co_includes/co_oznameni.php'; ?>
<?php require __DIR__ . '/../co_includes/co_uvitani.php'; ?>
<main id="app" class="app" hidden>
<?php require __DIR__ . '/../co_includes/co_navigace.php'; ?>
  <section class="chat" aria-label="Otevřená konverzace">
<?php require __DIR__ . '/../co_includes/co_konverzace_hlavicka.php'; ?>
<?php require __DIR__ . '/../co_includes/co_hledani.php'; ?>
<?php require __DIR__ . '/../co_includes/co_zpravy.php'; ?>
<?php require __DIR__ . '/../co_includes/co_psani.php'; ?>
  </section>
</main>
<?php require __DIR__ . '/../co_includes/co_domovska_sablona.php'; ?>
<?php require __DIR__ . '/../co_modaly/co_panel.php'; ?>
<?php require __DIR__ . '/../co_includes/co_skripty.php'; ?>
</body></html>
