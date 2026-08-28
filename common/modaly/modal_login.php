<?php
declare(strict_types=1);

require_once __DIR__ . '/../funkce/last_aktualizace_systemu.php';

cb_last_aktualizace_systemu();

$aktualniUrl = cb_url_abs('');
$loginDbOk = !empty($cbLoginDbOk);
$loginDbName = trim((string)($cbLoginDbName ?? '---'));
if ($loginDbName === '') {
    $loginDbName = '---';
}
$loginDbText = 'DB ' . $loginDbName . ($loginDbOk ? ' OK' : ' nepřístupná');
$loginDbClass = $loginDbOk ? 'is-ok' : 'is-bad';
$loginDisabled = $loginDbOk ? '' : ' disabled';
$loginFlash = trim((string)($_SESSION['cb_flash'] ?? ''));
$loginShowUrl = (string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL';
unset($_SESSION['cb_flash']);
?>
<div id="cb-login-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="Přihlášení Comeback">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-logo" aria-hidden="true">
        <img src="<?= h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">Vstup do<br>IS Comeback</p>
        <p class="modal-sub">Použijte přihlašovací údaje ze systému Směny.</p>
      </div>
    </div>

    <form method="post" action="<?= h(cb_root_url('common/lib/login_smeny.php')) ?>" class="modal-form" id="cbLoginForm">
      <div class="modal-field">
        <label class="modal-label" for="cb_email">Email:</label>
        <input class="modal-input"
               id="cb_email"
               name="email"
               type="email"
               autocomplete="username"
               placeholder="Email"
               required<?= $loginDisabled ?>>
      </div>

      <div class="modal-field">
        <label class="modal-label" for="cb_pass">Heslo:</label>
        <input class="modal-input"
               id="cb_pass"
               name="heslo"
               type="password"
               autocomplete="current-password"
               placeholder="Heslo"
               required<?= $loginDisabled ?>>
      </div>
      <input type="hidden" name="module" value="provoz">

      <div class="modal-actions">
        <button class="modal-btn primary" type="submit"<?= $loginDisabled ?>>
          <span class="modal-btn-main">Přihlásit</span>
        </button>
      </div>
      <p class="modal-login-status" id="cbLoginStatus" aria-live="polite"><?= h($loginFlash) ?></p>

      <?php if ($loginShowUrl): ?>
      <p class="modal-sub modal-url">
        <span class="modal-db-state <?= h($loginDbClass) ?>"><?= h($loginDbText) ?></span>
        <span class="modal-url-main">LOCAL</span>
      </p>
      <?php endif; ?>
    </form>
  </div>
  <?php if (!empty($cbLoginBackgroundLabel)): ?>
  <p class="modal-login-count"><?= h((string)$cbLoginBackgroundLabel) ?></p>
  <?php endif; ?>
  <p class="modal-login-note">Případná podoba s kýmkoliv je čistě náhodná</p>
</div>
<script>
(function(){
  'use strict';
  var form = document.getElementById('cbLoginForm');
  var status = document.getElementById('cbLoginStatus');
  if (!form || !status) return;

  form.addEventListener('submit', function(){
    var button = form.querySelector('button[type="submit"]');
    status.textContent = 'Ověřuji přihlašovací údaje ...';
    if (button instanceof HTMLButtonElement) {
      button.disabled = true;
      button.classList.add('is-waiting');
    }
  });
})();
</script>
