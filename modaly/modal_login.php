<?php
declare(strict_types=1);
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

    <form method="post" action="<?= h(cb_url('lib/login_smeny.php')) ?>" class="modal-form">
      <div class="modal-field">
        <label class="modal-label" for="cb_email">E-mail</label>
        <input class="modal-input"
               id="cb_email"
               name="email"
               type="email"
               autocomplete="username"
               required>
      </div>

      <div class="modal-field">
        <label class="modal-label" for="cb_pass">Heslo</label>
        <input class="modal-input"
               id="cb_pass"
               name="heslo"
               type="password"
               autocomplete="current-password"
               required>
      </div>

      <div class="modal-actions">
        <button class="modal-btn primary" type="submit">Přihlásit</button>
      </div>
    </form>
  </div>
</div>

<script>
  (function(){
    var email = document.getElementById('cb_email');
    if (email) {
      setTimeout(function(){ email.focus(); }, 60);
    }
  })();
</script>
