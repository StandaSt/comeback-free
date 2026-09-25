<?php
// mobil/mobil_overeni.php * Verze: V11 * Aktualizace: 12.09.2026
declare(strict_types=1);

/*
 * 2FA - SCHVALENI PRIHLASENI NA MOBILU
 *
 * URL:
 * - mobil/mobil_overeni.php?t=<token>
 *
 * Ucel souboru:
 * - nacist 2FA pozadavek podle tokenu
 * - zobrazit udaje o prihlaseni a tlacitka pro rozhodnuti
 * - pri schvaleni ulozit cookie duveryhodneho zarizeni
 * - pri stejne prihlasovaci session dokoncit login a otevrit IS
 *
 * Poznamka:
 * - polling pocitace resi samostatny common/lib/push_2fa_api.php
 */
require_once __DIR__ . '/../../common/lib/session_boot.php';

require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/system.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/duveryhodne_zarizeni.php';
/* Limit pro odpocet v UI. Rozhodujici expirace je ulozena v databazi. */
$limitSecPhp = 60;
if (defined('CB_2FA_LIMIT_SEC')) {
    $limitSecPhp = (int)CB_2FA_LIMIT_SEC;
    if ($limitSecPhp <= 0) {
        $limitSecPhp = 60;
    }
}

/* Token z URL */
$token = (string)($_GET['t'] ?? '');
$token = trim($token);
$sessionToken = (string)($_SESSION['cb_2fa_token'] ?? '');
$sameLoginSession = ($token !== '' && $sessionToken !== '' && hash_equals($sessionToken, $token));
$checkUrl = cb_root_url('common/lib/push_2fa_api.php?check=1');
$targetUrl = cb_login_target_url();

/* Bezpecne escapuje text pro HTML vystup. */
function h1(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/* Nacte radek 2FA z databaze podle tokenu. */
function cb_fetch_2fa(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $stmt = db()->prepare('
        SELECT id, id_user, stav, ip, prohlizec, vytvoreno, vyprsi, id_zarizeni, TIMESTAMPDIFF(SECOND, NOW(), vyprsi) AS zbyva_sec
        FROM push_login_2fa
        WHERE token=?
        LIMIT 1
    ');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();

    $stmt->bind_result($id, $idUser, $stav, $ip, $prohlizec, $vytvoreno, $vyprsi, $idZarizeni, $zbyvaSec);
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
        'id_zarizeni' => (int)($idZarizeni ?? 0),
        'zbyva_sec' => (int)$zbyvaSec,
    ];
}

/* Nacte jmeno a email uzivatele pro schvalovaci obrazovku. */
function cb_fetch_user_info(int $idUser): array
{
    if ($idUser <= 0) {
        return ['cele_jmeno' => '', 'email' => ''];
    }

    $stmt = db()->prepare('
        SELECT ou.jmeno, ou.prijmeni, u.email
        FROM user u
        INNER JOIN hr_osobni_udaje ou ON ou.id_person = u.id_user AND ou.platny = 1
        WHERE u.id_user=?
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

/* Zapise rozhodnuti pouze do platne cekajici 2FA vyzvy. */
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

/* Zpracuje rozhodnuti odeslane tlacitkem. */
$didPost = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
$decision = '';
$decisionSaved = false;

if ($didPost) {
    $decision = (string)($_POST['decision'] ?? '');
    $decision = trim($decision);

    if ($decision === 'ok' || $decision === 'ne') {
        $decisionSaved = cb_set_2fa_decision($token, $decision);
    }
}

/* Nacte aktualni stav po pripadnem ulozeni rozhodnuti. */
$row = cb_fetch_2fa($token);

$stav = is_array($row) ? (string)($row['stav'] ?? '') : '';
$ip = is_array($row) ? (string)($row['ip'] ?? '') : '';
$zbyvaSec = is_array($row) ? (int)($row['zbyva_sec'] ?? 0) : 0;
$idUser = is_array($row) ? (int)($row['id_user'] ?? 0) : 0;
$idZarizeni = is_array($row) ? (int)($row['id_zarizeni'] ?? 0) : 0;

if ($decisionSaved && $decision === 'ok' && $idUser > 0 && $idZarizeni > 0) {
    cb_duveryhodne_zarizeni_uloz_cookie(db(), $idUser, $idZarizeni);
}

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
$ipDisplay = $ip;
if ($ipDisplay !== '---' && strlen($ipDisplay) > 24) {
    $ipDisplay = substr($ipDisplay, 0, 24) . '...';
}

/* Pripravi cas rozhodnuti pro text uzivateli. */
$kdyRozhodnuto = date('j. n. Y \v H:i') . ' hod.';

/* Texty do UI podle stavu */
$title = 'Schválení přihlášení';
$info = '';

if (!is_array($row)) {
    $info = 'Neplatný nebo neznámý požadavek.';
} else {
    if ($stav === 'ok') {
        $info = $sameLoginSession ? 'Přístup schválen – vstupuji do IS…' : 'Přístup byl povolen';
    } elseif ($stav === 'ne') {
        $title = 'Přihlášení zamítnuto';
    } elseif ($stav === 'exp' || $zbyvaSec <= 0) {
        $info = 'Tento požadavek vypršel.';
    } else {
        $info = 'Rozhodni o přístupu do IS.';
    }
}

/* Rozhodovani je povolene jen v okne platnosti. */
$canDecide = (is_array($row) && $stav === 'ceka' && $zbyvaSec > 0);

?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h1($title) ?></title>
  <link rel="stylesheet" href="<?= h1(cb_public_url('style/modal_alert.css')) ?>">

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
      margin:0 0 3px 0;
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
      margin:12px 0 10px;
      font-size:14px;
      font-weight:700;
      color:#0f172a;
      text-align:center;
    }
    .login-denied-head{
      text-align:left;
    }
    .login-denied-status{
      margin:0;
      color:#c00;
    }
    .login-denied-info{
      margin-top:6px;
      text-align:center;
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
    .approval-layout{
      display:block;
    }
    @media (orientation:landscape) and (max-height:520px){
      body{
        padding:10px;
      }
      .modal{
        width:min(680px, 100%);
        max-height:calc(100dvh - 20px);
        padding:14px 18px;
        overflow:auto;
      }
      .modal-head{
        margin-bottom:8px;
      }
      .approval-layout{
        display:grid;
        grid-template-columns:minmax(0, 1fr) minmax(220px, .8fr);
        gap:14px;
        align-items:center;
      }
      .approve-box{
        margin-top:0;
      }
      .approval-actions{
        display:flex;
        flex-direction:column;
        justify-content:center;
      }
      .approval-actions .approve-time{
        margin:0 0 10px;
      }
      .approval-actions .modal-spacer{
        height:8px;
      }
      .login-denied-modal{
        display:grid;
        grid-template-columns:minmax(0, 1fr) minmax(280px, 1.15fr);
        column-gap:14px;
        row-gap:8px;
        align-items:center;
      }
      .login-denied-modal > .modal-head{
        grid-column:1;
        grid-row:1 / span 3;
        margin-bottom:0;
      }
      .login-denied-modal > .warn-box{
        grid-column:2;
        grid-row:1;
        margin-top:0;
      }
      .login-denied-modal > .modal-spacer{
        grid-column:2;
        grid-row:2;
        height:0;
      }
      .login-denied-modal > .login-denied-reset-form{
        grid-column:2;
        grid-row:3;
      }
    }
  </style>
</head>
<body class="modal-page">

  <div class="modal<?= $stav === 'ne' ? ' login-denied-modal' : '' ?>" role="dialog" aria-modal="true" aria-label="Schválení přihlášení">

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
        <img src="<?= h1(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div<?= $stav === 'ne' ? ' class="login-denied-head"' : '' ?>>
        <?php if ($stav === 'ne') { ?>
          <p class="modal-title login-denied-status"><?= h1($title) ?></p>
        <?php } else { ?>
          <p class="modal-title"><?= h1($title) ?></p>
          <p class="<?= ($stav === 'ok' ? 'done-big' : 'modal-sub') ?>" id="approvalInfo"><?= h1($info) ?></p>
        <?php } ?>
      </div>
    </div>

    <?php if ($canDecide) { ?>
      <div class="approval-layout">
        <div class="approval-details">
          <div class="approve-box">
            <p class="approve-label">Přihlašuje se uživatel:</p>
            <p class="approve-value"><?= h1($celeJmeno) ?></p>

            <div class="modal-spacer"></div>

            <p class="approve-label">Email použitý k přihlášení:</p>
            <p class="approve-email"><?= h1($email) ?></p>

            <div class="modal-spacer"></div>

            <p class="approve-label">Přihlášení z IP:</p>
            <p class="approve-email"><?= h1($ipDisplay) ?></p>
          </div>

        </div>

        <div class="approval-actions">
          <div class="approve-time" id="countTxt">Na rozhodnutí zbývá: --:-- min.</div>

          <form method="post">
            <input type="hidden" name="decision" value="ok">
            <button class="modal-btn btn-ok" type="submit">Ano, jsem to já</button>
          </form>

          <div class="modal-spacer"></div>

          <form method="post">
            <input type="hidden" name="decision" value="ne">
            <button class="modal-btn btn-danger" type="submit">Zamítnout přístup</button>
          </form>
        </div>
      </div>
    <?php } elseif ($stav === 'ne') { ?>
      <div class="warn-box">
        Pokud máte podezření na zneužití Vašich přihlašovacích údajů do IS Comeback, změňte si co nejdříve heslo.
      </div>

      <div class="modal-spacer"></div>

      <form class="login-denied-reset-form" method="post" action="<?= h1(cb_root_url('')) ?>">
        <input type="hidden" name="cb_action" value="nove_heslo_formular">
        <input type="hidden" name="email" value="<?= h1($email === '---' ? '' : $email) ?>">
        <button class="modal-btn btn-danger" type="submit">Nastavit nové heslo</button>
      </form>
    <?php } elseif ($stav === 'ok' && $sameLoginSession) { ?>
      <button class="modal-btn" type="button" id="btnEnter">Vstoupit do IS</button>
    <?php } else { ?>
      <button class="modal-btn" type="button" id="btnClose">Zavři okno</button>
    <?php } ?>

  </div>

<script>
(function(){
  /* Ucel funkce: Ridit schvaleni 2FA na mobilu a dokoncit prihlaseni. */
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
  var sameLoginSession = <?= json_encode($sameLoginSession, JSON_UNESCAPED_UNICODE) ?>;
  var zbyva = <?= (int)$zbyvaSec ?>;
  var vstupBezi = false;
  var approvalInfo = document.getElementById('approvalInfo');

  /* Zavre samostatnou schvalovaci stranku. */
  function finish(){
    location.replace('about:blank');
  }

  /* Dokonci stejnou prihlasovaci session a otevre cilovy modul. */
  function vstupDoIs(){
    if (!sameLoginSession || vstupBezi) return;
    vstupBezi = true;

    fetch(<?= json_encode($checkUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
      cache: 'no-store',
      credentials: 'same-origin'
    })
      .then(function(response){ return response.json(); })
      .then(function(result){
        if (result && result.ok === true && (result.stav === 'ok' || result.stav === 'exp')) {
          location.replace(<?= json_encode($targetUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
          return;
        }
        throw new Error('Dokončení přihlášení se nezdařilo.');
      })
      .catch(function(){
        vstupBezi = false;
        if (approvalInfo) {
          approvalInfo.textContent = 'Přístup je schválen. Klepněte na „Vstoupit do IS“.';
        }
      });
  }

  /* Prevede sekundy na citelny odpocet. */
  function fmt(sec){
    if (sec < 0) sec = 0;
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return String(m) + ':' + String(s).padStart(2,'0');
  }

  var countTxt = document.getElementById('countTxt');

  /* Prekresli zbyvajici cas pro rozhodnuti. */
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
      if (sameLoginSession) {
        vstupDoIs();
      } else {
        setTimeout(finish, 2000);
      }
    }
  }

  var btnEnter = document.getElementById('btnEnter');
  if (btnEnter) {
    btnEnter.addEventListener('click', vstupDoIs);
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
/* mobil/mobil_overeni.php * Verze: V11 * Aktualizace: 12.09.2026 */
// Konec souboru
