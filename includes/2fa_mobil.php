<?php
// includes/2fa_mobil.php * Verze: V3 * Aktualizace: 27.2.2026
declare(strict_types=1);

/*
 * 2FA – schválení přihlášení (mobilní stránka)
 *
 * URL:
 * - includes/2fa_mobil.php?t=<token>
 *
 * Co dělá:
 * - načte 2FA požadavek z DB tabulky push_login_2fa podle tokenu
 * - zobrazí informace o pokusu (IP, prohlížeč, čas do expirace)
 * - umožní rozhodnout: ok / ne (jen pokud stav=ceka a nevypršelo)
 * - po rozhodnutí nabídne zavření okna (window.close)
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

/* Zapsání rozhodnutí (ok/ne) do DB – jen když stav=ceka a vyprsi > NOW() */
function cb_set_2fa_decision(string $token, string $decision): bool
{
    if ($token === '') {
        return false;
    }
    if ($decision !== 'ok' && $decision !== 'ne') {
        return false;
    }

    $stmt = db()->prepare('
        UPDATE push_login_2fa
        SET stav=?, rozhodnuto=NOW()
        WHERE token=? AND stav=\'ceka\' AND vyprsi > NOW()
    ');
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
$ua = is_array($row) ? (string)($row['prohlizec'] ?? '') : '';
$zbyvaSec = is_array($row) ? (int)($row['zbyva_sec'] ?? 0) : 0;

if ($ua === '') {
    $ua = '---';
}
if ($ip === '') {
    $ip = '---';
}

/* Texty do UI podle stavu */
$title = 'Schválení přihlášení';
$info = '';

if (!is_array($row)) {
    $info = 'Neplatný nebo neznámý požadavek.';
} else {
    if ($stav === 'ok') {
        $info = 'Hotovo: přístup byl povolen.';
    } elseif ($stav === 'ne') {
        $info = 'Hotovo: přístup byl zamítnut.';
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
  <link rel="stylesheet" href="<?= h(cb_url('style/1/modal_alert.css')) ?>">
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
      <div>
        <p class="modal-title"><?= h1($title) ?></p>
        <p class="modal-sub"><?= h1($info) ?></p>
      </div>
    </div>

    <?php if (is_array($row)) { ?>
      <div class="modal-box">
        <p class="modal-label">IP:</p>
        <div class="muted"><?= h1($ip) ?></div>

        <div class="modal-spacer"></div>

        <p class="modal-label">Prohlížeč:</p>
        <div class="muted"><?= h1($ua) ?></div>

        <div class="modal-spacer"></div>

        <p class="modal-label">Limit:</p>
        <div class="muted" id="limitTxt">--:--</div>
      </div>

      <div class="muted" id="countTxt"></div>
    <?php } ?>

    <?php if ($canDecide) { ?>
      <form method="post">
        <input type="hidden" name="decision" value="ok">
        <button class="modal-btn primary" type="submit">Ano, jsem to já</button>
      </form>

      <div class="modal-spacer"></div>

      <form method="post">
        <input type="hidden" name="decision" value="ne">
        <button class="modal-btn" type="submit">Zamítnout přístup, nejsem to já</button>
      </form>
    <?php } else { ?>
      <button class="modal-btn" type="button" id="btnClose">Zavři okno</button>
    <?php } ?>

  </div>

<script>
(function(){
  // Pokud nemáme řádek z DB (neplatný token), jen nabídneme zavření okna
  var rowOk = <?= json_encode(is_array($row), JSON_UNESCAPED_UNICODE) ?>;
  if (!rowOk) {
    var btnClose0 = document.getElementById('btnClose');
    if (btnClose0) btnClose0.addEventListener('click', function(){ window.close(); });
    var btnX0 = document.getElementById('btnX');
    if (btnX0) btnX0.addEventListener('click', function(){ window.close(); });
    return;
  }

  // Odpočet (UI) – skutečná platnost je stejně řízena DB (vyprsi)
  var canDecide = <?= json_encode($canDecide, JSON_UNESCAPED_UNICODE) ?>;
  var zbyva = <?= (int)$zbyvaSec ?>;
  var limit = <?= (int)$limitSecPhp ?>;

  function fmt(sec){
    if (sec < 0) sec = 0;
    var m = Math.floor(sec / 60);
    var s = sec % 60;
    return String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
  }

  var limitTxt = document.getElementById('limitTxt');
  var countTxt = document.getElementById('countTxt');

  function render(){
    if (limitTxt) limitTxt.textContent = fmt(zbyva);
    if (countTxt) countTxt.textContent = canDecide ? ('Na rozhodnutí máte ' + fmt(zbyva)) : '';
  }

  render();

  if (canDecide) {
    setInterval(function(){
      zbyva--;
      render();
    }, 1000);
  } else {
    // Pokud už je rozhodnuto / vypršelo: zkus zavřít okno, případně nabídni tlačítko
    var btnClose = document.getElementById('btnClose');
    if (btnClose) btnClose.addEventListener('click', function(){ window.close(); });
    var btnX = document.getElementById('btnX');
    if (btnX) btnX.addEventListener('click', function(){ window.close(); });
    setTimeout(function(){ window.close(); }, 300);
  }
})();
</script>

</body>
</html>
<?php
/* includes/2fa_mobil.php * Verze: V3 * Aktualizace: 27.2.2026 * Počet řádků: 278 */
// Konec souboru