<?php
declare(strict_types=1);

require_once __DIR__ . '/denni_report_prava.php';
require_once __DIR__ . '/../../common/db/db_cis_slot.php';

function cb_archiv_reportu_date(string $value, DateTimeZone $tz, DateTimeImmutable $fallback): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);

    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : $fallback;
}

function cb_archiv_reportu_comparison_rows(mysqli $conn, int $idPob, string $reportDate): array
{
    $slotLabels = cb_cis_slot_nazvy($conn);
    $isData = cb_denni_report_history_load($conn, $idPob, $reportDate);
    $googleData = cb_denni_report_google_history_load($conn, $idPob, $reportDate);
    if (!is_array($isData) || !is_array($googleData)) {
        return ['entry' => [], 'calculation' => []];
    }

    $entryFields = [
        ['Otevíral', 'oteviral_text', 'text', 'oteviral'], ['Zavíral', 'zaviral_text', 'text', 'zaviral'],
        ['Hotovost', 'hotovost', 'money'], ['Terminál', 'terminal', 'money'], ['Stravenky', 'stravenky', 'money'],
        ['PHM firemní auta', 'vydaje_benzin', 'money'], ['PHM výroba', 'vydaje_auta', 'money'], ['Suroviny', 'vydaje_suroviny', 'money'], ['Ostatní', 'vydaje_ostatni', 'money'], ['PHM soukromé', 'vydaje_phm_soukrome', 'money'],
    ];
    $calculationFields = [
        ['Rozdíl pokladna', 'rozdil', 'money'],
        ['Tržba', 'trzba', 'money'], ['Wolt', 'wolt', 'money'], ['Bolt', 'bolt', 'money'], ['Foodora', 'damejidlo', 'money'], ['Web', 'web', 'money'], ['Wolt drive cash', 'wolt_cash', 'money'], ['DJ cash', 'dj_cash', 'money'], ['COL', 'col_pomer', 'percent'],
        ['Zrušené objednávky', 'zrusene_obj_ks', 'integer'], ['Zrušené objednávky Kč', 'zrusene_obj_kc', 'money'], ['Zpožděné rozvozy +5 min', 'zpozdene_rozvozy_5_min', 'integer'], ['Průměrný make time', 'make_time_prumer_sec', 'minutes'], ['Nezrušené celkem', 'objednavky_nezrusene_ks', 'integer'], ['Naše rozvozy', 'nase_rozvozy_ks', 'integer'], ['Wolt Drive', 'woltdrive_ks', 'integer'], ['Pozdě WoltDrive 5+', 'woltdrive_pozde_5_min', 'integer'],
    ];
    $isReport = (array)($isData['report'] ?? []);
    $googleReport = (array)($googleData['report'] ?? []);
    $comparison = ['entry' => [], 'calculation' => []];
    foreach (['entry' => $entryFields, 'calculation' => $calculationFields] as $group => $fields) {
        foreach ($fields as $definition) {
            [$label, $field, $format] = $definition;
            $comparisonField = $definition[3] ?? $field;
            $isValue = $isReport[$field] ?? null;
            $googleValue = $googleReport[$field] ?? null;
            $sameValue = (string)($isReport[$comparisonField] ?? '') === (string)($googleReport[$comparisonField] ?? '');
            if (!$sameValue) {
                $comparison[$group][] = ['item' => $label, 'is' => $isValue, 'google' => $googleValue, 'format' => $format];
            }
        }
    }

    $workPeople = static function (array $people): array {
        $result = [];
        foreach ($people as $person) {
            $slot = (int)($person['id_slot'] ?? 0);
            if (!in_array($slot, [1, 2], true)) {
                continue;
            }
            $idUser = (int)($person['id_user'] ?? 0);
            $name = trim((string)($person['jmeno'] ?? '') . ' ' . (string)($person['prijmeni'] ?? ''));
            $key = $idUser > 0
                ? 'user:' . $idUser . ':slot:' . $slot
                : 'name:' . mb_strtolower($name, 'UTF-8') . ':slot:' . $slot;
            if (!isset($result[$key])) {
                $result[$key] = ['name' => $name, 'slot' => $slot, 'hours' => 0.0];
            }
            $result[$key]['hours'] += (float)($person['odpracovano'] ?? 0);
        }

        return $result;
    };
    $isWorkPeople = $workPeople((array)($isData['people_rows'] ?? []));
    $googleWorkPeople = $workPeople((array)($googleData['people_rows'] ?? []));
    $workPeopleKeys = array_unique(array_merge(array_keys($isWorkPeople), array_keys($googleWorkPeople)));
    usort($workPeopleKeys, static function (string $leftKey, string $rightKey) use ($isWorkPeople, $googleWorkPeople): int {
        $left = (array)($isWorkPeople[$leftKey] ?? $googleWorkPeople[$leftKey] ?? []);
        $right = (array)($isWorkPeople[$rightKey] ?? $googleWorkPeople[$rightKey] ?? []);
        $slotComparison = (int)($left['slot'] ?? 0) <=> (int)($right['slot'] ?? 0);
        if ($slotComparison !== 0) {
            return $slotComparison;
        }

        return strnatcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
    });
    foreach ($workPeopleKeys as $workPersonKey) {
        $isPresent = isset($isWorkPeople[$workPersonKey]);
        $googlePresent = isset($googleWorkPeople[$workPersonKey]);
        $isPerson = (array)($isWorkPeople[$workPersonKey] ?? []);
        $googlePerson = (array)($googleWorkPeople[$workPersonKey] ?? []);
        $isHours = (float)($isPerson['hours'] ?? 0);
        $googleHours = (float)($googlePerson['hours'] ?? 0);
        if ($isPresent && $googlePresent && abs($isHours - $googleHours) < 0.0001) {
            continue;
        }
        $person = $isPresent ? $isPerson : $googlePerson;
        $idSlot = (int)($person['slot'] ?? 0);
        $role = $slotLabels[$idSlot] ?? ('Slot ' . $idSlot);
        $name = trim((string)($person['name'] ?? ''));
        if ($name === '') {
            $name = 'Neznámý zaměstnanec';
        }
        $item = $role . ' — ' . $name;
        if (!$isPresent) {
            $item .= ' (v IS není zadán)';
        } elseif (!$googlePresent) {
            $item .= ' (v Google reportu není zadán)';
        }
        $comparison['entry'][] = ['item' => $item, 'is' => $isHours, 'google' => $googleHours, 'format' => 'hours'];
    }

    $personSummary = static function (array $people): array {
        $result = ['Rozvozy celkem' => 0, 'Vlastní vůz' => 0, 'PHM kurýrů' => 0.0];
        foreach ($people as $person) {
            $isInstor = (int)($person['id_slot'] ?? 0) === 1;
            if (!$isInstor) {
                $result['Rozvozy celkem'] += (int)($person['rozvozu_restia'] ?? 0) + (int)($person['rozvozu_manual'] ?? 0);
                $result['Vlastní vůz'] += (int)((int)($person['vlastni_vuz'] ?? 0) === 1);
                $result['PHM kurýrů'] += (float)($person['vyplatit_phm'] ?? 0);
            }
        }
        return $result;
    };
    $isPeople = $personSummary((array)($isData['people_rows'] ?? []));
    $googlePeople = $personSummary((array)($googleData['people_rows'] ?? []));
    $personFormats = ['Rozvozy celkem' => 'integer', 'Vlastní vůz' => 'integer', 'PHM kurýrů' => 'money'];
    foreach ($personFormats as $item => $format) {
        $isValue = $isPeople[$item] ?? null;
        $googleValue = $googlePeople[$item] ?? null;
        if ((string)$isValue !== (string)$googleValue) {
            $comparison['entry'][] = ['item' => $item, 'is' => $isValue, 'google' => $googleValue, 'format' => $format];
        }
    }

    $deliveryPeople = static function (array $people): array {
        $result = [];
        foreach ($people as $person) {
            if ((int)($person['id_slot'] ?? 0) !== 2) {
                continue;
            }
            $name = trim((string)($person['jmeno'] ?? '') . ' ' . (string)($person['prijmeni'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name, 'UTF-8');
            if (!isset($result[$key])) {
                $result[$key] = ['name' => $name, 'deliveries' => 0];
            }
            $result[$key]['deliveries'] += (int)($person['rozvozu_restia'] ?? 0) + (int)($person['rozvozu_manual'] ?? 0);
        }
        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);

        return $result;
    };
    $isDeliveryPeople = $deliveryPeople((array)($isData['people_rows'] ?? []));
    $googleDeliveryPeople = $deliveryPeople((array)($googleData['people_rows'] ?? []));
    $deliveryPeopleKeys = array_unique(array_merge(array_keys($isDeliveryPeople), array_keys($googleDeliveryPeople)));
    sort($deliveryPeopleKeys, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($deliveryPeopleKeys as $deliveryPersonKey) {
        $isDeliveryPerson = (array)($isDeliveryPeople[$deliveryPersonKey] ?? []);
        $googleDeliveryPerson = (array)($googleDeliveryPeople[$deliveryPersonKey] ?? []);
        $isDeliveries = (int)($isDeliveryPerson['deliveries'] ?? 0);
        $googleDeliveries = (int)($googleDeliveryPerson['deliveries'] ?? 0);
        if ($isDeliveries === $googleDeliveries) {
            continue;
        }
        $name = trim((string)($isDeliveryPerson['name'] ?? $googleDeliveryPerson['name'] ?? ''));
        if ($name !== '') {
            $comparison['entry'][] = ['item' => 'Rozvozy — ' . $name, 'is' => $isDeliveries, 'google' => $googleDeliveries, 'format' => 'integer'];
        }
    }

    $carPeople = static function (array $people): array {
        $result = [];
        foreach ($people as $person) {
            if ((int)($person['id_slot'] ?? 0) !== 2) {
                continue;
            }
            $name = trim((string)($person['jmeno'] ?? '') . ' ' . (string)($person['prijmeni'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name, 'UTF-8');
            if (!isset($result[$key])) {
                $result[$key] = ['name' => $name, 'has_own_car' => false];
            }
            $result[$key]['has_own_car'] = !empty($result[$key]['has_own_car']) || (int)($person['vlastni_vuz'] ?? 0) === 1;
        }
        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);

        return $result;
    };
    $isCarPeople = $carPeople((array)($isData['people_rows'] ?? []));
    $googleCarPeople = $carPeople((array)($googleData['people_rows'] ?? []));
    $carPeopleKeys = array_unique(array_merge(array_keys($isCarPeople), array_keys($googleCarPeople)));
    sort($carPeopleKeys, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($carPeopleKeys as $carPersonKey) {
        $isCarPerson = (array)($isCarPeople[$carPersonKey] ?? []);
        $googleCarPerson = (array)($googleCarPeople[$carPersonKey] ?? []);
        $isHasOwnCar = !empty($isCarPerson['has_own_car']);
        $googleHasOwnCar = !empty($googleCarPerson['has_own_car']);
        if ($isHasOwnCar === $googleHasOwnCar) {
            continue;
        }
        $name = trim((string)($isCarPerson['name'] ?? $googleCarPerson['name'] ?? ''));
        if ($name !== '') {
            $comparison['entry'][] = [
                'item' => 'Vlastní vůz — ' . $name,
                'is' => $isHasOwnCar ? 'ano' : 'ne',
                'google' => $googleHasOwnCar ? 'ano' : 'ne',
                'format' => 'text',
            ];
        }
    }

    return $comparison;
}

function cb_archiv_reportu_data(mysqli $conn, array $input): array
{
    $tz = new DateTimeZone('Europe/Prague');
    $currentWorkday = cb_denni_report_current_workday_date();
    $archiveEnd = $currentWorkday->modify('-1 day');
    $defaultMonth = (int)$currentWorkday->format('n');
    $defaultYear = (int)$currentWorkday->format('Y');
    $defaultFrom = $archiveEnd->modify('first day of this month');
    $user = $_SESSION['cb_user'] ?? [];
    $userId = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
    $filters = ['month' => $defaultMonth, 'year' => $defaultYear, 'from' => $defaultFrom->format('Y-m-d'), 'to' => $archiveEnd->format('Y-m-d'), 'branch' => 0, 'status' => 'all', 'page' => 1];

    if ($userId <= 0) {
        return ['filters' => $filters, 'branches' => [], 'rows' => [], 'total' => 0, 'error' => 'Pro zobrazení archivu je nutné přihlášení.'];
    }

    $filters['month'] = (int)($input['ar_month'] ?? $defaultMonth);
    $filters['year'] = (int)($input['ar_year'] ?? $defaultYear);
    if ($filters['month'] < 1 || $filters['month'] > 12) {
        $filters['month'] = $defaultMonth;
    }
    if ($filters['year'] < 2000 || $filters['year'] > $defaultYear) {
        $filters['year'] = $defaultYear;
    }
    $from = (new DateTimeImmutable(sprintf('%04d-%02d-01', $filters['year'], $filters['month']), $tz));
    $to = $from->modify('last day of this month');
    if ($to > $archiveEnd) {
        $to = $archiveEnd;
    }
    $filters['from'] = $from->format('Y-m-d');
    $filters['to'] = $to->format('Y-m-d');
    $filters['branch'] = max(0, (int)($input['ar_branch'] ?? 0));
    $filters['status'] = trim((string)($input['ar_status'] ?? 'all'));
    if (!in_array($filters['status'], ['all', 'saved', 'missing'], true)) {
        $filters['status'] = 'all';
    }
    $filters['page'] = max(1, (int)($input['ar_p'] ?? 1));
    $filters['sort'] = trim((string)($input['ar_sort'] ?? 'date'));
    $filters['dir'] = strtolower(trim((string)($input['ar_dir'] ?? 'desc')));
    if (!in_array($filters['sort'], ['date', 'branch', 'status', 'revenue', 'col', 'difference', 'hours', 'opening', 'closing'], true)) {
        $filters['sort'] = 'date';
    }
    if (!in_array($filters['dir'], ['asc', 'desc'], true)) {
        $filters['dir'] = 'desc';
    }

    $branches = [];
    $branchStmt = $conn->prepare('
        SELECT p.id_pob, p.nazev, up.main
        FROM user_pobocka up
        INNER JOIN pobocka p ON p.id_pob = up.id_pob
        WHERE up.id_user = ? AND p.aktivni = 1
        ORDER BY p.id_pob ASC
    ');
    if ($branchStmt === false) {
        return ['filters' => $filters, 'branches' => [], 'rows' => [], 'total' => 0, 'error' => 'Nepodařilo se načíst pobočky pro archiv reportů.'];
    }
    $branchStmt->bind_param('i', $userId);
    $branchStmt->execute();
    $branchResult = $branchStmt->get_result();
    if ($branchResult instanceof mysqli_result) {
        while ($branchRow = $branchResult->fetch_assoc()) {
            $idBranch = (int)($branchRow['id_pob'] ?? 0);
            if ($idBranch > 0) {
                $branches[$idBranch] = ['id' => $idBranch, 'name' => trim((string)($branchRow['nazev'] ?? '')), 'main' => ((int)($branchRow['main'] ?? 0) === 1)];
            }
        }
        $branchResult->free();
    }
    $branchStmt->close();
    if ($branches === []) {
        return ['filters' => $filters, 'branches' => [], 'rows' => [], 'total' => 0, 'error' => 'Nemáte přiřazenou žádnou aktivní pobočku.'];
    }
    $years = [];
    $yearStmt = $conn->prepare('
        SELECT MIN(first_date) AS first_date
        FROM (
            SELECT r.datum_reportu AS first_date
            FROM reporty_is r
            INNER JOIN user_pobocka up ON up.id_pob = r.id_pob AND up.id_user = ?
            WHERE r.platny = 1
            UNION ALL
            SELECT r.datum_reportu AS first_date
            FROM reporty r
            INNER JOIN user_pobocka up ON up.id_pob = r.id_pob AND up.id_user = ?
            WHERE r.platny = 1 AND r.zdroj = 1
        ) report_dates
    ');
    if ($yearStmt !== false) {
        $yearStmt->bind_param('ii', $userId, $userId);
        $yearStmt->execute();
        $yearResult = $yearStmt->get_result();
        $yearRow = $yearResult instanceof mysqli_result ? ($yearResult->fetch_assoc() ?: []) : [];
        if ($yearResult instanceof mysqli_result) { $yearResult->free(); }
        $yearStmt->close();
        $firstYear = (int)substr((string)($yearRow['first_date'] ?? ''), 0, 4);
        for ($year = $defaultYear; $year >= max(2000, $firstYear ?: $defaultYear); $year--) { $years[] = $year; }
    }
    if ($years === []) { $years[] = $defaultYear; }
    if ($filters['branch'] > 0 && !isset($branches[$filters['branch']])) {
        $filters['branch'] = 0;
    }

    $canCompleteReport = cb_denni_report_ma_pravo(CB_DENNI_REPORT_ZOBRAZIT_PRAVO)
        && cb_denni_report_ma_pravo(CB_DENNI_REPORT_UZAVRIT_PRAVO);

    $reportStmt = $conn->prepare('
        SELECT
            r.id_reportu,
            r.id_pob,
            r.datum_reportu,
            r.oteviral_text,
            r.zaviral_text,
            ri.trzba,
            ri.col_pomer,
            pk.rozdil,
            people.hodiny_celkem,
            people.hodiny_instor,
            people.hodiny_kuryr
        FROM reporty_is r
        INNER JOIN user_pobocka up ON up.id_pob = r.id_pob AND up.id_user = ?
        LEFT JOIN reporty_is_restia ri ON ri.id_reportu = r.id_reportu
        LEFT JOIN reporty_is_pokladna pk ON pk.id_reportu = r.id_reportu
        LEFT JOIN (
            SELECT
                id_reportu,
                SUM(COALESCE(odpracovano, 0)) AS hodiny_celkem,
                SUM(CASE WHEN slot = 1 THEN COALESCE(odpracovano, 0) ELSE 0 END) AS hodiny_instor,
                SUM(CASE WHEN slot = 2 THEN COALESCE(odpracovano, 0) ELSE 0 END) AS hodiny_kuryr
            FROM reporty_is_osoby
            GROUP BY id_reportu
        ) people ON people.id_reportu = r.id_reportu
        WHERE r.platny = 1 AND r.datum_reportu BETWEEN ? AND ?
        ORDER BY r.datum_reportu DESC, r.id_reportu DESC
    ');
    $savedByKey = [];
    if ($reportStmt !== false) {
        $fromValue = $filters['from'];
        $toValue = $filters['to'];
        $reportStmt->bind_param('iss', $userId, $fromValue, $toValue);
        $reportStmt->execute();
        $reportResult = $reportStmt->get_result();
        if ($reportResult instanceof mysqli_result) {
            while ($reportRow = $reportResult->fetch_assoc()) {
                $idBranch = (int)($reportRow['id_pob'] ?? 0);
                $date = trim((string)($reportRow['datum_reportu'] ?? ''));
                $key = $date . ':' . $idBranch;
                if ($idBranch > 0 && isset($branches[$idBranch]) && $date !== '' && !isset($savedByKey[$key])) {
                    $savedByKey[$key] = $reportRow;
                }
            }
            $reportResult->free();
        }
    $reportStmt->close();

    $googleByKey = [];
    $googleStmt = $conn->prepare('
        SELECT r.id_pob, r.datum_reportu
        FROM reporty r
        INNER JOIN user_pobocka up ON up.id_pob = r.id_pob AND up.id_user = ?
        WHERE r.platny = 1
          AND r.zdroj = 1
          AND r.datum_reportu BETWEEN ? AND ?
    ');
    if ($googleStmt !== false) {
        $fromValue = $filters['from'];
        $toValue = $filters['to'];
        $googleStmt->bind_param('iss', $userId, $fromValue, $toValue);
        $googleStmt->execute();
        $googleResult = $googleStmt->get_result();
        if ($googleResult instanceof mysqli_result) {
            while ($googleRow = $googleResult->fetch_assoc()) {
                $idBranch = (int)($googleRow['id_pob'] ?? 0);
                $date = trim((string)($googleRow['datum_reportu'] ?? ''));
                if ($idBranch > 0 && isset($branches[$idBranch]) && $date !== '') {
                    $googleByKey[$date . ':' . $idBranch] = true;
                }
            }
            $googleResult->free();
        }
        $googleStmt->close();
    }
    }

    $visibleBranches = $filters['branch'] > 0 ? [$filters['branch'] => $branches[$filters['branch']]] : $branches;
    $rows = [];
    for ($day = $to; $day >= $from; $day = $day->modify('-1 day')) {
        $date = $day->format('Y-m-d');
        foreach ($visibleBranches as $idBranch => $branch) {
            $saved = $savedByKey[$date . ':' . $idBranch] ?? null;
            $isSaved = is_array($saved);
            if (($filters['status'] === 'saved' && !$isSaved) || ($filters['status'] === 'missing' && $isSaved)) {
                continue;
            }
            $rows[] = [
                'date' => $date,
                'date_label' => cb_dt_weekday_date_label_cs($day, true),
                'branch_id' => $idBranch,
                'branch_name' => (string)$branch['name'],
                'saved' => $isSaved,
                'revenue' => $isSaved ? (float)($saved['trzba'] ?? 0) : null,
                'col' => $isSaved && ($saved['col_pomer'] ?? null) !== null ? (float)$saved['col_pomer'] : null,
                'difference' => $isSaved && ($saved['rozdil'] ?? null) !== null ? (float)$saved['rozdil'] : null,
                'hours_total' => $isSaved ? (float)($saved['hodiny_celkem'] ?? 0) : null,
                'hours_instor' => $isSaved ? (float)($saved['hodiny_instor'] ?? 0) : null,
                'hours_kuryr' => $isSaved ? (float)($saved['hodiny_kuryr'] ?? 0) : null,
                'opening' => $isSaved ? trim((string)($saved['oteviral_text'] ?? '')) : '',
                'closing' => $isSaved ? trim((string)($saved['zaviral_text'] ?? '')) : '',
                'google_available' => !$isSaved && isset($googleByKey[$date . ':' . $idBranch]),
                'google_report_available' => isset($googleByKey[$date . ':' . $idBranch]),
                'can_complete' => !$isSaved && $canCompleteReport,
            ];
        }
    }
    $sort = $filters['sort'];
    $direction = $filters['dir'] === 'asc' ? 1 : -1;
    usort($rows, static function (array $left, array $right) use ($sort, $direction): int {
        $values = [
            'date' => [(string)$left['date'], (string)$right['date']],
            'branch' => [(string)$left['branch_name'], (string)$right['branch_name']],
            'status' => [!empty($left['saved']) ? 'saved' : 'missing', !empty($right['saved']) ? 'saved' : 'missing'],
            'revenue' => [(float)($left['revenue'] ?? -1), (float)($right['revenue'] ?? -1)],
            'col' => [(float)($left['col'] ?? -1), (float)($right['col'] ?? -1)],
            'difference' => [(float)($left['difference'] ?? -1), (float)($right['difference'] ?? -1)],
            'hours' => [(float)($left['hours_total'] ?? -1), (float)($right['hours_total'] ?? -1)],
            'opening' => [(string)$left['opening'], (string)$right['opening']],
            'closing' => [(string)$left['closing'], (string)$right['closing']],
        ];
        [$leftValue, $rightValue] = $values[$sort];
        $comparison = $leftValue <=> $rightValue;
        if ($comparison === 0) {
            $comparison = ((int)$left['branch_id']) <=> ((int)$right['branch_id']);
        }
        return $comparison * $direction;
    });

    return ['filters' => $filters, 'branches' => $branches, 'years' => $years, 'rows' => $rows, 'total' => count($rows), 'error' => ''];
}
