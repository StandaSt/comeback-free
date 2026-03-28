<?php
// karty/admin_pobocky.php * Verze: V2 * Aktualizace: 18.03.2026
declare(strict_types=1);

$formAction = cb_url('/');
$msg = '';
$msgErr = false;
$keepExpanded = false;
$rows = [];
$baseCols = ['nazev', 'ulice', 'mesto', 'psc'];
$endCols = [];
$editCols = [];

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $resCols = $conn->query('SHOW COLUMNS FROM pobocka');
    if ($resCols) {
        while ($r = $resCols->fetch_assoc()) {
            $field = (string)($r['Field'] ?? '');
            if ($field !== '' && str_starts_with($field, 'end_')) {
                $endCols[] = $field;
            }
        }
        $resCols->free();
    }

    $editCols = array_values(array_merge($baseCols, $endCols));

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && (string)($_POST['cb_admin_pobocky_action'] ?? '') === 'save_row'
    ) {
        $keepExpanded = true;
        $idPob = (int)($_POST['id_pob'] ?? 0);
        if ($idPob <= 0) {
            throw new RuntimeException('Neplatné ID pobočky.');
        }

        $setParts = [];
        $types = '';
        $vals = [];
        foreach ($editCols as $col) {
            $setParts[] = '`' . $col . '` = ?';
            $types .= 's';
            $vals[] = trim((string)($_POST[$col] ?? ''));
        }

        if (!$setParts) {
            throw new RuntimeException('Nenalezeny sloupce k uložení.');
        }

        $sql = 'UPDATE pobocka SET ' . implode(', ', $setParts) . ' WHERE id_pob = ? LIMIT 1';
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('DB prepare selhal.');
        }

        $types .= 'i';
        $vals[] = $idPob;
        $bind = [$types];
        foreach ($vals as $k => $v) {
            $bind[] = &$vals[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
        $stmt->execute();
        $stmt->close();

        $msg = 'Změna pobočky byla uložena.';
    }

    $selectCols = array_merge(['id_pob'], $editCols);
    $sqlRows = 'SELECT ' . implode(', ', array_map(static fn(string $c): string => '`' . $c . '`', $selectCols))
        . ' FROM pobocka ORDER BY nazev ASC, id_pob ASC';
    $resRows = $conn->query($sqlRows);
    if ($resRows) {
        while ($r = $resRows->fetch_assoc()) {
            $rows[] = $r;
        }
        $resRows->free();
    }
} catch (Throwable $e) {
    $msg = $e->getMessage();
    $msgErr = true;
}

$card_min_html = '<p class="card_text">Správa poboček</p>';
$startExpanded = $keepExpanded;

ob_start();
?>
  <div class="table-wrap">
    <table class="table card_table_max">
      <thead>
        <tr>
          <th>Název</th>
          <th>Ulice</th>
          <th>Město</th>
          <th>PSČ</th>
          <?php foreach ($endCols as $col): ?>
            <th><?= h($col) ?></th>
          <?php endforeach; ?>
          <th>Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="<?= h((string)(5 + count($endCols))) ?>">Žádná data</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <form method="post" action="<?= h($formAction) ?>">
                <input type="hidden" name="cb_admin_pobocky_action" value="save_row">
                <input type="hidden" name="id_pob" value="<?= h((string)($row['id_pob'] ?? '0')) ?>">

                <?php foreach ($editCols as $col): ?>
                  <?php
                    $maxLenAttr = '';
                    $styleWidth = '';
                    // Inline stylovani je zde vyslovne povolene pro specialni sirky sloupcu.
                    if (str_starts_with($col, 'end_')) {
                        $maxLenAttr = ' maxlength="3"';
                        $styleWidth = ' style="width:4ch;"';
                    } elseif ($col === 'psc') {
                        $maxLenAttr = ' maxlength="7"';
                        $styleWidth = ' style="width:8ch;"';
                    } elseif ($col === 'nazev' || $col === 'mesto') {
                        $styleWidth = ' style="width:12ch;"';
                    }
                  ?>
                  <td>
                    <input
                      type="text"
                      class="card_input"
                      name="<?= h($col) ?>"
                      value="<?= h((string)($row[$col] ?? '')) ?>"
                      <?= $maxLenAttr ?><?= $styleWidth ?>
                    >
                  </td>
                <?php endforeach; ?>
                <td>
                  <button type="submit" class="admin_karty_btn">Uložit</button>
                </td>
              </form>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="card_text<?= $msgErr ? ' card_text_muted' : '' ?>">
    <?= h($msg) ?>
  </p>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/admin_pobocky.php * Verze: V2 * Aktualizace: 18.03.2026 */
?>
