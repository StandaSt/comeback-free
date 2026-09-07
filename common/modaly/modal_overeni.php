<?php
declare(strict_types=1);

if (!empty($_SESSION['login_ok'])) {
    return;
}

$cbAuthOk = !empty($_SESSION['cb_auth_ok']);
$cb2faToken = (string)($_SESSION['cb_2fa_token'] ?? '');

if ($cb2faToken !== '') {
    $pollMs = defined('CB_2FA_POLL_MS') ? (int)CB_2FA_POLL_MS : 2000;

    $pairUrl = cb_module_url('provoz') . 'mobil/mobil_overeni.php?t=' . rawurlencode($cb2faToken);
    $checkUrl = cb_root_url('common/lib/push_2fa_api.php?check=1');
    $cancelUrl = cb_root_url('common/lib/push_2fa_api.php?cancel=1');
    $targetUrl = cb_login_target_url();
    $loginUrl = cb_login_url();

    echo '<div class="cb-login-fill"></div>';
    ?>
    <div id="cb-2fa-ovl" class="modal-overlay" role="dialog" aria-modal="true" aria-label="Schválení přihlášení">
      <div class="modal">
        <button type="button" class="modal-x" id="cb2faClose" aria-label="Zavřít">×</button>

        <div class="modal-head">
          <div class="modal-logo" aria-hidden="true">
            <img src="<?= h(cb_public_url('img/logo_comeback.png')) ?>" alt="Comeback">
          </div>
          <div>
            <p class="modal-title">Schválení přihlášení</p>
            <p class="modal-sub">Comeback</p>
          </div>
        </div>

        <div class="modal-center">
          <div class="modal-box">
            <p class="modal-copy" style="color:#c00;text-align:center;">Zkontrolujte své registrované zařízení - odemkněte jej.</p>
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

    <script src="<?= h(cb_public_url('js/qrcode.min.js')) ?>"></script>
    <script>
      (function(){
        var st = document.getElementById('cb2faStatus');
        var btnX = document.getElementById('cb2faClose');
        var kontrolaInterval = null;
        var obnoveniDo = 0;
        var obnoveniTimeout = null;
        var kontrolaZastavena = false;

        function fmt(sec){
          if (typeof sec !== 'number' || sec < 0) sec = 0;
          var m = Math.floor(sec / 60);
          var s = sec % 60;
          return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        function setTxt(t){
          if (st) st.textContent = t;
        }

        function ukonciObnoveni(){
          obnoveniDo = 0;
          if (obnoveniTimeout !== null) {
            clearTimeout(obnoveniTimeout);
            obnoveniTimeout = null;
          }
        }

        function ukonciKontroluPoChybe(){
          kontrolaZastavena = true;
          if (kontrolaInterval !== null) {
            clearInterval(kontrolaInterval);
            kontrolaInterval = null;
          }
          setTxt('Přihlášení selhalo, zkuste to později. Administrátor byl o chybě informován.');
        }

        function zobrazObnoveniKontroly(){
          if (kontrolaZastavena) return;

          if (obnoveniDo === 0) {
            obnoveniDo = Date.now() + 30000;
            obnoveniTimeout = setTimeout(ukonciKontroluPoChybe, 30000);
          }

          var zbyva = Math.max(0, Math.ceil((obnoveniDo - Date.now()) / 1000));
          setTxt('Došlo k chybě při kontrole stavu schválení přihlášení. Vydržte, pokouším se o opakované přihlášení. Zbývá: ' + fmt(zbyva));
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
          if (kontrolaZastavena) return;

          fetch('<?= h($checkUrl) ?>', { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(j){
              if (kontrolaZastavena) return;
              if (!j || j.ok !== true) {
                zobrazObnoveniKontroly();
                return;
              }
              ukonciObnoveni();
              if (j.stav === 'ok') {
                setTxt('Přístup schválen - načítám modul');
                setTimeout(function(){
                  window.location.href = '<?= h($targetUrl) ?>';
                }, 400);
                return;
              }
              if (j.stav === 'ne') {
                setTxt('Přístup zamítnut. Přesměrovávám…');
                window.location.href = '<?= h($loginUrl) ?>';
                return;
              }
              if (j.stav === 'exp') {
                setTxt('Vypršelo. Přesměrovávám…');
                window.location.href = '<?= h($loginUrl) ?>';
                return;
              }
              if (typeof j.zbyva_sec === 'number') {
                setTxt('Na potvrzení přihlášení zbývá: ' + fmt(j.zbyva_sec));
                return;
              }
              setTxt('Na potvrzení přihlášení zbývá: --:--');
            })
            .catch(function(){
              zobrazObnoveniKontroly();
            });
        }

        if (btnX) {
          btnX.addEventListener('click', function(){
            fetch('<?= h($cancelUrl) ?>', { cache: 'no-store' })
              .then(function(){ window.location.href = '<?= h($loginUrl) ?>'; })
              .catch(function(){ window.location.href = '<?= h($loginUrl) ?>'; });
          });
        }

        kontrola2fa();
        kontrolaInterval = setInterval(kontrola2fa, <?= (int)$pollMs ?>);
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
