<?php
// G2: vytizenost pobocek behem dne

declare(strict_types=1);

$db = $cbGrafContext['db'];
$selectedPob = $cbGrafContext['selectedPob'];
$selectedPobSql = $cbGrafContext['selectedPobSql'];
$safeOdTs = $cbGrafContext['safeOdTs'];
$safeDoTsExclusive = $cbGrafContext['safeDoTsExclusive'];
$periodLabel = $cbGrafContext['periodLabel'];
$grafPolozky = $cbGrafContext['grafPolozky'];
$branchOrder = $cbGrafContext['branchOrder'];

$hourLabels = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '00', '01', '02', '03'];
$hourIndex = array_flip($hourLabels);

$dataByPob = [];
foreach ($branchOrder as $idPob) {
    $dataByPob[$idPob] = array_fill(0, count($hourLabels), 0);
}

$sql = '
    SELECT
        o.id_pob,
        LPAD(HOUR(o.restia_created_at), 2, "0") AS hodina,
        COUNT(*) AS cnt
    FROM objednavky_restia o
    WHERE o.restia_created_at IS NOT NULL
      AND o.restia_created_at >= "' . $safeOdTs . '"
      AND o.restia_created_at < "' . $safeDoTsExclusive . '"'
    . ($selectedPob !== [] ? '
      AND o.id_pob IN (' . $selectedPobSql . ')' : '') . '
    GROUP BY o.id_pob, hodina
    ORDER BY o.id_pob, hodina
';

$stmt = $db->query($sql);
if ($stmt instanceof mysqli_result) {
    while ($row = $stmt->fetch_assoc()) {
        $idPob = (int)($row['id_pob'] ?? 0);
        $hodina = (string)($row['hodina'] ?? '');
        $cnt = (int)($row['cnt'] ?? 0);

        if (isset($hourIndex[$hodina], $dataByPob[$idPob])) {
            $dataByPob[$idPob][(int)$hourIndex[$hodina]] += $cnt;
        }
    }
    $stmt->free();
}

$payload = [
    'kind' => 'line',
    'labels' => $hourLabels,
    'series' => [],
];

foreach ($branchOrder as $idPob) {
    $branch = $grafPolozky[$idPob] ?? null;
    if ($branch === null) {
        continue;
    }

    $rawHourData = $dataByPob[$idPob] ?? [];
    $maxValue = $rawHourData === [] ? 0 : max($rawHourData);
    $normalizedHourData = [];
    foreach ($rawHourData as $value) {
        $normalizedHourData[] = $maxValue > 0 ? round(($value / $maxValue) * 100, 1) : 0;
    }

    $payload['series'][] = [
        'name' => (string)$branch['nazev'],
        'color' => (string)$branch['barva'],
        'data' => $normalizedHourData,
    ];
}

$json = $jsonEncode($payload, 'Nepodařilo se připravit data pro hodinový graf.');

return $renderGrafTile('G2', 'Vytíženost poboček během dne', $periodLabel, 'graf_max_2', $json, '', 'g2_vytizenost_pobocek');
