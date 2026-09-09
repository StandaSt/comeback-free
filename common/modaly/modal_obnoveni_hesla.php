<?php
declare(strict_types=1);

/*
 * Modal obnoveni hesla.
 * Zobrazuje pouze formular pro nastaveni noveho hesla a neprovadi prihlaseni.
 */

$cbObnoveniUser = cb_prvni_vstup_user(db(), (int)($_SESSION['cb_obnoveni_hesla_user_id'] ?? 0));
if (!is_array($cbObnoveniUser)) { throw new RuntimeException('Obnovení hesla již není platné.'); }
$cbObnoveniZbyva = cb_obnoveni_hesla_zbyva();
if ($cbObnoveniZbyva <= 0) { throw new RuntimeException('Čas pro nastavení hesla vypršel.'); }
$cbObnoveniFlash = trim((string)($_SESSION['cb_flash'] ?? ''));
unset($_SESSION['cb_flash']);
?>
<div id="cb-login-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="Nastavení nového hesla">
  <div class="modal modal-prvni-vstup">
    <div class="modal-head"><div class="modal-logo"><img src="<?= h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback"></div><div><p class="modal-title">Nastavení nového<br>hesla</p></div></div>
    <form method="post" action="<?= h(cb_root_url('')) ?>" class="modal-form">
      <input type="hidden" name="cb_action" value="obnoveni_hesla_ulozit">
      <div class="modal-prvni-udaje">
        <div class="modal-prvni-udaj"><span>E-mail:</span><strong><?= h((string)$cbObnoveniUser['email']) ?></strong></div>
      </div>
      <div class="modal-prvni-heslo"><div class="modal-prvni-heslo-radek"><label for="cb_pass">Nové heslo:</label><input class="modal-input" id="cb_pass" name="heslo" type="password" autocomplete="new-password" required aria-describedby="cb-password-rules cb-password-status"></div></div>
      <div class="modal-password-strength"><span class="modal-password-strength-status" id="cb-password-status" aria-live="polite">Síla hesla</span><div class="modal-password-meter" aria-hidden="true"><span id="cb-password-meter-fill"></span></div></div>
      <div class="modal-prvni-heslo"><div class="modal-prvni-heslo-radek"><label for="cb_pass2">Heslo znovu:</label><input class="modal-input" id="cb_pass2" name="heslo_znovu" type="password" autocomplete="new-password" required aria-describedby="cb-password-match"></div><p class="modal-password-status" id="cb-password-match" aria-live="polite"></p></div>
      <p class="modal-password-rules" id="cb-password-rules">Heslo musí mít minimálně 8 znaků,<br>malé a velké písmeno a číslici.</p>
      <div class="modal-actions"><button class="modal-btn primary" id="cb-prvni-submit" type="submit" disabled><span class="modal-btn-main">Uložit nové heslo</span></button></div>
      <p class="modal-login-status" aria-live="polite"><?= h($cbObnoveniFlash) ?></p>
      <p class="modal-prvni-countdown" id="cb-prvni-countdown" data-seconds="<?= $cbObnoveniZbyva ?>" data-redirect="<?= h(cb_root_url('?zapomenute_heslo=1')) ?>">Na vložení hesel Vám zbývá <span></span> min.</p>
    </form>
  </div>
</div>
<script src="<?= h(cb_public_url('js/nove_heslo.js?v=' . filemtime(__DIR__ . '/../js/nove_heslo.js'))) ?>"></script>
