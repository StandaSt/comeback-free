<?php
// karty/user_setting.php * Verze: V2 * Aktualizace: 27.03.2026
declare(strict_types=1);

$usError = '';
$usOk = '';
$usRedirectUrl = '';

$usUser = $_SESSION['cb_user'] ?? null;
$usUserId = (is_array($usUser) && isset($usUser['id_user'])) ? (int)$usUser['id_user'] : 0;

$usPocetSl = 4;
$usNanoKde = 0;
$usPismo = 2;
$usDark = 0;

$formAction = cb_url('/');

if ($usUserId > 0) {
    try {
        $conn = db();

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (string)($_POST['us_action'] ?? '') === 'save') {
            $postPocetSl = (int)($_POST['us_pocet_sl'] ?? 4);
            $postNanoKde = (int)($_POST['us_nano_kde'] ?? 0);
            $postPismo = (int)($_POST['us_pismo'] ?? 2);
            $postDark = (int)($_POST['us_dark'] ?? 0);

            if (!in_array($postPocetSl, [3, 4], true)) {
                $postPocetSl = 4;
            }
            if (!in_array($postNanoKde, [0, 1], true)) {
                $postNanoKde = 0;
            }
            if (!in_array($postPismo, [1, 2, 3], true)) {
                $postPismo = 2;
            }
            if (!in_array($postDark, [0, 1], true)) {
                $postDark = 0;
            }

            $stmtUpd = $conn->prepare('UPDATE user_set SET pocet_sl = ?, nano_kde = ?, pismo = ?, dark = ? WHERE id_user = ?');
            if ($stmtUpd === false) {
                throw new RuntimeException('Nepodařilo se připravit update user_set.');
            }
            $stmtUpd->bind_param('iiiii', $postPocetSl, $postNanoKde, $postPismo, $postDark, $usUserId);
            $stmtUpd->execute();
            $stmtUpd->close();

            $usRedirectUrl = $formAction;
        }

        $stmtSel = $conn->prepare('SELECT pocet_sl, nano_kde, pismo, dark FROM user_set WHERE id_user = ? LIMIT 1');
        if ($stmtSel === false) {
            throw new RuntimeException('Nepodařilo se připravit select user_set.');
        }

        $stmtSel->bind_param('i', $usUserId);
        $stmtSel->execute();
        $stmtSel->bind_result($dbPocetSl, $dbNanoKde, $dbPismo, $dbDark);

        if ($stmtSel->fetch()) {
            $usPocetSl = in_array((int)$dbPocetSl, [3, 4], true) ? (int)$dbPocetSl : 4;
            $usNanoKde = in_array((int)$dbNanoKde, [0, 1], true) ? (int)$dbNanoKde : 0;
            $usPismo = in_array((int)$dbPismo, [1, 2, 3], true) ? (int)$dbPismo : 2;
            $usDark = in_array((int)$dbDark, [0, 1], true) ? (int)$dbDark : 0;
        } else {
            $usError = 'Nenalezen user_set pro uživatele.';
        }

        $stmtSel->close();
    } catch (Throwable $e) {
        $usError = 'Načtení uživatelského nastavení selhalo.';
    }
} else {
    $usError = 'Nastavení je dostupné až po přihlášení.';
}

$usNanoText = ($usNanoKde === 1) ? 'Do gridu' : 'Řádek';
$usDarkText = ($usDark === 1) ? 'Ano' : 'Ne';

$card_min_html = ''
    . '<p class="card_text">Sloupce dashboardu: <strong>' . h((string)$usPocetSl) . '</strong></p>'
    . '<p class="card_text">Nano karty: <strong>' . h($usNanoText) . '</strong></p>'
    . '<p class="card_text">Velikost textu: <strong>' . h((string)$usPismo) . '</strong> | Dark: <strong>' . h($usDarkText) . '</strong></p>';

ob_start();
?>
<?php if ($usError !== ''): ?>
  <p class="card_text card_text_muted"><?= h($usError) ?></p>
<?php else: ?>
  <?php if ($usRedirectUrl !== ''): ?>
    <script>
      window.location.href = <?= json_encode($usRedirectUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    </script>
  <?php endif; ?>
  <form method="post" action="<?= h($formAction) ?>" class="card_stack" autocomplete="off">
    <input type="hidden" name="us_action" value="save">

    <section class="card_section">
      <h4 class="card_section_title">Dashboard</h4>
      <label class="card_field">
        <span>Počet sloupců</span>
        <select class="card_select" name="us_pocet_sl">
          <option value="3"<?= $usPocetSl === 3 ? ' selected' : '' ?>>3 sloupce</option>
          <option value="4"<?= $usPocetSl === 4 ? ' selected' : '' ?>>4 sloupce</option>
        </select>
      </label>

      <label class="card_field" style="margin-top:10px;">
        <span>Nano karty</span>
        <select class="card_select" name="us_nano_kde">
          <option value="0"<?= $usNanoKde === 0 ? ' selected' : '' ?>>0 = řádek</option>
          <option value="1"<?= $usNanoKde === 1 ? ' selected' : '' ?>>1 = do gridu</option>
        </select>
      </label>
    </section>

    <section class="card_section">
      <h4 class="card_section_title">Vzhled</h4>
      <label class="card_field">
        <span>Velikost textu</span>
        <select class="card_select" name="us_pismo">
          <option value="1"<?= $usPismo === 1 ? ' selected' : '' ?>>1 = menší</option>
          <option value="2"<?= $usPismo === 2 ? ' selected' : '' ?>>2 = default</option>
          <option value="3"<?= $usPismo === 3 ? ' selected' : '' ?>>3 = větší</option>
        </select>
      </label>

      <label class="card_field" style="margin-top:10px;">
        <span>Dark režim</span>
        <select class="card_select" name="us_dark">
          <option value="0"<?= $usDark === 0 ? ' selected' : '' ?>>0 = ne</option>
          <option value="1"<?= $usDark === 1 ? ' selected' : '' ?>>1 = ano</option>
        </select>
      </label>
    </section>

    <div class="card_actions">
      <button type="submit" class="btn btn-primary">Uložit nastavení</button>
    </div>
  </form>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/user_setting.php * Verze: V2 * Aktualizace: 27.03.2026 */
// počet řádků 149
?>
