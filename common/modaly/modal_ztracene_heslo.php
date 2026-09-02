<?php
declare(strict_types=1);

require_once __DIR__ . '/../funkce/last_aktualizace_systemu.php';

cb_last_aktualizace_systemu();

$loginDbOk = !empty($cbLoginDbOk);
$loginDisabled = $loginDbOk ? '' : ' disabled';
$loginFlash = trim((string)($_SESSION['cb_flash'] ?? ''));
$forgotPasswordSent = $loginFlash === 'E-mail byl odeslán';
$forgotPasswordUnknown = $loginFlash === "Neznámý E-mail,\nkontaktujte admina IS";
$forgotPasswordRedirect = $forgotPasswordSent || $forgotPasswordUnknown;
unset($_SESSION['cb_flash']);
?>
<div id="cb-login-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="Ztracené heslo do Comeback">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-logo" aria-hidden="true">
        <img src="<?= h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">Ztracené heslo<br>pro IS Comeback</p>
      </div>
    </div>

    <form method="post" action="<?= h(cb_root_url('')) ?>" class="modal-form" id="cbForgotPasswordForm">
      <input type="hidden" name="cb_action" value="zapomenute_heslo">
      <p class="modal-sub">Zadejte Váš e-mail pro přihlášení do IS.<br>Poté zkontrolujte Vaší emailovou schránku,<br>najdete tam odkaz pro nastavení nového hesla.</p>
      <div class="modal-field">
        <label class="modal-label" for="cb_email">Email:</label>
        <input class="modal-input" id="cb_email" name="email" type="email" autocomplete="email" placeholder="Email" required<?= $loginDisabled ?>>
      </div>
      <div class="modal-actions">
        <button class="modal-btn primary" type="submit"<?= $loginDisabled ?>><span class="modal-btn-main">Resetovat heslo</span></button>
      </div>
      <p class="modal-login-link"><a href="<?= h(cb_root_url('')) ?>">Zpět k přihlášení</a></p>
      <p class="modal-login-status<?= ($forgotPasswordSent || $forgotPasswordUnknown) ? ' is-result' : '' ?>" aria-live="polite"><?= nl2br(h($loginFlash)) ?></p>
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
  var form = document.getElementById('cbForgotPasswordForm');
  if (!form) return;

  form.addEventListener('submit', function(){
    var button = form.querySelector('button[type="submit"]');
    if (button instanceof HTMLButtonElement) {
      button.disabled = true;
      button.classList.add('is-waiting');
    }
  });

  <?php if ($forgotPasswordRedirect): ?>
  window.setTimeout(function(){
    window.location.href = <?= json_encode(cb_root_url(''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  }, 15000);
  <?php endif; ?>
})();
</script>
