<?php
declare(strict_types=1);
$cbPrvniUser = cb_prvni_vstup_user(db(), (int)($_SESSION['cb_prvni_vstup_user_id'] ?? 0));
if (!is_array($cbPrvniUser)) { throw new RuntimeException('První vstup již není platný.'); }
$cbPrvniZbyva = cb_prvni_vstup_zbyva();
if ($cbPrvniZbyva <= 0) { throw new RuntimeException('Čas pro nastavení hesla vypršel.'); }
$cbPrvniFlash = trim((string)($_SESSION['cb_flash'] ?? ''));
unset($_SESSION['cb_flash']);
?>
<div id="cb-login-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="První vstup do Comeback">
  <div class="modal modal-prvni-vstup">
    <div class="modal-head"><div class="modal-logo"><img src="<?= h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback"></div><div><p class="modal-title">První vstup do<br>IS Comeback</p></div></div>
    <form method="post" action="<?= h(cb_root_url('')) ?>" class="modal-form">
      <input type="hidden" name="cb_action" value="prvni_vstup_ulozit">
      <div class="modal-prvni-udaje">
        <div class="modal-prvni-udaj"><span>Jméno:</span><strong><?= h((string)$cbPrvniUser['jmeno']) ?></strong></div>
        <div class="modal-prvni-udaj"><span>Příjmení:</span><strong><?= h((string)$cbPrvniUser['prijmeni']) ?></strong></div>
        <div class="modal-prvni-udaj"><span>E-mail:</span><strong><?= h((string)$cbPrvniUser['email']) ?></strong></div>
      </div>
      <input type="hidden" name="jmeno" value="<?= h((string)$cbPrvniUser['jmeno']) ?>">
      <input type="hidden" name="prijmeni" value="<?= h((string)$cbPrvniUser['prijmeni']) ?>">
      <input type="hidden" name="email" value="<?= h((string)$cbPrvniUser['email']) ?>">
      <div class="modal-prvni-heslo"><div class="modal-prvni-heslo-radek"><label for="cb_pass">Nové heslo:</label><input class="modal-input" id="cb_pass" name="heslo" type="password" autocomplete="new-password" required aria-describedby="cb-password-rules cb-password-status"></div></div>
      <div class="modal-password-strength"><span class="modal-password-strength-status" id="cb-password-status" aria-live="polite">Síla hesla</span><div class="modal-password-meter" aria-hidden="true"><span id="cb-password-meter-fill"></span></div></div>
      <div class="modal-prvni-heslo"><div class="modal-prvni-heslo-radek"><label for="cb_pass2">Heslo znovu:</label><input class="modal-input" id="cb_pass2" name="heslo_znovu" type="password" autocomplete="new-password" required aria-describedby="cb-password-match"></div><p class="modal-password-status" id="cb-password-match" aria-live="polite"></p></div>
      <p class="modal-password-rules" id="cb-password-rules">Heslo musí mít minimálně 8 znaků,<br>malé a velké písmeno a číslici.</p>
      <div class="modal-actions"><button class="modal-btn primary" id="cb-prvni-submit" type="submit" disabled><span class="modal-btn-main">Uložit a vstoupit</span></button></div>
      <p class="modal-login-status" aria-live="polite"><?= h($cbPrvniFlash) ?></p>
      <p class="modal-prvni-countdown" id="cb-prvni-countdown" data-seconds="<?= $cbPrvniZbyva ?>">Na vložení hesel Vám zbývá <span></span> min.</p>
    </form>
  </div>
</div>
<script>
(function(){
  var password = document.getElementById('cb_pass');
  var confirmation = document.getElementById('cb_pass2');
  var meter = document.getElementById('cb-password-meter-fill');
  var status = document.getElementById('cb-password-status');
  var matchStatus = document.getElementById('cb-password-match');
  var submit = document.getElementById('cb-prvni-submit');
  var countdown = document.getElementById('cb-prvni-countdown');
  if (!password || !confirmation || !meter || !status || !matchStatus || !submit || !countdown) return;
  var countdownValue = countdown.querySelector('span');
  var deadline = Date.now() + (Number(countdown.getAttribute('data-seconds')) * 1000);
  var expired = false;
  function check(){
    var value = password.value;
    var points = 0;
    if (value.length >= 8) points++;
    if (/[a-z]/.test(value)) points++;
    if (/[A-Z]/.test(value)) points++;
    if (/[0-9]/.test(value)) points++;
    if (/[^A-Za-z0-9]/.test(value)) points++;
    var valid = value.length >= 8 && /[a-z]/.test(value) && /[A-Z]/.test(value) && /[0-9]/.test(value);
    var level = value === '' ? 0 : (valid ? 4 : (points <= 1 ? 1 : (points === 2 ? 2 : 3)));
    meter.style.width = String(level * 25) + '%';
    meter.className = valid ? 'is-strong' : (level >= 3 ? 'is-medium' : 'is-weak');
    var strength = value === '' ? 'Síla hesla' : (level === 1 ? 'Velmi slabé' : (level === 2 ? 'Slabé' : (level === 3 ? 'Málo bezpečné' : 'OK')));
    status.textContent = strength;
    status.className = 'modal-password-strength-status ' + (valid ? 'is-ok' : (level <= 1 ? 'is-weak' : 'is-medium'));
    var same = confirmation.value !== '' && value === confirmation.value;
    var sameBeginning = value.indexOf(confirmation.value) === 0;
    matchStatus.textContent = confirmation.value === '' || (sameBeginning && !same) ? '' : (same ? 'Hesla se shodují.' : 'Hesla se neshodují.');
    matchStatus.className = 'modal-password-status' + (same ? ' is-ok' : (sameBeginning ? '' : ' is-error'));
    submit.disabled = expired || !(valid && same);
  }
  function updateCountdown(){
    var seconds = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
    var minutes = Math.floor(seconds / 60);
    var rest = String(seconds % 60).padStart(2, '0');
    countdownValue.textContent = String(minutes) + ':' + rest;
    if (seconds <= 0) {
      expired = true;
      submit.disabled = true;
      window.location.replace(<?= json_encode(cb_root_url(''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>);
      return;
    }
    window.setTimeout(updateCountdown, 250);
  }
  password.addEventListener('input', check);
  confirmation.addEventListener('input', check);
  check();
  updateCountdown();
})();
</script>
