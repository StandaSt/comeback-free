<?php
// K12
// jednoducha karta - od nuly

declare(strict_types=1);

require_once __DIR__ . '/../db/db_connect.php';

$card_min_html = '';
$card_max_html = '';

$pdo = db_connect();
if (method_exists($pdo, 'set_charset')) {
    $pdo->set_charset('utf8mb4');
}

$selectedPob = function_exists('get_selected_pobocky') ? get_selected_pobocky() : [];
$selectedPob = array_values(array_filter(array_map('intval', $selectedPob), static fn(int $v): bool => $v > 0));
if ($selectedPob === []) {
    $fallbackPob = (int)($_SESSION['cb_pobocka_id'] ?? 0);
    if ($fallbackPob > 0) {
        $selectedPob = [$fallbackPob];
    }
}

$periodOd = trim((string)($_SESSION['cb_obdobi_od'] ?? ''));
$periodDo = trim((string)($_SESSION['cb_obdobi_do'] ?? ''));
if ($periodOd === '' || $periodDo === '') {
    $today = (new DateTimeImmutable('today'))->setTime(6, 0, 0)->format('Y-m-d H:i:s');
    $periodOd = $periodOd !== '' ? $periodOd : $today;
    $periodDo = $periodDo !== '' ? $periodDo : $today;
}

$periodOdDate = new DateTimeImmutable($periodOd);
$periodDoDate = new DateTimeImmutable($periodDo);
$periodOdTs = $periodOdDate;
$periodDoExclusive = $periodDoDate;
$titleOd = $periodOdDate->format('d.m.Y H:i');
$titleDo = $periodDoDate->format('d.m.Y H:i');
$periodLabel = $titleOd . ' - ' . $titleDo;

$safeOdTs = $pdo->real_escape_string($periodOdTs->format('Y-m-d H:i:s'));
$safeDoTsExclusive = $pdo->real_escape_string($periodDoExclusive->format('Y-m-d H:i:s'));
$selectedPobSql = $selectedPob !== [] ? implode(',', array_map('intval', $selectedPob)) : '';

$pobWhere = '';
if ($selectedPob !== []) {
    $pobWhere = 'WHERE p.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}

$branchesSql = '
    SELECT p.id_pob, p.nazev, p.pob_color
    FROM pobocka p
    ' . $pobWhere . '
    ORDER BY p.id_pob
';

$grafPolozky = [];
$branchOrder = [];
$stmtBranches = $pdo->query($branchesSql);
if ($stmtBranches instanceof mysqli_result) {
    while ($row = $stmtBranches->fetch_assoc()) {
        $idPob = (int)($row['id_pob'] ?? 0);
        $nazev = trim((string)($row['nazev'] ?? ''));
        $barva = trim((string)($row['pob_color'] ?? ''));
        if ($idPob <= 0) {
            continue;
        }
        if ($nazev === '') {
            $nazev = (string)$idPob;
        }
        if ($barva === '') {
            throw new RuntimeException('Chybí barva pro pobočku s id_pob=' . $idPob . '.');
        }

        $grafPolozky[$idPob] = [
            'id_pob' => $idPob,
            'nazev' => $nazev,
            'barva' => $barva,
        ];
        $branchOrder[] = $idPob;
    }
    $stmtBranches->free();
}

$trendStart = $periodOdDate->modify('first day of this month')->setTime(0, 0, 0);
$trendEndExclusive = $periodDoDate->modify('first day of next month')->setTime(0, 0, 0);
$trendMonthLabels = [];
$trendMonthKeys = [];
for ($trendMonth = $trendStart; $trendMonth < $trendEndExclusive; $trendMonth = $trendMonth->modify('+1 month')) {
    $trendMonthLabels[] = $trendMonth->format('m/Y');
    $trendMonthKeys[] = $trendMonth->format('Y-m-01');
}
$trendMonthIndex = array_flip($trendMonthKeys);

$hourLabels = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '00', '01', '02', '03'];
$hourIndex = array_flip($hourLabels);

$renderGrafRoot = static function (string $bodyHtml, string $rootJson): string {
    return ''
        . '<div class="sirka100 displ_flex flex_sloupec" style="height:100%; min-height:0;" data-cb-prehledy-grafy="1">'
        . '<script type="application/json" data-cb-prehledy-grafy-data>' . $rootJson . '</script>'
        . $bodyHtml
        . '</div>';
};

$renderGrafTile = static function (string $code, string $title, string $periodText, string $chartId, string $chartJson = '', string $chartStyleExtra = ''): string {
    $payloadAttr = '';
    if ($chartJson !== '') {
        $payloadAttr = ' data-cb-prehledy-grafy-chart-data="' . h($chartJson) . '"';
    }

    return ''
        . '<div class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0; overflow:hidden;">'
        . '<div class="odstup_spod_4">'
        . '<div style="display:grid; grid-template-columns:36px minmax(0, 1fr) 36px; align-items:start; column-gap:8px; line-height:1.15;">'
        . '<div class="card_text text_12" style="color:var(--clr_seda_3);">' . h($code) . '</div>'
        . '<div class="card_text txt_c"><strong>' . h($title) . '</strong></div>'
        . '<div></div>'
        . '</div>'
        . '<div class="card_text txt_seda text_12 txt_c" style="line-height:1.15;">' . h($periodText) . '</div>'
        . '</div>'
        . '<div id="' . h($chartId) . '" data-cb-prehledy-grafy-chart="1"' . $payloadAttr . ' class="sirka100" style="height:460px;' . h(trim($chartStyleExtra)) . '"></div>'
        . '</div>';
};

$jsonEncode = static function (array $payload, string $errorMessage): string {
    $json = json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
    if (!is_string($json) || $json === '') {
        throw new RuntimeException($errorMessage);
    }

    return $json;
};

$baseUnionSql = '
    SELECT
        o.id_pob,
        o.id_zak,
        COALESCE(z.jmeno, "") AS zak_jmeno,
        COALESCE(z.prijmeni, "") AS zak_prijmeni,
        DATE(o.restia_created_at) AS report_date,
        COALESCE(c.cena_celk, 0) AS cena_celk,
        o.restia_created_at AS event_dt
    FROM objednavky_restia o
    LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
    LEFT JOIN zakaznik z ON z.id_zak = o.id_zak
    WHERE o.restia_created_at IS NOT NULL
      AND o.restia_created_at >= "' . $safeOdTs . '"
      AND o.restia_created_at < "' . $safeDoTsExclusive . '"'
    . ($selectedPob !== [] ? '
      AND o.id_pob IN (' . $selectedPobSql . ')' : '');

$loadMiniCounts = static function (mysqli $pdo, string $baseUnionSql): array {
    $sql = '
        SELECT src.id_pob, COUNT(*) AS cnt
        FROM (' . $baseUnionSql . ') src
        GROUP BY src.id_pob
        ORDER BY src.id_pob
    ';

    $counts = [];
    $stmt = $pdo->query($sql);
    if ($stmt instanceof mysqli_result) {
        while ($row = $stmt->fetch_assoc()) {
            $counts[(int)($row['id_pob'] ?? 0)] = (int)($row['cnt'] ?? 0);
        }
        $stmt->free();
    }

    return $counts;
};

$miniCountsByPob = $loadMiniCounts($pdo, $baseUnionSql);

$nazvyPobocek = [];
$hodnotyPobocek = [];
$barvyPobocek = [];
foreach ($branchOrder as $idPob) {
    $branch = $grafPolozky[$idPob] ?? null;
    if ($branch === null) {
        continue;
    }
    $nazvyPobocek[] = (string)$branch['nazev'];
    $hodnotyPobocek[] = (int)($miniCountsByPob[$idPob] ?? 0);
    $barvyPobocek[] = (string)$branch['barva'];
}

$grafPayload = [
    'kind' => 'bar',
    'title' => 'Počet objednávek, ' . $periodLabel,
    'labels' => $nazvyPobocek,
    'values' => $hodnotyPobocek,
    'colors' => $barvyPobocek,
];
$grafJson = $jsonEncode($grafPayload, 'Nepodařilo se připravit data pro graf.');

if (($cbDashboardRenderMode ?? '') === 'mini') {
    $card_min_html = $renderGrafRoot(
        '<div class="sirka100 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0;">'
        . '<div class="displ_flex jc_mezi text_11 txt_seda gap_8" style="align-items:flex-start; flex-wrap:wrap; line-height:1.15;">'
        . '<span>' . h($periodLabel) . '</span>'
        . '<span class="displ_flex gap_8" style="flex-wrap:wrap; justify-content:flex-end;">'
        . '<span><strong>' . h((string)array_sum($hodnotyPobocek)) . '</strong> objednávek</span>'
        . '</span>'
        . '</div>'
        . '<div id="mini_graf" data-cb-prehledy-grafy-chart="1" class="sirka100" style="height:180px;"></div>'
        . '</div>',
        $grafJson
    );

    return;
}

$trendDataByPob = [];
$salesDataByPob = [];
$hourDataByPob = [];
foreach ($branchOrder as $idPob) {
    $trendDataByPob[$idPob] = array_fill(0, count($trendMonthKeys), 0);
    $salesDataByPob[$idPob] = array_fill(0, count($trendMonthKeys), 0.0);
    $hourDataByPob[$idPob] = array_fill(0, count($hourLabels), 0);
}

$zakOrderPieCounts = [
    'anonymni' => 0,
    'v_restauraci' => 0,
    'telefonem' => 0,
];

$maxAggSql = '
    SELECT
        src.id_pob,
        DATE_FORMAT(src.report_date, "%Y-%m-01") AS mesic,
        LPAD(HOUR(src.event_dt), 2, "0") AS hodina,
        CASE
            WHEN src.id_zak IS NULL THEN "anonymni"
            WHEN LOWER(TRIM(src.zak_jmeno)) = "anonymni" AND LOWER(TRIM(src.zak_prijmeni)) = "zakaznik" THEN "anonymni"
            WHEN src.id_zak = 1 THEN "v_restauraci"
            ELSE "telefonem"
        END AS zak_typ,
        COUNT(*) AS cnt,
        SUM(src.cena_celk) AS suma
    FROM (' . $baseUnionSql . ') src
    GROUP BY src.id_pob, mesic, hodina, zak_typ
    ORDER BY src.id_pob, mesic, hodina, zak_typ
';

$stmtMaxAgg = $pdo->query($maxAggSql);
if ($stmtMaxAgg instanceof mysqli_result) {
    while ($row = $stmtMaxAgg->fetch_assoc()) {
        $idPob = (int)($row['id_pob'] ?? 0);
        $mesic = (string)($row['mesic'] ?? '');
        $hodina = (string)($row['hodina'] ?? '');
        $zakTyp = (string)($row['zak_typ'] ?? '');
        $cnt = (int)($row['cnt'] ?? 0);
        $suma = (float)($row['suma'] ?? 0);

        if (isset($trendMonthIndex[$mesic], $trendDataByPob[$idPob])) {
            $monthIndex = (int)$trendMonthIndex[$mesic];
            $trendDataByPob[$idPob][$monthIndex] += $cnt;
            $salesDataByPob[$idPob][$monthIndex] += $suma;
        }

        if (isset($hourIndex[$hodina], $hourDataByPob[$idPob])) {
            $hourDataByPob[$idPob][(int)$hourIndex[$hodina]] += $cnt;
        }

        if (isset($zakOrderPieCounts[$zakTyp])) {
            $zakOrderPieCounts[$zakTyp] += $cnt;
        }
    }
    $stmtMaxAgg->free();
}

$trendPayload = [
    'kind' => 'line',
    'labels' => $trendMonthLabels,
    'series' => [],
];
$salesPayload = [
    'kind' => 'line',
    'labels' => $trendMonthLabels,
    'series' => [],
];
$hourPayload = [
    'kind' => 'line',
    'labels' => $hourLabels,
    'series' => [],
];

foreach ($branchOrder as $idPob) {
    $branch = $grafPolozky[$idPob] ?? null;
    if ($branch === null) {
        continue;
    }

    $trendPayload['series'][] = [
        'name' => (string)$branch['nazev'],
        'color' => (string)$branch['barva'],
        'data' => $trendDataByPob[$idPob] ?? [],
    ];
    $salesPayload['series'][] = [
        'name' => (string)$branch['nazev'],
        'color' => (string)$branch['barva'],
        'data' => $salesDataByPob[$idPob] ?? [],
    ];

    $rawHourData = $hourDataByPob[$idPob] ?? [];
    $maxValue = $rawHourData === [] ? 0 : max($rawHourData);
    $normalizedHourData = [];
    foreach ($rawHourData as $value) {
        $normalizedHourData[] = $maxValue > 0 ? round(($value / $maxValue) * 100, 1) : 0;
    }

    $hourPayload['series'][] = [
        'name' => (string)$branch['nazev'],
        'color' => (string)$branch['barva'],
        'data' => $normalizedHourData,
    ];
}

$zakOrderPiePayload = [
    'kind' => 'pie',
    'title' => 'Typ zákazníka v objednávkách, ' . $periodLabel,
    'labels' => ['Anonymní', 'V restauraci', 'Telefonem'],
    'values' => [
        $zakOrderPieCounts['anonymni'],
        $zakOrderPieCounts['v_restauraci'],
        $zakOrderPieCounts['telefonem'],
    ],
    'total' => $zakOrderPieCounts['anonymni'] + $zakOrderPieCounts['v_restauraci'] + $zakOrderPieCounts['telefonem'],
    'colors' => ['#94a3b8', '#f59e0b', '#2563eb'],
];

$trendJson = $jsonEncode($trendPayload, 'Nepodařilo se připravit data pro trendový graf.');
$salesJson = $jsonEncode($salesPayload, 'Nepodařilo se připravit data pro tržní graf.');
$hourJson = $jsonEncode($hourPayload, 'Nepodařilo se připravit data pro hodinový graf.');
$zakOrderPieJson = $jsonEncode($zakOrderPiePayload, 'Nepodařilo se připravit data pro koláčový graf zákazníků.');

$maxTiles = '';
$maxTiles .= $renderGrafTile('G1', 'Trend objednávek podle měsíce', $periodLabel, 'graf_max_1', $trendJson);
$maxTiles .= $renderGrafTile('G2', 'Vytíženost poboček během dne', $periodLabel, 'graf_max_2', $hourJson);
$maxTiles .= $renderGrafTile('G3', 'Typ zákazníka v objednávkách', $periodLabel, 'graf_max_3', $zakOrderPieJson, ' transform:translateX(-35px); width:calc(100% + 35px);');
$maxTiles .= $renderGrafTile('G4', 'Přehled tržeb podle měsíce', $periodLabel, 'graf_max_4', $salesJson);
$maxTiles .= $renderGrafTile('G5', 'Graf 5', $periodLabel, 'graf_max_5');
$maxTiles .= $renderGrafTile('G6', 'Graf 6', $periodLabel, 'graf_max_6');

$card_max_html = $renderGrafRoot(
    '<div class="sirka100" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); grid-template-rows:repeat(2, minmax(0, 1fr)); gap:10px; height:100%; min-height:0; flex:1 1 auto; align-content:stretch;">'
    . $maxTiles
    . '</div>',
    $grafJson
);
