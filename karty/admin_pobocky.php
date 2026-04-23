<?php
// K6
// karty/admin_pobocky.php * Verze: V2 * Aktualizace: 18.03.2026
declare(strict_types=1);

$formAction = cb_url('/');
$msg = '';
$msgErr = false;
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

$card_min_html = '<p class="card_text txt_seda odstup_vnejsi_0">Správa poboček</p>';
$sirkaSloupcu = [
    'nazev' => 'width:15ch;',
    'ulice' => 'width:20ch;',
    'mesto' => 'width:12ch;',
    'psc' => 'width:7ch;',
    'end' => 'width:4ch;',
    'akce' => 'width:9ch;',
];

ob_start();
?>
  <div class="table-wrap ram_normal bg_bila zaobleni_12">
    <table class="table ram_normal bg_bila radek_1_35">
      <thead>
        <tr>
          <th class="txt_r" style="<?= h($sirkaSloupcu['nazev']) ?>">Název</th>
          <th class="txt_r" style="<?= h($sirkaSloupcu['ulice']) ?>">Ulice</th>
          <th class="txt_r" style="<?= h($sirkaSloupcu['mesto']) ?>">Město</th>
          <th class="txt_r" style="<?= h($sirkaSloupcu['psc']) ?>">PSČ</th>
          <?php foreach ($endCols as $col): ?>
            <th class="txt_r" style="<?= h($sirkaSloupcu['end']) ?>"><?= h($col) ?></th>
          <?php endforeach; ?>
          <th class="txt_r" style="<?= h($sirkaSloupcu['akce']) ?>">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="<?= h((string)(5 + count($endCols))) ?>">Žádná data</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php $formId = 'cb-admin-pobocky-' . (int)($row['id_pob'] ?? 0); ?>
            <tr>
              <?php foreach ($editCols as $col): ?>
                <?php $maxLenAttr = str_starts_with($col, 'end_') ? ' maxlength="3"' : ''; ?>
                <?php
                  $inputStyle = match (true) {
                      str_starts_with($col, 'end_') => ' style="' . $sirkaSloupcu['end'] . '"',
                      isset($sirkaSloupcu[$col]) => ' style="' . $sirkaSloupcu[$col] . '"',
                      default => '',
                  };
                ?>
                <td class="txt_r">
                  <?php if ($col === $editCols[0]): ?>
                    <input type="hidden" form="<?= h($formId) ?>" name="cb_admin_pobocky_action" value="save_row">
                    <input type="hidden" form="<?= h($formId) ?>" name="id_pob" value="<?= h((string)($row['id_pob'] ?? '0')) ?>">
                  <?php endif; ?>
                  <input
                    type="text"
                    class="card_input ram_sedy txt_seda vyska_32 txt_r"
                    form="<?= h($formId) ?>"
                    name="<?= h($col) ?>"
                    value="<?= h((string)($row[$col] ?? '')) ?>"
                    <?= $maxLenAttr ?><?= $inputStyle ?>
                  >
                </td>
              <?php endforeach; ?>
              <td class="txt_r">
                <button type="submit" form="<?= h($formId) ?>" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Uložit</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
    <?php foreach ($rows as $row): ?>
      <?php $formId = 'cb-admin-pobocky-' . (int)($row['id_pob'] ?? 0); ?>
      <form id="<?= h($formId) ?>" method="post" action="<?= h($formAction) ?>"></form>
    <?php endforeach; ?>
  </div>

  <p class="card_text txt_seda odstup_vnejsi_0<?= $msgErr ? ' card_text_muted' : '' ?>">
    <?= h($msg) ?>
  </p>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/admin_pobocky.php * Verze: V2 * Aktualizace: 18.03.2026 */
?>
