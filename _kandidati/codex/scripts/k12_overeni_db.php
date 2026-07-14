<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../db/db_connect.php';

$pdo = db_connect();
if (method_exists($pdo, 'set_charset')) {
    $pdo->set_charset('utf8mb4');
}

$periodOd = '2026-05-01 06:00:00';
$periodDo = '2026-05-02 03:00:00';
$branchNames = ['Malešice', 'Chodov', 'Zličín', 'Prosek', 'Libuš', 'Bolevec'];

$safeBranchNames = array_map([$pdo, 'real_escape_string'], $branchNames);
$branchListSql = "'" . implode("','", $safeBranchNames) . "'";

$sql = '
    SELECT
        p.id_pob,
        p.nazev,
        COUNT(o.id_obj) AS cnt,
        MIN(o.restia_created_at) AS first_dt,
        MAX(o.restia_created_at) AS last_dt
    FROM pobocka p
    LEFT JOIN objednavky_restia o
      ON o.id_pob = p.id_pob
     AND o.restia_created_at IS NOT NULL
     AND o.restia_created_at >= "' . $pdo->real_escape_string($periodOd) . '"
     AND o.restia_created_at < "' . $pdo->real_escape_string($periodDo) . '"
    WHERE p.nazev IN (' . $branchListSql . ')
    GROUP BY p.id_pob, p.nazev
    ORDER BY p.id_pob
';

$result = $pdo->query($sql);
if (!$result instanceof mysqli_result) {
    fwrite(STDERR, 'SQL error: ' . $pdo->error . PHP_EOL);
    exit(1);
}

echo 'K12 DB overeni' . PHP_EOL;
echo 'Obdobi OD: ' . $periodOd . PHP_EOL;
echo 'Obdobi DO: ' . $periodDo . PHP_EOL;
echo str_repeat('-', 70) . PHP_EOL;

$total = 0;
while ($row = $result->fetch_assoc()) {
    $count = (int)($row['cnt'] ?? 0);
    $total += $count;

    echo json_encode([
        'id_pob' => (int)($row['id_pob'] ?? 0),
        'nazev' => (string)($row['nazev'] ?? ''),
        'cnt' => $count,
        'first_dt' => $row['first_dt'] !== null ? (string)$row['first_dt'] : null,
        'last_dt' => $row['last_dt'] !== null ? (string)$row['last_dt'] : null,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
$result->free();

echo str_repeat('-', 70) . PHP_EOL;
echo 'TOTAL=' . $total . PHP_EOL;

$checkSql = '
    SELECT
        COUNT(*) AS total_all,
        MIN(restia_created_at) AS first_dt,
        MAX(restia_created_at) AS last_dt
    FROM objednavky_restia
    WHERE restia_created_at IS NOT NULL
      AND restia_created_at >= "' . $pdo->real_escape_string($periodOd) . '"
      AND restia_created_at < "' . $pdo->real_escape_string($periodDo) . '"
';

$checkResult = $pdo->query($checkSql);
if ($checkResult instanceof mysqli_result) {
    $checkRow = $checkResult->fetch_assoc();
    $checkResult->free();
    echo 'ALL_BRANCHES=' . json_encode($checkRow, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
