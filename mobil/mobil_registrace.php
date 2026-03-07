<?php
// mobil/mobil_registrace.php * Verze: V7 * Aktualizace: 07.03.2026 * Po�et ��dk�: 376
// P�edchoz� po�et ��dk�: 393
declare(strict_types=1);

/*
 * REGISTRACE ZA��ZEN� (mobiln� str�nka)
 *
 * Co d�l�:
 * - jede BEZ session: identifikace u�ivatele je p�es token v URL (?t=...)
 * - vy��d� povolen� notifikac�
 * - zaregistruje Service Worker (/sw.js)
 * - vytvo�� Push subscription (VAPID public)
 * - ode�le subscription + token na server (POST)
 * - server ov��� token v tabulce push_parovani (hash, aktivn�, neexpirace, nepou�it�)
 * - pravidlo: v�dy jen 1 aktivn� za��zen� (ostatn� deaktivuje)
 * - ulo�� subscription do DB do push_zarizeni a ozna�� token jako pou�it�
 *
 * CSS:
 * - pou��v� jednotn� t��dy z style/1/modal_alert.css (modal-page, modal, modal-btn, atd.)
 */

require_once __DIR__ . '/../lib/bootstrap.php';

header('X-Robots-Tag: noindex, nofollow');

$vapidPublic = defined('CB_VAPID_PUBLIC') ? (string)CB_VAPID_PUBLIC : '';

$token = '';
if (isset($_GET['t'])) {
    $token = trim((string)$_GET['t']);
}

function cb_find_pair_token(string $token): ?array
{
    if ($token === '' || strlen($token) < 20) {
        return null;
    }

    $conn = db();

    $stmt = $conn->prepare('
        SELECT id, id_user
        FROM push_parovani
        WHERE token_hash = UNHEX(SHA2(?,256))
          AND aktivni=1
          AND pouzito_kdy IS NULL
          AND expirace > NOW()
        LIMIT 1
    ');

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();

    $idPar = null;
    $idUser = null;

    $stmt->bind_result($idPar, $idUser);
    $has = $stmt->fetch();
    $stmt->close();

    if ($has !== true || $idPar === null || $idUser === null) {
        return null;
    }

    return [
        'id' => (int)$idPar,
        'id_user' => (int)$idUser,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $conn = null;

    try {
        $raw = (string)file_get_contents('php://input');
        if ($raw === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chyb� JSON.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Neplatn� JSON.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $tokenPost = isset($data['token']) ? trim((string)$data['token']) : '';
        $subscription = $data['subscription'] ?? null;
        $nazev = isset($data['nazev']) ? trim((string)$data['nazev']) : 'Za��zen�';

        if ($nazev === '') {
            $nazev = 'Za��zen�';
        }

        if (!is_array($subscription)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chyb� subscription.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $endpoint = isset($subscription['endpoint']) ? trim((string)$subscription['endpoint']) : '';
        $keys = $subscription['keys'] ?? null;

        if ($endpoint === '' || !is_array($keys)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Neplatn� subscription data.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $p256dh = isset($keys['p256dh']) ? trim((string)$keys['p256dh']) : '';
        $auth = isset($keys['auth']) ? trim((string)$keys['auth']) : '';

        if ($p256dh === '' || $auth === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chyb� kl��e subscription.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $pair = cb_find_pair_token($tokenPost);
        if (!is_array($pair)) {
            http_response_code(410);
            echo json_encode(['ok' => false, 'err' => 'Token je neplatn� nebo vypr�el.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $idPar = (int)$pair['id'];
        $idUser = (int)$pair['id_user'];

        $conn = db();
        $conn->begin_transaction();

        $stmt = $conn->prepare('
            UPDATE push_zarizeni
            SET aktivni=0
            WHERE id_user=?
        ');
        if (!$stmt) {
            throw new RuntimeException('Nelze deaktivovat star� za��zen�.');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();

        $subJson = json_encode($subscription, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($subJson === false) {
            throw new RuntimeException('Nelze serializovat subscription.');
        }

        $stmt = $conn->prepare('
            INSERT INTO push_zarizeni
              (id_user, nazev, endpoint, p256dh, auth, subscription_json, aktivni, vytvoreno_kdy)
            VALUES
              (?, ?, ?, ?, ?, ?, 1, NOW())
        ');
        if (!$stmt) {
            throw new RuntimeException('Nelze ulo�it za��zen�.');
        }
        $stmt->bind_param('isssss', $idUser, $nazev, $endpoint, $p256dh, $auth, $subJson);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('
            UPDATE push_parovani
            SET pouzito_kdy=NOW(), aktivni=0
            WHERE id=?
            LIMIT 1
        ');
        if (!$stmt) {
            throw new RuntimeException('Nelze uzav��t token.');
        }
        $stmt->bind_param('i', $idPar);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        if ($conn instanceof mysqli) {
            $conn->rollback();
        }
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$pair = cb_find_pair_token($token);
$tokenOk = is_array($pair);
$dbgUser = $tokenOk ? (int)($pair['id_user'] ?? 0) : 0;
$dbgToken = substr($token, 0, 8);
$dbgStav = $tokenOk ? 'token_ok' : 'token_bad';
$dbgText = 'DBG: V7 | user ' . $dbgUser . ' | token ' . $dbgToken . ' | stav ' . $dbgStav;
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback � registrace za��zen�</title>
  <link rel="stylesheet" href="<?= h(cb_url('style/1/modal_alert.css')) ?>">
</head>
<body class="modal-page">

  <div class="modal modal-device-register" role="dialog" aria-modal="true" aria-label="Registrace za��zen�">

    <a class="modal-x" href="about:blank" aria-label="Zav��t">�</a>

    <div class="modal-head modal-head-top">
      <div class="modal-logo modal-logo-lg"><img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback"></div>
    </div>

    <div class="modal-center modal-center-lg">
      <p class="modal-title modal-title-center">Registrace za��zen�</p>
      <div class="modal-copy modal-copy-wide"><?= h($dbgText) ?></div>

      <?php if ($vapidPublic === '') { ?>
        <p><strong>Chyb� VAPID public key.</strong></p>
        <p class="muted">Nastav konstantu <code>CB_VAPID_PUBLIC</code> v <code>lib/system.php</code>.</p>
      <?php } elseif (!$tokenOk) { ?>
        <p><strong>Neplatn� nebo expirovan� odkaz.</strong></p>
        <p class="muted">Vra�te se na PC, otev�ete IS a vygenerujte nov� QR k�d pro registraci za��zen�.</p>
      <?php } else { ?>
        <div class="modal-copy modal-copy-wide">
          Je t�eba povolit notifikace a zaregistrovat toto za��zen�.
        </div>

        <button type="button" class="modal-btn" id="btnPerm">Povolit notifikace</button>
        <div class="modal-spacer"></div>
        <button type="button" class="modal-btn primary" id="btnPair" disabled>Registrovat za��zen�</button>

        <div class="modal-status modal-status-center" id="countdownTxt">Na zaregistrov�n� za��zen� zb�v�: 05:00</div>
        <div class="out" id="out">Stav: �ek�m�</div>
      <?php } ?>

    </div>

  </div>

<?php if ($vapidPublic !== '' && $tokenOk) { ?>
<script>
(function(){
  var out = document.getElementById('out');
  var btnPerm = document.getElementById('btnPerm');
  var btnPair = document.getElementById('btnPair');
  var countdownTxt = document.getElementById('countdownTxt');
  var deadlineTs = Date.now() + 300000;

  function pad(n){
    return String(n).padStart(2, '0');
  }

  function renderCountdown(){
    if (!countdownTxt) {
      return;
    }
    var ms = deadlineTs - Date.now();
    if (ms < 0) {
      ms = 0;
    }
    var sec = Math.floor(ms / 1000);
    var min = Math.floor(sec / 60);
    var rest = sec % 60;
    countdownTxt.textContent = 'Na zaregistrov�n� za��zen� zb�v�: ' + pad(min) + ':' + pad(rest);
  }

  function log(msg){
    if (out) {
      out.textContent = 'Stav: ' + msg;
    }
  }

  if (!('serviceWorker' in navigator)) {
    log('Service Worker nen� podporovan�.');
    return;
  }
  if (!('Notification' in window)) {
    log('Notifikace nejsou podporovan�.');
    return;
  }
  if (!('PushManager' in window)) {
    log('PushManager nen� podporovan�.');
    return;
  }

  var vapidPublic = <?php echo json_encode($vapidPublic, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  var token = <?php echo json_encode($token, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;

  function b64UrlToUint8Array(base64Url){
    var padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
    var base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; i++) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  }

  btnPerm.addEventListener('click', function(){
    Notification.requestPermission().then(function(permission){
      if (permission === 'granted') {
        log('Notifikace byly povoleny.');
        btnPair.disabled = false;
      } else {
        log('Notifikace nebyly povoleny.');
      }
    }).catch(function(err){
      log('Chyba: ' + (err && err.message ? err.message : err));
    });
  });

  btnPair.addEventListener('click', function(){
    if (Notification.permission !== 'granted') {
      log('Nejd��v povolte notifikace.');
      return;
    }

    navigator.serviceWorker.ready.then(function(reg){
      return reg.pushManager.getSubscription().then(function(sub){
        if (sub) {
          return sub;
        }
        return reg.pushManager.subscribe({
          userVisibleOnly: true,
          applicationServerKey: b64UrlToUint8Array(vapidPublic)
        });
      });
    }).then(function(subscription){
      log('Subscription z�sk�n, ukl�d�m do DB�');

      return fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token: token,
          subscription: subscription,
          nazev: 'Za��zen�'
        })
      });
    }).then(function(res){
      return res.json().then(function(data){
        return { status: res.status, data: data };
      });
    }).then(function(r){
      if (r.status !== 200 || !r.data || r.data.ok !== true) {
        log('Ulo�en� selhalo: ' + (r.data && r.data.err ? r.data.err : 'nezn�m� chyba'));
        return;
      }
      log('Hotovo: za��zen� je zaregistrov�no. M��ete se vr�tit na PC.');
      btnPair.disabled = true;
    }).catch(function(err){
      log('Chyba: ' + (err && err.message ? err.message : err));
    });
  });

  renderCountdown();
  setInterval(renderCountdown, 1000);
})();
</script>
<?php } ?>
</body>
</html>
<?php
// Konec souboru
