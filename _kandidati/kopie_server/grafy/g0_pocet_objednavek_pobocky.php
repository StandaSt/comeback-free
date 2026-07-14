<?php
// K12 mini graf: pocet objednavek podle pobocek

declare(strict_types=1);

$db = $cbGrafContext['db'];
$selectedPob = $cbGrafContext['selectedPob'];
$selectedPobSql = $cbGrafContext['selectedPobSql'];
$safeOdTs = $cbGrafContext['safeOdTs'];
$safeDoTsExclusive = $cbGrafContext['safeDoTsExclusive'];
$periodLabel = $cbGrafContext['periodLabel'];
$grafPolozky = $cbGrafContext['grafPolozky'];
$branchOrder = $cbGrafContext['branchOrder'];

$sql = '
    SELECT o.id_pob, COUNT(*) AS cnt
    FROM objednavky_restia o
    WHERE o.restia_created_at IS NOT NULL
      AND o.restia_created_at >= "' . $safeOdTs . '"
      AND o.restia_created_at < "' . $safeDoTsExclusive . '"'
    . ($selectedPob !== [] ? '
      AND o.id_pob IN (' . $selectedPobSql . ')' : '') . '
    GROUP BY o.id_pob
    ORDER BY o.id_pob
';

$counts = [];
$stmt = $db->query($sql);
if ($stmt instanceof mysqli_result) {
    while ($row = $stmt->fetch_assoc()) {
        $counts[(int)($row['id_pob'] ?? 0)] = (int)($row['cnt'] ?? 0);
    }
    $stmt->free();
}

$nazvyPobocek = [];
$hodnotyPobocek = [];
$barvyPobocek = [];
foreach ($branchOrder as $idPob) {
    $branch = $grafPolozky[$idPob] ?? null;
    if ($branch === null) {
        continue;
    }

    $nazvyPobocek[] = (string)$branch['nazev'];
    $hodnotyPobocek[] = (int)($counts[$idPob] ?? 0);
    $barvyPobocek[] = (string)$branch['barva'];
}

$grafPayload = [
    'kind' => 'bar',
    'title' => 'Počet objednávek, ' . $periodLabel,
    'labels' => $nazvyPobocek,
    'series' => [[
        'id' => 'values',
        'name' => 'Objednavky',
        'data' => $hodnotyPobocek,
        'colors' => $barvyPobocek,
    ]],
    'meta' => [
        'title' => 'Pocet objednavek, ' . $periodLabel,
    ],
];

$grafJson = $jsonEncode($grafPayload, 'Nepodařilo se připravit data pro graf.');

return ''
    . '<div class="sirka100 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0;">'
    . '<div class="displ_flex jc_mezi text_11 txt_seda gap_8" style="align-items:flex-start; flex-wrap:wrap; line-height:1.15;">'
    . '<span>' . h($periodLabel) . '</span>'
    . '<span class="displ_flex gap_8" style="flex-wrap:wrap; justify-content:flex-end;">'
    . '<span><strong>' . h((string)array_sum($hodnotyPobocek)) . '</strong> objednávek</span>'
    . '</span>'
    . '</div>'
    . '<div id="mini_graf" data-cb-prehledy-grafy-chart="1" data-cb-prehledy-grafy-chart-data="' . h($grafJson) . '" class="sirka100" style="height:180px;"></div>'
    . '</div>';
