<?php
// includes/hlavicka/head_logo.php * Verze: V3 * Aktualizace: 08.03.2026
declare(strict_types=1);

$cbNow = new DateTimeImmutable('now');
$cbLogoDate = $cbNow->format('j.n.Y');
$cbLogoTime = $cbNow->format('H:i:s');
?>
<div class="head_logo_wrap">
  <a class="head_logo" href="<?= h(cb_url('')) ?>" aria-label="Comeback">
    <img class="head_logo_img" src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
  </a>
  <div class="head_logo_meta" aria-live="off">
    <span class="head_logo_date" id="cbHeadLogoDate"><?= h($cbLogoDate) ?></span>
    <span class="head_logo_time" id="cbHeadLogoTime"><?= h($cbLogoTime) ?></span>
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
