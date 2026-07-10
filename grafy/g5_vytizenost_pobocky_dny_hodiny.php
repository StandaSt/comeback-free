<?php
// G5: vytizenost jedne pobocky podle dnu a hodin provozniho dne

declare(strict_types=1);

$db = $cbGrafContext['db'];
$safeOdTs = $cbGrafContext['safeOdTs'];
$safeDoTsExclusive = $cbGrafContext['safeDoTsExclusive'];
$periodLabel = $cbGrafContext['periodLabel'];

$currentUser = $_SESSION['cb_user'] ?? [];
$currentUserId = is_array($currentUser) ? (int)($currentUser['id_user'] ?? 0) : 0;
$currentRoleId = is_array($currentUser) ? (int)($currentUser['id_role'] ?? 0) : 0;
if ($currentRoleId <= 0) {
    $currentRoleId = 9;
}

$canAccessAllBranches = ($currentRoleId <= 3);
$canChooseBranch = ($currentRoleId === 3);
$branches = [];
$branchCloseTimes = [];
$closeColumns = ['end_po', 'end_ut', 'end_st', 'end_ct', 'end_pa', 'end_so', 'end_ne'];

if ($canAccessAllBranches) {
    $sqlBranches = '
        SELECT p.id_pob, p.nazev, p.end_po, p.end_ut, p.end_st, p.end_ct, p.end_pa, p.end_so, p.end_ne
        FROM pobocka p
        ORDER BY p.id_pob ASC
    ';
    $stmtBranches = $db->query($sqlBranches);
    if ($stmtBranches instanceof mysqli_result) {
        while ($row = $stmtBranches->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            $nazev = trim((string)($row['nazev'] ?? ''));
            if ($idPob <= 0) {
                continue;
            }
            $branches[] = [
                'id' => $idPob,
                'name' => $nazev !== '' ? $nazev : ('Pobočka ' . $idPob),
            ];
            $branchCloseTimes[$idPob] = [];
            foreach ($closeColumns as $column) {
                $branchCloseTimes[$idPob][] = (string)($row[$column] ?? '01:00:00');
            }
        }
        $stmtBranches->free();
    }
} elseif ($currentUserId > 0) {
    $stmtMain = $db->prepare('
        SELECT up.id_pob, p.nazev, p.end_po, p.end_ut, p.end_st, p.end_ct, p.end_pa, p.end_so, p.end_ne
        FROM user_pobocka up
        INNER JOIN pobocka p ON p.id_pob = up.id_pob
        WHERE up.id_user = ?
          AND up.main = 1
        ORDER BY up.id_pob ASC
        LIMIT 1
    ');
    if ($stmtMain !== false) {
        $stmtMain->bind_param('i', $currentUserId);
        $stmtMain->execute();
        $resMain = $stmtMain->get_result();
        if ($resMain instanceof mysqli_result) {
            $row = $resMain->fetch_assoc();
            if (is_array($row)) {
                $idPob = (int)($row['id_pob'] ?? 0);
                $nazev = trim((string)($row['nazev'] ?? ''));
                if ($idPob > 0) {
                    $branches[] = [
                        'id' => $idPob,
                        'name' => $nazev !== '' ? $nazev : ('Pobočka ' . $idPob),
                    ];
                    $branchCloseTimes[$idPob] = [];
                    foreach ($closeColumns as $column) {
                        $branchCloseTimes[$idPob][] = (string)($row[$column] ?? '01:00:00');
                    }
                }
            }
            $resMain->free();
        }
        $stmtMain->close();
    }
}

$renderInfoTile = static function (string $message) use ($periodLabel): string {
    return ''
        . '<div class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0; overflow:hidden;" data-cb-graf-tile="1" data-cb-graf-code="G5" data-cb-graf-key="g5_vytizenost_pobocky_dny_hodiny">'
        . '<div class="odstup_spod_4">'
        . '<div style="display:grid; grid-template-columns:36px minmax(0, 1fr) 36px; align-items:start; column-gap:8px; line-height:1.15;">'
        . '<div class="card_text text_12" style="color:var(--clr_seda_3);">G5</div>'
        . '<div class="displ_flex flex_sloupec ai_stred txt_c">'
        . '<div class="card_text"><strong>Vytíženost pobočky kumulovaně</strong></div>'
        . '<div class="card_text txt_seda text_12" style="line-height:1.15;">' . h($periodLabel) . '</div>'
        . '</div>'
        . '<div></div>'
        . '</div>'
        . '</div>'
        . '<div class="displ_flex ai_stred jc_stred sirka100" style="height:420px; min-height:0; flex:1 1 auto;">'
        . '<p class="card_text txt_seda txt_c odstup_vnejsi_0">' . h($message) . '</p>'
        . '</div>'
        . '</div>';
};

if ($branches === []) {
    return $renderInfoTile($canAccessAllBranches ? 'Nejsou dostupné žádné pobočky.' : 'Nemáte nastavenu main pobočku');
}

$selectedBranchId = (int)$branches[0]['id'];
$branchIds = array_values(array_filter(array_map(static fn(array $branch): int => (int)($branch['id'] ?? 0), $branches), static fn(int $id): bool => $id > 0));
$branchIdsSql = implode(',', $branchIds);

$hourLabels = ['11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23', '00', '01', '02', '03'];
$hourIntervals = ['11-12', '12-13', '13-14', '14-15', '15-16', '16-17', '17-18', '18-19', '19-20', '20-21', '21-22', '22-23', '23-00', '00-01', '01-02', '02-03', '03-04'];
$hourIndex = array_flip($hourLabels);
$dayLabels = ['Pondělí', 'Úterý', 'Středa', 'Čtvrtek', 'Pátek', 'Sobota', 'Neděle'];
$dayShortLabels = ['po', 'út', 'st', 'čt', 'pá', 'so', 'ne'];

$timeToOperatingMinutes = static function (string $time): int {
    $parts = explode(':', $time);
    $hours = isset($parts[0]) ? (int)$parts[0] : 1;
    $minutes = isset($parts[1]) ? (int)$parts[1] : 0;
    $total = max(0, min(23, $hours)) * 60 + max(0, min(59, $minutes));

    return $total <= 11 * 60 ? $total + 24 * 60 : $total;
};

$hourToOperatingMinutes = static function (string $hour): int {
    $hourNumber = (int)$hour;
    $total = $hourNumber * 60;

    return $hourNumber <= 3 ? $total + 24 * 60 : $total;
};

$matrixByBranch = [];
foreach ($branchIds as $idPob) {
    $matrixByBranch[$idPob] = [];
    foreach ($dayLabels as $dayIndex => $_dayLabel) {
        foreach ($hourLabels as $hourLabel) {
            $matrixByBranch[$idPob][$dayIndex][$hourLabel] = 0;
        }
    }
}

if ($branchIdsSql !== '') {
    $orderTimeExpr = 'COALESCE(c.cas_pripr_v, c.cas_pripr_do)';
    $sql = '
        SELECT
            o.id_pob,
            WEEKDAY(
                CASE
                    WHEN HOUR(' . $orderTimeExpr . ') BETWEEN 0 AND 3
                    THEN DATE_SUB(' . $orderTimeExpr . ', INTERVAL 1 DAY)
                    ELSE ' . $orderTimeExpr . '
                END
            ) AS den_idx,
            LPAD(HOUR(' . $orderTimeExpr . '), 2, "0") AS hodina,
            COUNT(*) AS cnt
        FROM objednavky_restia o
        LEFT JOIN obj_casy c ON c.id_obj = o.id_obj
        WHERE ' . $orderTimeExpr . ' IS NOT NULL
          AND ' . $orderTimeExpr . ' >= "' . $safeOdTs . '"
          AND ' . $orderTimeExpr . ' < "' . $safeDoTsExclusive . '"
          AND o.id_pob IN (' . $branchIdsSql . ')
          AND (HOUR(' . $orderTimeExpr . ') BETWEEN 11 AND 23 OR HOUR(' . $orderTimeExpr . ') BETWEEN 0 AND 3)
        GROUP BY o.id_pob, den_idx, hodina
        ORDER BY o.id_pob, den_idx, hodina
    ';

    $stmt = $db->query($sql);
    if ($stmt instanceof mysqli_result) {
        while ($row = $stmt->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            $dayIndex = (int)($row['den_idx'] ?? -1);
            $hodina = (string)($row['hodina'] ?? '');
            $cnt = (int)($row['cnt'] ?? 0);

            if (isset($matrixByBranch[$idPob][$dayIndex][$hodina], $hourIndex[$hodina])) {
                $matrixByBranch[$idPob][$dayIndex][$hodina] += $cnt;
            }
        }
        $stmt->free();
    }
}

$dataByBranch = [];
foreach ($branchIds as $idPob) {
    $dataByBranch[(string)$idPob] = [];
    foreach ($dayLabels as $dayIndex => $_dayLabel) {
        $closeTimes = $branchCloseTimes[$idPob] ?? [];
        $closeMinutes = $timeToOperatingMinutes((string)($closeTimes[$dayIndex] ?? '01:00:00'));
        foreach ($hourLabels as $hourLabel) {
            $isOpen = $hourToOperatingMinutes($hourLabel) < $closeMinutes;
            $dataByBranch[(string)$idPob][] = [
                (int)$hourIndex[$hourLabel],
                $dayIndex,
                (int)($matrixByBranch[$idPob][$dayIndex][$hourLabel] ?? 0),
                $isOpen ? 1 : 0,
            ];
        }
    }
}

$payload = [
    'kind' => 'heatmap_week_hour',
    'labels' => $hourLabels,
    'intervalLabels' => $hourIntervals,
    'yLabels' => $dayLabels,
    'yShortLabels' => $dayShortLabels,
    'selectedBranchId' => $selectedBranchId,
    'branches' => $branches,
    'dataByBranch' => $dataByBranch,
];

$json = $jsonEncode($payload, 'Nepodařilo se připravit data pro heatmapu pobočky.');

$selectHtml = '';
if ($canChooseBranch) {
    $selectHtml = '<select class="card_text text_12" data-cb-graf-branch-select="1" title="Pobočka grafu" style="width:120px; max-width:100%; min-width:0;">';
    foreach ($branches as $branch) {
        $idPob = (int)($branch['id'] ?? 0);
        if ($idPob <= 0) {
            continue;
        }
        $selected = $idPob === $selectedBranchId ? ' selected' : '';
        $selectHtml .= '<option value="' . h((string)$idPob) . '"' . $selected . '>' . h((string)$branch['name']) . '</option>';
    }
    $selectHtml .= '</select>';
}

$selectedBranchName = trim((string)($branches[0]['name'] ?? ''));
$branchControlHtml = $selectHtml;
if ($branchControlHtml === '' && $selectedBranchName !== '') {
    $branchControlHtml = '<span class="card_text text_12 txt_seda" title="Pobočka grafu">' . h($selectedBranchName) . '</span>';
}

return ''
    . '<div class="card_text ram_sedy zaobleni_8 bg_bila odstup_vnitrni_8 displ_flex flex_sloupec gap_4" style="height:100%; min-height:0; overflow:hidden;" data-cb-graf-tile="1" data-cb-graf-code="G5" data-cb-graf-key="g5_vytizenost_pobocky_dny_hodiny">'
    . '<div class="odstup_spod_4">'
    . '<div style="display:grid; grid-template-columns:36px minmax(0, 1fr) minmax(0, 120px); align-items:start; column-gap:8px; line-height:1.15;">'
    . '<div class="card_text text_12" style="color:var(--clr_seda_3);">G5</div>'
    . '<div class="displ_flex flex_sloupec ai_stred txt_c">'
    . '<div class="card_text"><strong>Vytíženost pobočky kumulovaně</strong></div>'
    . '<div class="card_text txt_seda text_12" style="line-height:1.15;">' . h($periodLabel) . '</div>'
    . '</div>'
    . '<div class="txt_p">' . $branchControlHtml . '</div>'
    . '</div>'
    . '</div>'
    . '<div id="graf_max_5" data-cb-prehledy-grafy-chart="1" data-cb-prehledy-grafy-chart-data="' . h($json) . '" class="sirka100" style="height:420px; min-height:0; flex:1 1 auto;"></div>'
    . '</div>';
