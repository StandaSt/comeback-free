<?php
// karty/zakaznici.php * Verze: V9 * Aktualizace: 13.03.2026
declare(strict_types=1);

$totalZak = 0;
$activeZak = 0;
$blockedZak = 0;
$topLines = ['-', '-', '-'];
$zakRows = [];
$zakTotal = 0;
$zakPages = 1;
$zakPage = 1;
$zakPer = 20;
$zakBlk = '0';
$zakFilters = [];
$zakError = '';
$currentSekce = isset($cb_dashboard_sekce) ? (int)$cb_dashboard_sekce : (int)($_GET['sekce'] ?? 3);
if (!in_array($currentSekce, [1, 2, 3], true)) {
    $currentSekce = 3;
}
$formAction = cb_url('/?sekce=' . $currentSekce);
$keepExpanded = isset($_GET['zak_p']) || isset($_GET['zak_per']) || isset($_GET['zak_blk']) || isset($_GET['zak_f']);

$zakCols = [
    'id' => ['label' => 'id', 'db' => 'id_zak'],
    'prijmeni' => ['label' => 'prijmeni', 'db' => 'prijmeni', 'filter' => true],
    'jmeno' => ['label' => 'jmeno', 'db' => 'jmeno', 'filter' => true],
    'telefon' => ['label' => 'telefon', 'db' => 'telefon', 'filter' => true],
    'email' => ['label' => 'email', 'db' => 'email', 'filter' => true],
    'ulice' => ['label' => 'ulice', 'db' => 'ulice', 'filter' => true],
    'mesto' => ['label' => 'mesto', 'db' => 'mesto', 'filter' => true],
    'pobocka' => ['label' => 'pobočka', 'db' => 'pobocka', 'filter' => true],
    'posl_obj' => ['label' => 'posl_obj', 'db' => 'posledni_obj'],
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

$zakPerRaw = (int)($_GET['zak_per'] ?? 20);
if (in_array($zakPerRaw, [20, 50, 100], true)) {
    $zakPer = $zakPerRaw;
}

$zakPageRaw = (int)($_GET['zak_p'] ?? 1);
if ($zakPageRaw > 1) {
    $zakPage = $zakPageRaw;
}

$zakBlkRaw = (string)($_GET['zak_blk'] ?? '0');
if (in_array($zakBlkRaw, ['0', '1', 'all'], true)) {
    $zakBlk = $zakBlkRaw;
}

$zakFiltersRaw = $_GET['zak_f'] ?? [];
if (is_array($zakFiltersRaw)) {
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
        FROM objednavka o
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

    if ($zakBlk !== 'all') {
        $where[] = 'z.blokovany = ' . (int)$zakBlk;
    }

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

    $zakPages = max(1, (int)ceil($zakTotal / $zakPer));
    if ($zakPage > $zakPages) {
        $zakPage = $zakPages;
    }
    $offset = ($zakPage - 1) * $zakPer;

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
            z.posledni_obj
        FROM zakaznik z
        LEFT JOIN pobocka p ON p.id_pob = z.id_pob
    ' . $whereSql . '
        ORDER BY z.id_zak DESC
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

$zakBaseParams = [
    'sekce=' . rawurlencode((string)$currentSekce),
    'zak_per=' . rawurlencode((string)$zakPer),
    'zak_blk=' . rawurlencode($zakBlk),
];
foreach ($zakFilters as $key => $value) {
    if ($value === '') {
        continue;
    }
    $zakBaseParams[] = 'zak_f[' . rawurlencode($key) . ']=' . rawurlencode($value);
}
$zakBaseUrl = cb_url('/?' . implode('&', $zakBaseParams));
?>

<?php
ob_start();
?>
<p class="card_text">Nalezeno zákazníků: <strong><?= h((string)$totalZak) ?></strong></p>
    <p class="card_text">Aktivních / blokovaných: <strong><?= h((string)$activeZak) ?></strong> / <strong><?= h((string)$blockedZak) ?></strong></p>
    <p class="card_text">Nejaktivnější zákazníci:</p>
    <p class="card_text"><?= h($topLines[0]) ?></p>
    <p class="card_text"><?= h($topLines[1]) ?></p>
    <p class="card_text"><?= h($topLines[2]) ?></p>
<?php
$card_min_html = (string)ob_get_clean();
$card_min_html = ''
    . '<div class="table-wrap">'
    . '  <table class="table card_table_min" >'
    . '    <tbody>'
    . '      <tr>'
    . '        <td>Zákazníků v DB</td>'
    . '        <td style="text-align:right;"><strong>' . h((string)$totalZak) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>aktivní/blokovaní</td>'
    . '        <td style="text-align:right;"><strong>' . h((string)$activeZak) . '/' . h((string)$blockedZak) . '</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>nejčastější zákazník:</td>'
    . '        <td style="text-align:right;"><strong>František Skočdopole</strong></td>'
    . '      </tr>'
    . '      <tr>'
    . '        <td>top zákazník:</td>'
    . '        <td style="text-align:right;"><strong>Emanuel Bacigala</strong></td>'
    . '      </tr>'
    . '    </tbody>'
    . '  </table>'
    . '</div>';
$startExpanded = $keepExpanded;

ob_start();
?>
<?php if ($zakError !== ''): ?>
      <p class="card_text card_text_muted"><?= h($zakError) ?></p>
    <?php else: ?>
      <form method="get" action="<?= h($formAction) ?>" class="card_stack" autocomplete="off">
        <input type="hidden" name="sekce" value="<?= h((string)$currentSekce) ?>">
        <input type="hidden" name="zak_p" value="1">

        <div class="table-wrap">
          <table class="table card_table_max">
            <thead>
              <tr class="filter-row">
                <?php foreach ($zakCols as $key => $cfg): ?>
                  <?php if ($key === 'id'): ?>
                    <th></th>
                  <?php elseif (!empty($cfg['filter'])): ?>
                    <th><input class="filter-input" style="<?= h((string)($zakFilterStyle[$key] ?? 'width:10ch;')) ?>" type="text" name="zak_f[<?= h($key) ?>]" value="<?= h($zakFilters[$key] ?? '') ?>"></th>
                  <?php else: ?>
                    <th><a class="icon-btn icon-x small" href="<?= h($formAction) ?>">×</a></th>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tr>
              <tr>
                <?php foreach ($zakCols as $key => $cfg): ?>
                  <th><?= h($cfg['label']) ?></th>
                <?php endforeach; ?>
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
                              $value = $ts === false ? $rawDate : date('j.n.y', $ts);
                          }
                      }
                      ?>
                      <td><?= h($value) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="list-bottom">
          <div class="per-form">
            <span>Zobrazuji</span>
            <select name="zak_per" class="filter-input per-select" onchange="this.form.zak_p.value=1; this.form.submit();">
              <option value="20"<?= $zakPer === 20 ? ' selected' : '' ?>>20 řádků</option>
              <option value="50"<?= $zakPer === 50 ? ' selected' : '' ?>>50 řádků</option>
              <option value="100"<?= $zakPer === 100 ? ' selected' : '' ?>>100 řádků</option>
            </select>
          </div>

          <div class="pagination-icon">
            <?php $prevDisabled = $zakPage <= 1; ?>
            <?php $nextDisabled = $zakPage >= $zakPages; ?>
            <a class="icon-btn w44<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($zakBaseUrl . '&zak_p=1') ?>">«</a>
            <a class="icon-btn w44<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($zakBaseUrl . '&zak_p=' . (string)max(1, $zakPage - 1)) ?>">‹</a>

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
                <span class="icon-btn w44 disabled">…</span>
              <?php elseif ((int)$item === $zakPage): ?>
                <span class="icon-btn w44 page-current"><?= h((string)$item) ?></span>
              <?php else: ?>
                <a class="icon-btn w44" href="<?= h($zakBaseUrl . '&zak_p=' . (string)$item) ?>"><?= h((string)$item) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>

            <a class="icon-btn w44<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($zakBaseUrl . '&zak_p=' . (string)min($zakPages, $zakPage + 1)) ?>">›</a>
            <a class="icon-btn w44<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($zakBaseUrl . '&zak_p=' . (string)$zakPages) ?>">»</a>
          </div>

          <div class="per-form right">
            <select name="zak_blk" class="filter-input blk-select" onchange="this.form.zak_p.value=1; this.form.submit();">
              <option value="0"<?= $zakBlk === '0' ? ' selected' : '' ?>>Aktivní</option>
              <option value="1"<?= $zakBlk === '1' ? ' selected' : '' ?>>Blokovaní</option>
              <option value="all"<?= $zakBlk === 'all' ? ' selected' : '' ?>>Vše</option>
            </select>
          </div>
        </div>
      </form>
    <?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();
/* karty/zakaznici.php * Verze: V9 * Aktualizace: 13.03.2026 */
?>
