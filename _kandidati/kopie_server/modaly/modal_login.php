<?php
declare(strict_types=1);

require_once __DIR__ . '/../funkce/last_aktualizace_systemu.php';

cb_last_aktualizace_systemu();

$aktualniUrl = cb_url_abs('');
$loginDbOk = !empty($cbLoginDbOk);
$loginDbName = trim((string)($cbLoginDbName ?? '---'));
if ($loginDbName === '') {
    $loginDbName = '---';
}
$loginDbText = 'DB ' . $loginDbName . ($loginDbOk ? ' OK' : ' nepřístupná');
$loginDbClass = $loginDbOk ? 'is-ok' : 'is-bad';
$loginDisabled = $loginDbOk ? '' : ' disabled';
?>
<div id="cb-login-overlay" class="modal-overlay" aria-modal="true" role="dialog" aria-label="Přihlášení do IS Comeback">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-logo" aria-hidden="true">
        <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
      </div>
      <div>
        <p class="modal-title">Přihlášení do IS Comeback</p>
        <p class="modal-sub">Použijte přihlašovací údaje ze systému Směny.</p>
      </div>
    </div>

    <form method="post" action="<?= h(cb_url('lib/login_smeny.php')) ?>" class="modal-form" id="cbLoginForm">
      <div class="modal-field">
        <label class="modal-label" for="cb_email">E-mail</label>
        <input class="modal-input"
               id="cb_email"
               name="email"
               type="email"
               autocomplete="username"
               required<?= $loginDisabled ?>>
      </div>

      <div class="modal-field">
        <label class="modal-label" for="cb_pass">Heslo</label>
        <input class="modal-input"
               id="cb_pass"
               name="heslo"
               type="password"
               autocomplete="current-password"
               required<?= $loginDisabled ?>>
      </div>

      <div class="modal-actions">
        <button class="modal-btn primary" type="submit" id="cbLoginSubmit"<?= $loginDisabled ?>>Přihlásit</button>
      </div>

      <p class="modal-sub modal-url" style="margin-top:10px;">
        <span class="modal-db-state <?= h($loginDbClass) ?>"><?= h($loginDbText) ?></span>
        <span class="modal-url-main">URL: <code><?= h($aktualniUrl) ?></code></span>
      </p>
    </form>
  </div>
</div>

<script>
  (function(){
    // CB_LOGIN_TRACE_TEMP_START
    var LOGOUT_TRACE_KEY = 'cb_login_trace_logout';
    // CB_LOGIN_TRACE_TEMP_START
    function traceLogin(eventName, data) {
      try {
        var payload = JSON.stringify({
          event: eventName,
          href: window.location.href,
          path: window.location.pathname,
          data: data || {}
        });
        if (navigator.sendBeacon) {
          navigator.sendBeacon('<?= h(cb_url('lib/ajax_trace.php')) ?>', new Blob([payload], { type: 'application/json' }));
          return;
        }
        fetch('<?= h(cb_url('lib/ajax_trace.php')) ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: payload,
          keepalive: true
        }).catch(function(){});
      } catch (e) {}
    }
    // CB_LOGIN_TRACE_TEMP_END
    var form = document.getElementById('cbLoginForm');
    var email = document.getElementById('cb_email');
    var pass = document.getElementById('cb_pass');
    var submit = document.getElementById('cbLoginSubmit');
    // CB_LOGIN_TRACE_TEMP_START
    var logoutTrace = null;
    try {
      if (window.sessionStorage) {
        logoutTrace = JSON.parse(String(window.sessionStorage.getItem(LOGOUT_TRACE_KEY) || 'null'));
      }
    } catch (e) {
      logoutTrace = null;
    }
    traceLogin('login_trace_form_visible', {
      ready_state: document.readyState,
      logout_reason: logoutTrace && logoutTrace.reason ? String(logoutTrace.reason) : '',
      logout_to_form_ms: logoutTrace && logoutTrace.at_ms ? Math.max(0, Date.now() - Number(logoutTrace.at_ms || 0)) : 0
    });
    try {
      if (window.sessionStorage) {
        window.sessionStorage.removeItem(LOGOUT_TRACE_KEY);
      }
    } catch (e) {}
    // CB_LOGIN_TRACE_TEMP_END

    if (form && submit) {
      form.addEventListener('submit', function(){
        // CB_LOGIN_TRACE_TEMP_START
        traceLogin('login_trace_form_submit', {
          email_len: email ? String(email.value || '').length : 0,
          has_password: !!(pass && String(pass.value || '') !== '')
        });
        // CB_LOGIN_TRACE_TEMP_END
        submit.textContent = 'Ověřuji přihlášení...';
        submit.classList.add('is-waiting');
        submit.disabled = true;
        if (email) {
          email.readOnly = true;
        }
        if (pass) {
          pass.readOnly = true;
        }
      });
    }

    if (email) {
      setTimeout(function(){ email.focus(); }, 60);
    }
  })();
</script>

