<?php
// mobil/mobil_overeni.php * Verze: V8 * Aktualizace: 07.03.2026
declare(strict_types=1);

/*
 * 2FA – schválení přihlášení (mobilní stránka)
 *
 * URL:
 * - mobil/mobil_overeni.php?t=<token>
 *
 * Co dělá:
 * - načte 2FA požadavek z DB tabulky push_login_2fa podle tokenu
 * - zobrazí informace o pokusu o přihlášení
 * - umožní rozhodnout: ok / ne (jen pokud stav=ceka a nevypršelo)
 * - po povolení ukáže velké potvrzení a po chvíli odejde na prázdnou stránku
 * - po zamítnutí ukáže varování a nabídne zavření okna
 *
 * Pozn.:
 * - toto je stránka pro mobil, NE API pro PC polling (to řeší lib/push_2fa_api.php)
 */

require_once __DIR__ . '/../lib/bootstrap.php';

/* Limit pro odpočet v UI (sekundy). Hodnota je i v DB (vyprsi), UI je jen zobrazení. */
$limitSecPhp = 300;
if (defined('CB_2FA_LIMIT_SEC')) {
    $limitSecPhp = (int)CB_2FA_LIMIT_SEC;
    if ($limitSecPhp <= 0) {
        $limitSecPhp = 300;
    }
}

/* Token z URL */
$token = (string)($_GET['t'] ?? '');
$token = trim($token);

/* HTML escape (ochrana proti vložení HTML do stránky) */
function h1(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* Načtení řádku 2FA z DB podle tokenu */
function cb_fetch_2fa(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare('
        SELECT id, id_user, stav, ip, prohlizec, vytvoreno, vyprsi, TIMESTAMPDIFF(SECOND, NOW(), vyprsi) AS zbyva_sec
        FROM push_login_2fa
        WHERE token=?
        LIMIT 1
    ');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();

    $stmt->bind_result($id, $idUser, $stav, $ip, $prohlizec, $vytvoreno, $vyprsi, $zbyvaSec);
    $ok = $stmt->fetch();
    $stmt->close();

    if (!$ok) {
        return null;
    }

    return [
        'id' => (int)$id,
        'id_user' => (int)$idUser,
        'stav' => (string)$stav,
        'ip' => (string)$ip,
        'prohlizec' => (string)($prohlizec ?? ''),
        'vytvoreno' => (string)$vytvoreno,
        'vyprsi' => (string)$vyprsi,
        'zbyva_sec' => (int)$zbyvaSec,
    ];
}

/* Načtení jména a emailu uživatele */
function cb_fetch_user_info(int $idUser): array
{
    if ($idUser <= 0) {
        return ['cele_jmeno' => '', 'email' => ''];
    }

    $stmt = db()->prepare('
        SELECT jmeno, prijmeni, email
        FROM user
        WHERE id_user=?
        LIMIT 1
    ');
    if (!$stmt) {
        return ['cele_jmeno' => '', 'email' => ''];
    }

    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $stmt->bind_result($jmeno, $prijmeni, $email);
    $ok = $stmt->fetch();
    $stmt->close();

    if (!$ok) {
        return ['cele_jmeno' => '', 'email' => ''];
    }

    $celeJmeno = trim((string)($jmeno ?? '') . ' ' . (string)($prijmeni ?? ''));

    return [
        'cele_jmeno' => $celeJmeno,
        'email' => (string)($email ?? ''),
    ];
}

/* Zapsání rozhodnutí (ok/ne) do DB – jen když stav=ceka a vyprsi > NOW() */
function cb_set_2fa_decision(string $token, string $decision): bool
{
    if ($token === '') {
        return false;
    }
    if ($decision !== 'ok' && $decision !== 'ne') {
        return false;
    }

    $stmt = db()->prepare("UPDATE push_login_2fa SET stav=?, rozhodnuto=NOW() WHERE token=? AND stav='ceka' AND vyprsi > NOW()");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('ss', $decision, $token);
    $stmt->execute();
    $changed = ($stmt->affected_rows > 0);
    $stmt->close();

    return $changed;
}

/* Zpracování POST (klik na tlačítko) */
$didPost = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
$decision = '';

if ($didPost) {
    $decision = (string)($_POST['decision'] ?? '');
    $decision = trim($decision);

    if ($decision === 'ok' || $decision === 'ne') {
        cb_set_2fa_decision($token, $decision);
    }
}

/* Načti aktuální stav z DB (po případném POSTu) */
$row = cb_fetch_2fa($token);

$stav = is_array($row) ? (string)($row['stav'] ?? '') : '';
$ip = is_array($row) ? (string)($row['ip'] ?? '') : '';
$zbyvaSec = is_array($row) ? (int)($row['zbyva_sec'] ?? 0) : 0;
$idUser = is_array($row) ? (int)($row['id_user'] ?? 0) : 0;

$userInfo = cb_fetch_user_info($idUser);
$celeJmeno = (string)($userInfo['cele_jmeno'] ?? '');
$email = (string)($userInfo['email'] ?? '');

if ($celeJmeno === '') {
    $celeJmeno = '---';
}
if ($email === '') {
    $email = '---';
}
if ($ip === '') {
    $ip = '---';
}

/* Čas rozhodnutí */
$kdyRozhodnuto = date('j. n. Y \v H:i') . ' hod.';

/* Debug */
$dbgToken = substr($token, 0, 8);
$dbgStav = $stav;
if ($dbgStav === '') {
    $dbgStav = is_array($row) ? 'ceka' : 'neznamy';
}
$dbgText = 'DBG: V8 | user ' . $idUser . ' | token ' . $dbgToken . ' | stav ' . $dbgStav;

/* Texty do UI podle stavu */
$title = 'Schválení přihlášení';
$info = '';

if (!is_array($row)) {
    $info = 'Neplatný nebo neznámý požadavek.';
} else {
    if ($stav === 'ok') {
        $info = 'Přístup byl povolen';
    } elseif ($stav === 'ne') {
        $info = 'Zamítl/a jste přihlášení pro uživatele „' . $celeJmeno . '“ dne ' . $kdyRozhodnuto . '.';
    } elseif ($stav === 'exp' || $zbyvaSec <= 0) {
        $info = 'Tento požadavek vypršel.';
    } else {
        $info = 'Rozhodni o přístupu do IS.';
    }
}

/* Rozhodování je povolené jen v okně platnosti */
$canDecide = (is_array($row) && $stav === 'ceka' && $zbyvaSec > 0);

?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h1($title) ?></title>
  <link rel="stylesheet" href="<?= h1(cb_url('style/1/modal_alert.css')) ?>">

  <style>
    .modal{
      width:min(315px, 100%);
    }
    .modal-logo{
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .modal-logo img{
      width:100%;
      height:100%;
      object-fit:contain;
      object-position:center center;
      padding:7px;
      display:block;
    }
    .done-big{
      font-size:28px;
      font-weight:800;
      line-height:1.15;
      text-align:left;
      margin:2px 0 0 0;
      color:#166534;
    }
    .warn-box{
      margin-top:12px;
      padding:12px;
      border-radius:14px;
      background:rgba(220,38,38,.08);
      border:1px solid rgba(220,38,38,.18);
      color:rgba(127,29,29,.96);
      font-size:13px;
      line-height:1.45;
    }
    .approve-box{
      margin-top:10px;
      padding:12px;
      border-radius:14px;
      background:rgba(15,23,42,.06);
      border:1px solid rgba(0,0,0,.10);
    }
    .approve-label{
      font-size:13px;
      color:rgba(15,23,42,.70);
      margin:0 0 6px 0;
    }
    .approve-value{
      margin:0;
      font-size:16px;
      font-weight:800;
      line-height:1.35;
      color:#0f172a;
    }
    .approve-email{
      margin:0;
      font-size:14px;
      font-weight:700;
      line-height:1.35;
      color:#0f172a;
      word-break:break-word;
    }
    .approve-time{
      margin-top:12px;
      font-size:14px;
      font-weight:700;
      color:#0f172a;
    }
    .btn-ok{
      background:rgba(22,163,74,.96);
      border-color:rgba(22,163,74,.38);
      color:#fff;
      font-weight:700;
    }
    .btn-danger{
      background:rgba(220,38,38,.96);
      border-color:rgba(220,38,38,.38);
      color:#fff;
      font-weight:700;
    }
  </style>
</head>
<body class="modal-page">

  <div class="modal" role="dialog" aria-modal="true" aria-label="Schválení přihlášení">

    <?php if ($canDecide) { ?>
      <form method="post">
        <input type="hidden" name="decision" value="ne">
        <button type="submit" class="modal-x" aria-label="Zavřít">×</button>
      </form>
    <?php } else { ?>
      <button type="button" class="modal-x" id="btnX" aria-label="Zavřít">×</button>
    <?php } ?>

    <div class="modal-head">
      <div class="modal-logo">
        <img src="<?= h1(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title"><?= h1($title) ?></p>
        <p class="<?= ($stav === 'ok' ? 'done-big' : 'modal-sub') ?>"><?= h1($info) ?></p>
      </div>
    </div>

    <div class="modal-copy"><?= h1($dbgText) ?></div>

    <?php if ($canDecide) { ?>
      <div class="approve-box">
        <p class="approve-label">Přihlašuje se uživatel:</p>
        <p class="approve-value"><?= h1($celeJmeno) ?></p>

        <div class="modal-spacer"></div>

        <p class="approve-label">Email použitý k přihlášení:</p>
        <p class="approve-email"><?= h1($email) ?></p>

        <div class="modal-spacer"></div>

        <p class="approve-label">Přihlášení z IP:</p>
        <p class="approve-value"><?= h1($ip) ?></p>
      </div>

      <div class="approve-time" id="countTxt">Na rozhodnutí zbývá: --:-- min.</div>

      <div class="modal-spacer"></div>

      <form method="post">
        <input type="hidden" name="decision" value="ok">
        <button class="modal-btn btn-ok" type="submit">Ano, jsem to já</button>
      </form>

      <div class="modal-spacer"></div>

      <form method="post">
        <input type="hidden" name="decision" value="ne">
        <button class="modal-btn btn-danger" type="submit">Zamítnout přístup</button>
      </form>
    <?php } elseif ($stav === 'ne') { ?>
      <div class="warn-box">
        Pokud máte podezření na zneužití Vašich přihlašovacích údajů do systému „Směny“ společnosti Pizza Comeback, změňte si co nejdříve heslo.<br><br>
        <a href="https://smeny.pizzacomeback.cz/" target="_blank" rel="noopener noreferrer">https://smeny.pizzacomeback.cz/</a>
      </div>

      <div class="modal-spacer"></div>

      <button class="modal-btn btn-danger" type="button" id="btnClose">Zavři okno</button>
    <?php } else { ?>
      <button class="modal-btn" type="button" id="btnClose">Zavři okno</button>
    <?php } ?>

  </div>

<script>
(function(){
  var rowOk = <?= json_encode(is_array($row), JSON_UNESCAPED_UNICODE) ?>;
  if (!rowOk) {
    var btnClose0 = document.getElementById('btnClose');
    if (btnClose0) btnClose0.addEventListener('click', function(){ location.replace('about:blank'); });
    var btnX0 = document.getElementById('btnX');
    if (btnX0) btnX0.addEventListener('click', function(){ location.replace('about:blank'); });
    return;
  }

  var canDecide = <?= json_encode($canDecide, JSON_UNESCAPED_UNICODE) ?>;
  var stav = <?= json_encode($stav, JSON_UNESCAPED_UNICODE) ?>;
  var zbyva = <?= (int)$zbyvaSec ?>;

  function finish(){
    location.replace('about:blank');
  }

  function fmt(sec){
    if (sec < 0) sec = 0;
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return String(m) + ':' + String(s).padStart(2,'0');
  }

  var countTxt = document.getElementById('countTxt');

  function render(){
    if (countTxt && canDecide) {
      countTxt.textContent = 'Na rozhodnutí zbývá: ' + fmt(zbyva) + ' min.';
    }
  }

  render();

  if (canDecide) {
    setInterval(function(){
      zbyva--;
      render();
    }, 1000);
  } else {
    if (stav === 'ok') {
      setTimeout(finish, 2000);
    }
  }

  var btnClose = document.getElementById('btnClose');
  if (btnClose) {
    btnClose.addEventListener('click', finish);
  }

  var btnX = document.getElementById('btnX');
  if (btnX) {
    btnX.addEventListener('click', finish);
  }
})();
</script>

</body>
</html>
<?php
/* mobil/mobil_overeni.php * Verze: V8 * Aktualizace: 07.03.2026 * Počet řádků: 434 */
/* Předchozí počet řádků: 327 */
// Konec souboru
