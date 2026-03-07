<?php
// modaly/modal_registrace.php * Verze: V6 * Aktualizace: 06.03.2026
declare(strict_types=1);

/*
 * PRVNÍ PŘIHLÁŠENÍ – PC MODÁL (blokuje vstup do IS, dokud není spárované zařízení)
 *
 * Co dělá:
 * - zobrazí modální okno pro spárování zařízení
 * - vytvoří (nebo obnoví) párovací token v DB tabulce push_parovani (časově omezený)
 * - zobrazí QR kód (generuje se v prohlížeči přes js/qrcode.min.js)
 * - průběžně kontroluje, jestli už je v DB aktivní zařízení (push_zarizeni.aktivni=1)
 *   a jakmile ano, automaticky přesměruje do IS
 *
 * Bezpečnost:
 * - zavření modálu (X) = zrušení párování + logout
 * - timeout 5 minut = zrušení párování + logout
 *
 * Pozn.:
 * - párování zařízení běží bez session přes mobil/mobil_registrace.php?t=...
 * - QR se generuje v prohlížeči; knihovna musí být uložená lokálně jako js/qrcode.min.js
 */

require_once __DIR__ . '/../lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');

$loginOk = !empty($_SESSION['login_ok']);
$cbUser  = $_SESSION['cb_user'] ?? null;

$idUser  = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

if (isset($_GET['check']) && (string)($_GET['check']) === '1') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$loginOk || $idUser <= 0) {
        echo json_encode(['ok' => true, 'paired' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paired = false;

    $stmt = db()->prepare('
        SELECT id
        FROM push_zarizeni
        WHERE id_user=? AND aktivni=1
        LIMIT 1
    ');

    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->store_result();
        $paired = ($stmt->num_rows > 0);
        $stmt->close();
    }

    echo json_encode(['ok' => true, 'paired' => $paired], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['abort']) && (string)($_GET['abort']) === '1') {
    header('Content-Type: application/json; charset=utf-8');

    if ($loginOk && $idUser > 0) {
        $stmt = db()->prepare('
            UPDATE push_parovani
            SET aktivni=0
            WHERE id_user=? AND aktivni=1 AND pouzito_kdy IS NULL
        ');
        if ($stmt) {
            $stmt->bind_param('i', $idUser);
            $stmt->execute();
            $stmt->close();
        }
    }

    $_SESSION = [];
    session_destroy();

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

$pairUrl = '';
$token   = '';

if ($loginOk && $idUser > 0) {

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
  <div class="modal cb-prvni-card">

    <button type="button" class="modal-x" id="cbPrvniClose" aria-label="Zavřít">×</button>

    <div class="modal-head">
      <div class="modal-logo cb-prvni-logo">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">První přihlášení</p>
        <p class="modal-sub">Spárujte zařízení pro schvalování přihlášení.</p>
      </div>
    </div>

    <div class="cb-prvni-box">
      <div class="cb-prvni-main">Načtěte QR kód na zařízení, které chcete používat pro schvalování přihlášení.</div>
      <div class="cb-prvni-sub">Po načtení pokračujte podle pokynů na zařízení.</div>
    </div>

    <div class="cb-prvni-qrwrap">
      <div class="modal-qr cb-prvni-qr" id="cbPrvniQr"></div>
    </div>

    <div class="modal-foot">
      <div class="modal-status" id="cbPrvniStatus">Čekám na spárování zařízení…</div>
      <button type="button" class="modal-btn" id="cbPrvniReload">Zkontrolovat</button>
    </div>

  </div>
</div>

<style>
  .cb-prvni-card{
    width:min(500px, 100%);
  }
  .cb-prvni-logo img{
    object-position:center center;
  }
  .cb-prvni-box{
    margin-top:10px;
    padding:14px;
    border-radius:14px;
    background:rgba(255,255,255,.88);
    border:1px solid rgba(0,0,0,.10);
  }
  .cb-prvni-main{
    font-size:15px;
    font-weight:700;
    color:#0f172a;
  }
  .cb-prvni-sub{
    margin-top:8px;
    font-size:13px;
    line-height:1.45;
    color:rgba(15,23,42,.76);
  }
  .cb-prvni-qrwrap{
    display:grid;
    justify-items:center;
    margin-top:14px;
  }
  .cb-prvni-qr{
    width:184px;
    height:184px;
  }
</style>

<script src="<?= h(cb_url('js/qrcode.min.js')) ?>"></script>
<script>
(function(){
  var btn = document.getElementById('cbPrvniReload');
  var st  = document.getElementById('cbPrvniStatus');
  var x   = document.getElementById('cbPrvniClose');

  function setTxt(t){
    st.textContent = t;
  }

  function doAbort(){
    fetch('<?= h(cb_url('modaly/modal_registrace.php?abort=1')) ?>', { cache: 'no-store' })
      .then(function(){ window.location.href = '<?= h(cb_url('')) ?>'; })
      .catch(function(){ window.location.href = '<?= h(cb_url('')) ?>'; });
  }

  function checkNow(){
    fetch('<?= h(cb_url('modaly/modal_registrace.php?check=1')) ?>', { cache: 'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || j.ok !== true) {
          setTxt('Chyba kontroly. Zkuste to znovu.');
          return;
        }
        if (j.paired === true) {
          setTxt('Zařízení je spárováno. Načítám IS…');
          window.location.href = '<?= h(cb_url('')) ?>';
          return;
        }
        setTxt('Čekám na spárování zařízení…');
      })
      .catch(function(){
        setTxt('Chyba kontroly. Zkuste to znovu.');
      });
  }

  btn.addEventListener('click', function(){
    checkNow();
  });

  x.addEventListener('click', function(){
    doAbort();
  });

  try {
    var target = document.getElementById('cbPrvniQr');
    var url    = <?php echo json_encode($pairUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    if (target && typeof QRCode === 'function' && url) {
      new QRCode(target, {
        text: url,
        width: 168,
        height: 168,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
      });
    }
  } catch (e) {
  }

  setInterval(checkNow, 2500);

  setTimeout(function(){
    doAbort();
  }, 300000);

})();
</script>
<?php
/* modaly/modal_registrace.php * Verze: V6 * Aktualizace: 06.03.2026 * Počet řádků: 212 */
/* Předchozí počet řádků: 246 */
// Konec souboru
