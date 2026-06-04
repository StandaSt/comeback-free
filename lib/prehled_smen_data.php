<?php
// lib/prehled_smen_data.php * Verze: V1 * Aktualizace: 03.06.2026
declare(strict_types=1);

/*
 * Spolecna data pro K11 Prehled smen a jeho exporty.
 * Vraci mesicni souhrn z reporty + reporty_osoby.
 */

if (!function_exists('ps_tab_config')) {
    /**
     * @return array<string,mixed>
     */
    function ps_tab_config(): array
    {
        return [
            'enable_filters' => 1,
            'enable_sort' => 1,
            'enable_pagination' => 1,
            'default_per' => 20,
            'per_options' => [20, 50, 100],
            'default_sort' => 'cele_jmeno',
            'default_dir' => 'ASC',
        ];
    }
}

if (!function_exists('ps_cols')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function ps_cols(): array
    {
        return [
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
    }
}

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

if (!function_exists('ps_request_state')) {
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    function ps_request_state(array $request): array
    {
        $tabKonfig = ps_tab_config();
        $psCols = ps_cols();
        $sortMap = array_fill_keys(array_keys($psCols), true);

        $defaultMonthStart = (new DateTimeImmutable('first day of this month'))->modify('-1 month')->setTime(0, 0);
        $defaultMonth = (int)$defaultMonthStart->format('n');
        $defaultYear = (int)$defaultMonthStart->format('Y');

        $filters = [
            'mesic' => (string)$defaultMonth,
            'rok' => (string)$defaultYear,
            'cele_jmeno' => '',
            'slot' => '',
        ];

        if (isset($request['ps_f']) && is_array($request['ps_f'])) {
            $monthRaw = trim((string)($request['ps_f']['mesic'] ?? (string)$defaultMonth));
            $yearRaw = trim((string)($request['ps_f']['rok'] ?? (string)$defaultYear));
            if (preg_match('/^(?:[1-9]|1[0-2])$/', $monthRaw) === 1) {
                $filters['mesic'] = $monthRaw;
            }
            if (preg_match('/^\d{4}$/', $yearRaw) === 1) {
                $filters['rok'] = $yearRaw;
            }
            $filters['cele_jmeno'] = trim((string)($request['ps_f']['cele_jmeno'] ?? ''));
            $slotRaw = trim((string)($request['ps_f']['slot'] ?? ''));
            if (in_array($slotRaw, ['', '1', '2', '3'], true)) {
                $filters['slot'] = $slotRaw;
            }
        }

        $sortRaw = trim((string)($request['ps_sort'] ?? (string)$tabKonfig['default_sort']));
        $dirRaw = strtoupper(trim((string)($request['ps_dir'] ?? (string)$tabKonfig['default_dir'])));
        $sort = array_key_exists($sortRaw, $sortMap) ? $sortRaw : (string)$tabKonfig['default_sort'];
        $dir = in_array($dirRaw, ['ASC', 'DESC'], true) ? $dirRaw : (string)$tabKonfig['default_dir'];

        $perOptions = array_values(array_filter(array_map('intval', (array)$tabKonfig['per_options']), static fn (int $v): bool => $v > 0));
        if ($perOptions === []) {
            $perOptions = [20, 50, 100];
        }
        $per = (int)$tabKonfig['default_per'];
        $perRaw = (int)($request['ps_per'] ?? (int)$tabKonfig['default_per']);
        if (in_array($perRaw, $perOptions, true)) {
            $per = $perRaw;
        }

        $page = 1;
        $pageRaw = (int)($request['ps_p'] ?? 1);
        if ($pageRaw > 1) {
            $page = $pageRaw;
        }

        return [
            'tabKonfig' => $tabKonfig,
            'cols' => $psCols,
            'defaultMonth' => $defaultMonth,
            'defaultYear' => $defaultYear,
            'filters' => $filters,
            'selectedMonth' => (int)$filters['mesic'],
            'selectedYear' => (int)$filters['rok'],
            'sort' => $sort,
            'dir' => $dir,
            'per' => $per,
            'perOptions' => $perOptions,
            'page' => $page,
        ];
    }
}

if (!function_exists('ps_prehled_smen_data')) {
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>
     */
    function ps_prehled_smen_data(array $request): array
    {
        $state = ps_request_state($request);
        $selectedMonth = (int)$state['selectedMonth'];
        $selectedYear = (int)$state['selectedYear'];
        $filters = (array)$state['filters'];
        $sort = (string)$state['sort'];
        $dir = (string)$state['dir'];

        $rows = [];
        $rowsAll = [];
        $filteredRows = [];
        $totalHours = 0.0;
        $filteredHours = 0.0;
        $error = '';

        $yearStart = (new DateTimeImmutable($selectedYear . '-01-01'))->setTime(0, 0);
        $yearEnd = $yearStart->modify('last day of december')->setTime(23, 59, 59);

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

            $dateFrom = $yearStart->format('Y-m-d');
            $dateTo = $yearEnd->format('Y-m-d');
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

                if (!isset($rows[$key])) {
                    $rows[$key] = [
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

                $rows[$key]['celkem'] += $worked;
                $rows[$key]['den'] += $parts['day'];
                $rows[$key]['noc'] += $parts['night'];
                $rows[$key]['vikend'] += $parts['weekend'];
                $rows[$key]['svatek'] += $parts['holiday'];
                foreach ($parts['holiday_details'] as $holidayKey => $holidayDetail) {
                    if (!isset($rows[$key]['svatek_detail'][$holidayKey])) {
                        $rows[$key]['svatek_detail'][$holidayKey] = $holidayDetail;
                        continue;
                    }
                    $rows[$key]['svatek_detail'][$holidayKey]['hours'] += (float)$holidayDetail['hours'];
                }
                $totalHours += $worked;
            }
            $stmt->close();

            $rowsAll = array_values($rows);
        } catch (Throwable $e) {
            $rows = [];
            $rowsAll = [];
            $totalHours = 0.0;
            $error = 'Načtení přehledu směn selhalo.';
        }

        foreach ($rowsAll as $row) {
            if ((int)$row['mesic'] !== $selectedMonth) {
                continue;
            }
            if ((int)$row['rok'] !== $selectedYear) {
                continue;
            }
            if ((string)$filters['cele_jmeno'] !== '' && mb_stripos((string)$row['cele_jmeno'], (string)$filters['cele_jmeno'], 0, 'UTF-8') === false) {
                continue;
            }
            if ((string)$filters['slot'] !== '' && (int)$row['slot'] !== (int)$filters['slot']) {
                continue;
            }
            $filteredRows[] = $row;
        }

        usort($filteredRows, static function (array $a, array $b) use ($sort, $dir): int {
            $left = $a[$sort] ?? '';
            $right = $b[$sort] ?? '';
            if ($sort === 'cele_jmeno') {
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

            return $dir === 'DESC' ? -$cmp : $cmp;
        });

        foreach ($filteredRows as $row) {
            $filteredHours += (float)($row['celkem'] ?? 0.0);
        }

        return $state + [
            'rowsAll' => $rowsAll,
            'filteredRows' => $filteredRows,
            'totalRows' => count($filteredRows),
            'totalHours' => $totalHours,
            'filteredHours' => $filteredHours,
            'monthLabel' => ps_czech_month($selectedMonth) . ' ' . (string)$selectedYear,
            'error' => $error,
        ];
    }
}

/* lib/prehled_smen_data.php * Verze: V1 * Aktualizace: 03.06.2026 */
?>
