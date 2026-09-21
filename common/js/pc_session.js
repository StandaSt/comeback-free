/*
 * Ucel: Udrzuje serverovou session zarizeni zive a rychle uzamkne stare okno,
 * pokud se ve stejnem profilu prohlizece prihlasil jiny uzivatel.
 */
(function () {
  'use strict';

  var sessionToken = String(window.CB_PC_SESSION_TOKEN || '');
  if (sessionToken === '' || typeof window.fetch !== 'function') {
    return;
  }

  var heartbeatRunning = false;
  var lastHeartbeatAt = 0;
  var heartbeatIntervalMs = 60 * 1000;
  var minimumRepeatMs = 15 * 1000;

  function heartbeat(force) {
    var now = Date.now();
    if (heartbeatRunning || (!force && (now - lastHeartbeatAt) < minimumRepeatMs)) {
      return;
    }

    heartbeatRunning = true;
    lastHeartbeatAt = now;
    window.fetch(window.CB_ENDPOINT || 'index.php', {
      method: 'POST',
      headers: { 'X-Comeback-Heartbeat': '1' },
      credentials: 'same-origin',
      cache: 'no-store'
    }).catch(function () {
      // Stav 401/409 zpracovava centralni AJAX vrstva. Vypadek site se zkusi znovu.
    }).finally(function () {
      heartbeatRunning = false;
    });
  }

  heartbeat(true);
  window.setInterval(function () {
    heartbeat(false);
  }, heartbeatIntervalMs);

  window.addEventListener('focus', function () {
    heartbeat(true);
  });
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      heartbeat(true);
    }
  });
}());
