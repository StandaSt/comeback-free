<?php
// includes/login_modal.php * Verze: V3 * Aktualizace: 27.2.2026
declare(strict_types=1);

/*
 * LOGIN MODÁL (PC)
 *
 * Co dělá:
 * - vykreslí modální okno s formulářem pro přihlášení (email + heslo)
 * - odesílá POST na lib/login_smeny.php
 * - po vykreslení nastaví fokus (kurzor) do pole E-mail
 *
 * Pozn.:
 * - vzhled řeší společné CSS v style/1/modal_alert.css (třídy .modal-*)
 * - tento soubor neřeší ověření hesla ani session – to dělá lib/login_smeny.php
 */

?>
<div id="cb-login-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="Přihlášení uživatele">
  <div class="modal">

    <!-- hlavička modálu: logo + titulek -->
    <div class="modal-head">
      <div class="modal-logo" aria-hidden="true">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">Informační systém Comeback</p>
        <p class="modal-sub">Přihlášení uživatele</p>
      </div>
    </div>

    <!-- tělo modálu: info + formulář -->
    <div class="cb-login-body">
      <div class="cb-info">
        Vstupujete do IS společnosti <strong>…</strong>.<br>
        K přihlášení prosím použijte přihlašovací údaje ze systému pro plánování směn.
      </div>

      <form method="post" action="<?= h(cb_url('lib/login_smeny.php')) ?>">
        <div class="cb-field">
          <label for="cb_email">E-mail</label>
          <input class="cb-input"
                 id="cb_email"
                 name="email"
                 type="email"
                 autocomplete="username"
                 required>
        </div>

        <div class="cb-field">
          <label for="cb_pass">Heslo</label>
          <input class="cb-input"
                 id="cb_pass"
                 name="heslo"
                 type="password"
                 autocomplete="current-password"
                 required>
        </div>

        <div class="cb-actions">
          <button class="modal-btn primary" type="submit">Přihlásit</button>
        </div>
      </form>
    </div>

  </div>
</div>

<script>
  (function(){
    // malá prodleva kvůli renderu modálu, pak fokus do E-mail
    var email = document.getElementById('cb_email');
    if (email) {
      setTimeout(function(){ email.focus(); }, 60);
    }
  })();
</script>
<?php
/* includes/login_modal.php * Verze: V3 * Aktualizace: 27.2.2026 * Počet řádků: 81 */
// Konec souboru