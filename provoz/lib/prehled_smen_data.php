<?php
// Pripravuje hodiny a souhrny podle opravneni; rozpad smen sdili s HR, export sklada ze stavajicich detailu.
// lib/prehled_smen_data.php * Verze: V2 * Aktualizace: 04.06.2026
declare(strict_types=1);
// Strukturovane jmeno a titul sdili centralni zdroj; stejne radky pouzivaji vsechny exporty.
require_once __DIR__.'/../../common/lib/osoba_jmeno.php';

require_once __DIR__ . '/../../common/db/db_cis_slot.php';
require_once __DIR__ . '/reporty_pristup.php';
require_once __DIR__ . '/../../common/lib/smeny_hodiny.php';

/*
 * Spolecna data pro Prehled hodin a jeho exporty.
 * Vraci mesicni souhrn z finalne ulozenych reportu IS.
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
    function ps_slot_label(int $slot, array $slotLabels): string
    {
        return $slotLabels[$slot] ?? ('Slot ' . $slot);
    }
}

if (!function_exists('ps_num')) {
    // Všechny souhrny hodin a exporty přebírají stejný uživatelský numerický formát.
    function ps_num(float $value): string
    {
        return cb_format('n', $value);
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
            if ($slotRaw === '' || (ctype_digit($slotRaw) && (int)$slotRaw >= 0)) {
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
     * @param bool $includeMaxData
     * @return array<string,mixed>
     */
    // Nacte povolene hodiny a z nich vypocte mesicni i rocni souhrny a detaily.
    function ps_prehled_smen_data(array $request, bool $includeMaxData = true): array
    {
        $viewerId = (int)($_SESSION['cb_user']['id_user'] ?? 0);
        if ($viewerId <= 0) {
            throw new RuntimeException('Chybí přihlášený uživatel.');
        }

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
        $slotLabels = [];

        $yearStart = (new DateTimeImmutable($selectedYear . '-01-01'))->setTime(0, 0);
        $yearEnd = $yearStart->modify('last day of december')->setTime(23, 59, 59);
        $monthStart = (new DateTimeImmutable(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth)))->setTime(0, 0);
        $monthEnd = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

        try {
            $conn = db();
            $conn->set_charset('utf8mb4');
            // Rozsah se uplatni v SQL pred soucty, a je proto shodny pro stranku i exporty.
            $scope = cb_reporty_pristup_nacti($conn, $viewerId);
            $slotLabels = cb_cis_slot_nazvy($conn);

            $sql = '
                SELECT
                    r.datum_reportu,
                    ro.id_user,
                    COALESCE(NULLIF(ou.jmeno, ""), ro.jmeno) AS jmeno,
                    COALESCE(NULLIF(ou.prijmeni, ""), ro.prijmeni) AS prijmeni,
                    ou.druhe_jmeno,ou.titul_za,
                    ro.slot,
                    ro.smena_od,
                    ro.smena_do,
                    ro.pauza_od, ro.pauza_do,
                    ro.odpracovano,
                    r.id_pob,
                    COALESCE(p.nazev, "") AS pobocka
                FROM reporty_is_osoby ro
                INNER JOIN reporty_is r ON r.id_reportu = ro.id_reportu

                LEFT JOIN ' . cb_osoby_zdroj_sql() . ' ou ON ou.id_person=ro.id_user
                LEFT JOIN pobocka p ON p.id_pob = r.id_pob
                WHERE r.platny = 1

                  AND r.datum_reportu >= ?
                  AND r.datum_reportu <= ?
                  AND ' . cb_reporty_hodiny_sql($scope) . '
                ORDER BY COALESCE(NULLIF(ou.prijmeni, ""), ro.prijmeni) ASC,
                         COALESCE(NULLIF(ou.jmeno, ""), ro.jmeno) ASC,
                         ro.slot ASC, r.datum_reportu ASC
            ';

            $stmt = $conn->prepare($sql);
            if (!$stmt instanceof mysqli_stmt) {
                throw new RuntimeException('Nepodařilo se připravit dotaz přehledu směn.');
            }

            $dateFrom = ($includeMaxData ? $yearStart : $monthStart)->format('Y-m-d');
            $dateTo = ($includeMaxData ? $yearEnd : $monthEnd)->format('Y-m-d');
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
                        'id_user' => $idUser,
                        'jmeno' => $jmeno,
                        'prijmeni' => $prijmeni,
                        'cele_jmeno' => cb_osoba_jmeno($row,true),
                        'slot' => $slot,
                        'celkem' => 0.0,
                    ];
                    if ($includeMaxData) {
                        $rows[$key]['den'] = 0.0;
                        $rows[$key]['noc'] = 0.0;
                        $rows[$key]['vikend'] = 0.0;
                        $rows[$key]['svatek'] = 0.0;
                        $rows[$key]['svatek_detail'] = [];
                        $rows[$key]['detail_key'] = substr(hash('sha256', $key), 0, 12);
                        $rows[$key]['detail_rows'] = [];
                    }
                }

                $worked = (float)($row['odpracovano'] ?? 0);
                $rows[$key]['celkem'] += $worked;
                if ($includeMaxData) {
                    $parts = ps_shift_parts(
                        (string)$row['datum_reportu'],
                        (string)($row['smena_od'] ?? '00:00:00'),
                        (string)($row['smena_do'] ?? '00:00:00'),
                        $worked,
                        $row['pauza_od'] ?? null,
                        $row['pauza_do'] ?? null
                    );

                    $rows[$key]['den'] += $parts['day'];
                    $rows[$key]['noc'] += $parts['night'];
                    $rows[$key]['vikend'] += $parts['weekend'];
                    $rows[$key]['svatek'] += $parts['holiday'];
                    $rows[$key]['detail_rows'][] = [
                        'datum' => (string)$row['datum_reportu'],
                        'id_pob' => (int)($row['id_pob'] ?? 0),
                        'pobocka' => trim((string)($row['pobocka'] ?? '')),
                        'slot' => $slot,
                        'celkem' => $worked,
                        'den' => (float)$parts['day'],
                        'noc' => (float)$parts['night'],
                        'vikend' => (float)$parts['weekend'],
                        'svatek' => (float)$parts['holiday'],
                        'svatek_detail' => $parts['holiday_details'],
                    ];
                    foreach ($parts['holiday_details'] as $holidayKey => $holidayDetail) {
                        if (!isset($rows[$key]['svatek_detail'][$holidayKey])) {
                            $rows[$key]['svatek_detail'][$holidayKey] = $holidayDetail;
                            continue;
                        }
                        $rows[$key]['svatek_detail'][$holidayKey]['hours'] += (float)$holidayDetail['hours'];
                    }
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

        if ($includeMaxData) {
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
        }

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
            'slotLabels' => $slotLabels,
            'error' => $error,
        ];
    }
}

if (!function_exists('ps_pracovnici_pobocky')) {
    // Secte existujici detaily za zamestnance a pobocku napric sloty, bez dalsiho nacitani dat.
    function ps_pracovnici_pobocky(array $rows): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $userId = (int)($row['id_user'] ?? 0);
            $personKey = $userId > 0 ? 'u:' . $userId : 'n:' . mb_strtolower((string)$row['cele_jmeno'], 'UTF-8');
            foreach ((array)($row['detail_rows'] ?? []) as $detail) {
                $branchId = (int)($detail['id_pob'] ?? 0);
                $key = $personKey . '|p:' . $branchId;
                if (!isset($groups[$key])) {
                    $branchName = trim((string)($detail['pobocka'] ?? ''));
                    $groups[$key] = [
                        'cele_jmeno' => (string)$row['cele_jmeno'],
                        'pobocka' => $branchName !== '' ? $branchName : ($branchId > 0 ? 'ID ' . $branchId : '-'),
                        'celkem' => 0.0, 'den' => 0.0, 'noc' => 0.0, 'vikend' => 0.0, 'svatek' => 0.0,
                    ];
                }
                foreach (['celkem', 'den', 'noc', 'vikend', 'svatek'] as $column) {
                    $groups[$key][$column] += (float)($detail[$column] ?? 0.0);
                }
            }
        }
        // Zamestnanec zustane pohromade i pri puvodnim razeni prehledu podle hodin nebo slotu.
        usort($groups, static function (array $a, array $b): int {
            return [mb_strtolower($a['cele_jmeno'], 'UTF-8'), mb_strtolower($a['pobocka'], 'UTF-8')]
                <=> [mb_strtolower($b['cele_jmeno'], 'UTF-8'), mb_strtolower($b['pobocka'], 'UTF-8')];
        });
        return $groups;
    }
}

/* lib/prehled_smen_data.php * Verze: V2 * Aktualizace: 04.06.2026 */
?>
