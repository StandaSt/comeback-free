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

$card_min_html = '<p class="card_mini_text txt_seda">SprĂˇva poboÄŤek</p>';
$renderMode = isset($cbDashboardRenderMode) ? trim((string)$cbDashboardRenderMode) : '';

if ($renderMode === 'mini') {
    return;
}

$isEndCol = static function (string $col): bool {
    return str_starts_with($col, 'end_');
};

$formatEndTime = static function (mixed $value): string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '00:00';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m) === 1) {
        return sprintf('%d:%02d', (int)$m[1], (int)$m[2]);
    }
    if (preg_match('/^\d{1,2}$/', $raw) === 1) {
        return sprintf('%d:00', (int)$raw);
    }

    return '0:00';
};

$normalizeEndTimeForDb = static function (mixed $value): string {
    $raw = trim((string)$value);
    if ($raw === '') {
        return '00:00:00';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $raw, $m) === 1) {
        $hour = max(0, min(23, (int)$m[1]));
        $minute = max(0, min(59, (int)$m[2]));
        return sprintf('%02d:%02d:00', $hour, $minute);
    }
    if (preg_match('/^\d{1,2}$/', $raw) === 1) {
        $hour = max(0, min(23, (int)$raw));
        return sprintf('%02d:00:00', $hour);
    }

    return '00:00:00';
};

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
            if ($isEndCol($col)) {
                $vals[] = $normalizeEndTimeForDb($_POST[$col] ?? '');
            } else {
                $vals[] = trim((string)($_POST[$col] ?? ''));
            }
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
        . ' FROM pobocka WHERE id_pob > 0 ORDER BY id_pob ASC';
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

$card_min_html = '<p class="card_mini_text txt_seda">Správa poboček</p>';
$sirkaSloupcu = [
    'nazev' => 'width:15ch;',
    'ulice' => 'width:20ch;',
    'mesto' => 'width:12ch;',
    'psc' => 'width:7ch;',
    'end' => 'width:54px;',
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
                <?php $isEndInput = $isEndCol($col); ?>
                <?php $maxLenAttr = $isEndInput ? ' maxlength="5" pattern="[0-9]{1,2}:[0-9]{2}" placeholder="0:00"' : ''; ?>
                <?php
                  $inputStyle = match (true) {
                      $isEndInput => ' style="' . $sirkaSloupcu['end'] . '"',
                      isset($sirkaSloupcu[$col]) => ' style="' . $sirkaSloupcu[$col] . '"',
                      default => '',
                  };
                  $inputValue = $isEndInput
                      ? $formatEndTime($row[$col] ?? '')
                      : (string)($row[$col] ?? '');
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
                    value="<?= h($inputValue) ?>"
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
