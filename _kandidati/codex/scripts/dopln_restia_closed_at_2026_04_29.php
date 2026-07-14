<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$apply = in_array('--apply', $argv, true);
$report = '2026-04-29';
$fromLocal = '2026-04-29 06:00:00';
$toLocal = '2026-04-30 05:59:59.999000';
$tzLocal = new DateTimeZone('Europe/Prague');
$tzUtc = new DateTimeZone('UTC');

function cb_fix_restia_local(?string $value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    try {
        $dt = new DateTimeImmutable(trim($value), new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone('Europe/Prague'))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return null;
    }
}

function cb_fix_restia_get(mysqli $conn, string $activePosId, string $fromZ, string $toZ, int $page): array
{
    $tokenRes = $conn->query('SELECT access_token, expires_at FROM restia_token WHERE id_restia_token = 1 LIMIT 1');
    if (!($tokenRes instanceof mysqli_result)) {
        throw new RuntimeException('Nelze nacist restia_token.');
    }
    $tokenRow = $tokenRes->fetch_assoc();
    $tokenRes->free();

    $accessToken = is_array($tokenRow) ? trim((string)($tokenRow['access_token'] ?? '')) : '';
    if ($accessToken === '') {
        throw new RuntimeException('Restia token je prazdny.');
    }

    $query = http_build_query([
        'page' => $page,
        'limit' => 100,
        'createdFrom' => $fromZ,
        'createdTo' => $toZ,
        'activePosId' => $activePosId,
    ]);

    $url = 'https://apilite.restia.cz/api/orders?' . $query;
    $headersIn = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headersIn): int {
            $len = strlen($line);
            $line = trim($line);
            if ($line !== '' && stripos($line, 'HTTP/') !== 0) {
                $headersIn[] = $line;
            }
            return $len;
        },
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL chyba: ' . $err);
    }
    curl_close($ch);

    if ($http < 200 || $http > 299) {
        throw new RuntimeException('Restia HTTP ' . $http . ': ' . substr((string)$body, 0, 300));
    }

    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Restia vratila neplatny JSON.');
    }

    if (array_is_list($decoded)) {
        return $decoded;
    }
    if (isset($decoded['data']) && is_array($decoded['data']) && array_is_list($decoded['data'])) {
        return $decoded['data'];
    }
    if (isset($decoded['orders']) && is_array($decoded['orders']) && array_is_list($decoded['orders'])) {
        return $decoded['orders'];
    }

    return [];
}

function cb_fix_status_id(mysqli $conn, string $status): ?int
{
    $status = trim($status);
    if ($status === '') {
        return null;
    }

    $stmt = $conn->prepare('SELECT id_stav FROM cis_obj_stav WHERE nazev = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('Prepare cis_obj_stav selhal.');
    }
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    $stmt->close();

    return is_array($row) ? (int)$row['id_stav'] : null;
}

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    throw new RuntimeException('Chybi local DB konfigurace.');
}

$conn = new mysqli((string)$cfg['host'], (string)$cfg['user'], (string)$cfg['pass'], (string)$cfg['name']);
$conn->set_charset('utf8mb4');

$fromZ = (new DateTimeImmutable($fromLocal, $tzLocal))->setTimezone($tzUtc)->format('Y-m-d\TH:i:s.v\Z');
$toZ = (new DateTimeImmutable($toLocal, $tzLocal))->setTimezone($tzUtc)->format('Y-m-d\TH:i:s.v\Z');

$sql = "
    SELECT o.id_obj, o.id_pob, p.nazev, p.restia_activePosId, o.restia_id_obj, o.restia_order_number
    FROM obj_casy c
    JOIN objednavky_restia o ON o.id_obj = c.id_obj
    JOIN pobocka p ON p.id_pob = o.id_pob
    WHERE c.report = ?
      AND c.cas_uzavreni IS NULL
      AND o.restia_id_obj IS NOT NULL
      AND o.restia_id_obj <> ''
    ORDER BY o.id_pob, c.cas_vytvor
";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    throw new RuntimeException('Prepare target objednavek selhal.');
}
$stmt->bind_param('s', $report);
$stmt->execute();
$res = $stmt->get_result();

$targets = [];
$branches = [];
while ($row = $res->fetch_assoc()) {
    $idObj = (int)$row['id_obj'];
    $idPob = (int)$row['id_pob'];
    $restiaId = trim((string)$row['restia_id_obj']);
    $targets[$restiaId] = [
        'id_obj' => $idObj,
        'id_pob' => $idPob,
        'pobocka' => (string)$row['nazev'],
        'order_number' => (string)$row['restia_order_number'],
    ];
    $branches[$idPob] = [
        'id_pob' => $idPob,
        'nazev' => (string)$row['nazev'],
        'active_pos_id' => (string)$row['restia_activePosId'],
    ];
}
$res->free();
$stmt->close();

echo 'Rezim: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL;
echo 'Report: ' . $report . PHP_EOL;
echo 'Cilu v DB: ' . count($targets) . PHP_EOL;

if ($targets === []) {
    exit(0);
}

$found = [];
foreach ($branches as $branch) {
    $activePosId = trim((string)$branch['active_pos_id']);
    if ($activePosId === '') {
        throw new RuntimeException('Pobocka nema restia_activePosId: ' . (string)$branch['nazev']);
    }

    for ($page = 1; $page <= 20; $page++) {
        $orders = cb_fix_restia_get($conn, $activePosId, $fromZ, $toZ, $page);
        echo 'Pobocka ' . (string)$branch['nazev'] . ' page ' . $page . ': ' . count($orders) . PHP_EOL;

        foreach ($orders as $order) {
            if (!is_array($order)) {
                continue;
            }
            $restiaId = trim((string)($order['id'] ?? ''));
            if ($restiaId !== '' && isset($targets[$restiaId])) {
                $found[$restiaId] = $order;
            }
        }

        if (count($orders) < 100) {
            break;
        }
    }
}

$updates = [];
foreach ($targets as $restiaId => $target) {
    $order = $found[$restiaId] ?? null;
    if (!is_array($order)) {
        echo 'NENALEZENO id_obj=' . $target['id_obj'] . ' restia_id=' . $restiaId . PHP_EOL;
        continue;
    }

    $closedAt = cb_fix_restia_local($order['closedAt'] ?? null);
    if ($closedAt === null) {
        echo 'BEZ closedAt id_obj=' . $target['id_obj'] . ' restia_id=' . $restiaId . PHP_EOL;
        continue;
    }

    $status = trim((string)($order['status'] ?? ''));
    $updates[] = [
        'id_obj' => (int)$target['id_obj'],
        'restia_id_obj' => $restiaId,
        'order_number' => (string)$target['order_number'],
        'pobocka' => (string)$target['pobocka'],
        'status' => $status,
        'id_stav' => cb_fix_status_id($conn, $status),
        'cas_dokonc' => cb_fix_restia_local($order['finishedAt'] ?? null),
        'cas_doruc' => cb_fix_restia_local($order['deliveredAt'] ?? null),
        'cas_status_zmena' => cb_fix_restia_local($order['statusUpdatedAt'] ?? null),
        'cas_uzavreni' => $closedAt,
    ];
}

echo 'Pripraveno update: ' . count($updates) . PHP_EOL;
foreach ($updates as $row) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

if (!$apply) {
    echo 'Bez --apply nebyla DB zmenena.' . PHP_EOL;
    exit(0);
}

$conn->begin_transaction();
try {
    $stmtCasy = $conn->prepare('
        UPDATE obj_casy
        SET cas_dokonc = ?,
            cas_doruc = ?,
            cas_status_zmena = ?,
            cas_uzavreni = ?
        WHERE id_obj = ?
          AND report = ?
          AND cas_uzavreni IS NULL
        LIMIT 1
    ');
    if ($stmtCasy === false) {
        throw new RuntimeException('Prepare update obj_casy selhal.');
    }

    $stmtObj = $conn->prepare('
        UPDATE objednavky_restia
        SET id_stav = ?
        WHERE id_obj = ?
          AND restia_id_obj = ?
        LIMIT 1
    ');
    if ($stmtObj === false) {
        throw new RuntimeException('Prepare update objednavky_restia selhal.');
    }

    $changedCasy = 0;
    $changedObj = 0;
    foreach ($updates as $row) {
        $idObj = (int)$row['id_obj'];
        $casDokonc = $row['cas_dokonc'];
        $casDoruc = $row['cas_doruc'];
        $casStatus = $row['cas_status_zmena'];
        $casUzavreni = $row['cas_uzavreni'];

        $stmtCasy->bind_param('ssssis', $casDokonc, $casDoruc, $casStatus, $casUzavreni, $idObj, $report);
        $stmtCasy->execute();
        $changedCasy += $stmtCasy->affected_rows;

        $idStav = $row['id_stav'];
        if ($idStav !== null) {
            $restiaId = $row['restia_id_obj'];
            $stmtObj->bind_param('iis', $idStav, $idObj, $restiaId);
            $stmtObj->execute();
            $changedObj += $stmtObj->affected_rows;
        }
    }

    $stmtCasy->close();
    $stmtObj->close();
    $conn->commit();
    echo 'ULOZENO obj_casy affected=' . $changedCasy . ' objednavky_restia affected=' . $changedObj . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
