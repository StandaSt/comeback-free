<?php
// includes/parovani_mobilu.php * Verze: V4 * Aktualizace: 26.2.2026 * Počet řádků: 380
declare(strict_types=1);

/*
 * PÁROVÁNÍ MOBILU (mobilní stránka)
 *
 * Co dělá:
 * - jede BEZ session: identifikace uživatele je přes token v URL (?t=...)
 * - vyžádá povolení notifikací
 * - zaregistruje Service Worker (/sw.js)
 * - vytvoří Push subscription (VAPID public)
 * - odešle subscription + token na server (POST)
 * - server ověří token v tabulce push_parovani (hash, aktivní, neexpirace, nepoužitý)
 * - pravidlo: vždy jen 1 aktivní zařízení (ostatní deaktivuje)
 * - uloží subscription do DB do push_zarizeni a označí token jako použitý
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

/* =========================
   0) POST JSON: uložení subscription do DB (párování)
   ========================= */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $conn = null;

    try {
        $raw = (string)file_get_contents('php://input');
        if ($raw === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chybí JSON.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Neplatný JSON.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $t = $data['token'] ?? '';
        if (!is_string($t)) {
            $t = '';
        }
        $t = trim($t);

        $tok = cb_find_pair_token($t);
        if ($tok === null) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'err' => 'Neplatný nebo expirovaný token.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $idPar = (int)$tok['id'];
        $idUser = (int)$tok['id_user'];

        $sub = $data['subscription'] ?? null;
        if (!is_array($sub)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chybí subscription.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $endpoint = $sub['endpoint'] ?? '';
        if (!is_string($endpoint) || trim($endpoint) === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chybí endpoint.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $endpoint = trim($endpoint);

        $keys = $sub['keys'] ?? null;
        if (!is_array($keys)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chybí keys.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $kPublic = $keys['p256dh'] ?? '';
        $kAuth   = $keys['auth'] ?? '';

        if (!is_string($kPublic) || trim($kPublic) === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chybí klic_public.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!is_string($kAuth) || trim($kAuth) === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'err' => 'Chybí klic_auth.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $kPublic = trim($kPublic);
        $kAuth = trim($kAuth);

        $nazev = $data['nazev'] ?? '';
        if (!is_string($nazev)) {
            $nazev = '';
        }
        $nazev = trim($nazev);
        if ($nazev === '') {
            $nazev = 'Mobil';
        }

        $conn = db();
        $conn->begin_transaction();

        // 1) deaktivuj stará zařízení (pravidlo: 1 aktivní)
        $stmt = $conn->prepare('UPDATE push_zarizeni SET aktivni=0 WHERE id_user=?');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (deaktivace).');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();

        // 2) vlož/aktualizuj nové jako aktivní
        $sql = '
            INSERT INTO push_zarizeni
            (id_user, endpoint, endpoint_hash, klic_public, klic_auth, nazev, aktivni, vytvoreno, naposledy)
            VALUES
            (?, ?, UNHEX(SHA2(?,256)), ?, ?, ?, 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
              klic_public = VALUES(klic_public),
              klic_auth   = VALUES(klic_auth),
              nazev       = VALUES(nazev),
              aktivni     = 1,
              naposledy   = NOW()
        ';

        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (insert).');
        }

        $stmt->bind_param('isssss', $idUser, $endpoint, $endpoint, $kPublic, $kAuth, $nazev);
        $stmt->execute();
        $stmt->close();

        // 3) označ token jako použitý (a zneaktivni)
        $stmt = $conn->prepare('UPDATE push_parovani SET aktivni=0, pouzito_kdy=NOW() WHERE id=?');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (token used).');
        }
        $stmt->bind_param('i', $idPar);
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Throwable $e) {
        if ($conn instanceof mysqli) {
            try {
                $conn->rollback();
            } catch (Throwable $e2) {
                // nic
            }
        }

        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* =========================
   1) HTML
   ========================= */
$tokenRow = cb_find_pair_token($token);
$tokenOk = ($tokenRow !== null);
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback – párování mobilu</title>
</head>
<body>
  <div class="modal-page">
    <div class="modal">

      <a class="modal-x" href="about:blank" aria-label="Zavřít">×</a>

      <div class="modal-head">
        <div class="modal-logo"><img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback"></div>
        <div>
          <p class="modal-title">Párování mobilu</p>
          <div class="modal-sub">Povol notifikace a spáruj mobil pro schvalování přihlášení.</div>
        </div>
      </div>

      <?php if ($vapidPublic === '') { ?>
        <p><strong>Chybí VAPID public key.</strong></p>
        <p class="muted">Nastav konstantu <code>CB_VAPID_PUBLIC</code> v <code>lib/system.php</code>.</p>
      <?php } elseif (!$tokenOk) { ?>
        <p><strong>Neplatný nebo expirovaný odkaz.</strong></p>
        <p class="muted">Vrať se na PC, otevři IS a vygeneruj nový QR kód pro párování.</p>
      <?php } else { ?>
        <p class="muted">Postup: 1) Povolit notifikace → 2) Spárovat mobil.</p>

        <button type="button" class="modal-btn" id="btnPerm">1) Povolit notifikace</button>
        <div style="height:10px"></div>
        <button type="button" class="modal-btn primary" id="btnPair" disabled>2) Spárovat mobil</button>

        <div class="out" id="out">Stav: čekám…</div>
      <?php } ?>
    </div>
  </div>

<?php if ($vapidPublic !== '' && $tokenOk) { ?>
<script>
(function(){
  var out = document.getElementById('out');
  var btnPerm = document.getElementById('btnPerm');
  var btnPair = document.getElementById('btnPair');

  function log(msg){
    out.textContent = 'Stav: ' + msg;
  }

  if (!('serviceWorker' in navigator)) {
    log('Service Worker není podporovaný.');
    return;
  }
  if (!('Notification' in window)) {
    log('Notifikace nejsou podporované.');
    return;
  }
  if (!('PushManager' in window)) {
    log('PushManager není podporovaný.');
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

  navigator.serviceWorker.register('/sw.js').then(function(reg){
    log('Service Worker registrován.');
    if (Notification.permission === 'granted') {
      btnPair.disabled = false;
      log('Notifikace jsou povolené.');
    }
    return reg;
  }).catch(function(err){
    log('Registrace Service Worker selhala: ' + (err && err.message ? err.message : err));
  });

  btnPerm.addEventListener('click', function(){
    Notification.requestPermission().then(function(p){
      if (p === 'granted') {
        btnPair.disabled = false;
        log('Notifikace povoleny.');
        return;
      }
      log('Notifikace nejsou povoleny: ' + p);
    }).catch(function(err){
      log('requestPermission selhal: ' + (err && err.message ? err.message : err));
    });
  });

  btnPair.addEventListener('click', function(){
    if (Notification.permission !== 'granted') {
      log('Nejdřív povol notifikace.');
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
      log('Subscription získán, ukládám do DB…');

      return fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          token: token,
          subscription: subscription,
          nazev: 'Mobil'
        })
      });
    }).then(function(res){
      return res.json().then(function(data){
        return { status: res.status, data: data };
      });
    }).then(function(r){
      if (r.status !== 200 || !r.data || r.data.ok !== true) {
        log('Uložení selhalo: ' + (r.data && r.data.err ? r.data.err : 'neznámá chyba'));
        return;
      }
      log('Hotovo: mobil spárován. Můžeš se vrátit na PC.');
    }).catch(function(err){
      log('Chyba: ' + (err && err.message ? err.message : err));
    });
  });

})();
</script>
<?php } ?>
</body>
</html>
<?php
/* includes/parovani_mobilu.php * Verze: V4 * Aktualizace: 26.2.2026 * Počet řádků: 380 */
// Konec souboru