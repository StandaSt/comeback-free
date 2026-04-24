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
$titleOd = (new DateTimeImmutable($periodOd))->format('d.m.Y');
$titleDo = (new DateTimeImmutable($periodDo))->format('d.m.Y');
$trendStart = (new DateTimeImmutable($periodDo))->modify('first day of this month')->modify('-11 months')->setTime(0, 0, 0);
$trendEndExclusive = (new DateTimeImmutable($periodDo))->modify('first day of next month')->setTime(0, 0, 0);
$trendMonthLabels = [];
$trendMonthKeys = [];
for ($i = 0; $i < 12; $i++) {
    $trendMonth = $trendStart->modify('+' . $i . ' months');
    $trendMonthLabels[] = $trendMonth->format('m/Y');
    $trendMonthKeys[] = $trendMonth->format('Y-m-01');
}
$trendStartSql = $trendStart->format('Y-m-d H:i:s');
$trendEndSql = $trendEndExclusive->format('Y-m-d H:i:s');
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
            throw new RuntimeException('Chybi barva pro pobočku s id_pob=' . $idPob . '.');
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
        $trendDataByPob[$idPob] = array_fill(0, 12, 0);
    }
}

$trendMonthIndex = array_flip($trendMonthKeys);
$trendWhereParts = [
    'o.restia_created_at >= "' . $trendStartSql . '"',
    'o.restia_created_at < "' . $trendEndSql . '"',
];
if ($selectedPob !== []) {
    $trendWhereParts[] = 'o.id_pob IN (' . implode(',', array_map('intval', $selectedPob)) . ')';
}

$trendSql = '
    SELECT
        o.id_pob,
        DATE_FORMAT(o.restia_created_at, "%Y-%m-01") AS mesic,
        COUNT(*) AS cnt
    FROM objednavky_restia o
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
    throw new RuntimeException('Nepodarilo se pripravit data pro trendovy graf.');
}

$salesDataByPob = [];
foreach ($grafPolozky as $item) {
    $idPob = (int)($item['id_pob'] ?? 0);
    if ($idPob > 0) {
        $salesDataByPob[$idPob] = array_fill(0, 12, 0);
    }
}

$salesSql = '
    SELECT
        o.id_pob,
        DATE_FORMAT(o.restia_created_at, "%Y-%m-01") AS mesic,
        SUM(COALESCE(c.cena_celk, 0)) AS suma
    FROM objednavky_restia o
    LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
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
    throw new RuntimeException('Nepodarilo se pripravit data pro trzni graf.');
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
    throw new RuntimeException('Nepodarilo se pripravit data pro hodinovy graf.');
}

$renderGrafTile = static function (string $title, string $chartId, string $chartJson = '', bool $centerTitle = false) use ($grafJson): string {
    $titleEsc = h($title);
    $idEsc = h($chartId);
    $payloadAttr = '';
    if ($chartJson !== '') {
        $payloadAttr = ' data-cb-prehledy-grafy-chart-data="' . h($chartJson) . '"';
    }
    $titleClass = $centerTitle ? 'txt_c' : '';

    return ''
        . '<section class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8 displ_flex flex_sloupec" style="min-width:0; min-height:220px; height:100%; overflow:hidden;">'
        . '<div class="card_text txt_tucne odstup_spod_4 ' . $titleClass . '">' . $titleEsc . '</div>'
        . '<div id="' . $idEsc . '" data-cb-prehledy-grafy-chart="1"' . $payloadAttr . ' class="sirka100" style="flex:1 1 auto; min-height:180px; height:100%;"></div>'
        . '</section>';
};

$renderGrafRoot = static function (string $bodyHtml) use ($grafJson): string {
    return ''
        . '<div class="sirka100 displ_flex flex_sloupec" style="height:100%; min-height:0;" data-cb-prehledy-grafy="1">'
        . '<script type="application/json" data-cb-prehledy-grafy-data>' . $grafJson . '</script>'
        . $bodyHtml
        . '</div>';
};

$card_min_html = $renderGrafRoot(
    '<div class="sirka100 displ_flex flex_sloupec gap_6" style="height:100%; min-height:0;">'
    . '<div class="txt_tucne text_14 txt_c">Počet objednávek, ' . h($titleOd) . ' - ' . h($titleDo) . '</div>'
    . '<div style="flex:1 1 auto; min-height:0;"></div>'
    . '<div id="mini_graf" data-cb-prehledy-grafy-chart="1" class="sirka100" style="height:190px;"></div>'
    . '</div>'
);

$maxTiles = '';
$maxTiles .= $renderGrafTile('Trend objednávek, ' . $titleOd . ' - ' . $titleDo, 'graf_max_1', $trendJson, true);
for ($i = 2; $i <= 6; $i++) {
    if ($i === 2) {
        $maxTiles .= $renderGrafTile('Vytíženost poboček během dne (11-03)', 'graf_max_2', $hourJson, true);
        continue;
    }
    if ($i === 4) {
        $maxTiles .= $renderGrafTile('Přehled tržeb posledních 12 měsíců', 'graf_max_4', $salesJson, true);
        continue;
    }
    $maxTiles .= $renderGrafTile('Graf ' . $i, 'graf_max_' . $i);
}

$card_max_html = $renderGrafRoot(
    '<div class="sirka100" style="display:grid; grid-template-columns:repeat(3, minmax(0, 1fr)); grid-template-rows:repeat(2, minmax(0, 1fr)); gap:10px; height:100%; min-height:0; flex:1 1 auto; align-content:stretch;">'
    . $maxTiles
    . '</div>'
);

/* karty/prehledy_grafy.php * Verze: V2 * Aktualizace: 17.04.2026 */
?>
