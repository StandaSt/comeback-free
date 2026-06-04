<?php
// K11
// karty/prehled_smen.php * Verze: V15 * Aktualizace: 04.06.2026
declare(strict_types=1);

/*
 * Karta "Přehled směn":
 * - měsíční souhrn pro výplaty,
 * - vždy zobrazuje poslední kompletní měsíc,
 * - bere všechny pobočky i výrobu,
 * - čte z reporty + reporty_osoby.
 */

require_once __DIR__ . '/../lib/prehled_smen_data.php';

if (!function_exists('ps_num_or_dash')) {
    function ps_num_or_dash(float $value): string
    {
        return abs($value) < 0.005 ? '-' : ps_num($value);
    }
}

if (!function_exists('ps_num_with_info')) {
    function ps_num_with_info(float $value, string $title): string
    {
        $text = h(ps_num_or_dash($value));
        if (abs($value) < 0.005 || $title === '') {
            return $text;
        }

        return $text
            . ' <span title="' . h($title) . '" style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#dcecff;color:#005bd3;border:1px solid #7fb0ff;font-size:10px;font-weight:700;line-height:14px;cursor:help;">i</span>';
    }
}

$psData = ps_prehled_smen_data($_GET);
$tabKonfig = (array)$psData['tabKonfig'];
$psCols = (array)$psData['cols'];
$psFilters = (array)$psData['filters'];
$psSelectedMonth = (int)$psData['selectedMonth'];
$psSelectedYear = (int)$psData['selectedYear'];
$psSort = (string)$psData['sort'];
$psDir = (string)$psData['dir'];
$psPer = (int)$psData['per'];
$perOptions = (array)$psData['perOptions'];
$psPage = (int)$psData['page'];
$psFilteredRows = (array)$psData['filteredRows'];
$psFilteredHours = (float)$psData['filteredHours'];
$psTotal = (int)$psData['totalRows'];
$psError = (string)$psData['error'];
$psMonthLabel = (string)$psData['monthLabel'];
$psPages = max(1, (int)ceil($psTotal / $psPer));
if ($psPage > $psPages) {
    $psPage = $psPages;
}
$offset = ($psPage - 1) * $psPer;
$psDisplayRows = array_slice($psFilteredRows, $offset, $psPer);
$formAction = cb_url('/');
$psColWidths = ['6%', '8%', '22%', '10%', '9%', '9%', '9%', '9%', '10%', '8%'];
$psDetailBg = '#d9ecff';
$psDetailEndBg = '#ffdada';

$card_min_html = ''
    . '<p class="card_text txt_seda odstup_vnejsi_0">Měsíc: <strong>' . h($psMonthLabel) . '</strong></p>'
    . '<p class="card_text txt_seda odstup_vnejsi_0">Osob/slotů: <strong>' . h((string)$psTotal) . '</strong></p>'
    . '<p class="card_text txt_seda odstup_vnejsi_0">Hodin celkem: <strong>' . h(ps_num($psFilteredHours)) . '</strong></p>';

$psQueryDefaults = [
    'ps_p' => '1',
    'ps_per' => (string)$tabKonfig['default_per'],
    'ps_sort' => (string)$tabKonfig['default_sort'],
    'ps_dir' => (string)$tabKonfig['default_dir'],
];

$psBaseParams = [
    'cb_load_max' => '1',
    'ps_per' => (string)$psPer,
];
if ((int)$tabKonfig['enable_sort'] === 1) {
    $psBaseParams['ps_sort'] = $psSort;
    $psBaseParams['ps_dir'] = $psDir;
}
if ((int)$tabKonfig['enable_filters'] === 1) {
    $activeFilters = array_filter($psFilters, static fn (string $value): bool => $value !== '');
    if ($activeFilters !== []) {
        $psBaseParams['ps_f'] = $activeFilters;
    }
}

$psBuildUrl = static function (array $extra = []) use ($psBaseParams, $psQueryDefaults): string {
    return cb_url_query('/', array_merge($psBaseParams, $extra), $psQueryDefaults);
};
$psResetUrl = cb_url_query('/', ['cb_load_max' => '1'], $psQueryDefaults);
$psExportParams = [];
if ((int)$tabKonfig['enable_sort'] === 1) {
    $psExportParams['ps_sort'] = $psSort;
    $psExportParams['ps_dir'] = $psDir;
}
if ((int)$tabKonfig['enable_filters'] === 1) {
    $activeFilters = array_filter($psFilters, static fn (string $value): bool => $value !== '');
    if ($activeFilters !== []) {
        $psExportParams['ps_f'] = $activeFilters;
    }
}
$psExportPdfUrl = cb_url_query('/lib/export_prehled_smen_pdf.php', $psExportParams, $psQueryDefaults);
$psExportTxtUrl = cb_url_query('/lib/export_prehled_smen_txt.php', $psExportParams, $psQueryDefaults);
$psExportXlsxUrl = cb_url_query('/lib/export_prehled_smen_xlsx.php', $psExportParams, $psQueryDefaults);

ob_start();
?>
<?php if ($psError !== ''): ?>
  <p class="card_text txt_seda odstup_vnejsi_0 card_text_muted"><?= h($psError) ?></p>
<?php else: ?>
  <form method="get" action="<?= h($formAction) ?>" class="card_stack gap_10 displ_flex" autocomplete="off" data-cb-max-form="1">
    <input type="hidden" name="cb_load_max" value="1">
    <input type="hidden" name="ps_p" value="1">
    <?php if ((int)$tabKonfig['enable_sort'] === 1): ?>
      <input type="hidden" name="ps_sort" value="<?= h($psSort) ?>">
      <input type="hidden" name="ps_dir" value="<?= h($psDir) ?>">
    <?php endif; ?>
    <div class="card-max-summary displ_flex jc_mezi ai_stred gap_8" style="margin-bottom:6px; font-size:14px; line-height:24px;">
      <div style="line-height:24px;">
        Přehled za <strong><?= h($psMonthLabel) ?></strong>, celkem: <strong><?= h(ps_num($psFilteredHours)) ?></strong> hodin
      </div>
      <div class="displ_flex ai_stred gap_8">
        <button type="button" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_24 card_btn_primary displ_inline_flex" data-ps-export-open="psExportModal">Export</button>
      </div>
    </div>

    <div class="table-wrap ram_normal bg_bila zaobleni_12">
      <table class="card-max-table is-compact">
        <colgroup>
          <?php foreach ($psColWidths as $psColWidth): ?>
            <col style="width:<?= h($psColWidth) ?>;">
          <?php endforeach; ?>
        </colgroup>
        <thead>
          <tr class="card-max-filter filter-row">
            <th class="txt_r" style="white-space:nowrap;">
              <select class="filter-input txt_r" name="ps_f[mesic]">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                  <option value="<?= h((string)$m) ?>"<?= $psSelectedMonth === $m ? ' selected' : '' ?>><?= h((string)$m) ?></option>
                <?php endfor; ?>
              </select>
            </th>
            <th class="txt_r" style="white-space:nowrap;">
              <input class="filter-input txt_r" style="width:8ch;" type="text" name="ps_f[rok]" value="<?= h((string)$psSelectedYear) ?>" autocomplete="off">
            </th>
            <th class="txt_r" style="white-space:nowrap;">
              <input class="filter-input txt_r" type="text" name="ps_f[cele_jmeno]" value="<?= h($psFilters['cele_jmeno']) ?>" autocomplete="off">
            </th>
            <th class="txt_r" style="white-space:nowrap;">
              <select class="filter-input txt_r" name="ps_f[slot]">
                <option value=""<?= $psFilters['slot'] === '' ? ' selected' : '' ?>>slot</option>
                <option value="1"<?= $psFilters['slot'] === '1' ? ' selected' : '' ?>>instor</option>
                <option value="2"<?= $psFilters['slot'] === '2' ? ' selected' : '' ?>>kurýr</option>
                <option value="3"<?= $psFilters['slot'] === '3' ? ' selected' : '' ?>>výroba</option>
              </select>
            </th>
            <th class="txt_r" style="white-space:nowrap;"></th>
            <th class="txt_r" style="white-space:nowrap;"></th>
            <th class="txt_r" style="white-space:nowrap;"></th>
            <th class="txt_r" style="white-space:nowrap;"></th>
            <th class="txt_r" style="white-space:nowrap;">
              <div class="filter-actions gap_8 displ_flex jc_konec">
                <a href="<?= h($psResetUrl) ?>" class="filter-reset-btn cursor_ruka ram_normal zaobleni_8 vyska_24 radek_24 displ_inline_flex">
                  <span class="filter-reset-x">&times;</span>
                  <span>Zrušit filtr</span>
                </a>
              </div>
            </th>
            <th class="txt_r" style="white-space:nowrap;"></th>
          </tr>
          <tr>
            <?php foreach ($psCols as $key => $cfg): ?>
              <?php
              $isActiveSort = ($psSort === $key);
              $arrow = '↕';
              if ($isActiveSort) {
                  $arrow = $psDir === 'ASC' ? '↑' : '↓';
              }
              $nextDir = ($isActiveSort && $psDir === 'ASC') ? 'DESC' : 'ASC';
              $sortUrl = $psBuildUrl([
                  'ps_p' => '1',
                  'ps_sort' => $key,
                  'ps_dir' => $nextDir,
              ]);
              ?>
              <th class="th-sort txt_r<?= $isActiveSort ? ' active' : '' ?>" style="white-space:nowrap;">
                <a class="th-sort-link gap_8 sirka100<?= $isActiveSort ? ' active' : '' ?>" href="<?= h($sortUrl) ?>" style="justify-content:flex-end; text-align:right; white-space:nowrap;">
                  <span class="th-sort-label"><?= h((string)$cfg['label']) ?></span>
                  <span class="th-sort-arrow txt_r"><?= h($arrow) ?></span>
                </a>
              </th>
            <?php endforeach; ?>
            <th class="txt_r" style="white-space:nowrap;">detail</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($psDisplayRows === []): ?>
            <tr><td colspan="10">Žádná data</td></tr>
          <?php else: ?>
            <?php foreach ($psDisplayRows as $row): ?>
              <?php
              $detailId = 'ps-detail-' . h((string)($row['detail_key'] ?? ''));
              $svatekTitle = '';
              if ((float)$row['svatek'] > 0.0 && isset($row['svatek_detail']) && is_array($row['svatek_detail'])) {
                  $svatekLines = [];
                  foreach ($row['svatek_detail'] as $detail) {
                      $detailDate = DateTimeImmutable::createFromFormat('Y-m-d', (string)($detail['date'] ?? ''));
                      $detailDateText = $detailDate instanceof DateTimeImmutable ? $detailDate->format('j.n.Y') : (string)($detail['date'] ?? '');
                      $detailName = trim((string)($detail['name'] ?? ''));
                      $detailHours = ps_num((float)($detail['hours'] ?? 0.0));
                      if ($detailDateText !== '' && $detailName !== '') {
                          $svatekLines[] = $detailDateText . ' ' . $detailName . ': ' . $detailHours . ' h';
                      }
                  }
                  $svatekTitle = implode("\n", $svatekLines);
              }
              ?>
              <tr>
                <td class="txt_r" style="white-space:nowrap;"><?= h((string)$row['mesic']) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h((string)$row['rok']) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h((string)$row['cele_jmeno']) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_slot_label((int)$row['slot'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_num_or_dash((float)$row['celkem'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_num_or_dash((float)$row['den'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_num_or_dash((float)$row['noc'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_num_or_dash((float)$row['vikend'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= ps_num_with_info((float)$row['svatek'], $svatekTitle) ?></td>
                <td class="txt_r" style="white-space:nowrap;">
                  <button type="button" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_24 card_btn_primary displ_inline_flex" data-row-detail-toggle="<?= $detailId ?>" aria-expanded="false">
                    detail
                  </button>
                </td>
              </tr>
              <tr data-row-detail="<?= $detailId ?>" hidden>
                <td colspan="10" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;font-size:15px;text-align:left;padding:10px 12px 8px 12px;font-weight:700;">Detail - odpracované hodiny <?= h((string)$row['cele_jmeno']) ?></td>
              </tr>
              <tr data-row-detail="<?= $detailId ?>" hidden>
                <th class="txt_r" colspan="2" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;">datum</th>
                <th class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;">pobočka</th>
                <th class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;">slot</th>
                <th class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;">odpracováno</th>
                <th class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;">6-22</th>
                <th class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;">22-6</th>
                <th class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;">So+Ne</th>
                <th class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;">svátek</th>
                <th class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"></th>
              </tr>
              <?php $detailRows = isset($row['detail_rows']) && is_array($row['detail_rows']) ? $row['detail_rows'] : []; ?>
              <?php if ($detailRows === []): ?>
                <tr data-row-detail="<?= $detailId ?>" hidden><td colspan="10" style="background:<?= h($psDetailBg) ?>;">Žádná data</td></tr>
              <?php else: ?>
                <?php foreach ($detailRows as $detailRow): ?>
                  <?php
                  $detailDate = DateTimeImmutable::createFromFormat('Y-m-d', (string)($detailRow['datum'] ?? ''));
                  $detailDateText = $detailDate instanceof DateTimeImmutable ? $detailDate->format('j.n.Y') : (string)($detailRow['datum'] ?? '');
                  $branchName = trim((string)($detailRow['pobocka'] ?? ''));
                  if ($branchName === '' && (int)($detailRow['id_pob'] ?? 0) > 0) {
                      $branchName = 'ID ' . (string)(int)$detailRow['id_pob'];
                  }
                  $detailSvatekTitle = '';
                  if ((float)($detailRow['svatek'] ?? 0.0) > 0.0 && isset($detailRow['svatek_detail']) && is_array($detailRow['svatek_detail'])) {
                      $detailSvatekLines = [];
                      foreach ($detailRow['svatek_detail'] as $svatekDetail) {
                          $svatekDate = DateTimeImmutable::createFromFormat('Y-m-d', (string)($svatekDetail['date'] ?? ''));
                          $svatekDateText = $svatekDate instanceof DateTimeImmutable ? $svatekDate->format('j.n.Y') : (string)($svatekDetail['date'] ?? '');
                          $svatekName = trim((string)($svatekDetail['name'] ?? ''));
                          $svatekHours = ps_num((float)($svatekDetail['hours'] ?? 0.0));
                          if ($svatekDateText !== '' && $svatekName !== '') {
                              $detailSvatekLines[] = $svatekDateText . ' ' . $svatekName . ': ' . $svatekHours . ' h';
                          }
                      }
                      $detailSvatekTitle = implode("\n", $detailSvatekLines);
                  }
                  ?>
                  <tr data-row-detail="<?= $detailId ?>" hidden>
                    <td class="txt_r" colspan="2" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"><?= h($detailDateText) ?></td>
                    <td class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"><?= h($branchName !== '' ? $branchName : '-') ?></td>
                    <td class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"><?= h(ps_slot_label((int)($detailRow['slot'] ?? 0))) ?></td>
                    <td class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"><?= h(ps_num_or_dash((float)($detailRow['celkem'] ?? 0.0))) ?></td>
                    <td class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"><?= h(ps_num_or_dash((float)($detailRow['den'] ?? 0.0))) ?></td>
                    <td class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"><?= h(ps_num_or_dash((float)($detailRow['noc'] ?? 0.0))) ?></td>
                    <td class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"><?= h(ps_num_or_dash((float)($detailRow['vikend'] ?? 0.0))) ?></td>
                    <td class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"><?= ps_num_with_info((float)($detailRow['svatek'] ?? 0.0), $detailSvatekTitle) ?></td>
                    <td class="txt_r" style="white-space:nowrap;background:<?= h($psDetailBg) ?>;"></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              <tr data-row-detail="<?= $detailId ?>" hidden>
                <td colspan="10" style="height:14px;padding:0;background:<?= h($psDetailEndBg) ?>;"></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card-max-pagination list-bottom gap_14 gap_10 odstup_vnitrni_0 displ_grid">
      <div class="per-form gap_8 displ_inline_flex">
        <span>Zobrazuji</span>
        <select name="ps_per" class="filter-input ram_sedy txt_seda bg_bila zaobleni_8 vyska_24 per-select" onchange="this.form.ps_p.value=1; if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
          <?php foreach ($perOptions as $optPer): ?>
            <option value="<?= h((string)$optPer) ?>"<?= $psPer === $optPer ? ' selected' : '' ?>><?= h((string)$optPer) ?> řádků</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="pagination-icon gap_4 displ_inline_flex">
        <?php $prevDisabled = $psPage <= 1; ?>
        <?php $nextDisabled = $psPage >= $psPages; ?>

        <a class="icon-btn<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($psBuildUrl(['ps_p' => '1'])) ?>">«</a>
        <a class="icon-btn<?= $prevDisabled ? ' disabled' : '' ?>" href="<?= $prevDisabled ? '#' : h($psBuildUrl(['ps_p' => (string)max(1, $psPage - 1)])) ?>">‹</a>

        <?php
        $pageItems = [];
        if ($psPages <= 7) {
            for ($i = 1; $i <= $psPages; $i++) {
                $pageItems[] = $i;
            }
        } elseif ($psPage <= 4) {
            $pageItems = [1, 2, 3, 4, 5, '…', $psPages];
        } elseif ($psPage >= $psPages - 3) {
            $pageItems = [1, '…', $psPages - 4, $psPages - 3, $psPages - 2, $psPages - 1, $psPages];
        } else {
            $pageItems = [1, '…', $psPage - 1, $psPage, $psPage + 1, '…', $psPages];
        }
        ?>

        <?php foreach ($pageItems as $item): ?>
          <?php if ($item === '…'): ?>
            <span class="icon-btn disabled">…</span>
          <?php elseif ((int)$item === $psPage): ?>
            <span class="icon-btn page-current"><?= h((string)$item) ?></span>
          <?php else: ?>
            <a class="icon-btn" href="<?= h($psBuildUrl(['ps_p' => (string)$item])) ?>"><?= h((string)$item) ?></a>
          <?php endif; ?>
        <?php endforeach; ?>

        <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($psBuildUrl(['ps_p' => (string)min($psPages, $psPage + 1)])) ?>">›</a>
        <a class="icon-btn<?= $nextDisabled ? ' disabled' : '' ?>" href="<?= $nextDisabled ? '#' : h($psBuildUrl(['ps_p' => (string)$psPages])) ?>">»</a>
      </div>

      <div class="per-form gap_8 right displ_inline_flex jc_konec">
        <span>Celkem: <strong><?= h((string)$psTotal) ?></strong></span>
      </div>
    </div>
  </form>
  <?php require __DIR__ . '/../modaly/modal_prehled_smen_export.php'; ?>
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/prehled_smen.php * Verze: V15 * Aktualizace: 04.06.2026 */
?>
