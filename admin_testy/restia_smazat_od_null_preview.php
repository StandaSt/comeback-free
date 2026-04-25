<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Prague');

require_once __DIR__ . '/../config/secrets.php';

function cb_db(): mysqli
{
    global $SECRETS;

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

    return $conn;
}

function cb_workday_start_date(string $dateTime): string
{
    $tz = new DateTimeZone('Europe/Prague');
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime, $tz);
    if (!($dt instanceof DateTimeImmutable)) {
        throw new RuntimeException('Neplatne datum objednavky: ' . $dateTime);
    }

    $dayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d') . ' 08:00:00', $tz);
    if (!($dayStart instanceof DateTimeImmutable)) {
        throw new RuntimeException('Neplatny zacatek dne pro ' . $dateTime);
    }

    if ($dt < $dayStart) {
        $dayStart = $dayStart->modify('-1 day');
    }

    return $dayStart->format('Y-m-d');
}

$conn = cb_db();

$sql = '
    SELECT
        o.id_pob,
        p.nazev,
        MIN(o.restia_created_at) AS cutoff_dt,
        COUNT(*) AS null_count
    FROM objednavky_restia o
    INNER JOIN obj_casy ca ON ca.id_obj = o.id_obj
    INNER JOIN pobocka p ON p.id_pob = o.id_pob
    WHERE ca.cas_uzavreni IS NULL
    GROUP BY o.id_pob, p.nazev
    ORDER BY MIN(o.restia_created_at) ASC, o.id_pob ASC
';

$res = $conn->query($sql);
if (!($res instanceof mysqli_result)) {
    throw new RuntimeException('DB dotaz na nejstarsi NULL cas_uzavreni selhal.');
}

$stmtOrders = $conn->prepare('
    SELECT COUNT(*) AS cnt
    FROM objednavky_restia
    WHERE id_pob = ?
      AND restia_created_at >= ?
');
if ($stmtOrders === false) {
    throw new RuntimeException('DB prepare selhal: objednavky preview.');
}

$stmtImports = $conn->prepare('
    SELECT COUNT(*) AS cnt
    FROM obj_import
    WHERE typ_importu = "historie"
      AND id_pob = ?
      AND datum_do >= ?
');
if ($stmtImports === false) {
    throw new RuntimeException('DB prepare selhal: obj_import preview.');
}

echo 'PREVIEW MAZANI RESTIA OD NEJSTARSI OBJEDNAVKY S NULL cas_uzavreni' . PHP_EOL;
echo 'Vygenerovano: ' . date('Y-m-d H:i:s') . PHP_EOL;
echo str_repeat('=', 90) . PHP_EOL;

while ($row = $res->fetch_assoc()) {
    $idPob = (int)($row['id_pob'] ?? 0);
    $nazev = trim((string)($row['nazev'] ?? ''));
    $cutoffDt = trim((string)($row['cutoff_dt'] ?? ''));
    $nullCount = (int)($row['null_count'] ?? 0);

    if ($idPob <= 0 || $cutoffDt === '') {
        continue;
    }

    $workdayDate = cb_workday_start_date($cutoffDt);
    $importCutoff = $workdayDate . ' 00:00:00';

    $stmtOrders->bind_param('is', $idPob, $cutoffDt);
    $stmtOrders->execute();
    $resOrders = $stmtOrders->get_result();
    $orderRow = ($resOrders instanceof mysqli_result) ? $resOrders->fetch_assoc() : null;
    if ($resOrders instanceof mysqli_result) {
        $resOrders->free();
    }

    $stmtImports->bind_param('is', $idPob, $importCutoff);
    $stmtImports->execute();
    $resImports = $stmtImports->get_result();
    $importRow = ($resImports instanceof mysqli_result) ? $resImports->fetch_assoc() : null;
    if ($resImports instanceof mysqli_result) {
        $resImports->free();
    }

    echo 'Pobocka ' . $idPob . ' - ' . ($nazev !== '' ? $nazev : '(bez nazvu)') . PHP_EOL;
    echo '  nejstarsi_null_od: ' . $cutoffDt . PHP_EOL;
    echo '  pracovni_den_od: ' . $workdayDate . ' 08:00:00' . PHP_EOL;
    echo '  null_cas_uzavreni: ' . $nullCount . PHP_EOL;
    echo '  smazat_objednavek: ' . (int)($orderRow['cnt'] ?? 0) . PHP_EOL;
    echo '  smazat_obj_import: ' . (int)($importRow['cnt'] ?? 0) . PHP_EOL;
    echo PHP_EOL;
}

$stmtOrders->close();
$stmtImports->close();
$res->free();
$conn->close();
