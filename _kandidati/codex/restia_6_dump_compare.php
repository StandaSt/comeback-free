<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

if (!isset($SECRETS['db']['local']) || !is_array($SECRETS['db']['local'])) {
    fwrite(STDERR, "Chybi DB local konfigurace.\n");
    exit(1);
}

$cfg = $SECRETS['db']['local'];
$conn = new mysqli(
    (string)($cfg['host'] ?? ''),
    (string)($cfg['user'] ?? ''),
    (string)($cfg['pass'] ?? ''),
    (string)($cfg['name'] ?? '')
);
if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . PHP_EOL);
    exit(1);
}
$conn->set_charset('utf8mb4');

$tokenRes = $conn->query("SELECT access_token FROM restia_token WHERE id_restia_token = 1 LIMIT 1");
if (!($tokenRes instanceof mysqli_result)) {
    fwrite(STDERR, "Nelze nacist restia token.\n");
    exit(1);
}
$tokenRow = $tokenRes->fetch_assoc();
$tokenRes->free();
$accessToken = trim((string)($tokenRow['access_token'] ?? ''));
if ($accessToken === '') {
    fwrite(STDERR, "Prazdny restia access token.\n");
    exit(1);
}

$tz = new DateTimeZone('Europe/Prague');
$nowLocal = new DateTimeImmutable('now', $tz);
$workdayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $nowLocal->format('Y-m-d') . ' 06:00:00', $tz);
if (!($workdayStart instanceof DateTimeImmutable)) {
    fwrite(STDERR, "Nelze sestavit start pracovniho dne.\n");
    exit(1);
}
if ($nowLocal < $workdayStart) {
    $workdayStart = $workdayStart->modify('-1 day');
}
$fromZ = $workdayStart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
$toZ = $nowLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');

$baseUrl = 'https://apilite.restia.cz';
$outDir = __DIR__ . '/restia_raw_6';
if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Nelze vytvorit vystupni slozku.\n");
    exit(1);
}

$resBranches = $conn->query("
    SELECT id_pob, nazev, restia_activePosId
    FROM pobocka
    WHERE restia_activePosId IS NOT NULL
      AND restia_activePosId <> ''
    ORDER BY id_pob ASC
");
if (!($resBranches instanceof mysqli_result)) {
    fwrite(STDERR, "DB dotaz na pobocky selhal.\n");
    exit(1);
}

$branches = [];
while ($row = $resBranches->fetch_assoc()) {
    $branches[] = [
        'id_pob' => (int)($row['id_pob'] ?? 0),
        'nazev' => trim((string)($row['nazev'] ?? '')),
        'active_pos_id' => trim((string)($row['restia_activePosId'] ?? '')),
    ];
}
$resBranches->free();

$summary = [
    'from_local' => $workdayStart->format('Y-m-d H:i:s'),
    'to_local' => $nowLocal->format('Y-m-d H:i:s'),
    'from_utc_z' => $fromZ,
    'to_utc_z' => $toZ,
    'branches' => [],
    'totals' => [
        'api_orders' => 0,
        'db_matching_ids' => 0,
        'missing_in_db' => 0,
    ],
];

foreach ($branches as $branch) {
    $idPob = $branch['id_pob'];
    $name = $branch['nazev'];
    $activePosId = $branch['active_pos_id'];

    $query = http_build_query([
        'page' => 1,
        'limit' => 200,
        'createdFrom' => $fromZ,
        'createdTo' => $toZ,
        'activePosId' => $activePosId,
    ]);
    $url = $baseUrl . '/api/orders?' . $query;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
    ]);
    $body = curl_exec($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if (!is_string($body)) {
        $body = '';
    }

    $safeName = preg_replace('/[^a-z0-9]+/i', '_', $name) ?: ('pob_' . $idPob);
    $rawFile = $outDir . '/pob_' . $idPob . '_' . $safeName . '_raw.json';
    file_put_contents($rawFile, $body);

    $apiIds = [];
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        $orders = [];
        if (array_is_list($decoded)) {
            $orders = $decoded;
        } elseif (isset($decoded['data']) && is_array($decoded['data']) && array_is_list($decoded['data'])) {
            $orders = $decoded['data'];
        } elseif (isset($decoded['orders']) && is_array($decoded['orders']) && array_is_list($decoded['orders'])) {
            $orders = $decoded['orders'];
        }
        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $id = trim((string)($order['id'] ?? ''));
            if ($id !== '') {
                $apiIds[$id] = true;
            }
        }
    }

    $apiIdsList = array_keys($apiIds);
    $dbIds = [];
    if ($apiIdsList !== []) {
        $quoted = [];
        foreach ($apiIdsList as $id) {
            $quoted[] = "'" . $conn->real_escape_string($id) . "'";
        }
        $sql = "
            SELECT restia_id_obj
            FROM objednavky_restia
            WHERE id_pob = " . $idPob . "
              AND restia_id_obj IN (" . implode(',', $quoted) . ")
        ";
        $resDb = $conn->query($sql);
        if ($resDb instanceof mysqli_result) {
            while ($row = $resDb->fetch_assoc()) {
                $dbId = trim((string)($row['restia_id_obj'] ?? ''));
                if ($dbId !== '') {
                    $dbIds[$dbId] = true;
                }
            }
            $resDb->free();
        }
    }

    $missing = [];
    foreach ($apiIdsList as $id) {
        if (!isset($dbIds[$id])) {
            $missing[] = $id;
        }
    }

    $missingFile = $outDir . '/pob_' . $idPob . '_' . $safeName . '_missing_ids.json';
    file_put_contents($missingFile, json_encode(array_values($missing), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $branchItem = [
        'id_pob' => $idPob,
        'nazev' => $name,
        'active_pos_id' => $activePosId,
        'http_status' => $httpStatus,
        'curl_error' => $curlErr,
        'api_orders_count' => count($apiIdsList),
        'db_matching_ids_count' => count($dbIds),
        'missing_in_db_count' => count($missing),
        'raw_file' => $rawFile,
        'missing_file' => $missingFile,
    ];
    $summary['branches'][] = $branchItem;
    $summary['totals']['api_orders'] += $branchItem['api_orders_count'];
    $summary['totals']['db_matching_ids'] += $branchItem['db_matching_ids_count'];
    $summary['totals']['missing_in_db'] += $branchItem['missing_in_db_count'];
}

$summaryFile = $outDir . '/summary.json';
file_put_contents($summaryFile, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
