<?php
// modaly/modal_overeni.php * Verze: V3 * Aktualizace: 07.03.2026
declare(strict_types=1);

/*
 * Nep�ihl�en� stav
 * - zobraz� �ek�n� na 2FA po zad�n� hesla
 * - nebo klasick� login modal
 *
 * Vstup z index.php:
 * - otev�en� layout kontejneru a hlavi�ka
 *
 * Chov�n�:
 * - po vykreslen� mod�lu ukon�� request p�es exit
 */

if (!empty($_SESSION['login_ok'])) {
    return;
}

$cb2faToken = (string)($_SESSION['cb_2fa_token'] ?? '');

if ($cb2faToken !== '') {

    $pollMs = 2000;
    if (defined('CB_2FA_POLL_MS')) {
        $pollMs = (int)CB_2FA_POLL_MS;
    }

    $pairUrl = cb_url_abs('mobil/mobil_overeni.php?t=' . rawurlencode($cb2faToken));
    $checkUrl = cb_url('lib/push_2fa_api.php?check=1');
    $cancelUrl = cb_url('lib/push_2fa_api.php?cancel=1');

    $dbgUser = 0;
    if (isset($_SESSION['cb_user']) && is_array($_SESSION['cb_user']) && isset($_SESSION['cb_user']['id_user'])) {
        $dbgUser = (int)$_SESSION['cb_user']['id_user'];
    }
    $dbgToken = substr($cb2faToken, 0, 8);
    $dbgText = 'DBG: V3 | user ' . $dbgUser . ' | token ' . $dbgToken . ' | stav ceka';

    echo '<div class="cb-login-fill"></div>';
    ?>

    <div id="cb-2fa-ovl" role="dialog" aria-modal="true" aria-label="Schv�len� p�ihl�en�">
      <div class="cb-2fa-card">
        <div class="cb-2fa-top">
          <div class="cb-2fa-head">
            <div class="cb-2fa-logo" aria-hidden="true">
              <img src="<?= h(cb_url('img/logo_comeback.png')) ?>" alt="Comeback">
            </div>
            <div>
              <p class="cb-2fa-title">Schv�len� p�ihl�en�</p>
              <p class="cb-2fa-sub">IS Pizzacomeback</p>
            </div>
          </div>
          <button type="button" class="cb-2fa-x" id="cb2faClose" aria-label="Zav��t">�</button>
        </div>

        <div class="cb-2fa-body">
          <div class="cb-2fa-box">
            <div class="cb-2fa-main">
              Potvr�te p�ihl�en� na Va�em za��zen�.
            </div>
            <div class="cb-2fa-status" id="cb2faStatus">Na potvrzen� p�ihl�en� zb�v�: --:--</div>
            <div class="cb-2fa-status" id="cb2faDbg"><?= h($dbgText) ?></div>
          </div>

          <div class="cb-2fa-fallback">
            <div class="cb-2fa-fallback-line"></div>
            <div class="cb-2fa-qrhint">
              Pokud jste neobdr�el/a notifikaci na registrovan� za��zen�,<br>
              na�t�te tento QR k�d.
            </div>
            <div class="cb-2fa-qrwrap">
              <div class="cb-2fa-qr" id="cb2faQr"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <script src="<?= h(cb_url('js/qrcode.min.js')) ?>"></script>
    <script>
      (function(){
        var st = document.getElementById('cb2faStatus');
        var btnX = document.getElementById('cb2faClose');

        function fmt(sec){
          if (typeof sec !== 'number' || sec < 0) {
            sec = 0;
          }
          var m = Math.floor(sec / 60);
          var s = sec % 60;
          return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        }

        function setTxt(t){
          if (st) {
            st.textContent = t;
          }
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
        } catch (e) {
          // QR je jen n�hradn� mo�nost, nesm� shodit str�nku.
        }

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
                setTxt('P��stup zam�tnut. P�esm�rov�v�m�');
                window.location.href = '<?= h(cb_url('')) ?>';
                return;
              }
              if (j.stav === 'exp') {
                setTxt('Vypr�elo. P�esm�rov�v�m�');
                window.location.href = '<?= h(cb_url('')) ?>';
                return;
              }
              if (typeof j.zbyva_sec === 'number') {
                setTxt('Na potvrzen� p�ihl�en� zb�v�: ' + fmt(j.zbyva_sec));
                return;
              }
              setTxt('Na potvrzen� p�ihl�en� zb�v�: --:--');
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

echo '<div class="cb-login-fill"></div>';
require_once __DIR__ . '/modal_login.php';
?>
</div>
</body>
</html>
<?php
/* modaly/modal_overeni.php * Verze: V3 * Aktualizace: 07.03.2026 * Po�et ��dk�: 179 */
/* P�edchoz� po�et ��dk�: 143 */
// Konec souboru
