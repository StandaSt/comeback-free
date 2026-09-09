<?php
declare(strict_types=1);

/*
 * Účel souboru: Zobrazí samostatné potvrzení změny přihlašovacího e-mailu.
 * Samotná databázová změna probíhá až po odeslání formuláře v index.php.
 */

$cbEmailToken = trim((string)($_GET['potvrdit_email'] ?? ''));
?><!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Potvrzení změny e-mailu – IS Comeback</title>
  <link rel="icon" type="image/png" href="<?= h(cb_public_url('img/logo_comeback.png')) ?>">
  <link rel="stylesheet" href="<?= h(cb_asset_url('style/global.css')) ?>">
</head>
<body>
  <main class="pp" style="min-height:100vh;display:grid;place-items:center;padding:20px;">
    <section class="blok" style="width:min(460px,100%);padding:24px;">
      <h1 style="margin-top:0;">Změna přihlašovacího e-mailu</h1>
      <p>Potvrzením se začne pro přihlášení do IS Comeback používat nový e-mail.</p>
      <form method="post" action="<?= h(cb_root_url('')) ?>">
        <input type="hidden" name="cb_action" value="potvrdit_email">
        <input type="hidden" name="token" value="<?= h($cbEmailToken) ?>">
        <button type="submit">Potvrdit změnu</button>
      </form>
    </section>
  </main>
</body>
</html>
