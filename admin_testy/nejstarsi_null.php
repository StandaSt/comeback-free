<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Prague');

require_once __DIR__ . '/../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    throw new RuntimeException('Chybi DB konfigurace $SECRETS["db"]["local"].');
}

$conn = new mysqli(
    (string)($cfg['host'] ?? ''),
    (string)($cfg['user'] ?? ''),
    (string)($cfg['pass'] ?? ''),
    (string)($cfg['name'] ?? '')
);

if ($conn->connect_error) {
    throw new RuntimeException('DB connect error: ' . $conn->connect_error);
}

if (!$conn->set_charset('utf8mb4')) {
    throw new RuntimeException('Nepodarilo se nastavit utf8mb4.');
}

$logPath = __DIR__ . '/../log/budeme_mazat.txt';

$branches = [];
$resBranches = $conn->query('SELECT id_pob, nazev FROM pobocka ORDER BY id_pob ASC');
if (!($resBranches instanceof mysqli_result)) {
    throw new RuntimeException('DB dotaz na pobočky selhal.');
}

while ($row = $resBranches->fetch_assoc()) {
    $idPob = (int)($row['id_pob'] ?? 0);
    if ($idPob <= 0) {
        continue;
    }
    $branches[$idPob] = [
        'id_pob' => $idPob,
        'nazev' => trim((string)($row['nazev'] ?? '')),
        'count_null' => 0,
        'first_row' => null,
    ];
}
$resBranches->free();

$sql = '
    SELECT
        o.id_pob,
        o.id_obj,
        o.restia_id_obj,
        o.restia_created_at,
        ca.cas_vytvor,
        ca.cas_uzavreni
    FROM objednavky_restia o
    INNER JOIN obj_casy ca ON ca.id_obj = o.id_obj
    WHERE ca.cas_uzavreni IS NULL
    ORDER BY o.id_pob ASC, o.restia_created_at ASC, o.id_obj ASC
';

$resNulls = $conn->query($sql);
if (!($resNulls instanceof mysqli_result)) {
    throw new RuntimeException('DB dotaz na NULL cas_uzavreni selhal.');
}

while ($row = $resNulls->fetch_assoc()) {
    $idPob = (int)($row['id_pob'] ?? 0);
    if ($idPob <= 0 || !isset($branches[$idPob])) {
        continue;
    }

    $branches[$idPob]['count_null']++;
    if ($branches[$idPob]['first_row'] === null) {
        $branches[$idPob]['first_row'] = [
            'id_obj' => (int)($row['id_obj'] ?? 0),
            'restia_id_obj' => trim((string)($row['restia_id_obj'] ?? '')),
            'restia_created_at' => trim((string)($row['restia_created_at'] ?? '')),
            'cas_vytvor' => trim((string)($row['cas_vytvor'] ?? '')),
            'cas_uzavreni' => trim((string)($row['cas_uzavreni'] ?? '')),
        ];
    }
}
$resNulls->free();

$lines = [];
$lines[] = 'Nejstarsi objednavka s NULL obj_casy.cas_uzavreni pro kazdou pobocku';
$lines[] = 'Vygenerovano: ' . date('Y-m-d H:i:s');
$lines[] = str_repeat('=', 90);

foreach ($branches as $branch) {
    $lines[] = 'Pobocka ' . (string)$branch['id_pob'] . ' - ' . ($branch['nazev'] !== '' ? $branch['nazev'] : '(bez nazvu)');

    if (!is_array($branch['first_row'])) {
        $lines[] = '  bez NULL cas_uzavreni';
        $lines[] = '';
        continue;
    }

    $first = $branch['first_row'];
    $lines[] = '  pocet_null: ' . (string)$branch['count_null'];
    $lines[] = '  id_obj: ' . (string)($first['id_obj'] ?? 0);
    $lines[] = '  restia_id_obj: ' . (string)($first['restia_id_obj'] ?? '');
    $lines[] = '  restia_created_at: ' . (string)($first['restia_created_at'] ?? '');
    $lines[] = '  cas_vytvor: ' . (string)($first['cas_vytvor'] ?? '');
    $lines[] = '  cas_uzavreni: NULL';
    $lines[] = '';
}

$data = implode(PHP_EOL, $lines) . PHP_EOL;
if (file_put_contents($logPath, $data, LOCK_EX) === false) {
    throw new RuntimeException('Nepodarilo se zapsat log do ' . $logPath);
}

echo 'OK: ' . $logPath . PHP_EOL;

$conn->close();
