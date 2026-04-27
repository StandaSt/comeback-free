<?php
// K12
// jednoducha karta - od nuly

declare(strict_types=1);

require_once __DIR__ . '/../db/db_connect.php';

$card_min_html = '';
$card_max_html = '';

$grafPolozky = [];
$nazvy_pobocek = [];
$hodnoty_pobocek = [];

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
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $periodOd = $periodOd !== '' ? $periodOd : $today;
    $periodDo = $periodDo !== '' ? $periodDo : $today;
}

$safeOd = $pdo->real_escape_string($periodOd);
$safeDo = $pdo->real_escape_string($periodDo);
$periodOdDate = new DateTimeImmutable($periodOd);
$periodDoDate = new DateTimeImmutable($periodDo);
$titleOd = $periodOdDate->format('d.m.Y');
$titleDo = $periodDoDate->format('d.m.Y');
$trendStart = $periodOdDate->modify('first day of this month')->setTime(0, 0, 0);
$trendEndExclusive = $periodDoDate->modify('first day of next month')->setTime(0, 0, 0);
$trendMonthLabels = [];
$trendMonthKeys = [];
for ($trendMonth = $trendStart; $trendMonth < $trendEndExclusive; $trendMonth = $trendMonth->modify('+1 month')) {
    $trendMonthLabels[] = $trendMonth->format('m/Y');
    $trendMonthKeys[] = $trendMonth->format('Y-m-01');
}
$pobWhere = '';
if ($selectedPob !== []) {
    $pobWhere = 'WHERE p.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}

$sql = '
    SELECT
        p.id_pob,
        p.nazev,
        p.pob_color,
        COALESCE(x.cnt, 0) AS cnt
    FROM pobocka p
    LEFT JOIN (
        SELECT
            o.id_pob,
            COUNT(*) AS cnt
        FROM objednavky_restia o
        LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
        WHERE COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))) >= "' . $safeOd . '"
          AND COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))) <= "' . $safeDo . '"
        GROUP BY o.id_pob
    ) x ON x.id_pob = p.id_pob
    ' . $pobWhere . '
    ORDER BY p.id_pob
';

$stmt = $pdo->query($sql);
if ($stmt instanceof mysqli_result) {
    while ($radek = $stmt->fetch_assoc()) {
        $idPob = (int)($radek['id_pob'] ?? 0);
        $nazev = trim((string)($radek['nazev'] ?? ''));
        $barva = trim((string)($radek['pob_color'] ?? ''));
        $cnt = (int)($radek['cnt'] ?? 0);
        if ($nazev === '') {
            $nazev = (string)$idPob;
        }
        if ($barva === '') {
            throw new RuntimeException('Chybí barva pro pobočku s id_pob=' . $idPob . '.');
        }

        $grafPolozky[] = [
            'id_pob' => $idPob,
            'nazev' => $nazev,
            'hodnota' => $cnt,
            'barva' => $barva,
        ];
        $nazvy_pobocek[] = $nazev;
        $hodnoty_pobocek[] = $cnt;
    }
    $stmt->free();
}

$trendDataByPob = [];
foreach ($grafPolozky as $item) {
    $idPob = (int)($item['id_pob'] ?? 0);
    if ($idPob > 0) {
        $trendDataByPob[$idPob] = array_fill(0, count($trendMonthKeys), 0);
    }
}

$trendMonthIndex = array_flip($trendMonthKeys);
$trendWhereParts = [
    'COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))) >= "' . $safeOd . '"',
    'COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))) <= "' . $safeDo . '"',
];
if ($selectedPob !== []) {
    $trendWhereParts[] = 'o.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}

$trendSql = '
    SELECT
        o.id_pob,
        DATE_FORMAT(COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))), "%Y-%m-01") AS mesic,
        COUNT(*) AS cnt
    FROM objednavky_restia o
    LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
    WHERE ' . implode('
      AND ', $trendWhereParts) . '
    GROUP BY o.id_pob, mesic
    ORDER BY o.id_pob, mesic
';

$stmtTrend = $pdo->query($trendSql);
if ($stmtTrend instanceof mysqli_result) {
    while ($radek = $stmtTrend->fetch_assoc()) {
        $idPob = (int)($radek['id_pob'] ?? 0);
        $mesic = (string)($radek['mesic'] ?? '');
        $cnt = (int)($radek['cnt'] ?? 0);
        if ($idPob <= 0 || $mesic === '' || !isset($trendDataByPob[$idPob], $trendMonthIndex[$mesic])) {
            continue;
        }
        $trendDataByPob[$idPob][(int)$trendMonthIndex[$mesic]] = $cnt;
    }
    $stmtTrend->free();
}

$grafPayload = [
    'kind' => 'bar',
    'title' => 'Počet objednávek, ' . $titleOd . ' - ' . $titleDo,
    'labels' => $nazvy_pobocek,
    'values' => $hodnoty_pobocek,
    'colors' => array_map(static fn(array $item): string => (string)$item['barva'], $grafPolozky),
];

$grafJson = json_encode(
    $grafPayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($grafJson) || $grafJson === '') {
    throw new RuntimeException('Nepodarilo se pripravit data pro graf.');
}

$renderGrafRoot = static function (string $bodyHtml, string $rootJson): string {
    return ''
        . '<div class="sirka100 displ_flex flex_sloupec" style="height:100%; min-height:0;" data-cb-prehledy-grafy="1">'
        . '<script type="application/json" data-cb-prehledy-grafy-data>' . $rootJson . '</script>'
        . $bodyHtml
        . '</div>';
};

if (($cbDashboardRenderMode ?? '') === 'mini') {
    $card_min_html = $renderGrafRoot(
        '<div class="sirka100 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0;">'
        . '<div class="displ_flex jc_mezi text_11 txt_seda gap_8" style="align-items:flex-start; flex-wrap:wrap; line-height:1.15;">'
        . '<span>' . h($titleOd) . ' - ' . h($titleDo) . '</span>'
        . '<span class="displ_flex gap_8" style="flex-wrap:wrap; justify-content:flex-end;">'
        . '<span><strong>' . h((string)array_sum($hodnoty_pobocek)) . '</strong> objednávek</span>'
        . '</span>'
        . '</div>'
        . '<div id="mini_graf" data-cb-prehledy-grafy-chart="1" class="sirka100" style="height:180px;"></div>'
        . '</div>',
        $grafJson
    );

    return;
}

$zakOrderWhereParts = [
    'COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))) >= "' . $safeOd . '"',
    'COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))) <= "' . $safeDo . '"',
];
if ($selectedPob !== []) {
    $zakOrderWhereParts[] = 'o.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}

$zakOrderPieSql = '
    SELECT
        SUM(CASE WHEN o.id_zak IS NULL THEN 1 ELSE 0 END) AS anonymni_cnt,
        SUM(CASE WHEN o.id_zak = 1 THEN 1 ELSE 0 END) AS v_restauraci_cnt,
        SUM(CASE WHEN o.id_zak > 1 THEN 1 ELSE 0 END) AS registrovani_cnt
    FROM objednavky_restia o
    LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
    WHERE ' . implode('
      AND ', $zakOrderWhereParts) . '
';

$zakOrderPieCounts = [
    'anonymni' => 0,
    'v_restauraci' => 0,
    'registrovani' => 0,
];
$stmtZakOrderPie = $pdo->query($zakOrderPieSql);
if ($stmtZakOrderPie instanceof mysqli_result) {
    $rowZakOrderPie = $stmtZakOrderPie->fetch_assoc() ?: [];
    $zakOrderPieCounts['anonymni'] = (int)($rowZakOrderPie['anonymni_cnt'] ?? 0);
    $zakOrderPieCounts['v_restauraci'] = (int)($rowZakOrderPie['v_restauraci_cnt'] ?? 0);
    $zakOrderPieCounts['registrovani'] = (int)($rowZakOrderPie['registrovani_cnt'] ?? 0);
    $stmtZakOrderPie->free();
}

$zakOrderPiePayload = [
    'kind' => 'pie',
    'title' => 'Typ zakaznika v objednavkach, ' . $titleOd . ' - ' . $titleDo,
    'labels' => ['Anonymni', 'V restauraci', 'Registrovany zakaznik'],
    'values' => [
        $zakOrderPieCounts['anonymni'],
        $zakOrderPieCounts['v_restauraci'],
        $zakOrderPieCounts['registrovani'],
    ],
    'total' => (
        $zakOrderPieCounts['anonymni']
        + $zakOrderPieCounts['v_restauraci']
        + $zakOrderPieCounts['registrovani']
    ),
    'colors' => ['#94a3b8', '#f59e0b', '#2563eb'],
];

$zakOrderPieJson = json_encode(
    $zakOrderPiePayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($zakOrderPieJson) || $zakOrderPieJson === '') {
    throw new RuntimeException('Nepodařilo se připravit data pro koláčový graf zákazníků.');
}

$trendPayload = [
    'kind' => 'line',
    'labels' => $trendMonthLabels,
    'series' => [],
];
foreach ($grafPolozky as $item) {
    $idPob = (int)($item['id_pob'] ?? 0);
    if ($idPob <= 0 || !isset($trendDataByPob[$idPob])) {
        continue;
    }
    $trendPayload['series'][] = [
        'name' => (string)($item['nazev'] ?? ''),
        'color' => (string)($item['barva'] ?? ''),
        'data' => $trendDataByPob[$idPob],
    ];
}

$trendJson = json_encode(
    $trendPayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($trendJson) || $trendJson === '') {
    throw new RuntimeException('Nepodařilo se připravit data pro trendový graf.');
}

$salesDataByPob = [];
foreach ($grafPolozky as $item) {
    $idPob = (int)($item['id_pob'] ?? 0);
    if ($idPob > 0) {
        $salesDataByPob[$idPob] = array_fill(0, count($trendMonthKeys), 0);
    }
}

$salesSql = '
    SELECT
        o.id_pob,
        DATE_FORMAT(COALESCE(ca.report, DATE(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at))), "%Y-%m-01") AS mesic,
        SUM(COALESCE(c.cena_celk, 0)) AS suma
    FROM objednavky_restia o
    LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
    LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
    WHERE ' . implode('
      AND ', $trendWhereParts) . '
    GROUP BY o.id_pob, mesic
    ORDER BY o.id_pob, mesic
';

$stmtSales = $pdo->query($salesSql);
if ($stmtSales instanceof mysqli_result) {
    while ($radek = $stmtSales->fetch_assoc()) {
        $idPob = (int)($radek['id_pob'] ?? 0);
        $mesic = (string)($radek['mesic'] ?? '');
        $suma = (float)($radek['suma'] ?? 0);
        if ($idPob <= 0 || $mesic === '' || !isset($salesDataByPob[$idPob], $trendMonthIndex[$mesic])) {
            continue;
        }
        $salesDataByPob[$idPob][(int)$trendMonthIndex[$mesic]] = $suma;
    }
    $stmtSales->free();
}

$salesPayload = [
    'kind' => 'line',
    'labels' => $trendMonthLabels,
    'series' => [],
];
foreach ($grafPolozky as $item) {
    $idPob = (int)($item['id_pob'] ?? 0);
    if ($idPob <= 0 || !isset($salesDataByPob[$idPob])) {
        continue;
    }
    $salesPayload['series'][] = [
        'name' => (string)($item['nazev'] ?? ''),
        'color' => (string)($item['barva'] ?? ''),
        'data' => $salesDataByPob[$idPob],
    ];
}

$salesJson = json_encode(
    $salesPayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($salesJson) || $salesJson === '') {
    throw new RuntimeException('Nepodařilo se připravit data pro tržní graf.');
}


$hourLabels = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '00', '01', '02', '03'];
$hourKeys = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '00', '01', '02', '03'];
$hourDataByPob = [];
foreach ($grafPolozky as $item) {
    $idPob = (int)($item['id_pob'] ?? 0);
    if ($idPob > 0) {
        $hourDataByPob[$idPob] = array_fill(0, count($hourKeys), 0);
    }
}

$hourIndex = array_flip($hourKeys);
$hourWhereParts = [
    'ca.report >= "' . $safeOd . '"',
    'ca.report <= "' . $safeDo . '"',
    'COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at) IS NOT NULL',
    'HOUR(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at)) IN (11,12,13,14,15,16,17,18,19,20,21,22,23,0,1,2,3)',
];
if ($selectedPob !== []) {
    $hourWhereParts[] = 'o.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}

$hourSql = '
    SELECT
        o.id_pob,
        LPAD(HOUR(COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at)), 2, "0") AS hodina,
        COUNT(*) AS cnt
    FROM objednavky_restia o
    LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
    WHERE ' . implode('
      AND ', $hourWhereParts) . '
    GROUP BY o.id_pob, hodina
    ORDER BY o.id_pob, FIELD(hodina, "11", "12", "13", "14", "15", "16", "17", "18", "19", "20", "21", "22", "23", "00", "01", "02", "03")
';

$stmtHour = $pdo->query($hourSql);
if ($stmtHour instanceof mysqli_result) {
    while ($radek = $stmtHour->fetch_assoc()) {
        $idPob = (int)($radek['id_pob'] ?? 0);
        $hodina = (string)($radek['hodina'] ?? '');
        $cnt = (int)($radek['cnt'] ?? 0);
        if ($idPob <= 0 || $hodina === '' || !isset($hourDataByPob[$idPob], $hourIndex[$hodina])) {
            continue;
        }
        $hourDataByPob[$idPob][(int)$hourIndex[$hodina]] = $cnt;
    }
    $stmtHour->free();
}

$hourPayload = [
    'kind' => 'line',
    'labels' => $hourLabels,
    'series' => [],
];
foreach ($grafPolozky as $item) {
    $idPob = (int)($item['id_pob'] ?? 0);
    if ($idPob <= 0 || !isset($hourDataByPob[$idPob])) {
        continue;
    }

    $rawData = $hourDataByPob[$idPob];
    $maxValue = max($rawData);
    $normalizedData = [];
    foreach ($rawData as $value) {
        $normalizedData[] = $maxValue > 0 ? round(($value / $maxValue) * 100, 1) : 0;
    }

    $hourPayload['series'][] = [
        'name' => (string)($item['nazev'] ?? ''),
        'color' => (string)($item['barva'] ?? ''),
        'data' => $normalizedData,
    ];
}

$hourJson = json_encode(
    $hourPayload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
if (!is_string($hourJson) || $hourJson === '') {
    throw new RuntimeException('Nepodařilo se připravit data pro hodinový graf.');
}

$renderGrafTile = static function (string $code, string $title, string $periodText, string $chartId, string $chartJson = '', bool $centerTitle = false) use ($grafJson): string {
    $codeEsc = h($code);
    $titleEsc = h($title);
    $periodEsc = h($periodText);
    $idEsc = h($chartId);
    $payloadAttr = '';
    if ($chartJson !== '') {
        $payloadAttr = ' data-cb-prehledy-grafy-chart-data="' . h($chartJson) . '"';
    }
    $titleClass = $centerTitle ? 'txt_c' : '';

    return ''
        . '<div class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0; overflow:hidden;">'
        . '<div class="odstup_spod_4 ' . $titleClass . '">'
        . '<div class="card_text txt_seda text_12">' . $codeEsc . '</div>'
        . '<div class="card_text txt_tucne">' . $titleEsc . '</div>'
        . '<div class="card_text txt_seda text_12">' . $periodEsc . '</div>'
        . '</div>'
        . '<div id="' . $idEsc . '" data-cb-prehledy-grafy-chart="1"' . $payloadAttr . ' class="sirka100" style="height:180px;"></div>'
        . '</div>';
};

$periodLabel = $titleOd . ' - ' . $titleDo;
$maxTiles = '';
$maxTiles .= $renderGrafTile('G1', 'Trend objednávek podle měsíce', $periodLabel, 'graf_max_1', $trendJson, true);
for ($i = 2; $i <= 6; $i++) {
    if ($i === 2) {
        $maxTiles .= $renderGrafTile('G2', 'Vytíženost poboček během dne', $periodLabel, 'graf_max_2', $hourJson, true);
        continue;
    }
    if ($i === 4) {
        $maxTiles .= $renderGrafTile('G4', 'Přehled tržeb podle měsíce', $periodLabel, 'graf_max_4', $salesJson, true);
        continue;
    }
    if ($i === 3) {
        $maxTiles .= $renderGrafTile('G3', 'Typ zákazníka v objednávkách', $periodLabel, 'graf_max_3', $zakOrderPieJson, true);
        continue;
    }
    $maxTiles .= $renderGrafTile('G' . $i, 'Graf ' . $i, $periodLabel, 'graf_max_' . $i);
}

$card_max_html = $renderGrafRoot(
    '<div class="sirka100" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); grid-template-rows:repeat(2, minmax(0, 1fr)); gap:10px; height:100%; min-height:0; flex:1 1 auto; align-content:stretch;">'
    . $maxTiles
    . '</div>',
    $grafJson
);

/* karty/prehledy_grafy.php * Verze: V4 * Aktualizace: 17.04.2026 */
?>
