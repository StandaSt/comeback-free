<?php
declare(strict_types=1);

/*
 * Ucel souboru: Kratce potvrdit vstup z duveryhodneho zarizeni
 * a potom uzivatele presmerovat do jeho ciloveho modulu.
 */

$cbDuveryhodneZarizeniTarget = isset($cbDuveryhodneZarizeniTarget)
    ? (string)$cbDuveryhodneZarizeniTarget
    : cb_login_target_url();
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback - vstup do systému</title>
  <link rel="icon" type="image/png" href="<?= h(cb_public_url('img/logo_comeback.png')) ?>">
  <link rel="stylesheet" href="<?= h(cb_public_url('style/modal_alert.css?v=' . filemtime(__DIR__ . '/../style/modal_alert.css'))) ?>">
</head>
<body class="modal-page modal-login-page">
  <div class="modal-login-container">
    <div class="modal-overlay" role="status" aria-live="polite" aria-label="Načítání systému">
      <div class="modal">
        <div class="modal-head">
          <div>
            <p class="modal-title">Vstupujete do IS Comeback<br>z registrovaného zařízení.</p>
          </div>
        </div>
        <p class="modal-sub modal-copy-wide">Načítám systém…</p>
      </div>
    </div>
  </div>

  <script>
  /* Ucel funkce: Po kratkem potvrzeni otevre cilovy modul IS. */
  setTimeout(function(){
    window.location.replace(<?= json_encode($cbDuveryhodneZarizeniTarget, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>);
  }, 700);
  </script>
</body>
</html>

