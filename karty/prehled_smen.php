<?php
// K11
// karty/prehled_smen.php * Verze: V9 * Aktualizace: 03.06.2026
declare(strict_types=1);

/*
 * Karta "Přehled směn":
 * - měsíční souhrn pro výplaty,
 * - vždy zobrazuje poslední kompletní měsíc,
 * - bere všechny pobočky i výrobu,
 * - čte z reporty + reporty_osoby.
 */

$psRows = [];
$psRowsAll = [];
$psDisplayRows = [];
$psError = '';
$psDefaultMonthStart = (new DateTimeImmutable('first day of this month'))->modify('-1 month')->setTime(0, 0);
$psDefaultMonth = (int)$psDefaultMonthStart->format('n');
$psDefaultYear = (int)$psDefaultMonthStart->format('Y');
$psYearStart = (new DateTimeImmutable($psDefaultYear . '-01-01'))->setTime(0, 0);
$psYearEnd = $psYearStart->modify('last day of december')->setTime(23, 59, 59);
$psTotalHours = 0.0;
$psTotal = 0;
$psPages = 1;
$psPage = 1;
$formAction = cb_url('/');

$tabKonfig = [
    'enable_filters' => 1,
    'enable_sort' => 1,
    'enable_pagination' => 1,
    'default_per' => 20,
    'per_options' => [20, 50, 100],
    'default_sort' => 'cele_jmeno',
    'default_dir' => 'ASC',
];

$psCols = [
    'mesic' => ['label' => 'měsíc'],
    'rok' => ['label' => 'rok'],
    'cele_jmeno' => ['label' => 'celé jméno', 'filter' => true],
    'slot' => ['label' => 'slot', 'filter' => true],
    'celkem' => ['label' => 'odpracováno'],
    'den' => ['label' => '6-22'],
    'noc' => ['label' => '22-6'],
    'vikend' => ['label' => 'So+Ne'],
    'svatek' => ['label' => 'svátek'],
];

$psSortMap = array_fill_keys(array_keys($psCols), true);
$psRequest = $_GET;
$psFilters = [
    'mesic' => (string)$psDefaultMonth,
    'rok' => (string)$psDefaultYear,
    'cele_jmeno' => '',
    'slot' => '',
];
$psSortRaw = trim((string)($psRequest['ps_sort'] ?? (string)$tabKonfig['default_sort']));
$psDirRaw = strtoupper(trim((string)($psRequest['ps_dir'] ?? (string)$tabKonfig['default_dir'])));
$psSort = array_key_exists($psSortRaw, $psSortMap) ? $psSortRaw : (string)$tabKonfig['default_sort'];
$psDir = in_array($psDirRaw, ['ASC', 'DESC'], true) ? $psDirRaw : (string)$tabKonfig['default_dir'];
$psPer = (int)$tabKonfig['default_per'];
$perOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn (int $v): bool => $v > 0));
if ($perOptions === []) {
    $perOptions = [20, 50, 100];
}
$psPerRaw = (int)($psRequest['ps_per'] ?? (int)$tabKonfig['default_per']);
if (in_array($psPerRaw, $perOptions, true)) {
    $psPer = $psPerRaw;
}
$psPageRaw = (int)($psRequest['ps_p'] ?? 1);
if ($psPageRaw > 1) {
    $psPage = $psPageRaw;
}
if (isset($psRequest['ps_f']) && is_array($psRequest['ps_f'])) {
    $monthRaw = trim((string)($psRequest['ps_f']['mesic'] ?? (string)$psDefaultMonth));
    $yearRaw = trim((string)($psRequest['ps_f']['rok'] ?? (string)$psDefaultYear));
    if (preg_match('/^(?:[1-9]|1[0-2])$/', $monthRaw) === 1) {
        $psFilters['mesic'] = $monthRaw;
    }
    if (preg_match('/^\d{4}$/', $yearRaw) === 1) {
        $psFilters['rok'] = $yearRaw;
    }
    $psFilters['cele_jmeno'] = trim((string)($psRequest['ps_f']['cele_jmeno'] ?? ''));
    $slotRaw = trim((string)($psRequest['ps_f']['slot'] ?? ''));
    if (in_array($slotRaw, ['', '1', '2', '3'], true)) {
        $psFilters['slot'] = $slotRaw;
    }
}
$psSelectedMonth = (int)$psFilters['mesic'];
$psSelectedYear = (int)$psFilters['rok'];

if (!function_exists('ps_slot_label')) {
    function ps_slot_label(int $slot): string
    {
        return match ($slot) {
            1 => 'instor',
            2 => 'kurýr',
            3 => 'výroba',
            default => '-',
        };
    }
}

if (!function_exists('ps_num')) {
    function ps_num(float $value): string
    {
        return number_format($value, 2, ',', ' ');
    }
}

if (!function_exists('ps_czech_month')) {
    function ps_czech_month(int $month): string
    {
        $months = [
            1 => 'leden',
            2 => 'únor',
            3 => 'březen',
            4 => 'duben',
            5 => 'květen',
            6 => 'červen',
            7 => 'červenec',
            8 => 'srpen',
            9 => 'září',
            10 => 'říjen',
            11 => 'listopad',
            12 => 'prosinec',
        ];

        return $months[$month] ?? (string)$month;
    }
}

if (!function_exists('ps_is_czech_holiday')) {
    function ps_is_czech_holiday(DateTimeImmutable $date): bool
    {
        return ps_czech_holiday_name($date) !== '';
    }
}

if (!function_exists('ps_czech_holiday_name')) {
    function ps_czech_holiday_name(DateTimeImmutable $date): string
    {
        $ymd = $date->format('m-d');
        $fixed = [
            '01-01' => 'Den obnovy samostatného českého státu / Nový rok',
            '05-01' => 'Svátek práce',
            '05-08' => 'Den vítězství',
            '07-05' => 'Den slovanských věrozvěstů Cyrila a Metoděje',
            '07-06' => 'Den upálení mistra Jana Husa',
            '09-28' => 'Den české státnosti',
            '10-28' => 'Den vzniku samostatného československého státu',
            '11-17' => 'Den boje za svobodu a demokracii',
            '12-24' => 'Štědrý den',
            '12-25' => '1. svátek vánoční',
            '12-26' => '2. svátek vánoční',
        ];
        if (isset($fixed[$ymd])) {
            return $fixed[$ymd];
        }

        $year = (int)$date->format('Y');
        $easter = (new DateTimeImmutable('@' . (string)easter_date($year)))->setTimezone(new DateTimeZone(date_default_timezone_get()));
        $goodFriday = $easter->modify('-2 days')->format('Y-m-d');
        $easterMonday = $easter->modify('+1 day')->format('Y-m-d');

        if ($date->format('Y-m-d') === $goodFriday) {
            return 'Velký pátek';
        }
        if ($date->format('Y-m-d') === $easterMonday) {
            return 'Velikonoční pondělí';
        }

        return '';
    }
}

if (!function_exists('ps_shift_parts')) {
    /**
     * @return array{day:float, night:float, weekend:float, holiday:float, holiday_details:array<string,array{date:string,name:string,hours:float}>}
     */
    function ps_shift_parts(string $date, string $from, string $to, float $workedHours): array
    {
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $from);
        $end = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' ' . $to);
        if (!$start instanceof DateTimeImmutable || !$end instanceof DateTimeImmutable) {
            return ['day' => 0.0, 'night' => 0.0, 'weekend' => 0.0, 'holiday' => 0.0, 'holiday_details' => []];
        }
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }

        $duration = max(0.0, ($end->getTimestamp() - $start->getTimestamp()) / 3600);
        if ($duration <= 0.0 || $workedHours <= 0.0) {
            return ['day' => 0.0, 'night' => 0.0, 'weekend' => 0.0, 'holiday' => 0.0, 'holiday_details' => []];
        }

        $ratio = min(1.0, $workedHours / $duration);
        $out = ['day' => 0.0, 'night' => 0.0, 'weekend' => 0.0, 'holiday' => 0.0, 'holiday_details' => []];
        $cursor = $start;

        while ($cursor < $end) {
            $next = $cursor->modify('+1 hour');
            if ($next > $end) {
                $next = $end;
            }

            $hours = (($next->getTimestamp() - $cursor->getTimestamp()) / 3600) * $ratio;
            $hour = (int)$cursor->format('G');
            if ($hour >= 6 && $hour < 22) {
                $out['day'] += $hours;
            } else {
                $out['night'] += $hours;
            }

            $dayNo = (int)$cursor->format('N');
            if ($dayNo >= 6) {
                $out['weekend'] += $hours;
            }
            $holidayName = ps_czech_holiday_name($cursor);
            if ($holidayName !== '') {
                $out['holiday'] += $hours;
                $holidayKey = $cursor->format('Y-m-d') . '|' . $holidayName;
                if (!isset($out['holiday_details'][$holidayKey])) {
                    $out['holiday_details'][$holidayKey] = [
                        'date' => $cursor->format('Y-m-d'),
                        'name' => $holidayName,
                        'hours' => 0.0,
                    ];
                }
                $out['holiday_details'][$holidayKey]['hours'] += $hours;
            }

            $cursor = $next;
        }

        return $out;
    }
}

try {
    $conn = db();
    $conn->set_charset('utf8mb4');

    $sql = '
        SELECT
            r.datum_reportu,
            ro.id_user,
            ro.jmeno,
            ro.prijmeni,
            ro.slot,
            ro.smena_od,
            ro.smena_do,
            ro.odpracovano
        FROM reporty_osoby ro
        INNER JOIN reporty r ON r.id_reportu = ro.id_reportu
        WHERE r.platny = 1
          AND r.stav = 1
          AND r.datum_reportu >= ?
          AND r.datum_reportu <= ?
        ORDER BY ro.prijmeni ASC, ro.jmeno ASC, ro.slot ASC, r.datum_reportu ASC
    ';

    $stmt = $conn->prepare($sql);
    if (!$stmt instanceof mysqli_stmt) {
        throw new RuntimeException('Nepodařilo se připravit dotaz přehledu směn.');
    }

    $dateFrom = $psYearStart->format('Y-m-d');
    $dateTo = $psYearEnd->format('Y-m-d');
    $stmt->bind_param('ss', $dateFrom, $dateTo);
    if (!$stmt->execute()) {
        throw new RuntimeException('Dotaz přehledu směn selhal.');
    }

    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $idUser = $row['id_user'] !== null ? (int)$row['id_user'] : 0;
        $jmeno = trim((string)($row['jmeno'] ?? ''));
        $prijmeni = trim((string)($row['prijmeni'] ?? ''));
        $slot = (int)($row['slot'] ?? 0);
        $reportDate = DateTimeImmutable::createFromFormat('Y-m-d', (string)$row['datum_reportu']);
        if (!$reportDate instanceof DateTimeImmutable) {
            continue;
        }
        $rowYear = (int)$reportDate->format('Y');
        $rowMonth = (int)$reportDate->format('n');
        $key = $rowYear . '-' . $rowMonth . '|' . ($idUser > 0 ? 'u:' . $idUser : 'n:' . mb_strtolower($prijmeni . ' ' . $jmeno, 'UTF-8')) . '|s:' . $slot;

        if (!isset($psRows[$key])) {
            $psRows[$key] = [
                'rok' => $rowYear,
                'mesic' => $rowMonth,
                'jmeno' => $jmeno,
                'prijmeni' => $prijmeni,
                'cele_jmeno' => trim($prijmeni . ' ' . $jmeno),
                'slot' => $slot,
                'celkem' => 0.0,
                'den' => 0.0,
                'noc' => 0.0,
                'vikend' => 0.0,
                'svatek' => 0.0,
                'svatek_detail' => [],
            ];
        }

        $worked = (float)($row['odpracovano'] ?? 0);
        $parts = ps_shift_parts(
            (string)$row['datum_reportu'],
            (string)($row['smena_od'] ?? '00:00:00'),
            (string)($row['smena_do'] ?? '00:00:00'),
            $worked
        );

        $psRows[$key]['celkem'] += $worked;
        $psRows[$key]['den'] += $parts['day'];
        $psRows[$key]['noc'] += $parts['night'];
        $psRows[$key]['vikend'] += $parts['weekend'];
        $psRows[$key]['svatek'] += $parts['holiday'];
        foreach ($parts['holiday_details'] as $holidayKey => $holidayDetail) {
            if (!isset($psRows[$key]['svatek_detail'][$holidayKey])) {
                $psRows[$key]['svatek_detail'][$holidayKey] = $holidayDetail;
                continue;
            }
            $psRows[$key]['svatek_detail'][$holidayKey]['hours'] += (float)$holidayDetail['hours'];
        }
        $psTotalHours += $worked;
    }
    $stmt->close();

    $psRowsAll = array_values($psRows);
} catch (Throwable $e) {
    $psRows = [];
    $psRowsAll = [];
    $psTotalHours = 0.0;
    $psError = 'Načtení přehledu směn selhalo.';
}

$psFilteredRows = [];
foreach ($psRowsAll as $row) {
    if ((int)$row['mesic'] !== $psSelectedMonth) {
        continue;
    }
    if ((int)$row['rok'] !== $psSelectedYear) {
        continue;
    }
    if ($psFilters['cele_jmeno'] !== '' && mb_stripos((string)$row['cele_jmeno'], $psFilters['cele_jmeno'], 0, 'UTF-8') === false) {
        continue;
    }
    if ($psFilters['slot'] !== '' && (int)$row['slot'] !== (int)$psFilters['slot']) {
        continue;
    }
    $psFilteredRows[] = $row;
}

usort($psFilteredRows, static function (array $a, array $b) use ($psSort, $psDir): int {
    $left = $a[$psSort] ?? '';
    $right = $b[$psSort] ?? '';
    if (in_array($psSort, ['cele_jmeno'], true)) {
        $cmp = mb_strtolower((string)$left, 'UTF-8') <=> mb_strtolower((string)$right, 'UTF-8');
    } else {
        $cmp = (float)$left <=> (float)$right;
    }
    if ($cmp === 0) {
        $cmp = [
            mb_strtolower((string)$a['prijmeni'], 'UTF-8'),
            mb_strtolower((string)$a['jmeno'], 'UTF-8'),
            (int)$a['slot'],
        ] <=> [
            mb_strtolower((string)$b['prijmeni'], 'UTF-8'),
            mb_strtolower((string)$b['jmeno'], 'UTF-8'),
            (int)$b['slot'],
        ];
    }

    return $psDir === 'DESC' ? -$cmp : $cmp;
});

$psTotal = count($psFilteredRows);
$psPages = max(1, (int)ceil($psTotal / $psPer));
if ($psPage > $psPages) {
    $psPage = $psPages;
}
$offset = ($psPage - 1) * $psPer;
$psDisplayRows = array_slice($psFilteredRows, $offset, $psPer);
$psFilteredHours = 0.0;
foreach ($psFilteredRows as $row) {
    $psFilteredHours += (float)($row['celkem'] ?? 0.0);
}

$psMonthLabel = ps_czech_month($psSelectedMonth) . ' ' . (string)$psSelectedYear;
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
    <div class="displ_flex jc_mezi ai_stred gap_8" style="margin-bottom:6px; font-size:14px; line-height:24px;">
      <div style="line-height:24px;">
        Přehled za <strong><?= h($psMonthLabel) ?></strong>, celkem: <strong><?= h(ps_num($psFilteredHours)) ?></strong> hodin
      </div>
      <div class="displ_flex ai_stred gap_8">
        <span style="line-height:24px;">Export do:</span>
        <a class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_24 card_btn_primary displ_inline_flex" href="#" aria-label="Export PDF">PDF</a>
        <a class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_24 card_btn_primary displ_inline_flex" href="#" aria-label="Export TXT">TXT</a>
        <a class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_24 card_btn_primary displ_inline_flex" href="#" aria-label="Export XLSX">XLSX</a>
      </div>
    </div>

    <div class="table-wrap ram_normal bg_bila zaobleni_12">
      <table class="card-max-table">
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
          </tr>
        </thead>
        <tbody>
          <?php if ($psDisplayRows === []): ?>
            <tr><td colspan="9">Žádná data</td></tr>
          <?php else: ?>
            <?php foreach ($psDisplayRows as $row): ?>
              <?php
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
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_num((float)$row['celkem'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_num((float)$row['den'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_num((float)$row['noc'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"><?= h(ps_num((float)$row['vikend'])) ?></td>
                <td class="txt_r" style="white-space:nowrap;"<?= $svatekTitle !== '' ? ' title="' . h($svatekTitle) . '"' : '' ?>><?= h(ps_num((float)$row['svatek'])) ?></td>
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
<?php endif; ?>
<?php
$card_max_html = (string)ob_get_clean();

/* karty/prehled_smen.php * Verze: V9 * Aktualizace: 03.06.2026 */
?>
