<?php
// includes/prvni_login.php * Verze: V2 * Aktualizace: 26.2.2026 * Počet řádků: 336
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
<style>
  .cb-prvni-overlay{
    position: fixed;
    inset: 0;
    background: rgba(10, 20, 40, .35);
    backdrop-filter: blur(26px);
    -webkit-backdrop-filter: blur(26px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 18px;
  }
  .cb-prvni-modal{
    width: min(560px, 100%);
    background: #ffffff;
    border: 1px solid rgba(0,0,0,.14);
    border-radius: 18px;
    box-shadow: 0 18px 46px rgba(0,0,0,.18);
    padding: 16px 16px 14px 16px;
  }
  .cb-prvni-head{
    display: flex;
    gap: 12px;
    align-items: center;
    margin-bottom: 10px;
  }
  .cb-prvni-logo{
    width: 52px;
    height: 52px;
    border-radius: 14px;
    border: 1px solid rgba(0,0,0,.12);
    background: #fff;
    display: grid;
    place-items: center;
    overflow: hidden;
    flex: 0 0 auto;
  }
  .cb-prvni-logo img{
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 7px;
  }
  .cb-prvni-title{
    font-weight: 800;
    font-size: 16px;
    margin: 0;
  }
  .cb-prvni-sub{
    margin: 2px 0 0 0;
    color: rgba(15, 23, 42, .75);
    font-size: 13px;
    line-height: 1.35;
  }
  .cb-prvni-box{
    margin-top: 10px;
    padding: 12px;
    border-radius: 14px;
    background: rgba(15,23,42,.06);
    border: 1px solid rgba(0,0,0,.10);
  }
  .cb-prvni-label{
    font-size: 13px;
    color: rgba(15, 23, 42, .70);
    margin: 0 0 6px 0;
  }
  .cb-prvni-phone{
    font-size: 18px;
    font-weight: 900;
    letter-spacing: .3px;
    margin: 0;
  }
  .cb-prvni-row{
    display: flex;
    gap: 14px;
    align-items: center;
    margin-top: 12px;
    flex-wrap: wrap;
  }
  .cb-prvni-qr{
    width: 180px;
    height: 180px;
    border-radius: 14px;
    border: 1px solid rgba(0,0,0,.12);
    background: #fff;
    display: grid;
    place-items: center;
    overflow: hidden;
    flex: 0 0 auto;
    padding: 8px;
  }
  .cb-prvni-qr canvas,
  .cb-prvni-qr img{
    width: 100% !important;
    height: 100% !important;
    display: block;
  }
  .cb-prvni-instr{
    flex: 1 1 260px;
    font-size: 13px;
    line-height: 1.4;
    color: rgba(15, 23, 42, .85);
  }
  .cb-prvni-url{
    margin-top: 8px;
    padding: 8px 10px;
    border-radius: 12px;
    background: rgba(255,255,255,.9);
    border: 1px solid rgba(0,0,0,.10);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 12px;
    word-break: break-all;
  }
  .cb-prvni-foot{
    margin-top: 12px;
    display: flex;
    justify-content: space-between;
    gap: 10px;
    align-items: center;
  }
  .cb-prvni-status{
    font-size: 13px;
    color: rgba(15, 23, 42, .75);
  }
  .cb-prvni-btn{
    border: 1px solid rgba(0,0,0,.14);
    background: #fff;
    border-radius: 14px;
    padding: 10px 12px;
    font-size: 13px;
    cursor: pointer;
  }
  .cb-prvni-btn:disabled{
    opacity: .55;
    cursor: default;
  }
</style>

<div class="cb-prvni-overlay" role="dialog" aria-modal="true" aria-label="První přihlášení">
  <div class="cb-prvni-modal">

    <div class="cb-prvni-head">
      <div class="cb-prvni-logo">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="cb-prvni-title">První přihlášení</p>
        <p class="cb-prvni-sub">Je toto Vaše telefonní číslo?</p>
      </div>
    </div>

    <div class="cb-prvni-box">
      <p class="cb-prvni-label">Telefon (ze Směn):</p>
      <p class="cb-prvni-phone"><?= h($telefon !== '' ? $telefon : '---') ?></p>
    </div>

    <div class="cb-prvni-row">
      <div class="cb-prvni-qr" id="cbPrvniQr"></div>

      <div class="cb-prvni-instr">
        Pokud telefonní číslo používáte, naskenujte QR kód, nebo zadejte do prohlížeče v mobilním telefonu tuto adresu:
        <div class="cb-prvni-url" id="cbPrvniUrl"><?= h($pairUrl !== '' ? $pairUrl : '---') ?></div>
        Dále postupujte podle pokynů v mobilním telefonu.
      </div>
    </div>

    <div class="cb-prvni-foot">
      <div class="cb-prvni-status" id="cbPrvniStatus">Čekám na spárování mobilu…</div>
      <button type="button" class="cb-prvni-btn" id="cbPrvniReload">Zkontrolovat</button>
    </div>

  </div>
</div>

<script src="<?= h(cb_url('js/qrcode.min.js')) ?>"></script>
<script>
(function(){
  var btn = document.getElementById('cbPrvniReload');
  var st  = document.getElementById('cbPrvniStatus');

  function setTxt(t){
    st.textContent = t;
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
})();
</script>
<?php
/* includes/prvni_login.php * Verze: V2 * Aktualizace: 26.2.2026 * Počet řádků: 336 */
// Konec souboru