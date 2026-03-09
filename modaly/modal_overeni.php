<?php
declare(strict_types=1);

if (!empty($_SESSION['login_ok'])) {
    return;
}

$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cb2faToken = (string)($_SESSION['cb_2fa_token'] ?? '');

if ($cb2faToken !== '') {
    $pollMs = defined('CB_2FA_POLL_MS') ? (int)CB_2FA_POLL_MS : 2000;

    $pairUrl = cb_url_abs('mobil/mobil_overeni.php?t=' . rawurlencode($cb2faToken));
    $checkUrl = cb_url('lib/push_2fa_api.php?check=1');
    $cancelUrl = cb_url('lib/push_2fa_api.php?cancel=1');

    echo '<div class="cb-login-fill"></div>';
    ?>
    <div id="cb-2fa-ovl" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Schválení přihlášení">
      <div class="modal">
        <button type="button" class="modal-x" id="cb2faClose" aria-label="Zavřít">×</button>

        <div class="modal-head">
          <div class="modal-logo" aria-hidden="true">
            <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
          </div>
          <div>
            <p class="modal-title">Schválení přihlášení</p>
            <p class="modal-sub">IS Comeback</p>
          </div>
        </div>

        <div class="modal-center">
          <div class="modal-box">
            <p class="modal-copy">Potvrďte přihlášení na Vašem zařízení.</p>
            <div class="modal-status modal-status-center" id="cb2faStatus">Na potvrzení přihlášení zbývá: --:--</div>
          </div>

          <div class="modal-divider"></div>

          <p class="modal-sub modal-copy-wide">
            Pokud jste neobdržel/a notifikaci na registrované zařízení, načtěte tento QR kód.
          </p>
          <div class="modal-qr modal-qr-main" id="cb2faQr"></div>
        </div>
      </div>
    </div>

    <script src="<?= h(cb_url('js/qrcode.min.js')) ?>"></script>
    <script>
      (function(){
        var st = document.getElementById('cb2faStatus');
        var btnX = document.getElementById('cb2faClose');

        function fmt(sec){
          if (typeof sec !== 'number' || sec < 0) sec = 0;
          var m = Math.floor(sec / 60);
          var s = sec % 60;
          return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        function setTxt(t){
          if (st) st.textContent = t;
        }

        try {
          if (typeof QRCode !== 'undefined') {
            var el = document.getElementById('cb2faQr');
            if (el) {
              new QRCode(el, {
                text: <?= json_encode($pairUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                width: 168,
                height: 168
              });
            }
          }
        } catch (e) {}

        function kontrola2fa(){
          fetch('<?= h($checkUrl) ?>', { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(j){
              if (!j || j.ok !== true) {
                setTxt('Chyba kontroly. Zkuste to znovu.');
                return;
              }
              if (j.stav === 'ok') {
                window.location.href = '<?= h(cb_url('')) ?>';
                return;
              }
              if (j.stav === 'ne') {
                setTxt('Přístup zamítnut. Přesměrovávám…');
                window.location.href = '<?= h(cb_url('')) ?>';
                return;
              }
              if (j.stav === 'exp') {
                setTxt('Vypršelo. Přesměrovávám…');
                window.location.href = '<?= h(cb_url('')) ?>';
                return;
              }
              if (typeof j.zbyva_sec === 'number') {
                setTxt('Na potvrzení přihlášení zbývá: ' + fmt(j.zbyva_sec));
                return;
              }
              setTxt('Na potvrzení přihlášení zbývá: --:--');
            })
            .catch(function(){
              setTxt('Chyba kontroly. Zkuste to znovu.');
            });
        }

        if (btnX) {
          btnX.addEventListener('click', function(){
            fetch('<?= h($cancelUrl) ?>', { cache: 'no-store' })
              .then(function(){ window.location.href = '<?= h(cb_url('')) ?>'; })
              .catch(function(){ window.location.href = '<?= h(cb_url('')) ?>'; });
          });
        }

        kontrola2fa();
        setInterval(kontrola2fa, <?= (int)$pollMs ?>);
      })();
    </script>
    </div>
    </body>
    </html>
    <?php
    exit;
}

if ($cbAuthOk) {
    return;
}

echo '<div class="cb-login-fill"></div>';
require_once __DIR__ . '/modal_login.php';
?>
</div>
</body>
</html>
