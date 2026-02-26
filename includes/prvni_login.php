<?php
// includes/prvni_login.php * Verze: V4 * Aktualizace: 26.2.2026 * Počet řádků: 246
declare(strict_types=1);

/*
 * PRVNÍ PŘIHLÁŠENÍ – PC MODÁL (blokuje vstup do IS, dokud není spárovaný mobil)
 *
 * Co dělá:
 * - zobrazí modální okno s informací + telefonem ze Směn (needotovatelné)
 * - vytvoří (nebo obnoví) párovací token v DB tabulce push_parovani (časově omezený)
 * - zobrazí QR kód (generuje se v prohlížeči přes js/qrcode.min.js) + textovou adresu
 * - průběžně kontroluje, jestli už je v DB aktivní zařízení (push_zarizeni.aktivni=1)
 *   a jakmile ano, automaticky přesměruje do IS
 *
 * Bezpečnost:
 * - zavření modálu (X) = zrušení párování + logout
 * - timeout 5 minut = zrušení párování + logout
 *
 * Pozn.:
 * - mobilní párování běží bez session přes includes/parovani_mobilu.php?t=...
 * - QR se generuje v prohlížeči; knihovna musí být uložená lokálně jako js/qrcode.min.js
 */

require_once __DIR__ . '/../lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');

$loginOk = !empty($_SESSION['login_ok']);
$cbUser  = $_SESSION['cb_user'] ?? null;

$idUser  = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
$telefon = (is_array($cbUser) && isset($cbUser['telefon'])) ? (string)$cbUser['telefon'] : '';

/* =========================
   0) JSON kontrola spárování (polling z PC)
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
$token   = '';

if ($loginOk && $idUser > 0) {

    // 64 hex znaků (32 bytes)
    $token = bin2hex(random_bytes(32));

    $conn = db();

    // 1) zneplatni starší aktivní tokeny uživatele
    $stmt = $conn->prepare('UPDATE push_parovani SET aktivni=0 WHERE id_user=? AND aktivni=1');
    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();
    }

    // 2) vlož nový token (hash v DB), expirace 10 minut
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

    $pairUrl = 'https://pokus.xo.je/includes/parovani_mobilu.php?t=' . rawurlencode($token);
}

/* =========================
   2) HTML (modál)
   ========================= */
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
        <p class="modal-sub">Je toto Vaše telefonní číslo?</p>
      </div>
    </div>

    <div class="modal-box">
      <p class="modal-label">Telefon (ze Směn):</p>
      <p class="modal-phone"><?= h($telefon !== '' ? $telefon : '---') ?></p>
    </div>

    <div class="modal-row">
      <div class="modal-qr" id="cbPrvniQr"></div>

      <div class="modal-instr">
        Pokud telefonní číslo používáte, naskenujte QR kód, nebo zadejte do prohlížeče v mobilním telefonu tuto adresu:
        <div class="modal-url" id="cbPrvniUrl"><?= h($pairUrl !== '' ? $pairUrl : '---') ?></div>
        Dále postupujte podle pokynů v mobilním telefonu.
      </div>
    </div>

    <div class="modal-foot">
      <div class="modal-status" id="cbPrvniStatus">Čekám na spárování mobilu…</div>
      <button type="button" class="modal-btn" id="cbPrvniReload">Zkontrolovat</button>
    </div>

  </div>
</div>

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
          setTxt('Mobil je spárován. Načítám IS…');
          window.location.href = '<?= h(cb_url('')) ?>';
          return;
        }
        setTxt('Čekám na spárování mobilu…');
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

  // QR (classic, čitelný čtečkami)
  try {
    var target = document.getElementById('cbPrvniQr');
    var url    = <?php echo json_encode($pairUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    if (target && typeof QRCode === 'function' && url) {
      new QRCode(target, {
        text: url,
        width: 164,
        height: 164,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.M
      });
    }
  } catch (e) {
    // QR nesmí shodit modál
  }

  setInterval(checkNow, 2500);

  // Timeout 5 minut: bez párování => logout
  setTimeout(function(){
    doAbort();
  }, 300000);

})();
</script>
<?php
/* includes/prvni_login.php * Verze: V4 * Aktualizace: 26.2.2026 * Počet řádků: 246 */
// Konec souboru