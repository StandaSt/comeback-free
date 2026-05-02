<?php
declare(strict_types=1);

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
<div id="cb-login-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="Přihlášení do IS Comeback">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-logo" aria-hidden="true">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">Přihlášení do IS Comeback</p>
        <p class="modal-sub">Použijte přihlašovací údaje ze systému Směny.</p>
      </div>
    </div>

    <form method="post" action="<?= h(cb_url('lib/login_smeny.php')) ?>" class="modal-form" id="cbLoginForm">
      <div class="modal-field">
        <label class="modal-label" for="cb_email">E-mail</label>
        <input class="modal-input"
               id="cb_email"
               name="email"
               type="email"
               autocomplete="username"
               required<?= $loginDisabled ?>>
      </div>

      <div class="modal-field">
        <label class="modal-label" for="cb_pass">Heslo</label>
        <input class="modal-input"
               id="cb_pass"
               name="heslo"
               type="password"
               autocomplete="current-password"
               required<?= $loginDisabled ?>>
      </div>

      <div class="modal-actions">
        <button class="modal-btn primary" type="submit" id="cbLoginSubmit"<?= $loginDisabled ?>>Přihlásit</button>
      </div>

      <p class="modal-sub modal-url" style="margin-top:10px;">
        <span class="modal-db-state <?= h($loginDbClass) ?>"><?= h($loginDbText) ?></span>
        <span class="modal-url-main">URL: <code><?= h($aktualniUrl) ?></code></span>
      </p>
    </form>
  </div>
</div>

<script>
  (function(){
    var form = document.getElementById('cbLoginForm');
    var email = document.getElementById('cb_email');
    var pass = document.getElementById('cb_pass');
    var submit = document.getElementById('cbLoginSubmit');

    if (form && submit) {
      form.addEventListener('submit', function(){
        submit.textContent = 'Ověřuji přihlášení...';
        submit.classList.add('is-waiting');
        submit.disabled = true;
        if (email) {
          email.readOnly = true;
        }
        if (pass) {
          pass.readOnly = true;
        }
      });
    }

    if (email) {
      setTimeout(function(){ email.focus(); }, 60);
    }
  })();
</script>

