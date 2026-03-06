<?php
// includes/prvni_login.php * Verze: V5 * Aktualizace: 06.03.2026 * Počet řádků: 245
// Předchozí počet řádků: 246
declare(strict_types=1);

/*
 * PRVNÍ PŘIHLÁŠENÍ – PC MODÁL (blokuje vstup do IS, dokud není spárované zařízení)
 *
 * Co dělá:
 * - vytvoří (nebo obnoví) párovací token v DB tabulce push_parovani (časově omezený)
 * - zobrazí QR kód pro registraci zařízení
 * - průběžně kontroluje, jestli už je v DB aktivní zařízení (push_zarizeni.aktivni=1)
 *   a jakmile ano, automaticky přesměruje do IS
 *
 * Bezpečnost:
 * - zavření modálu (X) = zrušení párování + logout
 * - timeout 5 minut = zrušení párování + logout
 *
 * Pozn.:
 * - mobilní registrace běží bez session přes includes/parovani_mobilu.php?t=...
 * - QR se generuje v prohlížeči; knihovna musí být uložená lokálně jako js/qrcode.min.js
 * - vzhled řeší společné CSS v style/1/modal_alert.css
 */

require_once __DIR__ . '/../lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');

$loginOk = !empty($_SESSION['login_ok']);
$cbUser  = $_SESSION['cb_user'] ?? null;

$idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

/* =========================
   0) JSON kontrola registrace (polling z PC)
   ========================= */
if (isset($_GET['check']) && (string)$_GET['check'] === '1') {
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

/* =========================
   0b) JSON abort (zavření modálu / timeout)
   ========================= */
if (isset($_GET['abort']) && (string)$_GET['abort'] === '1') {
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

/* =========================
   1) Vygeneruj párovací token (DB: push_parovani)
   ========================= */
$pairUrl = '';

if ($loginOk && $idUser > 0) {
    $token = bin2hex(random_bytes(32));

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

    $stmt = db()->prepare('
        INSERT INTO push_parovani (id_user, token_hash, expirace, aktivni)
        VALUES (?, UNHEX(SHA2(?, 256)), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 1)
    ');
    if ($stmt) {
        $stmt->bind_param('is', $idUser, $token);
        $stmt->execute();
        $stmt->close();
    }

    $pairUrl = 'https://pokus.xo.je/includes/parovani_mobilu.php?t=' . rawurlencode($token);
}

/* =========================
   2) HTML (modál)
   ========================= */
?>
<div class="modal-overlay" role="dialog" aria-modal="true" aria-label="Přihlašujete se poprvé do IS Pizzacomeback">
  <div class="modal modal-pair-first">

    <button type="button" class="modal-x" id="cbPrvniClose" aria-label="Zavřít">×</button>

    <div class="modal-head modal-head-top">
      <div class="modal-logo modal-logo-lg">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
    </div>

    <div class="modal-center modal-center-lg">
      <p class="modal-title modal-title-center">Přihlašujete se poprvé</p>
      <p class="modal-title modal-title-center">do IS Pizzacomeback</p>

      <div class="modal-copy modal-copy-wide">
        Je nutné zaregistrovat mobilní zařízení<br>
        pro budoucí ověřování Vaší identity.
      </div>

      <div class="modal-copy modal-copy-wide">
        Naskenujte QR kód pomocí zařízení<br>
        které budete používat
      </div>

      <div class="modal-qr modal-qr-main" id="cbPrvniQr"></div>

      <div class="modal-status modal-status-center" id="cbPrvniStatus">
        Na zaregistrování zařízení zbývá: 05:00
      </div>
    </div>

  </div>
</div>

<script src="<?= h(cb_url('js/qrcode.min.js')) ?>"></script>
<script>
(function(){
  var st = document.getElementById('cbPrvniStatus');
  var x  = document.getElementById('cbPrvniClose');
  var deadlineTs = Date.now() + 300000;

  function setTxt(t){
    if (st) {
      st.textContent = t;
    }
  }

  function pad(n){
    return String(n).padStart(2, '0');
  }

  function renderCountdown(){
    var ms = deadlineTs - Date.now();
    if (ms < 0) {
      ms = 0;
    }
    var sec = Math.floor(ms / 1000);
    var min = Math.floor(sec / 60);
    var rest = sec % 60;
    setTxt('Na zaregistrování zařízení zbývá: ' + pad(min) + ':' + pad(rest));
  }

  function doAbort(){
    fetch('<?= h(cb_url('includes/prvni_login.php?abort=1')) ?>', { cache: 'no-store' })
      .then(function(){ window.location.href = '<?= h(cb_url('')) ?>'; })
      .catch(function(){ window.location.href = '<?= h(cb_url('')) ?>'; });
  }

  function checkNow(){
    fetch('<?= h(cb_url('includes/prvni_login.php?check=1')) ?>', { cache: 'no-store' })
      .then(function(r){ return r.json(); })
      .then(function(j){
        if (!j || j.ok !== true) {
          setTxt('Chyba kontroly. Zkuste to znovu.');
          return;
        }
        if (j.paired === true) {
          setTxt('Zařízení je zaregistrováno. Načítám IS…');
          window.location.href = '<?= h(cb_url('')) ?>';
          return;
        }
        renderCountdown();
      })
      .catch(function(){
        setTxt('Chyba kontroly. Zkuste to znovu.');
      });
  }

  if (x) {
    x.addEventListener('click', function(){
      doAbort();
    });
  }

  try {
    var target = document.getElementById('cbPrvniQr');
    var url = <?php echo json_encode($pairUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    if (target && typeof QRCode === 'function' && url) {
      new QRCode(target, {
        text: url,
        width: 220,
        height: 220,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });
    }
  } catch (e) {
    // QR nesmí shodit modál
  }

  renderCountdown();
  setInterval(renderCountdown, 1000);
  setInterval(checkNow, 2500);

  setTimeout(function(){
    doAbort();
  }, 300000);
})();
</script>
<?php
/* includes/prvni_login.php * Verze: V5 * Aktualizace: 06.03.2026 * Počet řádků: 245 */
// Předchozí počet řádků: 246
// Konec souboru
