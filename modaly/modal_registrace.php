<?php
declare(strict_types=1);

$loginOk = !empty($_SESSION['login_ok']);
$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cbUser = $_SESSION['cb_user'] ?? null;
$idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

$pairUrl = '';
$token = '';

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

    $pairUrl = cb_url_abs('mobil/mobil_registrace.php?t=' . rawurlencode($token));
}
?>
<div class="modal-overlay" role="dialog" aria-modal="true" aria-label="První přihlášení">
  <div class="modal">
    <button type="button" class="modal-x" id="cbPrvniClose" aria-label="Zavřít">×</button>

    <div class="modal-head">
      <div class="modal-logo">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">První přihlášení</p>
        <p class="modal-sub">Spárujte zařízení pro schvalování přihlášení.</p>
      </div>
    </div>

    <div class="modal-box">
      <div class="modal-copy">
        Načtěte QR kód na zařízení, které chcete používat pro schvalování přihlášení.
      </div>
      <p class="modal-sub">Po načtení pokračujte podle pokynů na zařízení.</p>
    </div>

    <div class="modal-spacer"></div>
    <div class="modal-qr modal-qr-main" id="cbPrvniQr"></div>

    <div class="modal-foot">
      <div class="modal-status" id="cbPrvniStatus">Čekám na spárování zařízení…</div>
      <button type="button" class="modal-btn" id="cbPrvniReload">Zkontrolovat</button>
    </div>
  </div>
</div>

<script src="<?= h(cb_url('js/qrcode.min.js')) ?>"></script>
<script>
(function(){
  var btn = document.getElementById('cbPrvniReload');
  var st = document.getElementById('cbPrvniStatus');
  var x = document.getElementById('cbPrvniClose');

  function setTxt(t){
    if (st) {
      st.textContent = t;
    }
  }

  function debugTxt(j){
    var d = j && j.debug ? j.debug : null;
    if (!d) {
      return '';
    }

    return ' [user=' + (d.id_user || 0)
      + ', zarizeni=' + (d.device_id || 0)
      + ', login=' + (d.login_ok ? '1' : '0')
      + ', auth=' + (d.cb_auth_ok ? '1' : '0')
      + ', posun=' + (d.login_promoted ? '1' : '0')
      + (d.reason ? ', ' + d.reason : '')
      + ']';
  }

  function doAbort(){
    fetch('<?= h(cb_url('?action=registrace_abort')) ?>', { cache: 'no-store' })
      .then(function(){ window.location.href = '<?= h(cb_url('')) ?>'; })
      .catch(function(){ window.location.href = '<?= h(cb_url('')) ?>'; });
  }

  function checkNow(){
    setTxt('Kontroluji stav párování…');
    fetch('<?= h(cb_url('?action=registrace_check')) ?>', { cache: 'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || j.ok !== true) {
          setTxt('Chyba kontroly. Zkuste to znovu.');
          return;
        }
        if (j.paired === true) {
          setTxt('Zařízení je spárováno. Načítám IS…' + debugTxt(j));
          window.location.href = '<?= h(cb_url('')) ?>';
          return;
        }
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        setTxt('Zařízení zatím není spárováno. Zkontrolováno v ' + h + ':' + m + ':' + s + '.' + debugTxt(j));
      })
      .catch(function(){
        setTxt('Chyba kontroly. Zkuste to znovu.');
      });
  }

  if (btn) {
    btn.addEventListener('click', checkNow);
  }
  if (x) {
    x.addEventListener('click', doAbort);
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
