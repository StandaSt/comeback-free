<?php
// K17
// karty/mzdy.php * Verze: V3 * Aktualizace: 22.05.2026
declare(strict_types=1);

/*
 * Karta "Mzdy":
 * - nacita mesicni HR mzdy,
 * - v max rezimu umi filtry, trideni a strankovani,
 * - nezapisuje do DB.
 */

$tabKonfig = [
    'enable_filters' => 1,
    'enable_sort' => 1,
    'enable_pagination' => 1,
    'default_per' => 20,
    'default_sort' => 'mesic',
    'default_dir' => 'DESC',
    'per_options' => [20, 50, 100],
];

if (!function_exists('cb_k17_int')) {
    function cb_k17_int(mixed $value): string
    {
        return number_format((float)$value, 0, ',', ' ');
    }
}

if (!function_exists('cb_k17_dec')) {
    function cb_k17_dec(mixed $value): string
    {
        return number_format((float)$value, 2, ',', ' ');
    }
}

if (!function_exists('cb_k17_money')) {
    function cb_k17_money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return cb_k17_int($value) . ' Kč';
    }
}

if (!function_exists('cb_k17_month_label')) {
    function cb_k17_month_label(int $rok, int $mesic): string
    {
        return (string)$mesic . '/' . substr((string)$rok, -2);
    }
}

$mzdyRows = [];
$mzdyTotal = 0;
$mzdyPages = 1;
$mzdyPage = 1;
$mzdyPer = (int)$tabKonfig['default_per'];
$mzdyMode = 'all';
$mzdySlot = 'all';
$mzdyHours = 'all';
$mzdySort = (string)$tabKonfig['default_sort'];
$mzdyDir = (string)$tabKonfig['default_dir'];
$mzdyFilters = [];
$mzdyError = '';
$mzdyStats = [
    'total' => 0,
    'with_id' => 0,
    'without_id' => 0,
];
$mzdyTotals = [
    'hodiny' => 0.0,
    'cista_mzda' => 0.0,
    'hruba_mzda' => 0.0,
    'superhruba_mzda' => 0.0,
];
$formAction = cb_url('/');

$mzdyCols = [
    'mesic' => ['label' => 'měsíc', 'width' => '90px', 'filter' => true],
    'id_user' => ['label' => 'ID user', 'width' => '90px', 'filter' => true],
    'import_jmeno' => ['label' => 'importované jméno', 'width' => '190px', 'filter' => true],
    'prijmeni' => ['label' => 'příjmení', 'width' => '150px', 'filter' => true],
    'jmeno' => ['label' => 'jméno', 'width' => '150px', 'filter' => true],
    'mzda_typ' => ['label' => 'typ', 'width' => '110px', 'filter' => true],
    'hodiny' => ['label' => 'hodiny', 'width' => '110px'],
    'hodinova_sazba' => ['label' => 'sazba', 'width' => '110px'],
    'mesicni_fix' => ['label' => 'fix', 'width' => '110px'],
    'cista_mzda' => ['label' => 'čistá mzda', 'width' => '130px'],
    'hruba_mzda' => ['label' => 'hrubá mzda', 'width' => '130px'],
    'superhruba_mzda' => ['label' => 'náklad', 'width' => '130px'],
];

$mzdySortMap = [
    'mesic' => 'm.rok {DIR}, m.mesic',
    'id_user' => 'm.id_user',
    'import_jmeno' => 'COALESCE(m.import_jmeno, "")',
    'prijmeni' => 'COALESCE(u.prijmeni, "")',
    'jmeno' => 'COALESCE(u.jmeno, "")',
    'mzda_typ' => 'm.mzda_typ',
    'hodiny' => 'm.hodiny',
    'hodinova_sazba' => 'm.hodinova_sazba',
    'mesicni_fix' => 'm.mesicni_fix',
    'cista_mzda' => 'm.cista_mzda',
    'hruba_mzda' => 'm.hruba_mzda',
    'superhruba_mzda' => 'm.superhruba_mzda',
];

$mzdyPerOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn(int $v): bool => $v > 0));
if ($mzdyPerOptions === []) {
    $mzdyPerOptions = [20, 50, 100];
}

$mzdyPerRaw = (int)($_GET['mzdy_per'] ?? (int)$tabKonfig['default_per']);
if ((int)$tabKonfig['enable_pagination'] === 1 && in_array($mzdyPerRaw, $mzdyPerOptions, true)) {
    $mzdyPer = $mzdyPerRaw;
}

$mzdyPageRaw = (int)($_GET['mzdy_p'] ?? 1);
if ((int)$tabKonfig['enable_pagination'] === 1 && $mzdyPageRaw > 1) {
    $mzdyPage = $mzdyPageRaw;
}

$mzdyModeRaw = (string)($_GET['mzdy_mode'] ?? 'all');
if (in_array($mzdyModeRaw, ['all', 'with_id', 'without_id'], true)) {
    $mzdyMode = $mzdyModeRaw;
}

$mzdySlotRaw = (string)($_GET['mzdy_slot'] ?? 'all');
if (in_array($mzdySlotRaw, ['all', 'instor', 'kuryr'], true)) {
    $mzdySlot = $mzdySlotRaw;
}

$mzdyHoursRaw = (string)($_GET['mzdy_hours'] ?? 'all');
if (in_array($mzdyHoursRaw, ['all', 'with_hours'], true)) {
    $mzdyHours = $mzdyHoursRaw;
}

$mzdySortRaw = trim((string)($_GET['mzdy_sort'] ?? (string)$tabKonfig['default_sort']));
$mzdyDirRaw = strtoupper(trim((string)($_GET['mzdy_dir'] ?? (string)$tabKonfig['default_dir'])));
if ((int)$tabKonfig['enable_sort'] === 1 && array_key_exists($mzdySortRaw, $mzdySortMap)) {
    $mzdySort = $mzdySortRaw;
}
if ((int)$tabKonfig['enable_sort'] === 1 && in_array($mzdyDirRaw, ['ASC', 'DESC'], true)) {
    $mzdyDir = $mzdyDirRaw;
}

$mzdyFiltersRaw = $_GET['mzdy_f'] ?? [];
if ((int)$tabKonfig['enable_filters'] === 1 && is_array($mzdyFiltersRaw)) {
    foreach ($mzdyCols as $key => $cfg) {
        if (empty($cfg['filter'])) {
            continue;
        }
        $mzdyFilters[$key] = trim((string)($mzdyFiltersRaw[$key] ?? ''));
    }
}

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $resStats = $conn->query(
        'SELECT COUNT(*) AS total,
                COUNT(id_user) AS with_id,
                SUM(id_user IS NULL) AS without_id
         FROM hr_mzdy_mesic'
    );
    if ($resStats) {
        $rowStats = $resStats->fetch_assoc() ?: [];
        $mzdyStats['total'] = (int)($rowStats['total'] ?? 0);
        $mzdyStats['with_id'] = (int)($rowStats['with_id'] ?? 0);
        $mzdyStats['without_id'] = (int)($rowStats['without_id'] ?? 0);
        $resStats->free();
    }

    $where = [];
    if ($mzdyMode === 'with_id') {
        $where[] = 'm.id_user IS NOT NULL';
    } elseif ($mzdyMode === 'without_id') {
        $where[] = 'm.id_user IS NULL';
    }
    if ($mzdySlot !== 'all') {
        $where[] = "m.slot = '" . $conn->real_escape_string($mzdySlot) . "'";
    }
    if ($mzdyHours === 'with_hours') {
        $where[] = 'COALESCE(m.hodiny, 0) > 0';
    }

    if ((int)$tabKonfig['enable_filters'] === 1) {
        foreach ($mzdyFilters as $key => $value) {
            if ($value === '') {
                continue;
            }

            $safe = $conn->real_escape_string($value);
            if ($key === 'mesic') {
                $where[] = "CONCAT(m.mesic, '/', RIGHT(m.rok, 2)) LIKE '%" . $safe . "%'";
            } elseif ($key === 'id_user') {
                $intValue = (int)$value;
                if ($intValue > 0) {
                    $where[] = 'm.id_user = ' . $intValue;
                }
            } elseif ($key === 'import_jmeno') {
                $where[] = "COALESCE(m.import_jmeno, '') LIKE '%" . $safe . "%'";
            } elseif ($key === 'prijmeni') {
                $where[] = "COALESCE(u.prijmeni, '') LIKE '%" . $safe . "%'";
            } elseif ($key === 'jmeno') {
                $where[] = "COALESCE(u.jmeno, '') LIKE '%" . $safe . "%'";
            } elseif ($key === 'mzda_typ') {
                $where[] = "COALESCE(m.mzda_typ, '') LIKE '%" . $safe . "%'";
            }
        }
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    if (($cbDashboardRenderMode ?? '') !== 'mini') {
        $countSql = '
            SELECT COUNT(*)
            FROM hr_mzdy_mesic m
            LEFT JOIN `user` u ON u.id_user = m.id_user
        ' . $whereSql;
        $resCount = $conn->query($countSql);
        if ($resCount) {
            $rowCount = $resCount->fetch_row();
            $mzdyTotal = (int)($rowCount[0] ?? 0);
            $resCount->free();
        }

        $totalsSql = '
            SELECT
                SUM(COALESCE(m.hodiny, 0)) AS hodiny,
                SUM(COALESCE(m.cista_mzda, 0)) AS cista_mzda,
                SUM(COALESCE(m.hruba_mzda, 0)) AS hruba_mzda,
                SUM(COALESCE(m.superhruba_mzda, 0)) AS superhruba_mzda
            FROM hr_mzdy_mesic m
            LEFT JOIN `user` u ON u.id_user = m.id_user
        ' . $whereSql;
        $resTotals = $conn->query($totalsSql);
        if ($resTotals) {
            $rowTotals = $resTotals->fetch_assoc() ?: [];
            $mzdyTotals['hodiny'] = (float)($rowTotals['hodiny'] ?? 0);
            $mzdyTotals['cista_mzda'] = (float)($rowTotals['cista_mzda'] ?? 0);
            $mzdyTotals['hruba_mzda'] = (float)($rowTotals['hruba_mzda'] ?? 0);
            $mzdyTotals['superhruba_mzda'] = (float)($rowTotals['superhruba_mzda'] ?? 0);
            $resTotals->free();
        }

        if ((int)$tabKonfig['enable_pagination'] === 1) {
            $mzdyPages = max(1, (int)ceil($mzdyTotal / $mzdyPer));
            if ($mzdyPage > $mzdyPages) {
                $mzdyPage = $mzdyPages;
            }
            $offset = ($mzdyPage - 1) * $mzdyPer;
        } else {
            $mzdyPages = 1;
            $mzdyPage = 1;
            $mzdyPer = max(1, $mzdyTotal);
            $offset = 0;
        }

        $sortExpr = $mzdySortMap[$mzdySort] ?? $mzdySortMap['mesic'];
        if (str_contains($sortExpr, '{DIR}')) {
            $orderSql = str_replace('{DIR}', $mzdyDir, $sortExpr) . ' ' . $mzdyDir . ', m.id_hr_mzda_mesic DESC';
        } else {
            $orderSql = $sortExpr . ' ' . $mzdyDir . ', m.rok DESC, m.mesic DESC, m.id_hr_mzda_mesic DESC';
        }

        $dataSql = '
            SELECT
                m.id_hr_mzda_mesic,
                m.rok,
                m.mesic,
                m.id_user,
                COALESCE(m.import_jmeno, "") AS import_jmeno,
                COALESCE(u.prijmeni, "") AS prijmeni,
                COALESCE(u.jmeno, "") AS jmeno,
                m.mzda_typ,
                m.hodiny,
                m.hodinova_sazba,
                m.mesicni_fix,
                m.cista_mzda,
                m.hruba_mzda,
                m.superhruba_mzda
            FROM hr_mzdy_mesic m
            LEFT JOIN `user` u ON u.id_user = m.id_user
        ' . $whereSql . '
            ORDER BY ' . $orderSql . '
            LIMIT ' . (int)$mzdyPer . ' OFFSET ' . (int)$offset;

        $resRows = $conn->query($dataSql);
        if ($resRows) {
            while ($row = $resRows->fetch_assoc()) {
                $mzdyRows[] = $row;
            }
            $resRows->free();
        }
    }
} catch (Throwable $e) {
    $mzdyRows = [];
    $mzdyTotal = 0;
    $mzdyPages = 1;
    $mzdyPage = 1;
    $mzdyError = 'Načtení mezd selhalo.';
}

$mzdyQueryDefaults = [
    'mzdy_p' => '1',
    'mzdy_per' => (string)$tabKonfig['default_per'],
    'mzdy_mode' => 'all',
    'mzdy_slot' => 'all',
    'mzdy_hours' => 'all',
    'mzdy_sort' => (string)$tabKonfig['default_sort'],
    'mzdy_dir' => (string)$tabKonfig['default_dir'],
];
$mzdyBaseParams = [
    'cb_load_max' => '1',
    'mzdy_per' => (string)$mzdyPer,
    'mzdy_mode' => $mzdyMode,
    'mzdy_slot' => $mzdySlot,
    'mzdy_hours' => $mzdyHours,
];
if ((int)$tabKonfig['enable_sort'] === 1) {
    $mzdyBaseParams['mzdy_sort'] = $mzdySort;
    $mzdyBaseParams['mzdy_dir'] = $mzdyDir;
}
if ((int)$tabKonfig['enable_filters'] === 1 && $mzdyFilters !== []) {
    $mzdyBaseParams['mzdy_f'] = $mzdyFilters;
}
$mzdyBuildUrl = static function (array $extra = []) use ($mzdyBaseParams, $mzdyQueryDefaults): string {
    return cb_url_query('/', array_merge($mzdyBaseParams, $extra), $mzdyQueryDefaults);
};
$mzdyResetUrl = cb_url_query('/', ['cb_load_max' => '1'], $mzdyQueryDefaults);

ob_start();
?>
<div class="displ_flex jc_stred">
  <table class="card_mini_table">
    <tbody>
      <tr>
        <td>Mzdy celkem</td>
        <td class="txt_r"><span class="text_tucny"><?= h(cb_k17_int($mzdyStats['total'])) ?></span></td>
      </tr>
      <tr>
        <td>Bez ID</td>
        <td class="txt_r"><span class="text_tucny"><?= h(cb_k17_int($mzdyStats['without_id'])) ?></span></td>
      </tr>
    </tbody>
  </table>
</div>
<?php
$card_min_html = (string)ob_get_clean();

if (($cbDashboardRenderMode ?? '') === 'mini') {
    return;
}

ob_start();
?>
<?php $mzdyDebounceJs = "clearTimeout(this._cbDebounce);this._cbDebounce=setTimeout(function(field){field.form.mzdy_p.value=1;if(field.form.requestSubmit){field.form.requestSubmit();}else{field.form.submit();}},350,this);"; ?>
<?php if ($mzdyError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($mzdyError) ?></p>
<?php else: ?>
  <form method="get" action="<?= h($formAction) ?>" class="card_stack gap_10 displ_flex" autocomplete="off" data-cb-max-form="1">
    <input type="hidden" name="cb_load_max" value="1">
    <input type="hidden" name="mzdy_p" value="1">
    <?php if ((int)$tabKonfig['enable_sort'] === 1): ?>
      <input type="hidden" name="mzdy_sort" value="<?= h($mzdySort) ?>">
      <input type="hidden" name="mzdy_dir" value="<?= h($mzdyDir) ?>">
    <?php endif; ?>

    <div class="table-wrap ram_normal bg_bila zaobleni_12">
      <table class="card-max-table">
        <thead>
          <tr class="card-max-filter filter-row">
            <?php foreach ($mzdyCols as $key => $cfg): ?>
              <?php if (!empty($cfg['filter'])): ?>
                <th style="width:<?= h((string)$cfg['width']) ?>;">
                  <input class="filter-input" type="text" name="mzdy_f[<?= h($key) ?>]" value="<?= h($mzdyFilters[$key] ?? '') ?>" autocomplete="off" oninput="<?= h($mzdyDebounceJs) ?>">
                </th>
              <?php elseif ($key === 'hodiny'): ?>
                <th style="width:<?= h((string)$cfg['width']) ?>;">
                  <div class="filter-actions gap_8 displ_flex">
                    <a href="<?= h($mzdyResetUrl) ?>" class="filter-reset-btn cursor_ruka ram_normal zaobleni_8 vyska_24 radek_24 displ_inline_flex">
                      <span class="filter-reset-x">&times;</span>
                      <span>Zrušit filtr</span>
                    </a>
                  </div>
                </th>
              <?php elseif ($key === 'mesicni_fix'): ?>
                <th colspan="2" style="width:240px;">
                  <select name="mzdy_slot" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 sirka100" onchange="this.form.mzdy_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                    <option value="all"<?= $mzdySlot === 'all' ? ' selected' : '' ?>>Vše</option>
                    <option value="instor"<?= $mzdySlot === 'instor' ? ' selected' : '' ?>>Instor</option>
                    <option value="kuryr"<?= $mzdySlot === 'kuryr' ? ' selected' : '' ?>>Kurýr</option>
                  </select>
                </th>
              <?php elseif ($key === 'hruba_mzda'): ?>
                <th colspan="2" style="width:260px;">
                  <select name="mzdy_hours" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 sirka100" onchange="this.form.mzdy_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                    <option value="all"<?= $mzdyHours === 'all' ? ' selected' : '' ?>>Vše</option>
                    <option value="with_hours"<?= $mzdyHours === 'with_hours' ? ' selected' : '' ?>>Pouze s hodinami</option>
                  </select>
                </th>
              <?php elseif (in_array($key, ['cista_mzda', 'superhruba_mzda'], true)): ?>
                <?php continue; ?>
              <?php else: ?>
                <th style="width:<?= h((string)$cfg['width']) ?>;"></th>
              <?php endif; ?>
            <?php endforeach; ?>
          </tr>
          <tr>
            <?php foreach ($mzdyCols as $key => $cfg): ?>
              <?php
              $isSortable = isset($mzdySortMap[$key]);
              $isActiveSort = ($mzdySort === $key);
              $arrow = '↕';
              if ($isActiveSort) {
                  $arrow = $mzdyDir === 'ASC' ? '↑' : '↓';
              }
              $rightCols = ['id_user', 'hodiny', 'hodinova_sazba', 'mesicni_fix', 'cista_mzda', 'hruba_mzda', 'superhruba_mzda'];
              ?>
              <th class="th-sort<?= $isActiveSort ? ' active' : '' ?><?= in_array($key, $rightCols, true) ? ' txt_r' : '' ?>" style="width:<?= h((string)$cfg['width']) ?>;">
                <?php if ((int)$tabKonfig['enable_sort'] === 1 && $isSortable): ?>
                  <?php
                  $nextDir = ($isActiveSort && $mzdyDir === 'ASC') ? 'DESC' : 'ASC';
                  $sortUrl = $mzdyBuildUrl([
                      'mzdy_p' => '1',
                      'mzdy_sort' => $key,
                      'mzdy_dir' => $nextDir,
                  ]);
                  ?>
                  <a class="th-sort-link gap_8 jc_mezi sirka100<?= $isActiveSort ? ' active' : '' ?>" href="<?= h($sortUrl) ?>">
                    <span class="th-sort-label"><?= h((string)$cfg['label']) ?></span>
                    <span class="th-sort-arrow txt_r"><?= h($arrow) ?></span>
                  </a>
                <?php else: ?>
                  <span class="th-sort-link gap_8 jc_mezi sirka100"><span class="th-sort-label"><?= h((string)$cfg['label']) ?></span></span>
                <?php endif; ?>
              </th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (!$mzdyRows): ?>
            <tr>
              <td colspan="<?= h((string)count($mzdyCols)) ?>" class="txt_c odstup_vnitrni_14 txt_cervena">Žádná data</td>
            </tr>
          <?php else: ?>
            <?php foreach ($mzdyRows as $row): ?>
              <?php $isUnpaired = (int)($row['id_user'] ?? 0) <= 0; ?>
              <tr<?= $isUnpaired ? ' class="k17-row-unpaired"' : '' ?>>
                <td><?= h(cb_k17_month_label((int)$row['rok'], (int)$row['mesic'])) ?></td>
                <td class="txt_r"><?= h((string)($row['id_user'] ?? '')) ?></td>
                <td><?= h((string)($row['import_jmeno'] ?? '')) ?></td>
                <td><?= h((string)($row['prijmeni'] ?? '')) ?></td>
                <td><?= h((string)($row['jmeno'] ?? '')) ?></td>
                <td><?= h((string)($row['mzda_typ'] ?? '')) ?></td>
                <td class="txt_r"><?= h(cb_k17_dec($row['hodiny'] ?? 0)) ?></td>
                <td class="txt_r"><?= h(cb_k17_money($row['hodinova_sazba'] ?? null)) ?></td>
                <td class="txt_r"><?= h(cb_k17_money($row['mesicni_fix'] ?? null)) ?></td>
                <td class="txt_r"><?= h(cb_k17_money($row['cista_mzda'] ?? null)) ?></td>
                <td class="txt_r"><?= h(cb_k17_money($row['hruba_mzda'] ?? null)) ?></td>
                <td class="txt_r"><?= h(cb_k17_money($row['superhruba_mzda'] ?? null)) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="6">Součet</td>
            <td class="txt_r"><?= h(cb_k17_dec($mzdyTotals['hodiny'])) ?></td>
            <td class="txt_r"></td>
            <td class="txt_r"></td>
            <td class="txt_r"><?= h(cb_k17_money($mzdyTotals['cista_mzda'])) ?></td>
            <td class="txt_r"><?= h(cb_k17_money($mzdyTotals['hruba_mzda'])) ?></td>
            <td class="txt_r"><?= h(cb_k17_money($mzdyTotals['superhruba_mzda'])) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if ((int)$tabKonfig['enable_pagination'] === 1): ?>
      <div class="card-max-pagination list-bottom gap_14 gap_10 odstup_vnitrni_0 displ_grid">
        <div class="per-form gap_8 displ_inline_flex">
          <span>Zobrazit</span>
          <select name="mzdy_per" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 per-select" onchange="this.form.mzdy_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
            <option value="20"<?= $mzdyPer === 20 ? ' selected' : '' ?>>20 řádků</option>
            <option value="50"<?= $mzdyPer === 50 ? ' selected' : '' ?>>50 řádků</option>
            <option value="100"<?= $mzdyPer === 100 ? ' selected' : '' ?>>100 řádků</option>
          </select>
          <span>celkem <?= h(cb_k17_int($mzdyTotal)) ?> řádků</span>
        </div>

        <div class="pagination-icon gap_4 displ_inline_flex">
          <?php $prevDisabled = $mzdyPage <= 1; ?>
          <?php $nextDisabled = $mzdyPage >= $mzdyPages; ?>
          <a class="icon-btn<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($mzdyBuildUrl(['mzdy_p' => '1'])) ?>">«</a>
          <a class="icon-btn<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($mzdyBuildUrl(['mzdy_p' => (string)max(1, $mzdyPage - 1)])) ?>">‹</a>
          <?php
          $pageItems = [];
          if ($mzdyPages <= 7) {
              for ($i = 1; $i <= $mzdyPages; $i++) {
                  $pageItems[] = $i;
              }
          } elseif ($mzdyPage <= 4) {
              $pageItems = [1, 2, 3, 4, 5, '…', $mzdyPages];
          } elseif ($mzdyPage >= $mzdyPages - 3) {
              $pageItems = [1, '…', $mzdyPages - 4, $mzdyPages - 3, $mzdyPages - 2, $mzdyPages - 1, $mzdyPages];
          } else {
              $pageItems = [1, '…', $mzdyPage - 1, $mzdyPage, $mzdyPage + 1, '…', $mzdyPages];
          }
          ?>
          <?php foreach ($pageItems as $item): ?>
            <?php if ($item === '…'): ?>
              <span class="icon-btn disabled">…</span>
            <?php elseif ((int)$item === $mzdyPage): ?>
              <span class="icon-btn page-current"><?= h((string)$item) ?></span>
            <?php else: ?>
              <a class="icon-btn" href="<?= h($mzdyBuildUrl(['mzdy_p' => (string)$item])) ?>"><?= h((string)$item) ?></a>
            <?php endif; ?>
          <?php endforeach; ?>
          <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($mzdyBuildUrl(['mzdy_p' => (string)min($mzdyPages, $mzdyPage + 1)])) ?>">›</a>
          <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($mzdyBuildUrl(['mzdy_p' => (string)$mzdyPages])) ?>">»</a>
        </div>

        <div class="per-form gap_8 right displ_inline_flex jc_konec">
          <select name="mzdy_mode" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 akt-select sirka_min_160" onchange="this.form.mzdy_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
            <option value="all"<?= $mzdyMode === 'all' ? ' selected' : '' ?>>Všichni</option>
            <option value="with_id"<?= $mzdyMode === 'with_id' ? ' selected' : '' ?>>Zaměstnanci s ID</option>
            <option value="without_id"<?= $mzdyMode === 'without_id' ? ' selected' : '' ?>>Zaměstnanci bez ID</option>
          </select>
        </div>
      </div>
    <?php endif; ?>
  </form>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/mzdy.php * Verze: V3 * Aktualizace: 22.05.2026 */
?>
