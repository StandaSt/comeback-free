<?php
declare(strict_types=1);

/*
 * Modal prvniho vstupu.
 * Zobrazuje identitu pozvaneho uzivatele a formular pro jeho prvni heslo.
 */

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
      <p class="modal-prvni-countdown" id="cb-prvni-countdown" data-seconds="<?= $cbPrvniZbyva ?>" data-redirect="<?= h(cb_root_url('')) ?>">Na vložení hesel Vám zbývá <span></span> min.</p>
    </form>
  </div>
</div>
<script src="<?= h(cb_public_url('js/nove_heslo.js?v=' . filemtime(__DIR__ . '/../js/nove_heslo.js'))) ?>"></script>
