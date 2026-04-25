<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Prague');

require_once __DIR__ . '/../config/secrets.php';

const CB_CONFIRM_DELETE = 'ANO_SMAZAT';

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

if (($argv[1] ?? '') !== CB_CONFIRM_DELETE) {
    echo 'Nic nesmazano. Pro spusteni pouzij: php admin_testy/restia_smazat_od_null.php ' . CB_CONFIRM_DELETE . PHP_EOL;
    exit(1);
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

$targets = [];
while ($row = $res->fetch_assoc()) {
    $idPob = (int)($row['id_pob'] ?? 0);
    $cutoffDt = trim((string)($row['cutoff_dt'] ?? ''));
    if ($idPob <= 0 || $cutoffDt === '') {
        continue;
    }

    $targets[] = [
        'id_pob' => $idPob,
        'nazev' => trim((string)($row['nazev'] ?? '')),
        'cutoff_dt' => $cutoffDt,
        'workday_date' => cb_workday_start_date($cutoffDt),
        'null_count' => (int)($row['null_count'] ?? 0),
    ];
}
$res->free();

if ($targets === []) {
    echo 'Nebyly nalezeny zadne pobocky s NULL cas_uzavreni.' . PHP_EOL;
    $conn->close();
    exit(0);
}

$stmtDeleteImports = $conn->prepare('
    DELETE FROM obj_import
    WHERE typ_importu = "historie"
      AND id_pob = ?
      AND datum_do >= ?
');
if ($stmtDeleteImports === false) {
    throw new RuntimeException('DB prepare selhal: DELETE obj_import.');
}

$stmtDeleteOrders = $conn->prepare('
    DELETE FROM objednavky_restia
    WHERE id_pob = ?
      AND restia_created_at >= ?
');
if ($stmtDeleteOrders === false) {
    throw new RuntimeException('DB prepare selhal: DELETE objednavky_restia.');
}

$conn->begin_transaction();

try {
    foreach ($targets as $target) {
        $idPob = (int)$target['id_pob'];
        $nazev = (string)$target['nazev'];
        $cutoffDt = (string)$target['cutoff_dt'];
        $importCutoff = (string)$target['workday_date'] . ' 00:00:00';

        $stmtDeleteImports->bind_param('is', $idPob, $importCutoff);
        $stmtDeleteImports->execute();
        $deletedImports = $stmtDeleteImports->affected_rows;

        $stmtDeleteOrders->bind_param('is', $idPob, $cutoffDt);
        $stmtDeleteOrders->execute();
        $deletedOrders = $stmtDeleteOrders->affected_rows;

        echo 'Pobocka ' . $idPob . ' - ' . ($nazev !== '' ? $nazev : '(bez nazvu)') . PHP_EOL;
        echo '  od_objednavky: ' . $cutoffDt . PHP_EOL;
        echo '  od_importu: ' . $importCutoff . PHP_EOL;
        echo '  smazano_obj_import: ' . $deletedImports . PHP_EOL;
        echo '  smazano_objednavek: ' . $deletedOrders . PHP_EOL;
        echo PHP_EOL;
    }

    $conn->commit();
    echo 'OK: mazani dokonceno.' . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

$stmtDeleteImports->close();
$stmtDeleteOrders->close();
$conn->close();
