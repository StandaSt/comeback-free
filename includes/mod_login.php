<?php
// includes/mod_login.php * Verze: V1 * Aktualizace: 06.03.2026
declare(strict_types=1);

/*
 * Nepřihlášený stav
 * - zobrazí čekání na 2FA po zadání hesla
 * - nebo klasický login modal
 *
 * Vstup z index.php:
 * - otevřený layout kontejneru a hlavička
 *
 * Chování:
 * - po vykreslení modálu ukončí request přes exit
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

    $pairUrl = cb_url_abs('includes/2fa_mobil.php?t=' . rawurlencode($cb2faToken));
    $checkUrl = cb_url('lib/push_2fa_api.php?check=1');
    $cancelUrl = cb_url('lib/push_2fa_api.php?cancel=1');

    echo '<div class="cb-login-fill"></div>';
    ?>

    <div id="cb-2fa-ovl" role="dialog" aria-modal="true" aria-label="Schválení přihlášení">
      <div class="cb-2fa-card">
        <div class="cb-2fa-top">
          <p class="cb-2fa-title">Schválení přihlášení</p>
          <button type="button" class="cb-2fa-x" id="cb2faClose" aria-label="Zavřít">×</button>
        </div>

        <div class="cb-2fa-body">
          <div class="cb-2fa-box">
            Čeká se na schválení Vašeho přístupu.<br>
            Prosím, zkontrolujte si Vaše registrované zařízení.
            <div class="cb-2fa-qrwrap">
              <div class="cb-2fa-qr" id="cb2faQr"></div>
              <div class="cb-2fa-qrhint">
                Pokud jste neobdržel notifikaci na zaregistrované zařízení,<br>
                načtěte tento QR kód.
              </div>
            </div>
          </div>

          <div class="cb-2fa-status" id="cb2faStatus">Čekám na rozhodnutí na mobilu…</div>
        </div>
      </div>
    </div>

    <script src="<?= h(cb_url('js/qrcode.min.js')) ?>"></script>
    <script>
      (function(){
        var st = document.getElementById('cb2faStatus');
        var btnX = document.getElementById('cb2faClose');

        function setTxt(t){
          if (st) st.textContent = t;
        }

        try {
          if (typeof QRCode !== 'undefined') {
            var el = document.getElementById('cb2faQr');
            if (el) {
              new QRCode(el, {
                text: <?= json_encode($pairUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
                width: 220,
                height: 220
              });
            }
          }
        } catch (e) {
          // QR je jen náhradní možnost, nesmí shodit stránku.
        }

        function kontrola2fa(){
          fetch('<?= h($checkUrl) ?>', { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(j){
              if (!j || j.ok !== true) {
                setTxt('Chyba kontroly. Zkus to znovu.');
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
                setTxt('Čekám na rozhodnutí na mobilu… (zbývá ' + j.zbyva_sec + ' s)');
                return;
              }
              setTxt('Čekám na rozhodnutí na mobilu…');
            })
            .catch(function(){
              setTxt('Chyba kontroly. Zkus to znovu.');
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
require_once __DIR__ . '/login_modal.php';
?>
</div>
</body>
</html>
<?php
exit;
