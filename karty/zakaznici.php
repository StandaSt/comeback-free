<?php
// karty/zakaznici.php * Verze: V11 * Aktualizace: 27.03.2026
declare(strict_types=1);

/*
 * Karta "Zakaznici":
 * - nacita seznam zakazniku,
 * - umi filtrovani a strankovani v max rezimu,
 * - mini rezim ponechava jen souhrn.
 */

// === KONFIG TABULKY: ZAKAZNICI ===
$tabKonfig = [
    'enable_filters' => 1,
    'enable_sort' => 1,
    'enable_pagination' => 1,
    'default_per' => 20,
    'per_options' => [20, 50, 100],
];

$totalZak = 0;
$activeZak = 0;
$blockedZak = 0;
$topLines = ['-', '-', '-'];
$zakRows = [];
$zakTotal = 0;
$zakPages = 1;
$zakPage = 1;
$zakPer = (int)$tabKonfig['default_per'];
$zakBlk = '0';
$zakFilters = [];
$zakError = '';
$formAction = cb_url('/');

$zakCols = [
    'id' => ['label' => 'Poř.č.', 'db' => 'id_zak'],
    'prijmeni' => ['label' => 'příjmení', 'db' => 'prijmeni', 'filter' => true],
    'jmeno' => ['label' => 'jméno', 'db' => 'jmeno', 'filter' => true],
    'telefon' => ['label' => 'telefon', 'db' => 'telefon', 'filter' => true],
    'email' => ['label' => 'email', 'db' => 'email', 'filter' => true],
    'ulice' => ['label' => 'ulice', 'db' => 'ulice', 'filter' => true],
    'mesto' => ['label' => 'město', 'db' => 'mesto', 'filter' => true],
    'pobocka' => ['label' => 'pobočka', 'db' => 'pobocka', 'filter' => true],
    'posl_obj' => ['label' => 'aktivita', 'db' => 'posledni_obj'],
];
$zakFilterStyle = [
    'prijmeni' => 'width:10ch;',
    'jmeno' => 'width:8ch;',
    'telefon' => 'width:10ch;',
    'email' => 'width:16ch;',
    'ulice' => 'width:16ch;',
    'mesto' => 'width:8ch;',
    'pobocka' => 'width:10ch;',
];

// Whitelist sloupcu pro trideni.
$zakSortMap = [
    'id' => 'z.id_zak',
    'prijmeni' => 'COALESCE(z.prijmeni, "")',
    'jmeno' => 'COALESCE(z.jmeno, "")',
    'telefon' => 'COALESCE(z.telefon, "")',
    'email' => 'COALESCE(z.email, "")',
    'ulice' => 'COALESCE(z.ulice, "")',
    'mesto' => 'COALESCE(z.mesto, "")',
    'pobocka' => 'COALESCE(p.kod, "")',
    'posl_obj' => 'COALESCE(z.posledni_obj, "")',
];
$zakSortRaw = trim((string)($_GET['zak_sort'] ?? 'id'));
$zakDirRaw = strtoupper(trim((string)($_GET['zak_dir'] ?? 'DESC')));
$zakSort = 'id';
$zakDir = 'DESC';
if ((int)$tabKonfig['enable_sort'] === 1 && array_key_exists($zakSortRaw, $zakSortMap)) {
    $zakSort = $zakSortRaw;
}
if ((int)$tabKonfig['enable_sort'] === 1 && in_array($zakDirRaw, ['ASC', 'DESC'], true)) {
    $zakDir = $zakDirRaw;
}

$zakPerOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn(int $v): bool => $v > 0));
if ($zakPerOptions === []) {
    $zakPerOptions = [20, 50, 100];
}

$zakPerRaw = (int)($_GET['zak_per'] ?? (int)$tabKonfig['default_per']);
if ((int)$tabKonfig['enable_pagination'] === 1 && in_array($zakPerRaw, $zakPerOptions, true)) {
    $zakPer = $zakPerRaw;
}

$zakPageRaw = (int)($_GET['zak_p'] ?? 1);
if ((int)$tabKonfig['enable_pagination'] === 1 && $zakPageRaw > 1) {
    $zakPage = $zakPageRaw;
}

$zakBlkRaw = (string)($_GET['zak_blk'] ?? '0');
if (in_array($zakBlkRaw, ['0', '1'], true)) {
    $zakBlk = $zakBlkRaw;
}

$zakFiltersRaw = $_GET['zak_f'] ?? [];
if ((int)$tabKonfig['enable_filters'] === 1 && is_array($zakFiltersRaw)) {
    foreach ($zakCols as $key => $cfg) {
        if (empty($cfg['filter'])) {
            continue;
        }
        $zakFilters[$key] = trim((string)($zakFiltersRaw[$key] ?? ''));
    }
}

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $selectedPobocky = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
    $selectedPobocky = array_values(array_filter(array_map('intval', $selectedPobocky), static function (int $v): bool {
        return $v > 0;
    }));
    $selectedWhere = '';
    if ($selectedPobocky) {
        $selectedWhere = ' WHERE z.id_pob IN (' . implode(',', $selectedPobocky) . ')';
    }

    $sqlCount = '
        SELECT
            COUNT(*) AS total_cnt,
            SUM(CASE WHEN blokovany = 0 THEN 1 ELSE 0 END) AS active_cnt,
            SUM(CASE WHEN blokovany = 1 THEN 1 ELSE 0 END) AS blocked_cnt
        FROM zakaznik z
    ' . $selectedWhere;
    $resCount = $conn->query($sqlCount);
    if ($resCount) {
        $row = $resCount->fetch_assoc() ?: [];
        $totalZak = (int)($row['total_cnt'] ?? 0);
        $activeZak = (int)($row['active_cnt'] ?? 0);
        $blockedZak = (int)($row['blocked_cnt'] ?? 0);
        $resCount->free();
    }

    $sqlTop = '
        SELECT
            COALESCE(z.jmeno, "") AS jmeno,
            COALESCE(z.prijmeni, "") AS prijmeni,
            COALESCE(z.mesto, "") AS mesto,
            COUNT(o.id_obj) AS obj_count
        FROM objednavky_restia o
        INNER JOIN zakaznik z ON z.id_zak = o.id_zak
        ' . $selectedWhere . '
        GROUP BY z.id_zak, z.jmeno, z.prijmeni, z.mesto
        ORDER BY obj_count DESC, z.id_zak DESC
        LIMIT 3
    ';
    $resTop = $conn->query($sqlTop);
    if ($resTop) {
        $tmp = [];
        while ($r = $resTop->fetch_assoc()) {
            $jmeno = trim((string)($r['jmeno'] ?? ''));
            $prijmeni = trim((string)($r['prijmeni'] ?? ''));
            $mesto = trim((string)($r['mesto'] ?? ''));
            $obj = (int)($r['obj_count'] ?? 0);

            $fullName = trim($jmeno . ' ' . $prijmeni);
            if ($fullName === '') {
                $fullName = 'Neznámý zákazník';
            }
            if ($mesto === '') {
                $mesto = '-';
            }

            $tmp[] = $fullName . ' ' . $mesto . ' ' . $obj . ' obj.';
        }
        $resTop->free();

        for ($i = 0; $i < 3; $i++) {
            if (isset($tmp[$i])) {
                $topLines[$i] = $tmp[$i];
            }
        }
    }

    $where = [];
    if ($selectedPobocky) {
        $where[] = 'z.id_pob IN (' . implode(',', $selectedPobocky) . ')';
    }

    $where[] = 'z.blokovany = ' . (int)$zakBlk;

    if ((int)$tabKonfig['enable_filters'] === 1) {
        foreach ($zakFilters as $key => $value) {
            if ($value === '') {
                continue;
            }
            $safe = $conn->real_escape_string($value);
            if ($key === 'pobocka') {
                $where[] = "COALESCE(p.kod, '') LIKE '%" . $safe . "%'";
            } else {
                $dbKey = (string)($zakCols[$key]['db'] ?? '');
                if ($dbKey === '') {
                    continue;
                }
                $where[] = "COALESCE(z.`" . $dbKey . "`, '') LIKE '%" . $safe . "%'";
            }
        }
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $countSql = '
        SELECT COUNT(*)
        FROM zakaznik z
        LEFT JOIN pobocka p ON p.id_pob = z.id_pob
    ' . $whereSql;
    $resZakCount = $conn->query($countSql);
    if ($resZakCount) {
        $rowCount = $resZakCount->fetch_row();
        $zakTotal = (int)($rowCount[0] ?? 0);
        $resZakCount->free();
    }

    if ((int)$tabKonfig['enable_pagination'] === 1) {
        $zakPages = max(1, (int)ceil($zakTotal / $zakPer));
        if ($zakPage > $zakPages) {
            $zakPage = $zakPages;
        }
        $offset = ($zakPage - 1) * $zakPer;
    } else {
        $zakPages = 1;
        $zakPage = 1;
        $zakPer = max(1, $zakTotal);
        $offset = 0;
    }

    $orderSql = 'z.id_zak DESC';
    if ((int)$tabKonfig['enable_sort'] === 1) {
        $orderSql = $zakSortMap[$zakSort] . ' ' . $zakDir . ', z.id_zak DESC';
    }

    $dataSql = '
        SELECT
            z.id_zak,
            z.prijmeni,
            z.jmeno,
            COALESCE(z.telefon, "") AS telefon,
            COALESCE(z.email, "") AS email,
            COALESCE(z.ulice, "") AS ulice,
            COALESCE(z.mesto, "") AS mesto,
            COALESCE(p.kod, "") AS pobocka,
            z.posledni_obj,
            z.blokovany
        FROM zakaznik z
        LEFT JOIN pobocka p ON p.id_pob = z.id_pob
    ' . $whereSql . '
        ORDER BY ' . $orderSql . '
        LIMIT ' . (int)$zakPer . ' OFFSET ' . (int)$offset;

    $resZak = $conn->query($dataSql);
    if ($resZak) {
        while ($rowZak = $resZak->fetch_assoc()) {
            $zakRows[] = $rowZak;
        }
        $resZak->free();
    }
} catch (Throwable $e) {
    $totalZak = 0;
    $activeZak = 0;
    $blockedZak = 0;
    $topLines = ['-', '-', '-'];
    $zakRows = [];
    $zakTotal = 0;
    $zakPages = 1;
    $zakPage = 1;
    $zakError = 'Načtení zákazníků selhalo.';
}

$zakQueryDefaults = [
    'zak_p' => '1',
    'zak_per' => (string)$tabKonfig['default_per'],
    'zak_blk' => '0',
    'zak_sort' => 'id',
    'zak_dir' => 'DESC',
];
$zakBaseParams = [
    'zak_per' => (string)$zakPer,
    'zak_blk' => $zakBlk,
];
if ((int)$tabKonfig['enable_sort'] === 1) {
    $zakBaseParams['zak_sort'] = $zakSort;
    $zakBaseParams['zak_dir'] = $zakDir;
}
if ((int)$tabKonfig['enable_filters'] === 1 && $zakFilters !== []) {
    $zakBaseParams['zak_f'] = $zakFilters;
}
$zakBuildUrl = static function (array $extra = []) use ($zakBaseParams, $zakQueryDefaults): string {
    return cb_url_query('/', array_merge($zakBaseParams, $extra), $zakQueryDefaults);
};
?>

<?php
ob_start();
?>
<p class="card_text txt_seda odstup_vnejsi_0">Nalezeno zákazníků: <strong><?= h((string)$totalZak) ?></strong></p>
    <p class="card_text txt_seda odstup_vnejsi_0">Aktivních / blokovaných: <strong><?= h((string)$activeZak) ?></strong> / <strong><?= h((string)$blockedZak) ?></strong></p>
    <p class="card_text txt_seda odstup_vnejsi_0">Nejaktivnější zákazníci:</p>
    <p class="card_text txt_seda odstup_vnejsi_0"><?= h($topLines[0]) ?></p>
    <p class="card_text txt_seda odstup_vnejsi_0"><?= h($topLines[1]) ?></p>
    <p class="card_text txt_seda odstup_vnejsi_0"><?= h($topLines[2]) ?></p>
<?php
$card_min_html = (string)ob_get_clean();
$card_min_html = ''
    . '<div class="table-wrap ram_normal bg_bila zaobleni_12">'
    . '  <table class="table ram_normal bg_bila radek_1_35 card_table_min" >'
    . '    <tbody>'
    . '      <tr>'
    . '        <td>Zákazníků v DB</td>'
    . '        <td class="txt_r"><strong>' . h((string)$totalZak) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>aktivní/blokovaní</td>'
    . '        <td class="txt_r"><strong>' . h((string)$activeZak) . '/' . h((string)$blockedZak) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>nejčastější zákazník:</td>'
    . '        <td class="txt_r"><strong>František Skočdopole</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>top zákazník:</td>'
    . '        <td class="txt_r"><strong>Emanuel Bacigala</strong></td>'
    . '      </tr>'
    . '    </tbody>'
    . '  </table>'
    . '</div>';

ob_start();
?>
<?php if ($zakError !== ''): ?>
      <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($zakError) ?></p>
    <?php else: ?>
      <form method="get" action="<?= h($formAction) ?>" class="card_stack gap_10 displ_flex" autocomplete="off">
        <input type="hidden" name="zak_p" value="1">
        <?php if ((int)$tabKonfig['enable_sort'] === 1): ?>
          <input type="hidden" name="zak_sort" value="<?= h($zakSort) ?>">
          <input type="hidden" name="zak_dir" value="<?= h($zakDir) ?>">
        <?php endif; ?>

        <div class="table-wrap ram_normal bg_bila zaobleni_12">
          <table class="table ram_normal bg_bila radek_1_35 card_table_max">
            <thead>
              <tr class="filter-row">
                <th class="txt_r"></th>
                <th class="txt_r"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($zakFilterStyle['prijmeni'] ?? 'width:10ch;')) ?>" type="text" name="zak_f[prijmeni]" value="<?= h($zakFilters['prijmeni'] ?? '') ?>"></th>
                <th class="txt_l"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($zakFilterStyle['jmeno'] ?? 'width:10ch;')) ?>" type="text" name="zak_f[jmeno]" value="<?= h($zakFilters['jmeno'] ?? '') ?>"></th>
                <th class="txt_l"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($zakFilterStyle['telefon'] ?? 'width:10ch;')) ?>" type="text" name="zak_f[telefon]" value="<?= h($zakFilters['telefon'] ?? '') ?>"></th>
                <th class="txt_r"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($zakFilterStyle['email'] ?? 'width:10ch;')) ?>" type="text" name="zak_f[email]" value="<?= h($zakFilters['email'] ?? '') ?>"></th>
                <th class="txt_r"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($zakFilterStyle['ulice'] ?? 'width:10ch;')) ?>" type="text" name="zak_f[ulice]" value="<?= h($zakFilters['ulice'] ?? '') ?>"></th>
                <th class="txt_r"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($zakFilterStyle['mesto'] ?? 'width:10ch;')) ?>" type="text" name="zak_f[mesto]" value="<?= h($zakFilters['mesto'] ?? '') ?>"></th>
                <th class="txt_l"><input class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24" style="<?= h((string)($zakFilterStyle['pobocka'] ?? 'width:10ch;')) ?>" type="text" name="zak_f[pobocka]" value="<?= h($zakFilters['pobocka'] ?? '') ?>"></th>
                <th class="txt_r"> <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 icon-x small zaobleni_6 vyska_24 radek_24 displ_inline_flex" href="<?= h($formAction) ?>">&times;</a></th>
              </tr>
              <tr>
                <th class="th-sort txt_r"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">Poř.č.</span></span></th>
                <th class="th-sort txt_r"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">příjmení</span></span></th>
                <th class="th-sort txt_l"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">jméno</span></span></th>
                <th class="th-sort txt_l"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">telefon</span></span></th>
                <th class="th-sort txt_r"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">email</span></span></th>
                <th class="th-sort txt_r"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">ulice</span></span></th>
                <th class="th-sort txt_r"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">město</span></span></th>
                <th class="th-sort txt_l"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">pobočka</span></span></th>
                <th class="th-sort txt_r"><span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label">aktivita</span></span></th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$zakRows): ?>
                <tr>
                  <td colspan="<?= h((string)count($zakCols)) ?>">Žádná data</td>
                </tr>
              <?php else: ?>
                <?php foreach ($zakRows as $rowZak): ?>
                  <tr>
                    <?php foreach ($zakCols as $key => $cfg): ?>
                      <?php
                      $dbKey = (string)($cfg['db'] ?? '');
                      $value = (string)($rowZak[$dbKey] ?? '');

                      if ($key === 'telefon') {
                          $digits = preg_replace('~[^\d\+]~u', '', trim($value));
                          $value = $digits === '' ? '-' : (string)$digits;
                          if (preg_match('~^\+(\d{1,3})(\d{9})$~', $value, $m)) {
                              $value = '+' . $m[1] . ' ' . substr($m[2], 0, 3) . ' ' . substr($m[2], 3, 3) . ' ' . substr($m[2], 6, 3);
                          } elseif (preg_match('~^\d{9}$~', $value)) {
                              $value = substr($value, 0, 3) . ' ' . substr($value, 3, 3) . ' ' . substr($value, 6, 3);
                          }
                      } elseif ($key === 'posl_obj') {
                          $rawDate = trim($value);
                          if ($rawDate === '' || $rawDate === '0000-00-00' || $rawDate === '0000-00-00 00:00:00') {
                              $value = '';
                          } else {
                              $ts = strtotime($rawDate);
                              $value = $ts === false ? $rawDate : date('j.n.Y', $ts);
                          }
                      }
                      $colRight = in_array($key, ['id', 'prijmeni', 'email', 'ulice', 'mesto', 'posl_obj'], true);
                      ?>
                      <td<?= $colRight ? ' class="txt_r"' : '' ?>><?= h($value) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ((int)$tabKonfig['enable_pagination'] === 1): ?>
        <div class="list-bottom gap_14 gap_10 odstup_vnitrni_0 displ_grid">
          <div class="per-form gap_8 displ_inline_flex">
            <span>Zobrazuji</span>
            <select name="zak_per" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 per-select" onchange="this.form.zak_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
              <option value="20"<?= $zakPer === 20 ? ' selected' : '' ?>>20 řádků</option>
              <option value="50"<?= $zakPer === 50 ? ' selected' : '' ?>>50 řádků</option>
              <option value="100"<?= $zakPer === 100 ? ' selected' : '' ?>>100 řádků</option>
            </select>
          </div>

          <div class="pagination-icon gap_4 displ_inline_flex">
            <?php $prevDisabled = $zakPage <= 1; ?>
            <?php $nextDisabled = $zakPage >= $zakPages; ?>
            <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $prevDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $prevDisabled ? '#' : h($zakBuildUrl(['zak_p' => '1'])) ?>">«</a>
            <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $prevDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $prevDisabled ? '#' : h($zakBuildUrl(['zak_p' => (string)max(1, $zakPage - 1)])) ?>">‹</a>

            <?php
            $pageItems = [];
            if ($zakPages <= 7) {
                for ($i = 1; $i <= $zakPages; $i++) {
                    $pageItems[] = $i;
                }
            } elseif ($zakPage <= 4) {
                $pageItems = [1, 2, 3, 4, 5, '…', $zakPages];
            } elseif ($zakPage >= $zakPages - 3) {
                $pageItems = [1, '…', $zakPages - 4, $zakPages - 3, $zakPages - 2, $zakPages - 1, $zakPages];
            } else {
                $pageItems = [1, '…', $zakPage - 1, $zakPage, $zakPage + 1, '…', $zakPages];
            }
            ?>
            <?php foreach ($pageItems as $item): ?>
              <?php if ($item === '…'): ?>
                <span class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 disabled displ_inline_flex">…</span>
              <?php elseif ((int)$item === $zakPage): ?>
                <span class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 page-current displ_inline_flex"><?= h((string)$item) ?></span>
              <?php else: ?>
                <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24 displ_inline_flex" href="<?= h($zakBuildUrl(['zak_p' => (string)$item])) ?>"><?= h((string)$item) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>

            <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $nextDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $nextDisabled ? '#' : h($zakBuildUrl(['zak_p' => (string)min($zakPages, $zakPage + 1)])) ?>">›</a>
            <a class="icon-btn cursor_ruka ram_normal bg_seda text_18 w44 vyska_24 radek_24<?= $nextDisabled ? ' disabled' : '' ?> displ_inline_flex" href="<?= $nextDisabled ? '#' : h($zakBuildUrl(['zak_p' => (string)$zakPages])) ?>">»</a>
          </div>

          <div class="per-form gap_8 right displ_inline_flex jc_konec">
            <input type="hidden" name="zak_blk" value="0">
            <label class="displ_inline_flex gap_6 cursor_ruka" style="align-items:center; white-space:nowrap;">
              <input type="checkbox" name="zak_blk" value="1"<?= $zakBlk === '1' ? ' checked' : '' ?> onchange="this.form.zak_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
              <span>blokovaní (<?= h((string)$blockedZak) ?>)</span>
            </label>
          </div>
        </div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
/* karty/zakaznici.php * Verze: V11 * Aktualizace: 27.03.2026 */
// pocet radku 500
?>
