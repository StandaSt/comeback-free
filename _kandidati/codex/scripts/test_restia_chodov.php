<?php
declare(strict_types=1);

require __DIR__ . '/../../../config/secrets.php';

date_default_timezone_set('Europe/Prague');

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    fwrite(STDERR, "Chybi local DB konfigurace.\n");
    exit(1);
}

$conn = new mysqli(
    (string)($cfg['host'] ?? ''),
    (string)($cfg['user'] ?? ''),
    (string)($cfg['pass'] ?? ''),
    (string)($cfg['name'] ?? '')
);

if ($conn->connect_error) {
    fwrite(STDERR, "DB connect error: " . $conn->connect_error . "\n");
    exit(1);
}

$logDir = __DIR__ . '/../output';
if (!is_dir($logDir) && !mkdir($logDir, 0777, true) && !is_dir($logDir)) {
    fwrite(STDERR, "Nelze vytvorit log dir.\n");
    exit(1);
}

$logPath = $logDir . '/restia_chodov_test.log';

$branchSql = "
    SELECT id_pob, nazev, restia_activePosId
    FROM pobocka
    WHERE id_pob = 2
    LIMIT 1
";
$branchRes = $conn->query($branchSql);
if (!$branchRes) {
    fwrite(STDERR, "DB branch query error: " . $conn->error . "\n");
    exit(1);
}
$branch = $branchRes->fetch_assoc();
$branchRes->free();

if (!is_array($branch)) {
    fwrite(STDERR, "Pobocka id_pob=2 nenalezena.\n");
    exit(1);
}

$activePosId = trim((string)($branch['restia_activePosId'] ?? ''));
if ($activePosId === '') {
    fwrite(STDERR, "Chodov nema restia_activePosId.\n");
    exit(1);
}

$tokenRes = $conn->query("
    SELECT access_token, expires_at
    FROM restia_token
    WHERE id_restia_token = 1
    LIMIT 1
");
if (!$tokenRes) {
    fwrite(STDERR, "DB token query error: " . $conn->error . "\n");
    exit(1);
}
$tokenRow = $tokenRes->fetch_assoc();
$tokenRes->free();

$accessToken = trim((string)($tokenRow['access_token'] ?? ''));
if ($accessToken === '') {
    fwrite(STDERR, "Access token je prazdny.\n");
    exit(1);
}

$workdayStart = new DateTimeImmutable('today 06:00:00', new DateTimeZone('Europe/Prague'));
$nowLocal = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
if ((int)$nowLocal->format('H') < 6) {
    $workdayStart = $workdayStart->modify('-1 day');
}

$createdFromLocal = $workdayStart->format('Y-m-d H:i:s');
$openStmt = $conn->prepare('
    SELECT MIN(o.restia_created_at) AS first_open_created_at
    FROM objednavky_restia o
    LEFT JOIN obj_casy c ON c.id_obj = o.id_obj
    WHERE o.id_pob = ?
      AND o.restia_created_at IS NOT NULL
      AND o.restia_created_at >= ?
      AND c.cas_uzavreni IS NULL
');
if ($openStmt === false) {
    fwrite(STDERR, "DB prepare error for first open order.\n");
    exit(1);
}
$idPob = 2;
$workdayStartLocal = $createdFromLocal;
$openStmt->bind_param('is', $idPob, $workdayStartLocal);
$openStmt->execute();
$openRes = $openStmt->get_result();
$openRow = ($openRes instanceof mysqli_result) ? $openRes->fetch_assoc() : null;
if ($openRes instanceof mysqli_result) {
    $openRes->free();
}
$openStmt->close();

$firstOpenCreatedAt = trim((string)($openRow['first_open_created_at'] ?? ''));
if ($firstOpenCreatedAt !== '') {
    $createdFromLocal = $firstOpenCreatedAt;
}

$createdFromDt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $createdFromLocal, new DateTimeZone('Europe/Prague'));
if (!($createdFromDt instanceof DateTimeImmutable)) {
    fwrite(STDERR, "Nelze sestavit createdFrom.\n");
    exit(1);
}

$query = [
    'page' => 1,
    'limit' => 200,
    'createdFrom' => $createdFromDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
    'createdTo' => $nowLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z'),
    'activePosId' => $activePosId,
];

$url = 'https://apilite.restia.cz/api/orders?' . http_build_query($query);
$headersIn = [];
$headersFn = static function ($ch, string $line) use (&$headersIn): int {
    $headersIn[] = rtrim($line, "\r\n");
    return strlen($line);
};

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_HTTPGET => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADERFUNCTION => $headersFn,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $accessToken,
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$t0 = microtime(true);
$body = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = ($body === false) ? curl_error($ch) : '';
$elapsedMs = (int)round((microtime(true) - $t0) * 1000);
curl_close($ch);

$decoded = null;
if (is_string($body) && $body !== '') {
    $decoded = json_decode($body, true);
}

$orders = [];
if (is_array($decoded)) {
    if (array_is_list($decoded)) {
        $orders = $decoded;
    } elseif (isset($decoded['data']) && is_array($decoded['data']) && array_is_list($decoded['data'])) {
        $orders = $decoded['data'];
    } elseif (isset($decoded['orders']) && is_array($decoded['orders']) && array_is_list($decoded['orders'])) {
        $orders = $decoded['orders'];
    }
}

$sample = [];
foreach (array_slice($orders, 0, 15) as $order) {
    if (!is_array($order)) {
        continue;
    }
    $sample[] = [
        'id' => $order['id'] ?? null,
        'createdAt' => $order['createdAt'] ?? null,
        'statusUpdatedAt' => $order['statusUpdatedAt'] ?? null,
        'status' => $order['status'] ?? null,
        'closedAt' => $order['closedAt'] ?? null,
        'activePosId' => $order['activePosId'] ?? null,
    ];
}

$payload = [
    'tested_at_local' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s'),
    'branch' => [
        'id_pob' => 2,
        'nazev' => $branch['nazev'] ?? '',
        'activePosId' => $activePosId,
    ],
    'query' => $query,
    'created_from_local' => $createdFromLocal,
    'first_open_created_at_local' => $firstOpenCreatedAt,
    'http_code' => $httpCode,
    'elapsed_ms' => $elapsedMs,
    'curl_error' => $curlErr,
    'response_headers' => $headersIn,
    'orders_count_detected' => count($orders),
    'orders_sample' => $sample,
    'response_json' => $decoded,
    'response_body_raw' => is_string($body) ? $body : null,
];

$json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) {
    fwrite(STDERR, "Nelze serializovat log.\n");
    exit(1);
}

file_put_contents($logPath, $json . PHP_EOL);

echo $logPath . PHP_EOL;
