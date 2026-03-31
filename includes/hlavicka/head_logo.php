<?php
// includes/hlavicka/head_logo.php * Verze: V3 * Aktualizace: 08.03.2026
declare(strict_types=1);

$cbNow = new DateTimeImmutable('now');
$cbLogoDate = $cbNow->format('j.n.Y');
$cbLogoTime = $cbNow->format('H:i:s');
?>
<div class="head_logo_wrap gap_4 displ_flex flex_sloupec">
  <a class="head_logo ram_hlavicka bg_bila zaobleni_12 odstup_vnitrni_0 displ_flex jc_stred" href="<?= h(cb_url('')) ?>" aria-label="Comeback">
    <img class="head_logo_img displ_block sirka100" src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
  </a>
  <div class="head_logo_meta gap_2 displ_flex flex_sloupec" aria-live="off">
    <span class="head_logo_date txt_seda text_11 radek_1_05 displ_block" id="cbHeadLogoDate"><?= h($cbLogoDate) ?></span>
    <span class="head_logo_time txt_seda text_11 radek_1_05 displ_block" id="cbHeadLogoTime"><?= h($cbLogoTime) ?></span>
  </div>
</div>
<script>
(function () {
  var dateEl = document.getElementById('cbHeadLogoDate');
  var timeEl = document.getElementById('cbHeadLogoTime');
  if (!dateEl || !timeEl) return;

  function pad2(n) { return String(n).padStart(2, '0'); }

  function tick() {
    var now = new Date();
    dateEl.textContent = now.getDate() + '.' + (now.getMonth() + 1) + '.' + now.getFullYear();
    timeEl.textContent = pad2(now.getHours()) + ':' + pad2(now.getMinutes()) + ':' + pad2(now.getSeconds());
  }

  tick();
  window.setInterval(tick, 1000);
})();
</script>
