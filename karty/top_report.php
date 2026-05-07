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
    $branchSql = '
        SELECT
            o.id_pob,
            p.nazev,
            COALESCE(p.pob_color, "") AS pob_color,
            COUNT(*) AS objednavky,
            SUM(COALESCE(c.cena_celk, 0)) AS trzba
        FROM objednavky_restia o
        INNER JOIN pobocka p
            ON p.id_pob = o.id_pob
        LEFT JOIN obj_ceny c
            ON c.id_obj = o.id_obj
    ' . $whereSql . '
        GROUP BY o.id_pob, p.nazev, p.pob_color
        ORDER BY trzba DESC, p.nazev ASC
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
                }
                $colorIndex++;

                $branchMap[$branchId] = [
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
          AND r.stav = 1
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
        $salesCmp = ($b['trzba'] <=> $a['trzba']);
        if ($salesCmp !== 0) {
            return $salesCmp;
        }
        return strcmp((string)$a['nazev'], (string)$b['nazev']);
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
    $summaryBarsHtml = $renderChannelBars($channelRows, $maxChannelValue, $formatMoney);
    $comparisonBarsHtml = $renderComparisonBars($branchRows, $maxBranchSales, $formatMoney, $formatHours, $formatCount);
    $comparisonTableHtml = $renderComparisonTable($branchRows, $formatMoney, $formatHours, $formatCount);
    $uiId = 'cbTopReport' . substr(md5($periodLabel . '|' . implode(',', $selectedPob) . '|' . (string)count($branchRows)), 0, 8);

    ob_start();
    ?>
    <div
      id="<?= h($uiId) ?>"
      class="sirka100 displ_flex flex_sloupec gap_8"
      data-cb-top-report-ui="1"
      data-top-report-period="<?= h($periodLabel) ?>"
      data-top-report-tab-active="souhrn"
      data-top-report-view-active="graf">
      <div class="displ_flex jc_mezi gap_8" style="align-items:flex-start; flex-wrap:wrap;">
        <div class="displ_flex gap_6" style="flex-wrap:wrap;">
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_modra zaobleni_8 text_11 is-on" data-top-report-tab="souhrn">Souhrn</button>
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_modra zaobleni_8 text_11" data-top-report-tab="porovnani">Porovnání</button>
        </div>
        <div class="displ_flex gap_6" style="flex-wrap:wrap;">
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_modra zaobleni_8 text_11 is-on" data-top-report-view="graf">Graf</button>
          <button type="button" class="head_pill txt_c cursor_ruka ram_ovladace bg_modra zaobleni_8 text_11" data-top-report-view="tabulka">Tabulka</button>
        </div>
      </div>

      <div class="ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8">
        <div class="card_text text_11 txt_seda odstup_spod_6" style="line-height:1.2;" data-top-report-caption="1"></div>

        <div data-top-report-panel="souhrn:graf">
          <?= $summaryBarsHtml ?>
        </div>

        <div data-top-report-panel="souhrn:tabulka" style="display:none;">
          <?= $summaryTableHtml ?>
        </div>

        <div data-top-report-panel="porovnani:graf" style="display:none;">
          <?= $comparisonBarsHtml ?>
        </div>

        <div data-top-report-panel="porovnani:tabulka" style="display:none;">
          <?= $comparisonTableHtml ?>
        </div>
      </div>
    </div>
    <?php
    $card_max_html = (string)ob_get_clean();
}

/* karty/top_report.php * Verze: V5 * Aktualizace: 07.05.2026 */
?>
