<?php
// push_test.php * Verze: V2 * Aktualizace: 25.2.2026
declare(strict_types=1);

/*
 * Test + párování mobilu pro web notifikace
 *
 * Co umí:
 * - registrace Service Worker (/sw.js)
 * - vyžádání povolení notifikací
 * - vytvoření Push subscription (párování mobilu)
 * - uložení subscription do DB přes lib/push_pair.php
 *
 * Pozn.:
 * - Pro vytvoření subscription je nutný VAPID public key.
 * - Nastav ho jako konstantu CB_VAPID_PUBLIC (např. v lib/system.php).
 */

require_once __DIR__ . '/lib/bootstrap.php';

$loginOk = (bool)($_SESSION['login_ok'] ?? false);
$idUser = 0;
$cbUser = $_SESSION['cb_user'] ?? null;
if (is_array($cbUser) && isset($cbUser['id_user'])) {
    $idUser = (int)$cbUser['id_user'];
}

$vapidPublic = '';
if (defined('CB_VAPID_PUBLIC')) {
    $vapidPublic = (string)CB_VAPID_PUBLIC;
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback – párování mobilu</title>
  <style>
    body{ font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 0; padding: 18px; background: #eaf2fb; }
    .card{ max-width: 560px; margin: 0 auto; background: #fff; border: 1px solid rgba(0,0,0,.12); border-radius: 16px; padding: 16px; box-shadow: 0 10px 26px rgba(0,0,0,.10); }
    h1{ font-size: 18px; margin: 0 0 10px 0; }
    p{ margin: 8px 0; line-height: 1.35; }
    button{ width: 100%; padding: 12px 14px; border-radius: 14px; border: 1px solid rgba(0,0,0,.14); font-size: 14px; cursor: pointer; background: #fff; }
    button.primary{ background: rgba(37,99,235,.96); border-color: rgba(37,99,235,.35); color: #fff; font-weight: 600; }
    button:disabled{ opacity: .55; cursor: default; }
    .muted{ color: rgba(15,23,42,.70); font-size: 13px; }
    .out{ margin-top: 10px; padding: 10px 12px; border-radius: 14px; background: rgba(15,23,42,.06); font-size: 13px; }
    .logo{ width: 46px; height: 46px; border-radius: 14px; border: 1px solid rgba(0,0,0,.14); background: #fff; display: grid; place-items: center; overflow: hidden; }
    .logo img{ width: 100%; height: 100%; object-fit: contain; padding: 7px; }
    .row{ display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
    code{ background: rgba(15,23,42,.08); padding: 2px 6px; border-radius: 8px; }
  </style>
</head>
<body>
  <div class="card">
    <div class="row">
      <div class="logo"><img src="img/logo_comeback.png" alt="Comeback"></div>
      <div>
        <h1>Párování mobilu</h1>
        <div class="muted">Uloží mobil (prohlížeč) do DB, aby šlo schvalovat přihlášení notifikací.</div>
      </div>
    </div>

    <?php if (!$loginOk || $idUser <= 0) { ?>
      <p><strong>Nejsi přihlášen.</strong></p>
      <p class="muted">Nejdřív se přihlas v IS. Pak otevři tuhle stránku znovu na mobilu.</p>
    <?php } elseif ($vapidPublic === '') { ?>
      <p><strong>Chybí VAPID public key.</strong></p>
      <p class="muted">
        Nastav konstantu <code>CB_VAPID_PUBLIC</code> (např. v <code>lib/system.php</code>).
        Bez toho nejde vytvořit subscription (párování).
      </p>
    <?php } else { ?>
      <p class="muted">Postup: 1) Povolit notifikace → 2) Spárovat mobil.</p>

      <button type="button" id="btnPerm">1) Povolit notifikace</button>
      <div style="height:10px"></div>
      <button type="button" class="primary" id="btnPair" disabled>2) Spárovat mobil</button>

      <div class="out" id="out">Stav: čekám…</div>
    <?php } ?>
  </div>

<?php if ($loginOk && $idUser > 0 && $vapidPublic !== '') { ?>
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

  function b64UrlToUint8Array(base64Url){
    var padding = '='.repeat((4 - (base64Url.length % 4)) % 4);
    var base64 = (base64Url + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = atob(base64);
    var outputArray = new Uint8Array(rawData.length);
    var i = 0;
    for (i = 0; i < rawData.length; i++) {
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

      return fetch('<?php echo h(cb_url('lib/push_pair.php')); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
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
      log('Hotovo: mobil spárován.');
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
/* push_test.php * Verze: V2 * Aktualizace: 25.2.2026 * Počet řádků: 196 */
// Konec souboru