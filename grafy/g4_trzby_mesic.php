<?php
// G4: prehled trzeb podle mesice

declare(strict_types=1);

$db = $cbGrafContext['db'];
$selectedPob = $cbGrafContext['selectedPob'];
$selectedPobSql = $cbGrafContext['selectedPobSql'];
$periodOdDate = $cbGrafContext['periodOdDate'];
$periodDoDate = $cbGrafContext['periodDoDate'];
$safeOdTs = $cbGrafContext['safeOdTs'];
$safeDoTsExclusive = $cbGrafContext['safeDoTsExclusive'];
$periodLabel = $cbGrafContext['periodLabel'];
$grafPolozky = $cbGrafContext['grafPolozky'];
$branchOrder = $cbGrafContext['branchOrder'];

$trendStart = $periodOdDate->modify('first day of this month')->setTime(0, 0, 0);
$trendEndExclusive = $periodDoDate->modify('first day of next month')->setTime(0, 0, 0);
$trendMonthLabels = [];
$trendMonthKeys = [];
for ($trendMonth = $trendStart; $trendMonth < $trendEndExclusive; $trendMonth = $trendMonth->modify('+1 month')) {
    $trendMonthLabels[] = $trendMonth->format('m/Y');
    $trendMonthKeys[] = $trendMonth->format('Y-m-01');
}
$trendMonthIndex = array_flip($trendMonthKeys);

$dataByPob = [];
foreach ($branchOrder as $idPob) {
    $dataByPob[$idPob] = array_fill(0, count($trendMonthKeys), 0.0);
}

$sql = '
    SELECT
        o.id_pob,
        DATE_FORMAT(DATE(o.restia_created_at), "%Y-%m-01") AS mesic,
        SUM(COALESCE(c.cena_celk, 0)) AS suma
    FROM objednavky_restia o
    LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
    WHERE o.restia_created_at IS NOT NULL
      AND o.restia_created_at >= "' . $safeOdTs . '"
      AND o.restia_created_at < "' . $safeDoTsExclusive . '"'
    . ($selectedPob !== [] ? '
      AND o.id_pob IN (' . $selectedPobSql . ')' : '') . '
    GROUP BY o.id_pob, mesic
    ORDER BY o.id_pob, mesic
';

$stmt = $db->query($sql);
if ($stmt instanceof mysqli_result) {
    while ($row = $stmt->fetch_assoc()) {
        $idPob = (int)($row['id_pob'] ?? 0);
        $mesic = (string)($row['mesic'] ?? '');
        $suma = (float)($row['suma'] ?? 0);

        if (isset($trendMonthIndex[$mesic], $dataByPob[$idPob])) {
            $dataByPob[$idPob][(int)$trendMonthIndex[$mesic]] += $suma;
        }
    }
    $stmt->free();
}

$payload = [
    'kind' => 'line',
    'labels' => $trendMonthLabels,
    'series' => [],
];

foreach ($branchOrder as $idPob) {
    $branch = $grafPolozky[$idPob] ?? null;
    if ($branch === null) {
        continue;
    }

    $payload['series'][] = [
        'name' => (string)$branch['nazev'],
        'color' => (string)$branch['barva'],
        'data' => $dataByPob[$idPob] ?? [],
    ];
}

$json = $jsonEncode($payload, 'Nepodařilo se připravit data pro tržní graf.');

return $renderGrafTile('G4', 'Přehled tržeb podle měsíce', $periodLabel, 'graf_max_4', $json, '', 'g4_trzby_mesic');
