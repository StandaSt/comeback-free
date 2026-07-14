<?php
// G3: typ zakaznika v objednavkach

declare(strict_types=1);

$db = $cbGrafContext['db'];
$selectedPob = $cbGrafContext['selectedPob'];
$selectedPobSql = $cbGrafContext['selectedPobSql'];
$safeOdTs = $cbGrafContext['safeOdTs'];
$safeDoTsExclusive = $cbGrafContext['safeDoTsExclusive'];
$periodLabel = $cbGrafContext['periodLabel'];

$counts = [
    'anonymni' => 0,
    'v_restauraci' => 0,
    'telefonem' => 0,
];

$sql = '
    SELECT
        CASE
            WHEN o.id_zak IS NULL THEN "anonymni"
            WHEN LOWER(TRIM(COALESCE(z.jmeno, ""))) = "anonymni" AND LOWER(TRIM(COALESCE(z.prijmeni, ""))) = "zakaznik" THEN "anonymni"
            WHEN o.id_zak = 1 THEN "v_restauraci"
            ELSE "telefonem"
        END AS zak_typ,
        COUNT(*) AS cnt
    FROM objednavky_restia o
    LEFT JOIN zakaznik z ON z.id_zak = o.id_zak
    WHERE o.restia_created_at IS NOT NULL
      AND o.restia_created_at >= "' . $safeOdTs . '"
      AND o.restia_created_at < "' . $safeDoTsExclusive . '"'
    . ($selectedPob !== [] ? '
      AND o.id_pob IN (' . $selectedPobSql . ')' : '') . '
    GROUP BY zak_typ
    ORDER BY zak_typ
';

$stmt = $db->query($sql);
if ($stmt instanceof mysqli_result) {
    while ($row = $stmt->fetch_assoc()) {
        $zakTyp = (string)($row['zak_typ'] ?? '');
        if (isset($counts[$zakTyp])) {
            $counts[$zakTyp] += (int)($row['cnt'] ?? 0);
        }
    }
    $stmt->free();
}

$payload = [
    'kind' => 'pie',
    'title' => 'Typ zákazníka v objednávkách, ' . $periodLabel,
    'labels' => ['Anonymní', 'V restauraci', 'Telefonem'],
    'series' => [[
        'id' => 'values',
        'name' => 'Typ zakaznika',
        'data' => [
            $counts['anonymni'],
            $counts['v_restauraci'],
            $counts['telefonem'],
        ],
        'colors' => ['#94a3b8', '#f59e0b', '#2563eb'],
    ]],
    'meta' => [
        'title' => 'Typ zakaznika v objednavkach, ' . $periodLabel,
        'total' => $counts['anonymni'] + $counts['v_restauraci'] + $counts['telefonem'],
    ],
];

$json = $jsonEncode($payload, 'Nepodařilo se připravit data pro koláčový graf zákazníků.');

return $renderGrafTile('G3', 'Typ zákazníka v objednávkách', $periodLabel, 'graf_max_3', $json, ' transform:translateX(-35px); width:calc(100% + 35px);', 'g3_typ_zakaznika');
