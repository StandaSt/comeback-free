<?php
declare(strict_types=1);

/*
 * Ucel souboru: Zobrazeni prihlasovaciho formulare a predani identity
 * aktualni push registrace serveru pro rozpoznani registrovaneho mobilu.
 * Nabizi take prihlaseni jednorazovym e-mailovym odkazem po overeni hesla.
 */

require_once __DIR__ . '/../funkce/last_aktualizace_systemu.php';
require_once __DIR__ . '/../lib/ochrana_crf.php';

cb_last_aktualizace_systemu();

$aktualniUrl = cb_url_abs('');
$loginDbOk = !empty($cbLoginDbOk);
$loginDisabled = $loginDbOk ? '' : ' disabled';
$loginFlash = trim((string)($_SESSION['cb_flash'] ?? ''));
$loginEmailPrefill = trim((string)($_SESSION['cb_password_reset_email_prefill'] ?? ''));
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
               value="<?= h($loginEmailPrefill) ?>"
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
      <input type="hidden" name="device_endpoint" id="cbDeviceEndpoint" value="">
      <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
      <input type="hidden" name="login_metoda" id="cbLoginMetoda" value="mobil">

      <div class="modal-actions">
        <button class="modal-btn primary cb-login-mobile" type="submit" data-login-metoda="mobil"<?= $loginDisabled ?>>
          <span class="modal-btn-main"><span aria-hidden="true">📱</span> Přihlásit</span>
        </button>
        <button class="modal-btn cb-login-email" type="submit" name="login_volba" value="email" data-login-metoda="email"<?= $loginDisabled ?>>
          <span class="modal-btn-main"><span aria-hidden="true">✉</span> Přihlásit bez mobilu</span>
        </button>
      </div>
      <p class="modal-login-link"><a href="<?= h(cb_root_url('?zapomenute_heslo=1')) ?>">Nastavit nové heslo</a></p>
      <p class="modal-login-status" id="cbLoginStatus" aria-live="polite"><?= h($loginFlash) ?></p>
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

  /* Ucel funkce: Pripravi identitu push registrace pred povolenim prihlaseni. */
  var form = document.getElementById('cbLoginForm');
  var endpointInput = document.getElementById('cbDeviceEndpoint');
  if (!form) return;

  var button = form.querySelector('button[type="submit"]');
  var buttons = form.querySelectorAll('button[type="submit"]');
  var odeslano = false;
  var loginPovolen = button instanceof HTMLButtonElement && !button.disabled;
  if (loginPovolen) {
    button.disabled = true;
    button.classList.add('is-waiting');
  }

  var pripravaZarizeni = Promise.resolve();
  if ('serviceWorker' in navigator && 'PushManager' in window && endpointInput instanceof HTMLInputElement) {
    pripravaZarizeni = navigator.serviceWorker
      .getRegistration(<?= json_encode(cb_root_url(''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>)
      .then(function(registrace){
        /* Ucel funkce: Nacte existujici subscription bez vytvareni nove. */
        return registrace ? registrace.pushManager.getSubscription() : null;
      })
      .then(function(subscription){
        /* Ucel funkce: Preda serveru pouze endpoint aktualni subscription. */
        if (subscription && typeof subscription.endpoint === 'string') {
          endpointInput.value = subscription.endpoint;
        }
      })
      .catch(function(){
        /* Ucel funkce: Pri nedostupne subscription zachova standardni 2FA tok. */
        endpointInput.value = '';
      });
  }

  pripravaZarizeni.finally(function(){
    /* Ucel funkce: Povoli nativni prihlaseni po priprave identity zarizeni. */
    if (!odeslano && loginPovolen && button instanceof HTMLButtonElement) {
      button.disabled = false;
      button.classList.remove('is-waiting');
    }
  });

  form.addEventListener('submit', function(event){
    odeslano = true;
    /* Metodu uchova skryte pole i po zakazani tlacitek; Enter zachova mobilni vstup. */
    document.getElementById('cbLoginMetoda').value = event.submitter
      ? event.submitter.getAttribute('data-login-metoda') : 'mobil';
    buttons.forEach(function(item){
      item.disabled = true;
      item.classList.add('is-waiting');
    });
  });
})();
</script>
