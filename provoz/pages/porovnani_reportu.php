<?php
declare(strict_types=1);

/* Porovnání počítá rozdíly ze syrových hodnot; cb_format() upravuje až hodnoty pro tabulku. */
require_once __DIR__ . '/../lib/format_datum_cas.php';
require_once __DIR__ . '/../lib/denni_report_data.php';
require_once __DIR__ . '/../lib/archiv_reportu_data.php';

$comparisonArchiveData = cb_archiv_reportu_data(db(), $_GET);
$comparisonBranches = (array)($comparisonArchiveData['branches'] ?? []);
$comparisonFilters = (array)($comparisonArchiveData['filters'] ?? []);
$comparisonBranchId = (int)($_GET['compare_branch'] ?? 0);
$comparisonDate = trim((string)($_GET['compare_date'] ?? ''));
$comparisonRows = ['entry' => [], 'calculation' => []];
if (isset($comparisonBranches[$comparisonBranchId]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $comparisonDate) === 1) {
    $comparisonRows = cb_archiv_reportu_comparison_rows(db(), $comparisonBranchId, $comparisonDate);
}
$comparisonBackParams = [
    'm' => 'provoz', 'page' => 'archiv_reportu',
    'ar_month' => (string)(int)($comparisonFilters['month'] ?? 0),
    'ar_year' => (string)(int)($comparisonFilters['year'] ?? 0),
    'ar_branch' => (string)(int)($comparisonFilters['branch'] ?? 0),
    'ar_status' => (string)($comparisonFilters['status'] ?? 'all'),
    'ar_sort' => (string)($comparisonFilters['sort'] ?? 'date'),
    'ar_dir' => (string)($comparisonFilters['dir'] ?? 'desc'),
    'ar_p' => (string)(int)($comparisonFilters['page'] ?? 1),
];
$comparisonBackUrl = cb_root_url('index.php') . '?' . http_build_query($comparisonBackParams, '', '&', PHP_QUERY_RFC3986);
$comparisonFormat = static function ($value, string $format): string {
    if ($value === null || $value === '') { return '—'; }
    if ($format === 'text') { return (string)$value; }
    $formatTypes = ['money' => 'p', 'percent' => 'pr', 'minutes' => 'm', 'hours' => 'h', 'integer' => 'i', 'number' => 'n'];
    return cb_format($formatTypes[$format] ?? 'n', $value);
};
$comparisonDifference = static function ($isValue, $googleValue, string $format) use ($comparisonFormat): string {
    if (!is_numeric($isValue) || !is_numeric($googleValue)) { return ''; }
    return $comparisonFormat((float)$isValue - (float)$googleValue, $format);
};
?>
<section class="provoz_report_comparison">
  <?php foreach (['entry' => 'Rozdíly v zadání', 'calculation' => 'Rozdíly ve výpočtech'] as $comparisonGroup => $comparisonTitle): ?>
    <?php $comparisonGroupRows = (array)($comparisonRows[$comparisonGroup] ?? []); ?>
    <section class="card_section bg_bila zaobleni_10 odstup_vnitrni_10 provoz_report_comparison_group" id="rozdily-<?= $comparisonGroup === 'entry' ? 'zadani' : 'vypocet' ?>">
      <h2 class="provoz_report_comparison_title"><?= h($comparisonTitle) ?></h2>
      <?php if ($comparisonGroupRows === []): ?>
        <p class="odstup_vnejsi_0">Bez rozdílů.</p>
      <?php else: ?>
        <table class="provoz_objednavky_table">
          <thead><tr><th>Položka</th><th class="txt_r">Hodnota IS</th><th class="txt_r">Hodnota Google report</th><th class="txt_r">Rozdíl</th></tr></thead>
          <tbody>
            <?php foreach ($comparisonGroupRows as $comparisonRow): ?>
              <?php $comparisonFormatType = (string)($comparisonRow['format'] ?? 'number'); ?>
              <tr><td><?= h((string)$comparisonRow['item']) ?></td><td class="txt_r"><?= h($comparisonFormat($comparisonRow['is'] ?? null, $comparisonFormatType)) ?></td><td class="txt_r"><?= h($comparisonFormat($comparisonRow['google'] ?? null, $comparisonFormatType)) ?></td><td class="txt_r"><?= h($comparisonDifference($comparisonRow['is'] ?? null, $comparisonRow['google'] ?? null, $comparisonFormatType)) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</section>
