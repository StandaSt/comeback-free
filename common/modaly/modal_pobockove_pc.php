<?php
declare(strict_types=1);
/* Účel: Jednorázová povinná otázka na umístění vytipovaného PC; obě odpovědi umožní pokračovat do IS. */
if (!isset($cbPcChyba)) { http_response_code(404); exit; }
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Comeback – potvrzení pobočkového počítače</title>
  <link rel="stylesheet" href="<?= h(cb_public_url('style/modal_alert.css?v=' . filemtime(__DIR__ . '/../style/modal_alert.css'))) ?>">
</head>
<body class="modal-page">
<main class="modal-login-container">
  <section class="modal" role="dialog" aria-modal="true" aria-labelledby="pc-title">
    <h1 class="modal-title-alert" id="pc-title">Potvrzení pobočkového počítače</h1>
    <?php if ($cbPcChyba !== ''): ?>
      <p class="modal-copy" role="alert"><?= h($cbPcChyba) ?></p>
      <a class="modal-btn" href="<?= h(cb_root_url('')) ?>">Zpět do IS</a>
    <?php else: ?>
      <p class="modal-copy">Je tento počítač umístěný na pobočce <strong><?= h((string)$cbPcKandidat['nazev']) ?></strong>?</p>
      <form method="post" action="<?= h(cb_root_url('')) ?>" class="modal-center">
        <input type="hidden" name="cb_action" value="pobockove_pc_potvrdit">
        <input type="hidden" name="cb_crf" value="<?= h(cb_crf_token()) ?>">
        <input type="hidden" name="cb_pc_session" value="<?= h((string)($_SESSION['cb_pc_session_token'] ?? '')) ?>">
        <input type="hidden" name="id_pob" value="<?= (int)$cbPcKandidat['id_pob'] ?>">
        <button class="modal-btn primary" type="submit" name="odpoved" value="ano">Ano, je na pobočce <?= h((string)$cbPcKandidat['nazev']) ?></button>
        <button class="modal-btn" type="submit" name="odpoved" value="ne">Ne, není</button>
      </form>
    <?php endif; ?>
  </section>
</main>
<?php // Stejný heartbeat jako v IS udrží session i během čekání na odpověď; ochrana připojí identitu tohoto okna. ?>
<script>
window.CB_ENDPOINT = <?= json_encode(cb_root_url('index.php'), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;
window.CB_CRF_TOKEN = <?= json_encode(cb_crf_token(), JSON_HEX_TAG) ?>;
window.CB_PC_SESSION_TOKEN = <?= json_encode((string)($_SESSION['cb_pc_session_token'] ?? ''), JSON_HEX_TAG) ?>;
</script>
<script src="<?= h(cb_public_url('js/ochrana_crf.js?v=' . filemtime(__DIR__ . '/../js/ochrana_crf.js'))) ?>"></script>
<script src="<?= h(cb_public_url('js/pc_session.js?v=' . filemtime(__DIR__ . '/../js/pc_session.js'))) ?>"></script>
</body>
</html>
