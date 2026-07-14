<?php
declare(strict_types=1);

$cbAlertUserName = isset($cbAlertUserName) ? (string)$cbAlertUserName : 'Neznámý uživatel';
$cbAlertInvalidUrl = isset($cbAlertInvalidUrl) ? (string)$cbAlertInvalidUrl : '';
?>
<div id="cb-url-alert-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="Informace">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-logo" aria-hidden="true">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title-alert">Přihlášený: <?= h($cbAlertUserName) ?></p>
        <p class="modal-sub">URL: <?= h($cbAlertInvalidUrl) ?> <span style="color: red; font-weight: bold;">není platná !</span></p>
      </div>
    </div>

    <div class="modal-form">
      <p class="modal-sub" style="margin-bottom: 16px;">
        Vaše snaha o testování systému byla zaznamenána.<br>
        Používejte prosím pouze standardní ovládání, ušetříte nám místo v databázi.<br><br>
        Děkujeme že používáte IS Comeback.<br><br>
       
      </p>

      <div class="modal-actions">
        <button id="cb-url-alert-btn" class="modal-btn primary" type="button">Pokračovat v práci</button>
      </div>
    </div>
  </div>
</div>

<script>
  (function () {
    var btn = document.getElementById('cb-url-alert-btn');
    var overlay = document.getElementById('cb-url-alert-overlay');
    var rootUrl = '<?= h(cb_url('/')) ?>';

    if (!btn) {
      return;
    }

    btn.addEventListener('click', function () {
      if (window.history && typeof window.history.replaceState === 'function') {
        window.history.replaceState(null, '', rootUrl);
        if (overlay) {
          overlay.style.display = 'none';
        }
        return;
      }
      window.location.replace(rootUrl);
    });
  })();
</script>
