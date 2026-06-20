<?php
// K15
// karty/top_report.php * Verze: V5 * Aktualizace: 07.05.2026
declare(strict_types=1);

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$selectedPob = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
$selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static fn (int $v): bool => $v > 0));
if ($selectedPob === []) {
    $fallbackPob = (int)($_SESSION['cb_pobocka_id'] ?? 0);
    if ($fallbackPob > 0) {
        $selectedPob = [$fallbackPob];
    }
}

$periodOdRaw = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
$periodDoRaw = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));

try {
    $periodOd = $periodOdRaw !== '' ? new DateTimeImmutable($periodOdRaw) : new DateTimeImmutable('today 06:00:00');
} catch (Throwable $e) {
    $periodOd = new DateTimeImmutable('today 06:00:00');
}

try {
    $periodDo = $periodDoRaw !== '' ? new DateTimeImmutable($periodDoRaw) : new DateTimeImmutable('today 06:00:00');
} catch (Throwable $e) {
    $periodDo = new DateTimeImmutable('today 06:00:00');
}

if ($periodDo < $periodOd) {
    $periodDo = $periodOd;
}

$periodOdSql = $periodOd->format('Y-m-d H:i:s');
$periodDoSql = $periodDo->format('Y-m-d H:i:s');
$dateOd = $periodOd->format('Y-m-d');
$dateDo = $periodDo->format('Y-m-d');
$periodLabel = $periodOd->format('j.n.Y G:i') . ' - ' . $periodDo->format('j.n.Y G:i');
$renderMode = isset($cbDashboardRenderMode) ? trim((string)$cbDashboardRenderMode) : '';
$isMiniRender = ($renderMode === 'mini');
$isMaxRender = ($renderMode === 'max');

$formatMoney = static function (float $value): string {
    return number_format($value, 0, ',', ' ') . ' Kč';
};

$formatHours = static function (float $value): string {
    return number_format($value, 1, ',', ' ') . ' h';
};

$formatRatio = static function (float $value): string {
    return number_format($value, 1, ',', ' ') . ' %';
};

$formatCount = static function (int $value): string {
    return number_format($value, 0, ',', ' ');
};

$safeColors = [
    '#2563eb',
    '#22c55e',
    '#f97316',
    '#7c3aed',
    '#f59e0b',
    '#06b6d4',
];

$channelMeta = [
    'wolt' => ['label' => 'Wolt', 'color' => '#2563eb'],
    'bolt' => ['label' => 'Bolt', 'color' => '#22c55e'],
    'foodora' => ['label' => 'Foodora', 'color' => '#f97316'],
    'vlastni_web' => ['label' => 'Vlastní web', 'color' => '#7c3aed'],
    'rucni_obj' => ['label' => 'Ruční zadání', 'color' => '#f59e0b'],
    'ostatni' => ['label' => 'Adaptee', 'color' => '#64748b'],
];

$lookupIds = static function (mysqli $conn, string $table, string $idCol, string $nameCol, array $names): array {
    $names = array_values(array_filter(array_map('strval', $names), static fn (string $v): bool => trim($v) !== ''));
    if ($names === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($names), '?'));
    $sql = 'SELECT `' . $idCol . '` AS id, `' . $nameCol . '` AS name FROM `' . $table . '` WHERE `' . $nameCol . '` IN (' . $placeholders . ')';
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }

    $types = str_repeat('s', count($names));
    $bindValues = [];
    $bindValues[] = $types;
    foreach ($names as $key => $value) {
        $bindValues[] = &$names[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);
    $stmt->execute();
    $result = $stmt->get_result();

    $map = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $map[(string)($row['name'] ?? '')] = (int)($row['id'] ?? 0);
        }
        $result->free();
    }
    $stmt->close();

    return $map;
};

$cancelStateIds = array_values(array_filter(array_map(
    'intval',
    $lookupIds($conn, 'cis_obj_stav', 'id_stav', 'nazev', ['canceled', 'rejected', 'expired', 'not_accepted', 'cancel_accepted'])
), static fn (int $v): bool => $v > 0));
$onlinePaymentIds = array_values(array_filter(array_map(
    'intval',
    $lookupIds($conn, 'cis_obj_platby', 'id_platba', 'nazev', ['online'])
), static fn (int $v): bool => $v > 0));

$channelTotals = [
    'wolt' => 0.0,
    'bolt' => 0.0,
    'foodora' => 0.0,
    'vlastni_web' => 0.0,
    'rucni_obj' => 0.0,
    'ostatni' => 0.0,
];
$topSummary = [
    'trzba' => 0.0,
    'hodiny_celkem' => 0.0,
    'objednavky' => 0,
    'report_days' => 0,
    'branch_count' => 0,
];
$colorIndex = 0;

$branchMetaById = [];
$branchMetaSql = '
    SELECT p.id_pob, p.nazev, COALESCE(p.pob_color, "") AS pob_color
    FROM pobocka p
';
if ($selectedPob !== []) {
    $branchMetaSql .= ' WHERE p.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}
$branchMetaSql .= ' ORDER BY p.id_pob ASC';

$branchMetaResult = $conn->query($branchMetaSql);
if ($branchMetaResult instanceof mysqli_result) {
    while ($row = $branchMetaResult->fetch_assoc()) {
        $branchId = (int)($row['id_pob'] ?? 0);
        if ($branchId <= 0) {
            continue;
        }
        $branchMetaById[$branchId] = [
            'id_pob' => $branchId,
            'nazev' => trim((string)($row['nazev'] ?? 'Pobočka')),
            'color' => trim((string)($row['pob_color'] ?? '')),
        ];
    }
    $branchMetaResult->free();
}

$onlinePaymentIdsSql = $onlinePaymentIds !== [] ? implode(',', array_map('intval', $onlinePaymentIds)) : '0';
$whereSql = '
    WHERE o.restia_created_at IS NOT NULL
      AND o.restia_created_at >= ?
      AND o.restia_created_at < ?
';
$whereTypes = 'ss';
$whereParams = [$periodOdSql, $periodDoSql];

if ($cancelStateIds !== []) {
    $whereSql .= ' AND (o.id_stav IS NULL OR o.id_stav NOT IN (' . implode(',', array_map('intval', $cancelStateIds)) . '))';
}

if ($selectedPob !== []) {
    $placeholders = implode(',', array_fill(0, count($selectedPob), '?'));
    $whereSql .= ' AND o.id_pob IN (' . $placeholders . ')';
    $whereTypes .= str_repeat('i', count($selectedPob));
    foreach ($selectedPob as $idPob) {
        $whereParams[] = $idPob;
    }
}

$woltCondition = "LOWER(COALESCE(cp.kod, '')) = 'wolt'";
$boltCondition = "LOWER(COALESCE(cp.kod, '')) = 'bolt'";
$foodoraCondition = "LOWER(COALESCE(cp.kod, '')) IN ('foodora', 'damejidlo')";
$webCondition = "LOWER(COALESCE(cp.kod, '')) = 'generic' AND o.id_platba IN ($onlinePaymentIdsSql)";
$manualCondition = "LOWER(COALESCE(cp.kod, '')) IN ('manual', 'phone')";
$knownCondition = '(' . implode(' OR ', [$woltCondition, $boltCondition, $foodoraCondition, $webCondition, $manualCondition]) . ')';

$summarySql = '
    SELECT
        COUNT(*) AS objednavky,
        COUNT(DISTINCT o.id_pob) AS branch_count,
        COUNT(DISTINCT DATE(o.restia_created_at)) AS report_days,
        SUM(COALESCE(c.cena_celk, 0)) AS trzba,
        SUM(CASE WHEN ' . $woltCondition . ' THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS wolt,
        SUM(CASE WHEN ' . $boltCondition . ' THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS bolt,
        SUM(CASE WHEN ' . $foodoraCondition . ' THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS foodora,
        SUM(CASE WHEN ' . $webCondition . ' THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS vlastni_web,
        SUM(CASE WHEN ' . $manualCondition . ' THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS rucni_obj,
        SUM(CASE WHEN NOT ' . $knownCondition . ' THEN COALESCE(c.cena_celk, 0) ELSE 0 END) AS ostatni
    FROM objednavky_restia o
    LEFT JOIN cis_obj_platforma cp
        ON cp.id_platforma = o.id_platforma
    LEFT JOIN obj_ceny c
        ON c.id_obj = o.id_obj
' . $whereSql;

$stmtSummary = $conn->prepare($summarySql);
if ($stmtSummary === false) {
    $errorHtml = '<p class="card_text txt_cervena odstup_vnejsi_0">TOP REPORT se nepodařilo načíst.</p>';
    $card_min_html = $errorHtml;
    $card_max_html = $errorHtml;
    return;
}

$bindSummary = [];
$bindSummary[] = $whereTypes;
foreach ($whereParams as $key => $value) {
    $bindSummary[] = &$whereParams[$key];
}
call_user_func_array([$stmtSummary, 'bind_param'], $bindSummary);
$stmtSummary->execute();
$summaryResult = $stmtSummary->get_result();
if ($summaryResult instanceof mysqli_result) {
    $summaryRow = $summaryResult->fetch_assoc() ?: [];
    $topSummary['trzba'] = (float)($summaryRow['trzba'] ?? 0.0);
    $topSummary['objednavky'] = (int)($summaryRow['objednavky'] ?? 0);
    $topSummary['report_days'] = (int)($summaryRow['report_days'] ?? 0);
    $topSummary['branch_count'] = (int)($summaryRow['branch_count'] ?? 0);
    foreach (['wolt', 'bolt', 'foodora', 'vlastni_web', 'rucni_obj', 'ostatni'] as $channelKey) {
        $channelTotals[$channelKey] = (float)($summaryRow[$channelKey] ?? 0.0);
    }
    $summaryResult->free();
}
$stmtSummary->close();

$branchRows = [];
if ($isMaxRender) {
    $branchMap = [];
    foreach ($branchMetaById as $branchId => $meta) {
        $branchColor = trim((string)($meta['color'] ?? ''));
        if ($branchColor === '') {
            $branchColor = $safeColors[$colorIndex % count($safeColors)];
            $colorIndex++;
        }
        $branchMap[$branchId] = [
            'id_pob' => $branchId,
            'nazev' => trim((string)($meta['nazev'] ?? 'Pobočka')),
            'color' => $branchColor,
            'trzba' => 0.0,
            'objednavky' => 0,
            'hodiny_celkem' => 0.0,
            'trzba_na_hodinu' => 0.0,
        ];
    }
    $branchSql = '
        SELECT
            o.id_pob,
            p.nazev,
            COALESCE(p.pob_color, "") AS pob_color,
            COUNT(*) AS objednavky,
            SUM(COALESCE(c.cena_celk, 0)) AS trzba
        FROM objednavky_restia o FORCE INDEX (idx_objednavky_restia_idpob_created_at)
        INNER JOIN pobocka p
            ON p.id_pob = o.id_pob
        LEFT JOIN obj_ceny c
            ON c.id_obj = o.id_obj
    ' . $whereSql . '
        GROUP BY o.id_pob, p.nazev, p.pob_color
        ORDER BY o.id_pob ASC
    ';

    $stmtBranch = $conn->prepare($branchSql);
    if ($stmtBranch !== false) {
        $bindBranch = [];
        $bindBranch[] = $whereTypes;
        foreach ($whereParams as $key => $value) {
            $bindBranch[] = &$whereParams[$key];
        }
        call_user_func_array([$stmtBranch, 'bind_param'], $bindBranch);
        $stmtBranch->execute();
        $branchResult = $stmtBranch->get_result();

        if ($branchResult instanceof mysqli_result) {
            while ($row = $branchResult->fetch_assoc()) {
                $branchId = (int)($row['id_pob'] ?? 0);
                if ($branchId <= 0) {
                    continue;
                }
                $branchColor = trim((string)($row['pob_color'] ?? ''));
                if ($branchColor === '') {
                    $branchColor = $safeColors[$colorIndex % count($safeColors)];
                    $colorIndex++;
                }

                $branchMap[$branchId] = [
                    'id_pob' => $branchId,
                    'nazev' => trim((string)($row['nazev'] ?? 'Pobočka')),
                    'color' => $branchColor,
                    'trzba' => (float)($row['trzba'] ?? 0.0),
                    'objednavky' => (int)($row['objednavky'] ?? 0),
                    'hodiny_celkem' => 0.0,
                    'trzba_na_hodinu' => 0.0,
                ];
            }
            $branchResult->free();
        }
        $stmtBranch->close();
    }

    $hoursSql = '
        SELECT
            r.id_pob,
            SUM(COALESCE(ro.odpracovano, 0)) AS hodiny_celkem
        FROM reporty r
        LEFT JOIN reporty_osoby ro
            ON ro.id_reportu = r.id_reportu
        WHERE r.platny = 1
          AND r.datum_reportu >= ?
          AND r.datum_reportu <= ?
    ';

    $hoursTypes = 'ss';
    $hoursParams = [$dateOd, $dateDo];

    if ($selectedPob !== []) {
        $placeholders = implode(',', array_fill(0, count($selectedPob), '?'));
        $hoursSql .= ' AND r.id_pob IN (' . $placeholders . ')';
        $hoursTypes .= str_repeat('i', count($selectedPob));
        foreach ($selectedPob as $idPob) {
            $hoursParams[] = $idPob;
        }
    }

    $hoursSql .= '
        GROUP BY r.id_pob
    ';

    $stmtHours = $conn->prepare($hoursSql);
    if ($stmtHours !== false) {
        $bindHours = [];
        $bindHours[] = $hoursTypes;
        foreach ($hoursParams as $key => $value) {
            $bindHours[] = &$hoursParams[$key];
        }
        call_user_func_array([$stmtHours, 'bind_param'], $bindHours);
        $stmtHours->execute();
        $hoursResult = $stmtHours->get_result();

        if ($hoursResult instanceof mysqli_result) {
            while ($row = $hoursResult->fetch_assoc()) {
                $branchId = (int)($row['id_pob'] ?? 0);
                if ($branchId <= 0 || !isset($branchMap[$branchId])) {
                    continue;
                }
                $hours = (float)($row['hodiny_celkem'] ?? 0.0);
                $branchMap[$branchId]['hodiny_celkem'] = $hours;
                $topSummary['hodiny_celkem'] += $hours;
            }
            $hoursResult->free();
        }
        $stmtHours->close();
    }

    $branchRows = array_values($branchMap);
    foreach ($branchRows as &$branchRow) {
        $branchRow['trzba_na_hodinu'] = $branchRow['hodiny_celkem'] > 0
            ? ($branchRow['trzba'] / $branchRow['hodiny_celkem'])
            : 0.0;
    }
    unset($branchRow);

    usort($branchRows, static function (array $a, array $b): int {
        return ((int)($a['id_pob'] ?? 0)) <=> ((int)($b['id_pob'] ?? 0));
    });
}

$channelRows = [];
foreach (['wolt', 'bolt', 'foodora', 'vlastni_web', 'rucni_obj', 'ostatni'] as $channelKey) {
    $value = (float)($channelTotals[$channelKey] ?? 0.0);
    if ($channelKey === 'ostatni' && $value <= 0.0) {
        continue;
    }
    $channelRows[] = [
        'label' => (string)$channelMeta[$channelKey]['label'],
        'value' => $value,
        'color' => (string)$channelMeta[$channelKey]['color'],
    ];
}

$channelRows[] = [
    'label' => 'Celkem',
    'value' => (float)$topSummary['trzba'],
    'color' => '#111827',
];

$managerPayload = null;
if ($isMaxRender) {
    $monthKeys = [];
    $monthLabels = [];
    $monthCursor = $periodOd->modify('first day of this month')->setTime(0, 0, 0);
    $monthEnd = $periodDo->modify('first day of next month')->setTime(0, 0, 0);
    while ($monthCursor < $monthEnd) {
        $monthKey = $monthCursor->format('Y-m-01');
        $monthKeys[] = $monthKey;
        $monthLabels[$monthKey] = $monthCursor->format('m/Y');
        $monthCursor = $monthCursor->modify('+1 month');
    }

    $weekdayLabels = [
        0 => 'Po',
        1 => 'Út',
        2 => 'St',
        3 => 'Čt',
        4 => 'Pá',
        5 => 'So',
        6 => 'Ne',
    ];
    $hourLabels = ['10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '00', '01', '02', '03'];
    $hourLabelLookup = array_fill_keys($hourLabels, true);

    $branchAnalytics = [];
    foreach ($branchMetaById as $branchId => $meta) {
        $branchColor = trim((string)($meta['color'] ?? ''));
        if ($branchColor === '') {
            $branchColor = $safeColors[$branchId % count($safeColors)];
        }
        $branchAnalytics[$branchId] = [
            'id_pob' => $branchId,
            'label' => trim((string)($meta['nazev'] ?? 'Pobočka')),
            'color' => $branchColor,
            'trzba' => 0.0,
            'objednavky' => 0,
            'hodiny' => 0.0,
            'storna_ks' => 0,
            'storna_kc' => 0.0,
            'zpozdene' => 0,
            'channel_revenue' => array_fill_keys(array_keys($channelMeta), 0.0),
            'channel_orders' => array_fill_keys(array_keys($channelMeta), 0),
            'kosik' => 0.0,
            'trzba_hodina' => 0.0,
        ];
    }

    $monthAnalytics = [];
    foreach ($monthKeys as $monthKey) {
        $monthAnalytics[$monthKey] = [
            'label' => $monthLabels[$monthKey] ?? $monthKey,
            'trzba' => 0.0,
            'objednavky' => 0,
            'hodiny' => 0.0,
            'storna_ks' => 0,
            'storna_kc' => 0.0,
            'zpozdene' => 0,
            'kosik' => 0.0,
            'trzba_hodina' => 0.0,
        ];
    }

    $weekdayAnalytics = [];
    foreach ($weekdayLabels as $weekdayIndex => $weekdayLabel) {
        $weekdayAnalytics[$weekdayIndex] = [
            'label' => $weekdayLabel,
            'trzba' => 0.0,
            'objednavky' => 0,
            'hodiny' => 0.0,
            'storna_ks' => 0,
            'storna_kc' => 0.0,
            'zpozdene' => 0,
            'kosik' => 0.0,
            'trzba_hodina' => 0.0,
        ];
    }

    $hourAnalytics = [];
    foreach ($hourLabels as $hourLabel) {
        $hourAnalytics[$hourLabel] = [
            'label' => $hourLabel . ':00',
            'trzba' => 0.0,
            'objednavky' => 0,
            'kosik' => 0.0,
        ];
    }

    $channelAnalytics = [];
    foreach ($channelMeta as $channelKey => $meta) {
        $channelAnalytics[$channelKey] = [
            'key' => $channelKey,
            'label' => (string)$meta['label'],
            'color' => (string)$meta['color'],
            'trzba' => 0.0,
            'objednavky' => 0,
            'kosik' => 0.0,
        ];
    }

    $analyticsSql = '
        SELECT
            o.id_pob,
            DATE_FORMAT(o.restia_created_at, "%Y-%m-01") AS mesic,
            WEEKDAY(o.restia_created_at) AS den_tydne,
            LPAD(HOUR(o.restia_created_at), 2, "0") AS hodina,
            CASE
                WHEN ' . $woltCondition . ' THEN "wolt"
                WHEN ' . $boltCondition . ' THEN "bolt"
                WHEN ' . $foodoraCondition . ' THEN "foodora"
                WHEN ' . $webCondition . ' THEN "vlastni_web"
                WHEN ' . $manualCondition . ' THEN "rucni_obj"
                ELSE "ostatni"
            END AS kanal,
            COUNT(*) AS objednavky,
            SUM(COALESCE(c.cena_celk, 0)) AS trzba
        FROM objednavky_restia o FORCE INDEX (idx_objednavky_restia_idpob_created_at)
        LEFT JOIN cis_obj_platforma cp
            ON cp.id_platforma = o.id_platforma
        LEFT JOIN obj_ceny c
            ON c.id_obj = o.id_obj
    ' . $whereSql . '
        GROUP BY o.id_pob, mesic, den_tydne, hodina, kanal
    ';

    $stmtAnalytics = $conn->prepare($analyticsSql);
    if ($stmtAnalytics !== false) {
        $bindAnalytics = [];
        $bindAnalytics[] = $whereTypes;
        foreach ($whereParams as $key => $value) {
            $bindAnalytics[] = &$whereParams[$key];
        }
        call_user_func_array([$stmtAnalytics, 'bind_param'], $bindAnalytics);
        $stmtAnalytics->execute();
        $analyticsResult = $stmtAnalytics->get_result();

        if ($analyticsResult instanceof mysqli_result) {
            while ($row = $analyticsResult->fetch_assoc()) {
                $branchId = (int)($row['id_pob'] ?? 0);
                $monthKey = (string)($row['mesic'] ?? '');
                $weekdayIndex = (int)($row['den_tydne'] ?? -1);
                $hourKey = (string)($row['hodina'] ?? '');
                $channelKey = (string)($row['kanal'] ?? 'ostatni');
                $ordersCount = (int)($row['objednavky'] ?? 0);
                $revenue = (float)($row['trzba'] ?? 0.0);

                if (isset($branchAnalytics[$branchId])) {
                    $branchAnalytics[$branchId]['trzba'] += $revenue;
                    $branchAnalytics[$branchId]['objednavky'] += $ordersCount;
                    if (isset($branchAnalytics[$branchId]['channel_revenue'][$channelKey])) {
                        $branchAnalytics[$branchId]['channel_revenue'][$channelKey] += $revenue;
                    }
                    if (isset($branchAnalytics[$branchId]['channel_orders'][$channelKey])) {
                        $branchAnalytics[$branchId]['channel_orders'][$channelKey] += $ordersCount;
                    }
                }

                if (isset($monthAnalytics[$monthKey])) {
                    $monthAnalytics[$monthKey]['trzba'] += $revenue;
                    $monthAnalytics[$monthKey]['objednavky'] += $ordersCount;
                }

                if (isset($weekdayAnalytics[$weekdayIndex])) {
                    $weekdayAnalytics[$weekdayIndex]['trzba'] += $revenue;
                    $weekdayAnalytics[$weekdayIndex]['objednavky'] += $ordersCount;
                }

                if (isset($hourLabelLookup[$hourKey], $hourAnalytics[$hourKey])) {
                    $hourAnalytics[$hourKey]['trzba'] += $revenue;
                    $hourAnalytics[$hourKey]['objednavky'] += $ordersCount;
                }

                if (isset($channelAnalytics[$channelKey])) {
                    $channelAnalytics[$channelKey]['trzba'] += $revenue;
                    $channelAnalytics[$channelKey]['objednavky'] += $ordersCount;
                }
            }
            $analyticsResult->free();
        }
        $stmtAnalytics->close();
    }

    $hoursAnalyticsSql = '
        SELECT
            r.id_pob,
            DATE_FORMAT(r.datum_reportu, "%Y-%m-01") AS mesic,
            WEEKDAY(r.datum_reportu) AS den_tydne,
            SUM(COALESCE(ro.odpracovano, 0)) AS hodiny
        FROM reporty r
        LEFT JOIN reporty_osoby ro
            ON ro.id_reportu = r.id_reportu
        WHERE r.platny = 1
          AND r.datum_reportu >= ?
          AND r.datum_reportu <= ?
    ';

    $hoursAnalyticsTypes = 'ss';
    $hoursAnalyticsParams = [$dateOd, $dateDo];
    if ($selectedPob !== []) {
        $placeholders = implode(',', array_fill(0, count($selectedPob), '?'));
        $hoursAnalyticsSql .= ' AND r.id_pob IN (' . $placeholders . ')';
        $hoursAnalyticsTypes .= str_repeat('i', count($selectedPob));
        foreach ($selectedPob as $idPob) {
            $hoursAnalyticsParams[] = $idPob;
        }
    }
    $hoursAnalyticsSql .= '
        GROUP BY r.id_pob, mesic, den_tydne
    ';

    $stmtHoursAnalytics = $conn->prepare($hoursAnalyticsSql);
    if ($stmtHoursAnalytics !== false) {
        $bindHoursAnalytics = [];
        $bindHoursAnalytics[] = $hoursAnalyticsTypes;
        foreach ($hoursAnalyticsParams as $key => $value) {
            $bindHoursAnalytics[] = &$hoursAnalyticsParams[$key];
        }
        call_user_func_array([$stmtHoursAnalytics, 'bind_param'], $bindHoursAnalytics);
        $stmtHoursAnalytics->execute();
        $hoursAnalyticsResult = $stmtHoursAnalytics->get_result();

        if ($hoursAnalyticsResult instanceof mysqli_result) {
            while ($row = $hoursAnalyticsResult->fetch_assoc()) {
                $branchId = (int)($row['id_pob'] ?? 0);
                $monthKey = (string)($row['mesic'] ?? '');
                $weekdayIndex = (int)($row['den_tydne'] ?? -1);
                $hoursValue = (float)($row['hodiny'] ?? 0.0);

                if (isset($branchAnalytics[$branchId])) {
                    $branchAnalytics[$branchId]['hodiny'] += $hoursValue;
                }
                if (isset($monthAnalytics[$monthKey])) {
                    $monthAnalytics[$monthKey]['hodiny'] += $hoursValue;
                }
                if (isset($weekdayAnalytics[$weekdayIndex])) {
                    $weekdayAnalytics[$weekdayIndex]['hodiny'] += $hoursValue;
                }
            }
            $hoursAnalyticsResult->free();
        }
        $stmtHoursAnalytics->close();
    }

    $cancelWhereSql = '
        WHERE o.restia_created_at IS NOT NULL
          AND o.restia_created_at >= ?
          AND o.restia_created_at < ?
    ';
    $cancelTypes = 'ss';
    $cancelParams = [$periodOdSql, $periodDoSql];
    if ($cancelStateIds !== []) {
        $cancelWhereSql .= ' AND o.id_stav IN (' . implode(',', array_map('intval', $cancelStateIds)) . ')';
    } else {
        $cancelWhereSql .= ' AND 1 = 0';
    }
    if ($selectedPob !== []) {
        $placeholders = implode(',', array_fill(0, count($selectedPob), '?'));
        $cancelWhereSql .= ' AND o.id_pob IN (' . $placeholders . ')';
        $cancelTypes .= str_repeat('i', count($selectedPob));
        foreach ($selectedPob as $idPob) {
            $cancelParams[] = $idPob;
        }
    }

    $cancelAnalyticsSql = '
        SELECT
            o.id_pob,
            DATE_FORMAT(o.restia_created_at, "%Y-%m-01") AS mesic,
            WEEKDAY(o.restia_created_at) AS den_tydne,
            COUNT(*) AS storna_ks,
            SUM(COALESCE(c.cena_celk, 0)) AS storna_kc
        FROM objednavky_restia o FORCE INDEX (idx_objednavky_restia_idpob_created_at)
        LEFT JOIN obj_ceny c
            ON c.id_obj = o.id_obj
    ' . $cancelWhereSql . '
        GROUP BY o.id_pob, mesic, den_tydne
    ';

    $stmtCancelAnalytics = $conn->prepare($cancelAnalyticsSql);
    if ($stmtCancelAnalytics !== false) {
        $bindCancelAnalytics = [];
        $bindCancelAnalytics[] = $cancelTypes;
        foreach ($cancelParams as $key => $value) {
            $bindCancelAnalytics[] = &$cancelParams[$key];
        }
        call_user_func_array([$stmtCancelAnalytics, 'bind_param'], $bindCancelAnalytics);
        $stmtCancelAnalytics->execute();
        $cancelAnalyticsResult = $stmtCancelAnalytics->get_result();

        if ($cancelAnalyticsResult instanceof mysqli_result) {
            while ($row = $cancelAnalyticsResult->fetch_assoc()) {
                $branchId = (int)($row['id_pob'] ?? 0);
                $monthKey = (string)($row['mesic'] ?? '');
                $weekdayIndex = (int)($row['den_tydne'] ?? -1);
                $cancelCount = (int)($row['storna_ks'] ?? 0);
                $cancelValue = (float)($row['storna_kc'] ?? 0.0);

                if (isset($branchAnalytics[$branchId])) {
                    $branchAnalytics[$branchId]['storna_ks'] += $cancelCount;
                    $branchAnalytics[$branchId]['storna_kc'] += $cancelValue;
                }
                if (isset($monthAnalytics[$monthKey])) {
                    $monthAnalytics[$monthKey]['storna_ks'] += $cancelCount;
                    $monthAnalytics[$monthKey]['storna_kc'] += $cancelValue;
                }
                if (isset($weekdayAnalytics[$weekdayIndex])) {
                    $weekdayAnalytics[$weekdayIndex]['storna_ks'] += $cancelCount;
                    $weekdayAnalytics[$weekdayIndex]['storna_kc'] += $cancelValue;
                }
            }
            $cancelAnalyticsResult->free();
        }
        $stmtCancelAnalytics->close();
    }

    $delayAnalyticsSql = '
        SELECT
            o.id_pob,
            DATE_FORMAT(ca.cas_vytvor, "%Y-%m-01") AS mesic,
            WEEKDAY(ca.cas_vytvor) AS den_tydne,
            SUM(
                CASE
                    WHEN ca.cas_disp >= ca.cas_vytvor THEN TIMESTAMPDIFF(MINUTE, ca.cas_vytvor, ca.cas_disp)
                    ELSE TIMESTAMPDIFF(MINUTE, ca.cas_vytvor, DATE_ADD(ca.cas_disp, INTERVAL 1 DAY))
                END
            ) AS zpozdene
        FROM obj_casy ca
        INNER JOIN objednavky_restia o
            ON o.id_obj = ca.id_obj
        WHERE ca.cas_vytvor IS NOT NULL
          AND ca.cas_disp IS NOT NULL
          AND ca.cas_vytvor >= ?
          AND ca.cas_vytvor < ?
    ';

    $delayTypes = 'ss';
    $delayParams = [$periodOdSql, $periodDoSql];
    if ($selectedPob !== []) {
        $placeholders = implode(',', array_fill(0, count($selectedPob), '?'));
        $delayAnalyticsSql .= ' AND o.id_pob IN (' . $placeholders . ')';
        $delayTypes .= str_repeat('i', count($selectedPob));
        foreach ($selectedPob as $idPob) {
            $delayParams[] = $idPob;
        }
    }
    if ($cancelStateIds !== []) {
        $delayAnalyticsSql .= ' AND (o.id_stav IS NULL OR o.id_stav NOT IN (' . implode(',', array_map('intval', $cancelStateIds)) . '))';
    }
    $delayAnalyticsSql .= '
        GROUP BY o.id_pob, mesic, den_tydne
    ';

    $stmtDelayAnalytics = $conn->prepare($delayAnalyticsSql);
    if ($stmtDelayAnalytics !== false) {
        $bindDelayAnalytics = [];
        $bindDelayAnalytics[] = $delayTypes;
        foreach ($delayParams as $key => $value) {
            $bindDelayAnalytics[] = &$delayParams[$key];
        }
        call_user_func_array([$stmtDelayAnalytics, 'bind_param'], $bindDelayAnalytics);
        $stmtDelayAnalytics->execute();
        $delayAnalyticsResult = $stmtDelayAnalytics->get_result();

        if ($delayAnalyticsResult instanceof mysqli_result) {
            while ($row = $delayAnalyticsResult->fetch_assoc()) {
                $branchId = (int)($row['id_pob'] ?? 0);
                $monthKey = (string)($row['mesic'] ?? '');
                $weekdayIndex = (int)($row['den_tydne'] ?? -1);
                $delayValue = (float)($row['zpozdene'] ?? 0.0);

                if (isset($branchAnalytics[$branchId])) {
                    $branchAnalytics[$branchId]['zpozdene'] += $delayValue;
                }
                if (isset($monthAnalytics[$monthKey])) {
                    $monthAnalytics[$monthKey]['zpozdene'] += $delayValue;
                }
                if (isset($weekdayAnalytics[$weekdayIndex])) {
                    $weekdayAnalytics[$weekdayIndex]['zpozdene'] += $delayValue;
                }
            }
            $delayAnalyticsResult->free();
        }
        $stmtDelayAnalytics->close();
    }

    foreach ($branchAnalytics as &$branchMetric) {
        $ordersCount = (int)$branchMetric['objednavky'];
        $hoursValue = (float)$branchMetric['hodiny'];
        $revenue = (float)$branchMetric['trzba'];
        $branchMetric['kosik'] = $ordersCount > 0 ? ($revenue / $ordersCount) : 0.0;
        $branchMetric['trzba_hodina'] = $hoursValue > 0 ? ($revenue / $hoursValue) : 0.0;
    }
    unset($branchMetric);

    foreach ($monthAnalytics as &$monthMetric) {
        $ordersCount = (int)$monthMetric['objednavky'];
        $hoursValue = (float)$monthMetric['hodiny'];
        $revenue = (float)$monthMetric['trzba'];
        $monthMetric['kosik'] = $ordersCount > 0 ? ($revenue / $ordersCount) : 0.0;
        $monthMetric['trzba_hodina'] = $hoursValue > 0 ? ($revenue / $hoursValue) : 0.0;
    }
    unset($monthMetric);

    foreach ($weekdayAnalytics as &$weekdayMetric) {
        $ordersCount = (int)$weekdayMetric['objednavky'];
        $hoursValue = (float)$weekdayMetric['hodiny'];
        $revenue = (float)$weekdayMetric['trzba'];
        $weekdayMetric['kosik'] = $ordersCount > 0 ? ($revenue / $ordersCount) : 0.0;
        $weekdayMetric['trzba_hodina'] = $hoursValue > 0 ? ($revenue / $hoursValue) : 0.0;
    }
    unset($weekdayMetric);

    foreach ($hourAnalytics as &$hourMetric) {
        $ordersCount = (int)$hourMetric['objednavky'];
        $revenue = (float)$hourMetric['trzba'];
        $hourMetric['kosik'] = $ordersCount > 0 ? ($revenue / $ordersCount) : 0.0;
    }
    unset($hourMetric);

    foreach ($channelAnalytics as &$channelMetric) {
        $ordersCount = (int)$channelMetric['objednavky'];
        $revenue = (float)$channelMetric['trzba'];
        $channelMetric['kosik'] = $ordersCount > 0 ? ($revenue / $ordersCount) : 0.0;
    }
    unset($channelMetric);

    $metricMeta = [
        'trzby' => ['label' => 'Tržby', 'unit' => 'money'],
        'objednavky' => ['label' => 'Objednávky', 'unit' => 'count'],
        'hodiny' => ['label' => 'Hodiny', 'unit' => 'hours'],
        'kosik' => ['label' => 'Nákupní košík', 'unit' => 'money'],
        'storna' => ['label' => 'Storna', 'unit' => 'count'],
        'zpozdene' => ['label' => 'Zpožděné obj.', 'unit' => 'count'],
        'trzba_hodina' => ['label' => 'Tržba / hodina', 'unit' => 'money_hour'],
        'kanaly_trzby' => ['label' => 'Kanály / tržby', 'unit' => 'money'],
        'kanaly_objednavky' => ['label' => 'Kanály / objednávky', 'unit' => 'count'],
    ];
    $viewMeta = [
        'pobocky' => 'Pobočky',
        'trend' => 'Trend měsíců',
        'tyden' => 'Dny v týdnu',
        'hodiny' => 'Hodiny dne',
        'vykon' => 'Výkon poboček',
        'kanaly' => 'Kanály',
    ];
    $metricMeta['zpozdene'] = ['label' => 'Zpozdeni', 'unit' => 'minutes'];

    $availableViews = [
        'trzby' => ['pobocky', 'trend', 'tyden', 'hodiny'],
        'objednavky' => ['pobocky', 'trend', 'tyden', 'hodiny'],
        'hodiny' => ['pobocky', 'trend', 'tyden'],
        'kosik' => ['pobocky', 'trend', 'tyden', 'hodiny'],
        'storna' => ['pobocky', 'trend', 'tyden'],
        'zpozdene' => ['pobocky', 'trend', 'tyden'],
        'trzba_hodina' => ['pobocky', 'trend', 'tyden', 'vykon'],
        'kanaly_trzby' => ['kanaly'],
        'kanaly_objednavky' => ['kanaly'],
    ];

    $buildHBarGraph = static function (array $rows, string $unit): array {
        return [
            'kind' => 'hbars',
            'unit' => $unit,
            'rows' => $rows,
        ];
    };
    $buildVBarGraph = static function (array $rows, string $unit): array {
        return [
            'kind' => 'vbars',
            'unit' => $unit,
            'rows' => $rows,
        ];
    };
    $buildTable = static function (array $columns, array $rows): array {
        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    };
    $sortRowsDesc = static function (array &$rows): void {
        usort($rows, static function (array $a, array $b): int {
            $cmp = ((float)($b['value'] ?? 0)) <=> ((float)($a['value'] ?? 0));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });
    };

    $panels = [];

    $branchViewBuilder = static function (
        array $branchAnalytics,
        string $metricKey,
        array $metricMeta,
        callable $buildHBarGraph,
        callable $buildTable,
        callable $sortRowsDesc
    ): array {
        $graphRows = [];
        $tableRows = [];
        foreach ($branchAnalytics as $row) {
            $value = 0.0;
            $secondary = '';
            if ($metricKey === 'trzby') {
                $value = (float)$row['trzba'];
                $secondary = 'Obj. ' . (int)$row['objednavky'] . ' | Hodiny ' . number_format((float)$row['hodiny'], 1, ',', ' ');
            } elseif ($metricKey === 'objednavky') {
                $value = (float)$row['objednavky'];
                $secondary = 'Tržba ' . number_format((float)$row['trzba'], 0, ',', ' ');
            } elseif ($metricKey === 'hodiny') {
                $value = (float)$row['hodiny'];
                $secondary = 'Tržba ' . number_format((float)$row['trzba'], 0, ',', ' ');
            } elseif ($metricKey === 'kosik') {
                $value = (float)$row['kosik'];
                $secondary = 'Obj. ' . (int)$row['objednavky'] . ' | Tržba ' . number_format((float)$row['trzba'], 0, ',', ' ');
            } elseif ($metricKey === 'storna') {
                $value = (float)$row['storna_ks'];
                $secondary = 'Kč ' . number_format((float)$row['storna_kc'], 0, ',', ' ');
            } elseif ($metricKey === 'zpozdene') {
                $value = (float)$row['zpozdene'];
                $secondary = 'Hodiny ' . number_format((float)$row['hodiny'], 1, ',', ' ');
            } elseif ($metricKey === 'trzba_hodina') {
                $value = (float)$row['trzba_hodina'];
                $secondary = 'Tržba ' . number_format((float)$row['trzba'], 0, ',', ' ') . ' | Hodiny ' . number_format((float)$row['hodiny'], 1, ',', ' ');
            }

            $graphRows[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'label' => (string)$row['label'],
                'value' => $value,
                'color' => (string)$row['color'],
                'secondary' => $secondary,
            ];
            $tableRows[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'label' => (string)$row['label'],
                'value' => $value,
                'trzba' => (float)$row['trzba'],
                'objednavky' => (int)$row['objednavky'],
                'hodiny' => (float)$row['hodiny'],
                'kosik' => (float)$row['kosik'],
                'storna_ks' => (int)$row['storna_ks'],
                'storna_kc' => (float)$row['storna_kc'],
                'zpozdene' => (int)$row['zpozdene'],
                'trzba_hodina' => (float)$row['trzba_hodina'],
            ];
        }
        usort($graphRows, static fn (array $a, array $b): int => ((int)($a['id_pob'] ?? 0)) <=> ((int)($b['id_pob'] ?? 0)));
        usort($tableRows, static fn (array $a, array $b): int => ((int)($a['id_pob'] ?? 0)) <=> ((int)($b['id_pob'] ?? 0)));

        return [
            'graph' => $buildHBarGraph($graphRows, (string)$metricMeta[$metricKey]['unit']),
            'table' => $buildTable(
                [
                    ['key' => 'label', 'label' => 'Pobočka', 'type' => 'text'],
                    ['key' => 'value', 'label' => (string)$metricMeta[$metricKey]['label'], 'type' => (string)$metricMeta[$metricKey]['unit']],
                    ['key' => 'trzba', 'label' => 'Tržba', 'type' => 'money'],
                    ['key' => 'objednavky', 'label' => 'Obj.', 'type' => 'count'],
                    ['key' => 'hodiny', 'label' => 'Hodiny', 'type' => 'hours'],
                    ['key' => 'trzba_hodina', 'label' => 'Kč / hod', 'type' => 'money_hour'],
                ],
                $tableRows
            ),
        ];
    };

    foreach (['trzby', 'objednavky', 'hodiny', 'kosik', 'storna', 'zpozdene', 'trzba_hodina'] as $metricKey) {
        $branchPanel = $branchViewBuilder($branchAnalytics, $metricKey, $metricMeta, $buildHBarGraph, $buildTable, $sortRowsDesc);
        $panels[$metricKey . '|pobocky'] = [
            'title' => (string)$metricMeta[$metricKey]['label'] . ' podle poboček',
            'graph' => $branchPanel['graph'],
            'table' => $branchPanel['table'],
        ];
    }

    foreach (['trzby', 'objednavky', 'hodiny', 'kosik', 'storna', 'zpozdene', 'trzba_hodina'] as $metricKey) {
        $rows = [];
        $tableRows = [];
        foreach ($monthKeys as $monthKey) {
            $metricRow = $monthAnalytics[$monthKey] ?? null;
            if ($metricRow === null) {
                continue;
            }
            $value = 0.0;
            if ($metricKey === 'trzby') { $value = (float)$metricRow['trzba']; }
            elseif ($metricKey === 'objednavky') { $value = (float)$metricRow['objednavky']; }
            elseif ($metricKey === 'hodiny') { $value = (float)$metricRow['hodiny']; }
            elseif ($metricKey === 'kosik') { $value = (float)$metricRow['kosik']; }
            elseif ($metricKey === 'storna') { $value = (float)$metricRow['storna_ks']; }
            elseif ($metricKey === 'zpozdene') { $value = (float)$metricRow['zpozdene']; }
            elseif ($metricKey === 'trzba_hodina') { $value = (float)$metricRow['trzba_hodina']; }
            $rows[] = ['label' => (string)$metricRow['label'], 'value' => $value, 'color' => '#2563eb'];
            $tableRows[] = [
                'label' => (string)$metricRow['label'],
                'value' => $value,
                'trzba' => (float)$metricRow['trzba'],
                'objednavky' => (int)$metricRow['objednavky'],
                'hodiny' => (float)$metricRow['hodiny'],
                'storna_ks' => (int)$metricRow['storna_ks'],
                'zpozdene' => (int)$metricRow['zpozdene'],
                'kosik' => (float)$metricRow['kosik'],
                'trzba_hodina' => (float)$metricRow['trzba_hodina'],
            ];
        }
        $panels[$metricKey . '|trend'] = [
            'title' => (string)$metricMeta[$metricKey]['label'] . ' v průběhu období',
            'graph' => $buildVBarGraph($rows, (string)$metricMeta[$metricKey]['unit']),
            'table' => $buildTable(
                [
                    ['key' => 'label', 'label' => 'Měsíc', 'type' => 'text'],
                    ['key' => 'value', 'label' => (string)$metricMeta[$metricKey]['label'], 'type' => (string)$metricMeta[$metricKey]['unit']],
                    ['key' => 'trzba', 'label' => 'Tržba', 'type' => 'money'],
                    ['key' => 'objednavky', 'label' => 'Obj.', 'type' => 'count'],
                    ['key' => 'hodiny', 'label' => 'Hodiny', 'type' => 'hours'],
                    ['key' => 'kosik', 'label' => 'Košík', 'type' => 'money'],
                    ['key' => 'trzba_hodina', 'label' => 'Kč / hod', 'type' => 'money_hour'],
                ],
                $tableRows
            ),
        ];
    }

    foreach (['trzby', 'objednavky', 'hodiny', 'kosik', 'storna', 'zpozdene', 'trzba_hodina'] as $metricKey) {
        $rows = [];
        $tableRows = [];
        foreach ($weekdayAnalytics as $metricRow) {
            $value = 0.0;
            if ($metricKey === 'trzby') { $value = (float)$metricRow['trzba']; }
            elseif ($metricKey === 'objednavky') { $value = (float)$metricRow['objednavky']; }
            elseif ($metricKey === 'hodiny') { $value = (float)$metricRow['hodiny']; }
            elseif ($metricKey === 'kosik') { $value = (float)$metricRow['kosik']; }
            elseif ($metricKey === 'storna') { $value = (float)$metricRow['storna_ks']; }
            elseif ($metricKey === 'zpozdene') { $value = (float)$metricRow['zpozdene']; }
            elseif ($metricKey === 'trzba_hodina') { $value = (float)$metricRow['trzba_hodina']; }
            $rows[] = ['label' => (string)$metricRow['label'], 'value' => $value, 'color' => '#0f766e'];
            $tableRows[] = [
                'label' => (string)$metricRow['label'],
                'value' => $value,
                'trzba' => (float)$metricRow['trzba'],
                'objednavky' => (int)$metricRow['objednavky'],
                'hodiny' => (float)$metricRow['hodiny'],
                'storna_ks' => (int)$metricRow['storna_ks'],
                'zpozdene' => (int)$metricRow['zpozdene'],
                'kosik' => (float)$metricRow['kosik'],
                'trzba_hodina' => (float)$metricRow['trzba_hodina'],
            ];
        }
        $panels[$metricKey . '|tyden'] = [
            'title' => (string)$metricMeta[$metricKey]['label'] . ' podle dnů v týdnu',
            'graph' => $buildVBarGraph($rows, (string)$metricMeta[$metricKey]['unit']),
            'table' => $buildTable(
                [
                    ['key' => 'label', 'label' => 'Den', 'type' => 'text'],
                    ['key' => 'value', 'label' => (string)$metricMeta[$metricKey]['label'], 'type' => (string)$metricMeta[$metricKey]['unit']],
                    ['key' => 'trzba', 'label' => 'Tržba', 'type' => 'money'],
                    ['key' => 'objednavky', 'label' => 'Obj.', 'type' => 'count'],
                    ['key' => 'hodiny', 'label' => 'Hodiny', 'type' => 'hours'],
                    ['key' => 'kosik', 'label' => 'Košík', 'type' => 'money'],
                    ['key' => 'trzba_hodina', 'label' => 'Kč / hod', 'type' => 'money_hour'],
                ],
                $tableRows
            ),
        ];
    }

    foreach (['trzby', 'objednavky', 'kosik'] as $metricKey) {
        $rows = [];
        $tableRows = [];
        foreach ($hourLabels as $hourKey) {
            $metricRow = $hourAnalytics[$hourKey] ?? null;
            if ($metricRow === null) {
                continue;
            }
            $value = 0.0;
            if ($metricKey === 'trzby') { $value = (float)$metricRow['trzba']; }
            elseif ($metricKey === 'objednavky') { $value = (float)$metricRow['objednavky']; }
            elseif ($metricKey === 'kosik') { $value = (float)$metricRow['kosik']; }
            $rows[] = ['label' => (string)$metricRow['label'], 'value' => $value, 'color' => '#7c3aed'];
            $tableRows[] = [
                'label' => (string)$metricRow['label'],
                'value' => $value,
                'trzba' => (float)$metricRow['trzba'],
                'objednavky' => (int)$metricRow['objednavky'],
                'kosik' => (float)$metricRow['kosik'],
            ];
        }
        $panels[$metricKey . '|hodiny'] = [
            'title' => (string)$metricMeta[$metricKey]['label'] . ' podle hodin dne',
            'graph' => $buildVBarGraph($rows, (string)$metricMeta[$metricKey]['unit']),
            'table' => $buildTable(
                [
                    ['key' => 'label', 'label' => 'Hodina', 'type' => 'text'],
                    ['key' => 'value', 'label' => (string)$metricMeta[$metricKey]['label'], 'type' => (string)$metricMeta[$metricKey]['unit']],
                    ['key' => 'trzba', 'label' => 'Tržba', 'type' => 'money'],
                    ['key' => 'objednavky', 'label' => 'Obj.', 'type' => 'count'],
                    ['key' => 'kosik', 'label' => 'Košík', 'type' => 'money'],
                ],
                $tableRows
            ),
        ];
    }

    $performanceRows = [];
    foreach ($branchAnalytics as $row) {
        $performanceRows[] = [
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'label' => (string)$row['label'],
            'value' => (float)$row['trzba_hodina'],
            'color' => (string)$row['color'],
            'secondary' => 'Tržba ' . number_format((float)$row['trzba'], 0, ',', ' ') . ' | Hodiny ' . number_format((float)$row['hodiny'], 1, ',', ' '),
            'trzba' => (float)$row['trzba'],
            'objednavky' => (int)$row['objednavky'],
            'hodiny' => (float)$row['hodiny'],
            'trzba_hodina' => (float)$row['trzba_hodina'],
        ];
    }
    usort($performanceRows, static fn (array $a, array $b): int => ((int)($a['id_pob'] ?? 0)) <=> ((int)($b['id_pob'] ?? 0)));
    $panels['trzba_hodina|vykon'] = [
        'title' => 'Výkon poboček vzhledem k odpracovaným hodinám',
        'graph' => $buildHBarGraph($performanceRows, 'money_hour'),
        'table' => $buildTable(
            [
                ['key' => 'label', 'label' => 'Pobočka', 'type' => 'text'],
                ['key' => 'trzba_hodina', 'label' => 'Kč / hod', 'type' => 'money_hour'],
                ['key' => 'trzba', 'label' => 'Tržba', 'type' => 'money'],
                ['key' => 'hodiny', 'label' => 'Hodiny', 'type' => 'hours'],
                ['key' => 'objednavky', 'label' => 'Obj.', 'type' => 'count'],
            ],
            $performanceRows
        ),
    ];

    foreach (['kanaly_trzby' => 'trzba', 'kanaly_objednavky' => 'objednavky'] as $metricKey => $sourceKey) {
        $rows = [];
        $tableRows = [];
        foreach ($channelAnalytics as $channelKey => $row) {
            $value = (float)($row[$sourceKey] ?? 0);
            if ($sourceKey === 'trzba' && $channelKey === 'ostatni' && $value <= 0) {
                continue;
            }
            $rows[] = [
                'label' => (string)$row['label'],
                'value' => $value,
                'color' => (string)$row['color'],
                'secondary' => 'Obj. ' . (int)$row['objednavky'] . ' | Košík ' . number_format((float)$row['kosik'], 0, ',', ' '),
            ];
            $tableRows[] = [
                'label' => (string)$row['label'],
                'value' => $value,
                'trzba' => (float)$row['trzba'],
                'objednavky' => (int)$row['objednavky'],
                'kosik' => (float)$row['kosik'],
            ];
        }
        $sortRowsDesc($rows);
        usort($tableRows, static fn (array $a, array $b): int => ((float)$b['value']) <=> ((float)$a['value']));
        $panels[$metricKey . '|kanaly'] = [
            'title' => ($sourceKey === 'trzba' ? 'Kanály podle tržby' : 'Kanály podle objednávek'),
            'graph' => $buildHBarGraph($rows, (string)$metricMeta[$metricKey]['unit']),
            'table' => $buildTable(
                [
                    ['key' => 'label', 'label' => 'Kanál', 'type' => 'text'],
                    ['key' => 'value', 'label' => (string)$metricMeta[$metricKey]['label'], 'type' => (string)$metricMeta[$metricKey]['unit']],
                    ['key' => 'trzba', 'label' => 'Tržba', 'type' => 'money'],
                    ['key' => 'objednavky', 'label' => 'Obj.', 'type' => 'count'],
                    ['key' => 'kosik', 'label' => 'Košík', 'type' => 'money'],
                ],
                $tableRows
            ),
        ];
    }

    $managerPayload = [
        'default' => [
            'metric' => 'trzby',
            'view' => 'pobocky',
            'display' => 'graph',
        ],
        'periodLabel' => $periodLabel,
        'metrics' => array_map(
            static fn (string $metricId, array $meta): array => ['id' => $metricId, 'label' => (string)$meta['label']],
            array_keys($metricMeta),
            array_values($metricMeta)
        ),
        'views' => array_map(
            static fn (string $viewId, string $label): array => ['id' => $viewId, 'label' => $label],
            array_keys($viewMeta),
            array_values($viewMeta)
        ),
        'availableViews' => $availableViews,
        'panels' => $panels,
    ];

    $managerMetricMeta = [
        'trzby' => ['label' => 'Tržby', 'unit' => 'money', 'color' => '#2563eb'],
        'objednavky' => ['label' => 'Objednávky', 'unit' => 'count', 'color' => '#0f766e'],
        'hodiny' => ['label' => 'Hodiny', 'unit' => 'hours', 'color' => '#d97706'],
        'kosik' => ['label' => 'Nákupní košík', 'unit' => 'money', 'color' => '#7c3aed'],
        'storna' => ['label' => 'Storna', 'unit' => 'count', 'color' => '#dc2626'],
        'zpozdene' => ['label' => 'Zpoždění', 'unit' => 'minutes', 'color' => '#db2777'],
        'trzba_hodina' => ['label' => 'Tržba / hodina', 'unit' => 'money_hour', 'color' => '#0891b2'],
        'kanaly_trzby' => ['label' => 'Kanály / tržby', 'unit' => 'money', 'color' => '#2563eb'],
        'kanaly_objednavky' => ['label' => 'Kanály / objednávky', 'unit' => 'count', 'color' => '#0f766e'],
    ];
    $managerViewMeta = [
        'pobocky' => 'Pobočky',
        'trend' => 'Trend měsíců',
        'tyden' => 'Dny v týdnu',
        'hodiny' => 'Hodiny dne',
        'vykon' => 'Výkon poboček',
        'kanaly' => 'Kanály',
    ];
    $managerAvailableViews = [
        'trzby' => ['pobocky', 'trend', 'tyden', 'hodiny'],
        'objednavky' => ['pobocky', 'trend', 'tyden', 'hodiny'],
        'hodiny' => ['pobocky', 'trend', 'tyden'],
        'kosik' => ['pobocky', 'trend', 'tyden', 'hodiny'],
        'storna' => ['pobocky', 'trend', 'tyden'],
        'zpozdene' => ['pobocky', 'trend', 'tyden'],
        'trzba_hodina' => ['pobocky', 'trend', 'tyden', 'vykon'],
        'kanaly_trzby' => ['kanaly'],
        'kanaly_objednavky' => ['kanaly'],
    ];
    $managerViewMetrics = [
        'pobocky' => ['trzby', 'objednavky', 'hodiny', 'kosik', 'storna', 'zpozdene', 'trzba_hodina'],
        'trend' => ['trzby', 'objednavky', 'hodiny', 'kosik', 'storna', 'zpozdene', 'trzba_hodina'],
        'tyden' => ['trzby', 'objednavky', 'hodiny', 'kosik', 'storna', 'zpozdene', 'trzba_hodina'],
        'hodiny' => ['trzby', 'objednavky', 'kosik'],
        'vykon' => ['trzba_hodina'],
        'kanaly' => ['kanaly_trzby', 'kanaly_objednavky'],
    ];
    $managerBuildMetricDefs = static function (array $metricKeys, array $meta): array {
        $defs = [];
        foreach ($metricKeys as $metricKey) {
            if (!isset($meta[$metricKey])) {
                continue;
            }
            $defs[] = [
                'id' => $metricKey,
                'label' => (string)$meta[$metricKey]['label'],
                'unit' => (string)$meta[$metricKey]['unit'],
                'color' => (string)$meta[$metricKey]['color'],
            ];
        }
        return $defs;
    };
    $managerBuildColumns = static function (string $firstLabel, array $metricKeys, array $meta): array {
        $columns = [
            ['key' => 'label', 'label' => $firstLabel, 'type' => 'text'],
        ];
        foreach ($metricKeys as $metricKey) {
            if (!isset($meta[$metricKey])) {
                continue;
            }
            $columns[] = [
                'key' => $metricKey,
                'label' => (string)$meta[$metricKey]['label'],
                'type' => (string)$meta[$metricKey]['unit'],
                'metric' => $metricKey,
            ];
        }
        return $columns;
    };
    $managerBuildGraph = static function (string $kind, string $title, array $rows, array $metricKeys, array $meta) use ($managerBuildMetricDefs): array {
        return [
            'kind' => $kind,
            'title' => $title,
            'metrics' => $managerBuildMetricDefs($metricKeys, $meta),
            'rows' => $rows,
        ];
    };
    $managerBuildTable = static function (string $firstLabel, array $rows, array $metricKeys, array $meta) use ($buildTable, $managerBuildColumns): array {
        return $buildTable($managerBuildColumns($firstLabel, $metricKeys, $meta), $rows);
    };

    $managerPanels = [];

    $branchGraphRows = [];
    $branchTableRows = [];
    foreach ($branchAnalytics as $row) {
        $branchGraphRows[] = [
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'label' => (string)$row['label'],
            'color' => (string)($row['color'] ?? '#2563eb'),
            'values' => [
                'trzby' => (float)$row['trzba'],
                'objednavky' => (float)$row['objednavky'],
                'hodiny' => (float)$row['hodiny'],
                'kosik' => (float)$row['kosik'],
                'storna' => (float)$row['storna_ks'],
                'zpozdene' => (float)$row['zpozdene'],
                'trzba_hodina' => (float)$row['trzba_hodina'],
            ],
        ];
        $branchTableRows[] = [
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'label' => (string)$row['label'],
            'trzby' => (float)$row['trzba'],
            'objednavky' => (float)$row['objednavky'],
            'hodiny' => (float)$row['hodiny'],
            'kosik' => (float)$row['kosik'],
            'storna' => (float)$row['storna_ks'],
            'zpozdene' => (float)$row['zpozdene'],
            'trzba_hodina' => (float)$row['trzba_hodina'],
        ];
    }
    usort($branchGraphRows, static fn (array $a, array $b): int => ((int)($a['id_pob'] ?? 0)) <=> ((int)($b['id_pob'] ?? 0)));
    usort($branchTableRows, static fn (array $a, array $b): int => ((int)($a['id_pob'] ?? 0)) <=> ((int)($b['id_pob'] ?? 0)));
    $managerPanels['pobocky'] = [
        'title' => 'Pobočky',
        'graph' => $managerBuildGraph('hbars_multi', 'Pobočky', $branchGraphRows, $managerViewMetrics['pobocky'], $managerMetricMeta),
        'table' => $managerBuildTable('Pobočka', $branchTableRows, $managerViewMetrics['pobocky'], $managerMetricMeta),
    ];

    $trendGraphRows = [];
    $trendTableRows = [];
    foreach ($monthKeys as $monthKey) {
        $metricRow = $monthAnalytics[$monthKey] ?? null;
        if ($metricRow === null) {
            continue;
        }
        $trendGraphRows[] = [
            'label' => (string)$metricRow['label'],
            'values' => [
                'trzby' => (float)$metricRow['trzba'],
                'objednavky' => (float)$metricRow['objednavky'],
                'hodiny' => (float)$metricRow['hodiny'],
                'kosik' => (float)$metricRow['kosik'],
                'storna' => (float)$metricRow['storna_ks'],
                'zpozdene' => (float)$metricRow['zpozdene'],
                'trzba_hodina' => (float)$metricRow['trzba_hodina'],
            ],
        ];
        $trendTableRows[] = [
            'label' => (string)$metricRow['label'],
            'trzby' => (float)$metricRow['trzba'],
            'objednavky' => (float)$metricRow['objednavky'],
            'hodiny' => (float)$metricRow['hodiny'],
            'kosik' => (float)$metricRow['kosik'],
            'storna' => (float)$metricRow['storna_ks'],
            'zpozdene' => (float)$metricRow['zpozdene'],
            'trzba_hodina' => (float)$metricRow['trzba_hodina'],
        ];
    }
    $managerPanels['trend'] = [
        'title' => 'Trend měsíců',
        'graph' => $managerBuildGraph('vbars_multi', 'Trend měsíců', $trendGraphRows, $managerViewMetrics['trend'], $managerMetricMeta),
        'table' => $managerBuildTable('Měsíc', $trendTableRows, $managerViewMetrics['trend'], $managerMetricMeta),
    ];

    $weekdayGraphRows = [];
    $weekdayTableRows = [];
    foreach ($weekdayAnalytics as $metricRow) {
        $weekdayGraphRows[] = [
            'label' => (string)$metricRow['label'],
            'values' => [
                'trzby' => (float)$metricRow['trzba'],
                'objednavky' => (float)$metricRow['objednavky'],
                'hodiny' => (float)$metricRow['hodiny'],
                'kosik' => (float)$metricRow['kosik'],
                'storna' => (float)$metricRow['storna_ks'],
                'zpozdene' => (float)$metricRow['zpozdene'],
                'trzba_hodina' => (float)$metricRow['trzba_hodina'],
            ],
        ];
        $weekdayTableRows[] = [
            'label' => (string)$metricRow['label'],
            'trzby' => (float)$metricRow['trzba'],
            'objednavky' => (float)$metricRow['objednavky'],
            'hodiny' => (float)$metricRow['hodiny'],
            'kosik' => (float)$metricRow['kosik'],
            'storna' => (float)$metricRow['storna_ks'],
            'zpozdene' => (float)$metricRow['zpozdene'],
            'trzba_hodina' => (float)$metricRow['trzba_hodina'],
        ];
    }
    $managerPanels['tyden'] = [
        'title' => 'Dny v týdnu',
        'graph' => $managerBuildGraph('vbars_multi', 'Dny v týdnu', $weekdayGraphRows, $managerViewMetrics['tyden'], $managerMetricMeta),
        'table' => $managerBuildTable('Den', $weekdayTableRows, $managerViewMetrics['tyden'], $managerMetricMeta),
    ];

    $hourGraphRows = [];
    $hourTableRows = [];
    foreach ($hourLabels as $hourKey) {
        $metricRow = $hourAnalytics[$hourKey] ?? null;
        if ($metricRow === null) {
            continue;
        }
        $hourGraphRows[] = [
            'label' => (string)$metricRow['label'],
            'values' => [
                'trzby' => (float)$metricRow['trzba'],
                'objednavky' => (float)$metricRow['objednavky'],
                'kosik' => (float)$metricRow['kosik'],
            ],
        ];
        $hourTableRows[] = [
            'label' => (string)$metricRow['label'],
            'trzby' => (float)$metricRow['trzba'],
            'objednavky' => (float)$metricRow['objednavky'],
            'kosik' => (float)$metricRow['kosik'],
        ];
    }
    $managerPanels['hodiny'] = [
        'title' => 'Hodiny dne',
        'graph' => $managerBuildGraph('vbars_multi', 'Hodiny dne', $hourGraphRows, $managerViewMetrics['hodiny'], $managerMetricMeta),
        'table' => $managerBuildTable('Hodina', $hourTableRows, $managerViewMetrics['hodiny'], $managerMetricMeta),
    ];

    $performanceGraphRows = [];
    $performanceTableRows = [];
    foreach ($branchAnalytics as $row) {
        $performanceGraphRows[] = [
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'label' => (string)$row['label'],
            'secondary' => 'Tržby ' . number_format((float)$row['trzba'], 0, ',', ' ') . ' Kč | Hodiny ' . number_format((float)$row['hodiny'], 1, ',', ' '),
            'values' => [
                'trzba_hodina' => (float)$row['trzba_hodina'],
            ],
        ];
        $performanceTableRows[] = [
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'label' => (string)$row['label'],
            'trzba_hodina' => (float)$row['trzba_hodina'],
        ];
    }
    usort($performanceGraphRows, static fn (array $a, array $b): int => ((int)($a['id_pob'] ?? 0)) <=> ((int)($b['id_pob'] ?? 0)));
    usort($performanceTableRows, static fn (array $a, array $b): int => ((int)($a['id_pob'] ?? 0)) <=> ((int)($b['id_pob'] ?? 0)));
    $managerPanels['vykon'] = [
        'title' => 'Výkon poboček',
        'graph' => $managerBuildGraph('hbars_multi', 'Výkon poboček', $performanceGraphRows, $managerViewMetrics['vykon'], $managerMetricMeta),
        'table' => $managerBuildTable('Pobočka', $performanceTableRows, $managerViewMetrics['vykon'], $managerMetricMeta),
    ];

    $channelGraphRows = [];
    $channelTableRows = [];
    foreach ($channelAnalytics as $channelKey => $row) {
        if ($channelKey === 'ostatni' && (float)($row['trzba'] ?? 0) <= 0 && (int)($row['objednavky'] ?? 0) <= 0) {
            continue;
        }
        $channelGraphRows[] = [
            'label' => (string)$row['label'],
            'secondary' => 'Košík ' . number_format((float)$row['kosik'], 0, ',', ' ') . ' Kč',
            'values' => [
                'kanaly_trzby' => (float)$row['trzba'],
                'kanaly_objednavky' => (float)$row['objednavky'],
            ],
        ];
        $channelTableRows[] = [
            'label' => (string)$row['label'],
            'kanaly_trzby' => (float)$row['trzba'],
            'kanaly_objednavky' => (float)$row['objednavky'],
        ];
    }
    usort($channelGraphRows, static fn (array $a, array $b): int => ((float)($b['values']['kanaly_trzby'] ?? 0)) <=> ((float)($a['values']['kanaly_trzby'] ?? 0)));
    usort($channelTableRows, static fn (array $a, array $b): int => ((float)($b['kanaly_trzby'] ?? 0)) <=> ((float)($a['kanaly_trzby'] ?? 0)));
    $managerPanels['kanaly'] = [
        'title' => 'Kanály',
        'graph' => $managerBuildGraph('hbars_multi', 'Kanály', $channelGraphRows, $managerViewMetrics['kanaly'], $managerMetricMeta),
        'table' => $managerBuildTable('Kanál', $channelTableRows, $managerViewMetrics['kanaly'], $managerMetricMeta),
    ];

    $managerPayload = [
        'default' => [
            'metrics' => ['trzby'],
            'view' => 'pobocky',
            'display' => 'graph',
        ],
        'periodLabel' => $periodLabel,
        'metrics' => array_map(
            static fn (string $metricId, array $meta): array => ['id' => $metricId, 'label' => (string)$meta['label']],
            array_keys($managerMetricMeta),
            array_values($managerMetricMeta)
        ),
        'views' => array_map(
            static fn (string $viewId, string $label): array => ['id' => $viewId, 'label' => $label],
            array_keys($managerViewMeta),
            array_values($managerViewMeta)
        ),
        'availableViews' => $managerAvailableViews,
        'panels' => $managerPanels,
    ];
}

$maxChannelValue = 0.0;
foreach ($channelRows as $channelRow) {
    if ((string)($channelRow['label'] ?? '') === 'Celkem') {
        continue;
    }
    if ($channelRow['value'] > $maxChannelValue) {
        $maxChannelValue = $channelRow['value'];
    }
}

$maxBranchSales = 0.0;
foreach ($branchRows as $branchRow) {
    if ($branchRow['trzba'] > $maxBranchSales) {
        $maxBranchSales = $branchRow['trzba'];
    }
}

$renderMiniChannelBars = static function (array $rows, float $maxValue, float $totalValue, callable $formatMoney, callable $formatRatio): string {
    if ($rows === []) {
        return '<p class="card_text txt_seda odstup_vnejsi_0">Nejsou dostupná data kanálů.</p>';
    }

    $html = '<div class="displ_flex flex_sloupec gap_6">';
    foreach ($rows as $row) {
        $isTotal = ((string)($row['label'] ?? '') === 'Celkem');
        $width = $maxValue > 0 ? max(3.0, min(100.0, ($row['value'] / $maxValue) * 100.0)) : 3.0;
        $share = (!$isTotal && $totalValue > 0) ? (((float)$row['value'] / $totalValue) * 100.0) : 0.0;
        $html .= ''
            . ($isTotal ? '<div style="border-top:1px solid #d1d5db; margin:2px 0 1px 0;"></div>' : '')
            . '<div class="displ_flex gap_4" style="align-items:center;">'
            . '<div class="card_text text_11' . ($isTotal ? ' text_tucny' : '') . '" style="width:84px; line-height:1.05; white-space:nowrap;">' . h((string)$row['label']) . '</div>'
            . ($isTotal
                ? '<div style="flex:1 1 auto; min-width:0; height:15px;"></div>'
                : '<div class="ram_sedy zaobleni_8" style="flex:1 1 auto; min-width:0; height:15px; overflow:hidden; background:#eef2f7;">'
                    . '<div style="height:100%; width:' . h(number_format($width, 2, '.', '')) . '%; background:' . h((string)$row['color']) . ';"></div>'
                . '</div>')
            . '<div class="card_text text_11 txt_r' . ($isTotal ? ' text_tucny' : '') . '" style="width:46px; line-height:1.05; white-space:nowrap;">' . ($isTotal ? '' : h($formatRatio($share))) . '</div>'
            . '<div class="card_text text_11 txt_r' . ($isTotal ? ' text_tucny' : '') . '" style="width:88px; line-height:1.05; white-space:nowrap;">' . h($formatMoney((float)$row['value'])) . '</div>'
            . '</div>';
    }
    $html .= '</div>';

    return $html;
};

$renderChannelBars = static function (array $rows, float $maxValue, callable $formatMoney): string {
    if ($rows === []) {
        return '<p class="card_text txt_seda odstup_vnejsi_0">Nejsou dostupná data kanálů.</p>';
    }

    $html = '<div class="displ_flex flex_sloupec gap_6">';
    foreach ($rows as $row) {
        $width = $maxValue > 0 ? max(4.0, min(100.0, ($row['value'] / $maxValue) * 100.0)) : 4.0;
        $html .= ''
            . '<div>'
            . '<div class="displ_flex jc_mezi gap_8 card_text text_11" style="line-height:1.15;">'
            . '<span>' . h((string)$row['label']) . '</span>'
            . '<strong>' . h($formatMoney((float)$row['value'])) . '</strong>'
            . '</div>'
            . '<div class="ram_sedy zaobleni_8" style="height:10px; margin-top:4px; overflow:hidden; background:#eef2f7;">'
            . '<div style="height:100%; width:' . h(number_format($width, 2, '.', '')) . '%; background:' . h((string)$row['color']) . ';"></div>'
            . '</div>'
            . '</div>';
    }
    $html .= '</div>';

    return $html;
};

$renderComparisonBars = static function (array $rows, float $maxSales, callable $formatMoney, callable $formatHours, callable $formatCount): string {
    if ($rows === []) {
        return '<p class="card_text txt_seda odstup_vnejsi_0">Pro vybrané období nejsou k dispozici data poboček.</p>';
    }

    $html = '<div class="displ_flex flex_sloupec gap_8">';
    foreach ($rows as $row) {
        $width = $maxSales > 0 ? max(6.0, min(100.0, ($row['trzba'] / $maxSales) * 100.0)) : 6.0;
        $html .= ''
            . '<div class="ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8">'
            . '<div class="displ_flex jc_mezi gap_8 card_text" style="align-items:flex-start; line-height:1.15;">'
            . '<strong>' . h((string)$row['nazev']) . '</strong>'
            . '<span>' . h($formatMoney((float)$row['trzba'])) . '</span>'
            . '</div>'
            . '<div class="ram_sedy zaobleni_8" style="height:12px; margin-top:6px; overflow:hidden; background:#eef2f7;">'
            . '<div style="height:100%; width:' . h(number_format($width, 2, '.', '')) . '%; background:' . h((string)$row['color']) . ';"></div>'
            . '</div>'
            . '<div class="card_text text_11 txt_seda" style="line-height:1.25; margin-top:6px;">'
            . 'Hodiny ' . h($formatHours((float)$row['hodiny_celkem']))
            . ' | Tržba / hod ' . h($formatMoney((float)$row['trzba_na_hodinu']))
            . ' | Objednávky ' . h($formatCount((int)$row['objednavky']))
            . '</div>'
            . '</div>';
    }
    $html .= '</div>';

    return $html;
};

$renderComparisonTable = static function (array $rows, callable $formatMoney, callable $formatHours, callable $formatCount): string {
    if ($rows === []) {
        return '<p class="card_text txt_seda odstup_vnejsi_0">Pro vybrané období nejsou k dispozici data poboček.</p>';
    }

    $html = ''
        . '<div class="ram_sedy zaobleni_8 bg_bila" style="overflow:auto;">'
        . '<table class="sirka100" style="border-collapse:collapse; min-width:640px;">'
        . '<thead>'
        . '<tr class="card_text text_11 txt_seda">'
        . '<th style="text-align:left; padding:8px;">Pobočka</th>'
        . '<th style="text-align:right; padding:8px;">Tržba</th>'
        . '<th style="text-align:right; padding:8px;">Hodiny</th>'
        . '<th style="text-align:right; padding:8px;">Tržba / hod</th>'
        . '<th style="text-align:right; padding:8px;">Obj.</th>'
        . '</tr>'
        . '</thead>'
        . '<tbody>';

    foreach ($rows as $row) {
        $html .= ''
            . '<tr class="card_text" style="border-top:1px solid #e5e7eb;">'
            . '<td style="padding:8px;"><span style="display:inline-block; width:10px; height:10px; border-radius:999px; background:' . h((string)$row['color']) . '; margin-right:6px;"></span>' . h((string)$row['nazev']) . '</td>'
            . '<td style="padding:8px; text-align:right;">' . h($formatMoney((float)$row['trzba'])) . '</td>'
            . '<td style="padding:8px; text-align:right;">' . h($formatHours((float)$row['hodiny_celkem'])) . '</td>'
            . '<td style="padding:8px; text-align:right;">' . h($formatMoney((float)$row['trzba_na_hodinu'])) . '</td>'
            . '<td style="padding:8px; text-align:right;">' . h($formatCount((int)$row['objednavky'])) . '</td>'
            . '</tr>';
    }

    $html .= '</tbody></table></div>';
    return $html;
};

$formatManagerValue = static function ($value, string $type): string {
    if ($type === 'money') {
        return number_format((float)$value, 0, ',', ' ') . ' Kč';
    }
    if ($type === 'money_hour') {
        return number_format((float)$value, 0, ',', ' ') . ' Kč/h';
    }
    if ($type === 'hours') {
        return number_format((float)$value, 1, ',', ' ') . ' h';
    }
    if ($type === 'minutes') {
        return number_format((float)$value, 0, ',', ' ') . ' min';
    }
    if ($type === 'count') {
        return number_format((float)$value, 0, ',', ' ');
    }

    return (string)$value;
};

$renderManagerGraph = static function (array $graph, callable $formatValue): string {
    $rows = array_values(array_filter(
        $graph['rows'] ?? [],
        static fn ($row): bool => is_array($row) && trim((string)($row['label'] ?? '')) !== ''
    ));
    if ($rows === []) {
        return '<p class="card_text txt_seda odstup_vnejsi_0">Pro vybrané nastavení nejsou k dispozici data.</p>';
    }

    $kind = (string)($graph['kind'] ?? 'hbars_multi');
    $metrics = array_values(array_filter(
        $graph['metrics'] ?? [],
        static fn ($metric): bool => is_array($metric) && trim((string)($metric['id'] ?? '')) !== ''
    ));
    if ($metrics === []) {
        return '<p class="card_text txt_seda odstup_vnejsi_0">Pro vybrané nastavení nejsou k dispozici data.</p>';
    }

    $maxValue = 0.0;
    foreach ($rows as $row) {
        $values = is_array($row['values'] ?? null) ? $row['values'] : [];
        foreach ($metrics as $metric) {
            $metricId = (string)($metric['id'] ?? '');
            $value = (float)($values[$metricId] ?? 0.0);
            if ($value > $maxValue) {
                $maxValue = $value;
            }
        }
    }
    if ($maxValue <= 0.0) {
        $maxValue = 1.0;
    }

    if ($kind === 'vbars_multi') {
        $html = '<div style="overflow-x:auto; padding-bottom:4px;"><div class="displ_flex gap_12" style="align-items:flex-end; min-height:300px; min-width:max-content; padding:10px 4px 0 4px;">';
        foreach ($rows as $row) {
            $values = is_array($row['values'] ?? null) ? $row['values'] : [];
            $html .= '<div class="displ_flex flex_sloupec gap_8" style="min-width:110px; align-items:center; flex:0 0 auto;">';
            $html .= '<div class="displ_flex gap_8" style="align-items:flex-end; height:220px;">';
            foreach ($metrics as $metric) {
                $metricId = (string)($metric['id'] ?? '');
                $unit = (string)($metric['unit'] ?? 'count');
                $value = (float)($values[$metricId] ?? 0.0);
                $height = max(8.0, min(220.0, ($value / $maxValue) * 220.0));
                $color = trim((string)($metric['color'] ?? '#2563eb'));
                if ($color === '') {
                    $color = '#2563eb';
                }
                $html .= ''
                    . '<div data-top-report-metric-block="' . h($metricId) . '" class="displ_flex gap_4" style="align-items:flex-end;">'
                    . '<div class="zaobleni_8" style="width:22px; height:' . h(number_format($height, 2, '.', '')) . 'px; background:' . h($color) . ';"></div>'
                    . '<div class="card_text text_11 txt_seda" style="line-height:1.05; white-space:nowrap; margin-bottom:2px;">' . h($formatValue($value, $unit)) . '</div>'
                    . '</div>';
            }
            $html .= '</div>';
            $html .= '<div class="card_text text_11 txt_c" style="line-height:1.1; min-height:26px;">' . h((string)$row['label']) . '</div>';
            $html .= '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }

    $html = '<div class="displ_flex flex_sloupec gap_8">';
    foreach ($rows as $row) {
        $values = is_array($row['values'] ?? null) ? $row['values'] : [];
        $secondary = trim((string)($row['secondary'] ?? ''));
        $rowColor = trim((string)($row['color'] ?? ''));
        $html .= ''
            . '<div class="ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8">'
            . '<div class="card_text text_tucny" style="line-height:1.15;">' . h((string)$row['label']) . '</div>';
        if ($secondary !== '') {
            $html .= '<div class="card_text text_11 txt_seda" style="line-height:1.2; margin-top:4px;">' . h($secondary) . '</div>';
        }
        $html .= '<div class="displ_flex flex_sloupec gap_6" style="margin-top:8px;">';
        foreach ($metrics as $metric) {
            $metricId = (string)($metric['id'] ?? '');
            $unit = (string)($metric['unit'] ?? 'count');
            $value = (float)($values[$metricId] ?? 0.0);
            $width = max(3.0, min(100.0, ($value / $maxValue) * 100.0));
            $color = $rowColor !== '' ? $rowColor : trim((string)($metric['color'] ?? '#2563eb'));
            if ($color === '') {
                $color = '#2563eb';
            }
            $html .= ''
                . '<div data-top-report-metric-block="' . h($metricId) . '" class="displ_flex gap_8" style="align-items:center;">'
                . '<div class="card_text text_11 txt_seda" style="width:128px; line-height:1.1;">' . h((string)($metric['label'] ?? '')) . '</div>'
                . '<div class="ram_sedy zaobleni_8" style="flex:1 1 auto; min-width:0; height:14px; overflow:hidden; background:#eef2f7;">'
                . '<div style="height:100%; width:' . h(number_format($width, 2, '.', '')) . '%; background:' . h($color) . ';"></div>'
                . '</div>'
                . '<div class="card_text text_11" style="width:110px; text-align:right; line-height:1.1; white-space:nowrap;">' . h($formatValue($value, $unit)) . '</div>'
                . '</div>';
        }
        $html .= '</div></div>';
    }
    $html .= '</div>';

    return $html;
};

$renderManagerTable = static function (array $table, callable $formatValue): string {
    $columns = array_values(array_filter(
        $table['columns'] ?? [],
        static fn ($column): bool => is_array($column) && trim((string)($column['key'] ?? '')) !== ''
    ));
    $rows = array_values(array_filter(
        $table['rows'] ?? [],
        static fn ($row): bool => is_array($row)
    ));
    if ($columns === [] || $rows === []) {
        return '<p class="card_text txt_seda odstup_vnejsi_0">Pro vybrané nastavení nejsou k dispozici data.</p>';
    }

    $html = '<div class="ram_sedy zaobleni_8 bg_bila" style="overflow:auto;"><table class="sirka100" style="border-collapse:collapse; min-width:720px;"><thead><tr class="card_text text_11 txt_seda">';
    foreach ($columns as $column) {
        $isText = ((string)($column['type'] ?? 'text') === 'text');
        $metricAttr = trim((string)($column['metric'] ?? ''));
        $html .= '<th'
            . ($metricAttr !== '' ? ' data-top-report-metric-block="' . h($metricAttr) . '"' : '')
            . ' style="padding:8px; text-align:' . ($isText ? 'left' : 'right') . ';">' . h((string)($column['label'] ?? '')) . '</th>';
    }
    $html .= '</tr></thead><tbody>';

    foreach ($rows as $row) {
        $html .= '<tr class="card_text" style="border-top:1px solid #e5e7eb;">';
        foreach ($columns as $column) {
            $key = (string)$column['key'];
            $type = (string)($column['type'] ?? 'text');
            $rawValue = $row[$key] ?? '';
            $isText = ($type === 'text');
            $cellValue = $isText ? (string)$rawValue : $formatValue($rawValue, $type);
            $metricAttr = trim((string)($column['metric'] ?? ''));
            $html .= '<td'
                . ($metricAttr !== '' ? ' data-top-report-metric-block="' . h($metricAttr) . '"' : '')
                . ' style="padding:8px; text-align:' . ($isText ? 'left' : 'right') . ';">' . h($cellValue) . '</td>';
        }
        $html .= '</tr>';
    }

    $html .= '</tbody></table></div>';
    return $html;
};

$summaryTableHtml = ''
    . '<div class="ram_sedy zaobleni_8 bg_bila" style="overflow:auto;">'
    . '<table class="sirka100" style="border-collapse:collapse;">'
    . '<thead>'
    . '<tr class="card_text text_11 txt_seda">'
    . '<th style="text-align:left; padding:8px;">Kanál</th>'
    . '<th style="text-align:right; padding:8px;">Tržba</th>'
    . '<th style="text-align:right; padding:8px;">Podíl</th>'
    . '</tr>'
    . '</thead>'
    . '<tbody>';

foreach ($channelRows as $channelRow) {
    $share = $topSummary['trzba'] > 0 ? ($channelRow['value'] / $topSummary['trzba']) * 100.0 : 0.0;
    if ((string)$channelRow['label'] === 'Celkem') {
        $share = 100.0;
    }
    $summaryTableHtml .= ''
        . '<tr class="card_text" style="border-top:1px solid #e5e7eb;">'
        . '<td style="padding:8px;">' . h((string)$channelRow['label']) . '</td>'
        . '<td style="padding:8px; text-align:right;">' . h($formatMoney((float)$channelRow['value'])) . '</td>'
        . '<td style="padding:8px; text-align:right;">' . h($formatRatio($share)) . '</td>'
        . '</tr>';
}
$summaryTableHtml .= '</tbody></table></div>';

$summaryMiniHtml = $renderMiniChannelBars($channelRows, $maxChannelValue, (float)$topSummary['trzba'], $formatMoney, $formatRatio);
$card_min_html = '';
$card_max_html = '';
$subtitleMin = $periodLabel;
$subtitleMax = $periodLabel;

if ($isMiniRender || !$isMaxRender) {
    $miniBody = ''
        . '<div class="sirka100 displ_flex flex_sloupec gap_6" data-cb-top-report-root="1">'
        . '<div class="bg_bila odstup_vnitrni_8">'
        . '<div class="card_text text_11 txt_seda text_tucny" style="line-height:1.1; margin-bottom:10px;">Souhrn tržeb podle kanálů</div>'
        . $summaryMiniHtml
        . '</div>'
        . '<script>'
        . '(function (w, d) {'
        . '  if (w.__CB_TOP_REPORT_WIRED__) { return; }'
        . '  w.__CB_TOP_REPORT_WIRED__ = true;'
        . '  function getStateKey(root) {'
        . '    if (!(root instanceof HTMLElement)) { return "cb_top_report_state"; }'
        . '    var shell = root.closest(".card_shell");'
        . '    var cardId = shell ? String(shell.getAttribute("data-card-id") || "0") : "0";'
        . '    return "cb_top_report_state_" + cardId;'
        . '  }'
        . '  function loadState(root) {'
        . '    try {'
        . '      var raw = w.sessionStorage ? w.sessionStorage.getItem(getStateKey(root)) : "";'
        . '      if (!raw) { return null; }'
        . '      var parsed = JSON.parse(raw);'
        . '      return (parsed && typeof parsed === "object") ? parsed : null;'
        . '    } catch (e) {'
        . '      return null;'
        . '    }'
        . '  }'
        . '  function saveState(root) {'
        . '    if (!(root instanceof HTMLElement) || !w.sessionStorage) { return; }'
        . '    try {'
        . '      w.sessionStorage.setItem(getStateKey(root), JSON.stringify({'
        . '        tab: String(root.getAttribute("data-top-report-tab-active") || "souhrn"),'
        . '        view: String(root.getAttribute("data-top-report-view-active") || "graf")'
        . '      }));'
        . '    } catch (e) {}'
        . '  }'
        . '  function getCaption(tab, view) {'
        . '    if (tab === "souhrn" && view === "graf") { return "Rozpad tržeb podle kanálů v grafu."; }'
        . '    if (tab === "souhrn" && view === "tabulka") { return "Rozpad tržeb podle kanálů v tabulce."; }'
        . '    if (tab === "porovnani" && view === "graf") { return "Porovnání poboček podle výkonu v grafu."; }'
        . '    return "Porovnání poboček podle výkonu v tabulce.";'
        . '  }'
        . '  function syncRoot(root) {'
        . '    if (!(root instanceof HTMLElement)) { return; }'
        . '    var saved = loadState(root);'
        . '    if (saved && typeof saved.tab === "string") { root.setAttribute("data-top-report-tab-active", saved.tab); }'
        . '    if (saved && typeof saved.view === "string") { root.setAttribute("data-top-report-view-active", saved.view); }'
        . '    var tab = String(root.getAttribute("data-top-report-tab-active") || "souhrn");'
        . '    var view = String(root.getAttribute("data-top-report-view-active") || "graf");'
        . '    root.querySelectorAll("[data-top-report-panel]").forEach(function (panel) {'
        . '      var key = String(panel.getAttribute("data-top-report-panel") || "");'
        . '      panel.style.display = (key === (tab + ":" + view)) ? "" : "none";'
        . '    });'
        . '    root.querySelectorAll("[data-top-report-tab]").forEach(function (btn) {'
        . '      btn.classList.toggle("is-on", String(btn.getAttribute("data-top-report-tab") || "") === tab);'
        . '    });'
        . '    root.querySelectorAll("[data-top-report-view]").forEach(function (btn) {'
        . '      btn.classList.toggle("is-on", String(btn.getAttribute("data-top-report-view") || "") === view);'
        . '    });'
        . '    var period = String(root.getAttribute("data-top-report-period") || "");'
        . '    var shell = root.closest(".card_shell");'
        . '    var subtitle = shell ? shell.querySelector("[data-card-subtitle]") : null;'
        . '    if (subtitle instanceof HTMLElement && period !== "") {'
        . '      subtitle.setAttribute("data-subtitle-min", period);'
        . '      subtitle.setAttribute("data-subtitle-max", period);'
        . '      subtitle.textContent = period;'
        . '    }'
        . '    var caption = root.querySelector("[data-top-report-caption]");'
        . '    if (caption instanceof HTMLElement) { caption.textContent = getCaption(tab, view); }'
        . '    saveState(root);'
        . '  }'
        . '  d.addEventListener("click", function (event) {'
        . '    var target = event.target;'
        . '    if (!(target instanceof Element)) { return; }'
        . '    var btn = target.closest("[data-top-report-tab], [data-top-report-view]");'
        . '    if (!(btn instanceof HTMLElement)) { return; }'
        . '    var root = btn.closest(\'[data-cb-top-report-ui="1"]\');'
        . '    if (!(root instanceof HTMLElement)) { return; }'
        . '    if (btn.hasAttribute("data-top-report-tab")) {'
        . '      root.setAttribute("data-top-report-tab-active", String(btn.getAttribute("data-top-report-tab") || "souhrn"));'
        . '    }'
        . '    if (btn.hasAttribute("data-top-report-view")) {'
        . '      root.setAttribute("data-top-report-view-active", String(btn.getAttribute("data-top-report-view") || "graf"));'
        . '    }'
        . '    syncRoot(root);'
        . '  });'
        . '  d.addEventListener("cb:card-swapped", function (event) {'
        . '    var detail = event && event.detail && typeof event.detail === "object" ? event.detail : null;'
        . '    var card = detail && detail.card instanceof HTMLElement ? detail.card : null;'
        . '    if (!(card instanceof HTMLElement)) { return; }'
        . '    var root = card.querySelector(\'[data-cb-top-report-ui="1"]\');'
        . '    if (root instanceof HTMLElement) { syncRoot(root); }'
        . '  });'
        . '  d.querySelectorAll(\'[data-cb-top-report-ui="1"]\').forEach(syncRoot);'
        . '})(window, document);'
        . '</script>'
        . '</div>';
    $card_min_html = $miniBody;
}

if ($isMaxRender || !$isMiniRender) {
    $managerMetrics = is_array($managerPayload['metrics'] ?? null) ? $managerPayload['metrics'] : [];
    $managerViews = is_array($managerPayload['views'] ?? null) ? $managerPayload['views'] : [];
    $managerAvailableViews = is_array($managerPayload['availableViews'] ?? null) ? $managerPayload['availableViews'] : [];
    $managerPanels = is_array($managerPayload['panels'] ?? null) ? $managerPayload['panels'] : [];
    $managerDefault = is_array($managerPayload['default'] ?? null) ? $managerPayload['default'] : [
        'metrics' => ['trzby'],
        'view' => 'pobocky',
        'display' => 'graph',
    ];
    $uiId = 'cbTopReportManager' . substr(md5($periodLabel . '|' . implode(',', $selectedPob) . '|' . (string)count($managerPanels)), 0, 8);
    $availableViewsJson = json_encode($managerAvailableViews, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    ob_start();
    ?>
    <div
      id="<?= h($uiId) ?>"
      class="sirka100 displ_flex flex_sloupec gap_10"
      data-cb-top-report-manager="1"
      <?php /* NEMENIT: Rozmer K15 max se ridi pres data-card-max-fill v includes/priprav_kartu_mini.php. */ ?>
      data-top-report-period="<?= h($periodLabel) ?>"
      data-top-report-metrics-active="<?= h(implode(',', array_values(array_filter((array)($managerDefault['metrics'] ?? ['trzby']), static fn ($metric): bool => trim((string)$metric) !== '')))) ?>"
      data-top-report-view-active="<?= h((string)($managerDefault['view'] ?? 'pobocky')) ?>"
      data-top-report-display-active="<?= h((string)($managerDefault['display'] ?? 'graph')) ?>">
      <div class="ram_sedy zaobleni_16 bg_bila" style="margin:0; padding:10px; border-color:#d7deea; background:linear-gradient(180deg, #fbfdff 0%, #f3f7fc 100%); box-shadow:0 10px 28px rgba(36,67,120,.08);">
        <div class="displ_flex flex_sloupec gap_10">
          <div class="displ_flex gap_8" style="align-items:flex-start; flex-wrap:wrap;">
            <div class="card_text text_11 txt_seda text_tucny" style="width:82px; padding-top:6px;">Metrika</div>
            <div class="displ_flex gap_8" style="flex:1 1 auto; flex-wrap:wrap;">
              <?php foreach ($managerMetrics as $metric): ?>
                <label class="cbtr-choice cbtr-choice--check">
                  <input type="checkbox" name="<?= h($uiId) ?>_metric[]" value="<?= h((string)($metric['id'] ?? '')) ?>" data-top-report-metric="<?= h((string)($metric['id'] ?? '')) ?>">
                  <span class="cbtr-choice-ui"><?= h((string)($metric['label'] ?? '')) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="displ_flex gap_8" style="align-items:flex-start; flex-wrap:wrap;">
            <div class="card_text text_11 txt_seda text_tucny" style="width:82px; padding-top:6px;">Pohled</div>
            <div class="displ_flex gap_8" style="flex:1 1 auto; flex-wrap:wrap;">
              <?php foreach ($managerViews as $view): ?>
                <label class="cbtr-choice cbtr-choice--radio">
                  <input type="radio" name="<?= h($uiId) ?>_view" value="<?= h((string)($view['id'] ?? '')) ?>" data-top-report-view="<?= h((string)($view['id'] ?? '')) ?>">
                  <span class="cbtr-choice-ui"><?= h((string)($view['label'] ?? '')) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="displ_flex gap_8" style="align-items:center; flex-wrap:wrap;">
            <div class="card_text text_11 txt_seda text_tucny" style="width:82px;">Zobrazení</div>
            <div class="cbtr-switch" role="tablist" aria-label="Zobrazení">
              <label class="cbtr-switch-opt">
                <input type="radio" name="<?= h($uiId) ?>_display" value="graph" data-top-report-display="graph">
                <span>Graf</span>
              </label>
              <label class="cbtr-switch-opt">
                <input type="radio" name="<?= h($uiId) ?>_display" value="table" data-top-report-display="table">
                <span>Tabulka</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div class="ram_sedy zaobleni_16 bg_bila" style="margin:0; padding:10px; min-height:640px; flex:1 1 auto; display:flex; flex-direction:column; overflow:auto; background:linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);">
        <div class="card_text text_11 txt_seda odstup_spod_8" style="line-height:1.2;" data-top-report-caption="1"></div>
        <?php foreach ($managerPanels as $panelKey => $panel): ?>
          <?php
          $title = (string)($panel['title'] ?? '');
          $graph = is_array($panel['graph'] ?? null) ? $panel['graph'] : [];
          $table = is_array($panel['table'] ?? null) ? $panel['table'] : [];
          ?>
          <div data-top-report-panel="<?= h($panelKey) ?>|graph" data-top-report-title="<?= h($title) ?>" style="display:none;">
            <div class="displ_flex flex_sloupec gap_8">
              <div class="card_text text_11 txt_seda text_tucny" style="line-height:1.15;"><?= h($title) ?></div>
              <?= $renderManagerGraph($graph, $formatManagerValue) ?>
            </div>
          </div>
          <div data-top-report-panel="<?= h($panelKey) ?>|table" data-top-report-title="<?= h($title) ?>" style="display:none;">
            <div class="displ_flex flex_sloupec gap_8">
              <div class="card_text text_11 txt_seda text_tucny" style="line-height:1.15;"><?= h($title) ?></div>
              <?= $renderManagerTable($table, $formatManagerValue) ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <script type="application/json" data-top-report-available-views="1"><?= (string)$availableViewsJson ?></script>
      <style>
      .dash_card.is-maxi-overlay:has([data-cb-top-report-manager="1"]) .card_expanded{
        background:linear-gradient(180deg, #eef5ff 0%, #f7fbff 100%);
      }
      .dash_card.is-maxi-overlay:has([data-cb-top-report-manager="1"]) .card_expanded{
        padding:0 !important;
      }
      [data-cb-top-report-manager="1"]{
        flex:1 1 auto;
        min-height:0;
      }
      [data-cb-top-report-manager="1"] .cbtr-choice{
        display:inline-flex;
        align-items:center;
        gap:6px;
        cursor:pointer;
      }
      [data-cb-top-report-manager="1"] .cbtr-choice input{
        position:static;
        opacity:1;
        pointer-events:auto;
        width:14px;
        height:14px;
        margin:0;
        accent-color:#2d6fda;
        flex:0 0 auto;
      }
      [data-cb-top-report-manager="1"] .cbtr-choice-ui{
        display:inline;
        min-height:0;
        padding:0;
        border:0;
        border-radius:0;
        background:none;
        color:#24415e;
        font-size:12px;
        font-weight:600;
        box-shadow:none;
        transition:none;
      }
      [data-cb-top-report-manager="1"] .cbtr-choice-ui::before{
        display:none;
      }
      [data-cb-top-report-manager="1"] .cbtr-switch{
        display:inline-grid;
        grid-template-columns:repeat(2, minmax(0, 1fr));
        gap:4px;
        padding:4px;
        border:1px solid #c8d8ef;
        border-radius:999px;
        background:linear-gradient(180deg, #edf4fd 0%, #e3edf9 100%);
        box-shadow:inset 0 1px 0 rgba(255,255,255,.8);
      }
      [data-cb-top-report-manager="1"] .cbtr-switch-opt{
        position:relative;
        display:block;
        cursor:pointer;
      }
      [data-cb-top-report-manager="1"] .cbtr-switch-opt input{
        position:absolute;
        inset:0;
        opacity:0;
        pointer-events:none;
      }
      [data-cb-top-report-manager="1"] .cbtr-switch-opt span{
        display:block;
        min-width:88px;
        min-height:34px;
        padding:7px 16px;
        border-radius:999px;
        text-align:center;
        font-size:12px;
        font-weight:700;
        color:#4d6684;
        transition:background .14s ease, color .14s ease, box-shadow .14s ease, transform .14s ease;
      }
      [data-cb-top-report-manager="1"] .cbtr-switch-opt input:checked + span{
        background:linear-gradient(180deg, #ffffff 0%, #f7fbff 100%);
        color:#1f4f9f;
        box-shadow:0 8px 18px rgba(44,82,130,.12);
      }
      [data-cb-top-report-manager="1"] [data-top-report-panel]{
        flex:1 1 auto;
      }
      </style>
    </div>
    <?php
    $card_max_html = (string)ob_get_clean();
}

/* karty/top_report.php * Verze: V5 * Aktualizace: 07.05.2026 */
?>
