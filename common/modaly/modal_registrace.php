<?php
declare(strict_types=1);

/*
 * Modal registrace zarizeni.
 * Vytvori parovaci pozadavek, zobrazi QR kod a automaticky sleduje jeho stav.
 */

$loginOk = !empty($_SESSION['login_ok']);
$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cbUser = $_SESSION['cb_user'] ?? null;
$idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

$pairUrl = '';
$token = '';
$targetUrl = cb_login_target_url();
$loginUrl = cb_login_url();

if (($loginOk || $cbAuthOk) && $idUser > 0) {
    $token = bin2hex(random_bytes(32));
    $conn = db();

    $stmt = $conn->prepare('UPDATE push_parovani SET aktivni=0 WHERE id_user=? AND aktivni=1');
    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();
    }

    $stmt = $conn->prepare('
        INSERT INTO push_parovani
        (id_user, token_hash, aktivni, vytvoreno, expirace, pouzito_kdy)
        VALUES
        (?, UNHEX(SHA2(?,256)), 1, NOW(), (NOW() + INTERVAL 10 MINUTE), NULL)
    ');

    if ($stmt) {
        $stmt->bind_param('is', $idUser, $token);
        $stmt->execute();
        $stmt->close();
    }

    $pairUrl = cb_public_url_abs('mobil/mobil_registrace.php?t=' . rawurlencode($token));
}
?>
<div class="modal-overlay" role="dialog" aria-modal="true" aria-label="První přihlášení">
  <div class="modal">
    <button type="button" class="modal-x" id="cbPrvniClose" aria-label="Zavřít">×</button>

    <div class="modal-head">
      <div class="modal-logo">
        <img src="<?= h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">První přihlášení</p>
        <p class="modal-sub">Zaregistrujte zařízení pro schvalování přihlášení.</p>
      </div>
    </div>

    <div class="modal-box" style="background:#eff6ff; border-color:#bfdbfe;">
      <div class="modal-copy">
        Při vstupu do IS Comeback se provádí ověřování pomocí registrovaného zařízení. Ideálně mobilním telefonem.<br><br>
        Načtěte tedy tento QR kód Vaším mobilním telefonem a při vstupu do IS Comeback jej mějte u sebe.
      </div>
      <p class="modal-sub">Po načtení QR kódu se řiďte pokyny na zařízení.</p>
    </div>

    <div class="modal-spacer"></div>
    <div class="modal-qr modal-qr-main" id="cbPrvniQr"></div>
    <?php if ($pairUrl !== ''): ?>
      <div class="modal-spacer"></div>
      <a class="modal-btn primary modal-touch-register" id="cbPrvniTouchRegister" href="<?= h($pairUrl) ?>" hidden>Registrovat toto zařízení</a>
    <?php endif; ?>

    <div class="modal-foot">
      <div class="modal-status" id="cbPrvniStatus">Čekám na spárování zařízení…</div>
    </div>
  </div>
</div>

<script src="<?= h(cb_public_url('js/qrcode.min.js')) ?>"></script>
<script>
(function(){
  var st = document.getElementById('cbPrvniStatus');
  var x = document.getElementById('cbPrvniClose');
  var touchRegister = document.getElementById('cbPrvniTouchRegister');

  /* Pozna mobilni nebo jine dotykove zarizeni. */
  function isTouchDevice(){
    if (navigator && Number(navigator.maxTouchPoints || 0) > 0) {
      return true;
    }
    if (window.matchMedia) {
      return window.matchMedia('(pointer: coarse)').matches;
    }
    return false;
  }

  /* Zapise aktualni stav parovani do modalu. */
  function setTxt(t){
    if (st) {
      st.textContent = t;
    }
  }

  /* Zrusi rozpracovane parovani a vrati uzivatele na login. */
  function doAbort(){
    fetch('<?= h(cb_url('?action=registrace_abort')) ?>', { cache: 'no-store' })
      .then(function(){ window.location.href = '<?= h($loginUrl) ?>'; })
      .catch(function(){ window.location.href = '<?= h($loginUrl) ?>'; });
  }

  /* Jednorazove nacte stav parovani; opakovani ridi casovac nize. */
  function checkNow(){
    fetch('<?= h(cb_url('?action=registrace_check')) ?>', { cache: 'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || j.ok !== true) {
          return;
        }
        if (j.paired === true) {
          setTxt('Zařízení je spárováno. Načítám modul…');
          window.location.href = '<?= h($targetUrl) ?>';
          return;
        }
      })
      .catch(function(){});
  }

  if (x) {
    x.addEventListener('click', doAbort);
  }
  if (touchRegister && isTouchDevice()) {
    touchRegister.hidden = false;
  }

  try {
    var target = document.getElementById('cbPrvniQr');
    var url = <?php echo json_encode($pairUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    if (target && typeof QRCode === 'function' && url) {
      new QRCode(target, {
        text: url,
        width: 168,
        height: 168,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
    }
  } catch (e) {}

  setInterval(checkNow, 2500);
  setTimeout(function(){ doAbort(); }, 300000);
})();
</script>
