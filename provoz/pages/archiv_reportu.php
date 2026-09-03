<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../lib/denni_report_data.php';
require_once __DIR__ . '/../lib/archiv_reportu_data.php';

$archivData = cb_archiv_reportu_data(db(), $_GET);
$archivFilters = (array)($archivData['filters'] ?? []);
$archivBranches = (array)($archivData['branches'] ?? []);
$archivYears = (array)($archivData['years'] ?? []);
$archivRows = (array)($archivData['rows'] ?? []);
$archivTotal = (int)($archivData['total'] ?? 0);
$archivError = trim((string)($archivData['error'] ?? ''));
$archivPerPage = 50;
$archivPage = max(1, (int)($archivFilters['page'] ?? 1));
$archivPages = max(1, (int)ceil($archivTotal / $archivPerPage));
$archivPage = min($archivPage, $archivPages);
$archivDisplayRows = array_slice($archivRows, ($archivPage - 1) * $archivPerPage, $archivPerPage);
foreach ($archivDisplayRows as &$archivDisplayRow) {
    $archivDisplayRow['has_entry_difference'] = false;
    $archivDisplayRow['has_calculation_difference'] = false;
    if (!empty($archivDisplayRow['saved']) && !empty($archivDisplayRow['google_report_available'])) {
        $archivComparison = cb_archiv_reportu_comparison_rows(db(), (int)$archivDisplayRow['branch_id'], (string)$archivDisplayRow['date']);
        $archivDisplayRow['has_entry_difference'] = !empty($archivComparison['entry']);
        $archivDisplayRow['has_calculation_difference'] = !empty($archivComparison['calculation']);
    }
}
unset($archivDisplayRow);
$archivBaseParams = [
    'm' => 'provoz',
    'page' => 'archiv_reportu',
    'ar_month' => (string)(int)($archivFilters['month'] ?? 0),
    'ar_year' => (string)(int)($archivFilters['year'] ?? 0),
    'ar_branch' => (string)(int)($archivFilters['branch'] ?? 0),
    'ar_status' => (string)($archivFilters['status'] ?? 'all'),
    'ar_sort' => (string)($archivFilters['sort'] ?? 'date'),
    'ar_dir' => (string)($archivFilters['dir'] ?? 'desc'),
    'ar_p' => (string)$archivPage,
];
$archivUrl = static function (array $extra = []) use ($archivBaseParams): string {
    return cb_root_url('index.php') . '?' . http_build_query(array_merge($archivBaseParams, $extra), '', '&', PHP_QUERY_RFC3986);
};
$archivResetUrl = cb_root_url('index.php') . '?m=provoz&page=archiv_reportu';
$archivSortLink = static function (string $key, string $label, string $tooltip = '') use ($archivFilters, $archivUrl): string {
    $active = (string)$archivFilters['sort'] === $key;
    $direction = $active && (string)$archivFilters['dir'] === 'asc' ? 'desc' : 'asc';
    $arrow = !$active ? '↕' : ((string)$archivFilters['dir'] === 'asc' ? '↑' : '↓');
    $tooltipAttribute = $tooltip !== '' ? ' title="' . h($tooltip) . '"' : '';
    return '<a class="provoz_objednavky_sort_link" href="' . h($archivUrl(['ar_sort' => $key, 'ar_dir' => $direction, 'ar_p' => '1'])) . '"' . $tooltipAttribute . '>' . h($label) . ' <span>' . h($arrow) . '</span></a>';
};
$archivHourLabel = static function (float $value): string {
    $decimals = abs($value - round($value)) < 0.001 ? 0 : 2;
    return number_format($value, $decimals, ',', ' ');
};
?>
<div class="provoz_archiv_reportu_page">
  <?php if ($archivError !== ''): ?>
    <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10"><p class="txt_cervena odstup_vnejsi_0"><?= h($archivError) ?></p></section>
  <?php else: ?>
    <section class="provoz_archiv_reportu_page">
      <form method="get" action="<?= h(cb_root_url('index.php')) ?>" autocomplete="off" data-cb-max-form="1">
        <input type="hidden" name="m" value="provoz">
        <input type="hidden" name="page" value="archiv_reportu">
        <input type="hidden" name="ar_sort" value="<?= h((string)$archivFilters['sort']) ?>">
        <input type="hidden" name="ar_dir" value="<?= h((string)$archivFilters['dir']) ?>">
        <div class="archiv_reportu_filters">
          <div class="archiv_reportu_filter_period">
            <select class="provoz_objednavky_filter archiv_reportu_filter--month" name="ar_month" aria-label="Měsíc" onchange="this.form.submit()"><?php for ($month = ((int)$archivFilters['year'] === (int)date('Y') ? (int)date('n') : 12); $month >= 1; $month--): ?><option value="<?= h((string)$month) ?>"<?= (int)$archivFilters['month'] === $month ? ' selected' : '' ?>><?= h((string)$month) ?></option><?php endfor; ?></select>
            <select class="provoz_objednavky_filter archiv_reportu_filter--year" name="ar_year" aria-label="Rok" onchange="this.form.submit()"><?php foreach ($archivYears as $year): ?><option value="<?= h((string)$year) ?>"<?= (int)$archivFilters['year'] === (int)$year ? ' selected' : '' ?>><?= h((string)$year) ?></option><?php endforeach; ?></select>
          </div>
          <select class="provoz_objednavky_filter" name="ar_branch" aria-label="Pobočka" onchange="this.form.submit()"><option value="0">Vše</option><?php foreach ($archivBranches as $branch): ?><option value="<?= h((string)$branch['id']) ?>"<?= (int)$archivFilters['branch'] === (int)$branch['id'] ? ' selected' : '' ?>><?= h((string)$branch['name']) ?></option><?php endforeach; ?></select>
          <select class="provoz_objednavky_filter" name="ar_status" aria-label="Stav" onchange="this.form.submit()"><option value="all">Vše</option><option value="saved"<?= $archivFilters['status'] === 'saved' ? ' selected' : '' ?>>Zadané</option><option value="missing"<?= $archivFilters['status'] === 'missing' ? ' selected' : '' ?>>Chybějící</option></select>
        </div>
        <div class="provoz_objednavky_table_wrap">
        <table class="provoz_objednavky_table">
          <thead>
            <tr><th><?= $archivSortLink('date', 'Datum') ?></th><th><?= $archivSortLink('branch', 'Pobočka') ?></th><th><?= $archivSortLink('status', 'Stav') ?></th><th class="provoz_objednavky_num"><?= $archivSortLink('revenue', 'Tržba') ?></th><th class="provoz_objednavky_num"><?= $archivSortLink('col', 'COL', 'COL = (mzdové náklady + PHM + náklady Wolt Drive) / tržba bez DPH.') ?></th><th class="provoz_objednavky_num"><?= $archivSortLink('difference', 'Pokladna') ?></th><th class="provoz_objednavky_num"><?= $archivSortLink('hours', 'Hodiny') ?></th><th><?= $archivSortLink('opening', 'Otevíral') ?></th><th><?= $archivSortLink('closing', 'Zavíral') ?></th><th colspan="2">Rozdíly</th><th>Akce</th></tr>
          </thead>
          <tbody>
            <?php if ($archivDisplayRows === []): ?>
              <tr><td colspan="12">Pro zvolené filtry nejsou žádné reporty.</td></tr>
            <?php else: ?>
              <?php foreach ($archivDisplayRows as $row): ?>
                <?php
                $reportParams = array_merge($archivBaseParams, ['m' => 'provoz', 'page' => 'denni_report', 'zr_id_pob' => (string)$row['branch_id'], 'datum_reportu' => (string)$row['date'], 'zr_archive' => '1']);
                if (empty($row['saved']) && !empty($row['can_complete'])) {
                    $reportParams['zr_archive_edit'] = '1';
                }
                $reportUrl = cb_root_url('index.php') . '?' . http_build_query($reportParams, '', '&', PHP_QUERY_RFC3986);
                $googleReportUrl = cb_root_url('index.php') . '?' . http_build_query(array_merge($archivBaseParams, [
                    'm' => 'provoz',
                    'page' => 'denni_report',
                    'zr_id_pob' => (string)$row['branch_id'],
                    'datum_reportu' => (string)$row['date'],
                    'zr_archive' => '1',
                    'zr_google' => '1',
                ]), '', '&', PHP_QUERY_RFC3986);
                $comparisonUrl = cb_root_url('index.php') . '?' . http_build_query(array_merge($archivBaseParams, [
                    'm' => 'provoz',
                    'page' => 'porovnani_reportu',
                    'compare_branch' => (string)$row['branch_id'],
                    'compare_date' => (string)$row['date'],
                ]), '', '&', PHP_QUERY_RFC3986);
                ?>
                <tr<?= empty($row['saved']) ? ' class="archiv_reportu_row--missing"' : '' ?>>
                  <td><?= h((string)$row['date_label']) ?></td>
                  <td><?= h((string)$row['branch_name']) ?></td>
                  <td class="<?= !empty($row['saved']) ? 'archiv_reportu_status--saved' : '' ?>"><?= !empty($row['saved']) ? 'Zadaný' : 'Chybí' ?></td>
                  <td class="txt_r"><?= !empty($row['saved']) ? h(number_format((float)$row['revenue'], 0, ',', ' ') . ' Kč') : '—' ?></td>
                  <td class="txt_r"><?= !empty($row['saved']) && $row['col'] !== null ? h(cb_denni_report_format_percent((float)$row['col'])) : '—' ?></td>
                  <td class="txt_r"><?= !empty($row['saved']) && $row['difference'] !== null ? h(cb_denni_report_format_money_whole((float)$row['difference'])) : '—' ?></td>
                  <td class="txt_r"><?= !empty($row['saved']) ? h($archivHourLabel((float)$row['hours_total']) . ' (' . $archivHourLabel((float)$row['hours_instor']) . ' / ' . $archivHourLabel((float)$row['hours_kuryr']) . ')') : '—' ?></td>
                  <td><?= !empty($row['saved']) ? h((string)$row['opening']) : '—' ?></td>
                  <td><?= !empty($row['saved']) ? h((string)$row['closing']) : '—' ?></td>
                  <td>
                    <?php if (!empty($row['saved'])): ?>
                      <?php if (!empty($row['has_calculation_difference'])): ?><a class="archiv_reportu_action_badge" href="<?= h($comparisonUrl) ?>#rozdily-vypocet">Výpočet</a><?php endif; ?>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($row['saved']) && !empty($row['has_entry_difference'])): ?><a class="archiv_reportu_action_badge archiv_reportu_action_badge--entry" href="<?= h($comparisonUrl) ?>#rozdily-zadani">Zadání</a><?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($row['saved'])): ?>
                      <a class="archiv_reportu_action_badge" href="<?= h($reportUrl) ?>">Detail</a>
                    <?php elseif (!empty($row['google_available'])): ?>
                      <a class="archiv_reportu_action_badge" href="<?= h($googleReportUrl) ?>">Zobrazit report z Google disku</a>
                    <?php endif; ?>
                    <?php if (empty($row['saved']) && !empty($row['can_complete'])): ?>
                      <a class="archiv_reportu_action_badge" href="<?= h($reportUrl) ?>">Doplnit</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table></div>
      </form>

      <?php if ($archivPages > 1): ?>
        <div class="provoz_objednavky_pager">
          <div>Zobrazuji <?= h((string)count($archivDisplayRows)) ?> z <?= h((string)$archivTotal) ?></div>
          <div class="provoz_objednavky_pager_links">
            <a class="provoz_objednavky_page_link<?= $archivPage <= 1 ? ' is-disabled' : '' ?>" href="<?= h($archivUrl(['ar_p' => '1'])) ?>">«</a><a class="provoz_objednavky_page_link<?= $archivPage <= 1 ? ' is-disabled' : '' ?>" href="<?= h($archivUrl(['ar_p' => (string)($archivPage - 1)])) ?>">‹</a><span class="provoz_objednavky_page_current"><?= h((string)$archivPage) ?> / <?= h((string)$archivPages) ?></span><a class="provoz_objednavky_page_link<?= $archivPage >= $archivPages ? ' is-disabled' : '' ?>" href="<?= h($archivUrl(['ar_p' => (string)($archivPage + 1)])) ?>">›</a><a class="provoz_objednavky_page_link<?= $archivPage >= $archivPages ? ' is-disabled' : '' ?>" href="<?= h($archivUrl(['ar_p' => (string)$archivPages])) ?>">»</a>
          </div>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</div>
