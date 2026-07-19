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
?>
<div id="cb-login-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="Přihlášení Comeback">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-logo" aria-hidden="true">
        <img src="<?= h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">Přihlášení Comeback</p>
        <p class="modal-sub">Použijte přihlašovací údaje ze systému Směny.</p>
      </div>
    </div>

    <form method="post" action="<?= h(cb_root_url('lib/login_smeny.php')) ?>" class="modal-form" id="cbLoginForm">
      <div class="modal-field">
        <label class="modal-label" for="cb_email">E-mail</label>
        <input class="modal-input"
               id="cb_email"
               name="email"
               type="email"
               autocomplete="username"
               placeholder="Email"
               required<?= $loginDisabled ?>>
      </div>

      <div class="modal-field">
        <label class="modal-label" for="cb_pass">Heslo</label>
        <input class="modal-input"
               id="cb_pass"
               name="heslo"
               type="password"
               autocomplete="current-password"
               placeholder="Heslo"
               required<?= $loginDisabled ?>>
      </div>

      <div class="modal-actions modal-actions-modules">
        <button class="modal-btn primary" type="submit" name="module" value="is"<?= $loginDisabled ?>>
          <span class="modal-btn-main">IS</span>
          <span class="modal-btn-sub">Informační systém</span>
        </button>
        <button class="modal-btn primary" type="submit" name="module" value="hr"<?= $loginDisabled ?>>
          <span class="modal-btn-main">HR</span>
          <span class="modal-btn-sub">Personální agenda</span>
        </button>
        <button class="modal-btn primary" type="submit" name="module" value="smeny"<?= $loginDisabled ?>>
          <span class="modal-btn-main">směny</span>
          <span class="modal-btn-sub">Plánování směn</span>
        </button>
      </div>

      <p class="modal-sub modal-url" style="margin-top:10px;">
        <span class="modal-db-state <?= h($loginDbClass) ?>"><?= h($loginDbText) ?></span>
        <span class="modal-url-main">URL: <code><?= h($aktualniUrl) ?></code></span>
      </p>
    </form>
  </div>
  <?php if (!empty($cbLoginBackgroundLabel)): ?>
  <p class="modal-login-count"><?= h((string)$cbLoginBackgroundLabel) ?></p>
  <?php endif; ?>
  <p class="modal-login-note">Případná podoba s kýmkoliv je čistě náhodná</p>
</div>
