<?php
// _kandidati/codex/restia_hranice_2025_10_11.php
declare(strict_types=1);

require_once __DIR__ . '/../../config/secrets.php';

const CB_DIAG_ID_POB = 1;
const CB_DIAG_FROM_LOCAL = '2025-10-30 00:00:00';
const CB_DIAG_TO_LOCAL = '2025-11-03 00:00:00';
const CB_DIAG_LIMIT = 200;

function cb_diag_db(): mysqli
{
    global $SECRETS;
    $cfg = $SECRETS['db']['local'] ?? null;
    if (!is_array($cfg)) {
        throw new RuntimeException('Chybí lokální DB konfigurace.');
    }

    $conn = new mysqli((string)$cfg['host'], (string)$cfg['user'], (string)$cfg['pass'], (string)$cfg['name']);
    if ($conn->connect_error) {
        throw new RuntimeException($conn->connect_error);
    }
    $conn->set_charset('utf8mb4');

    return $conn;
}

function cb_diag_out_path(): string
{
    return __DIR__ . '/../restia_hranice_2025_10_11.txt';
}

function cb_diag_line(string $key, mixed $value): string
{
    if (is_array($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    if (!is_string($value)) {
        $value = (string)$value;
    }
    return $key . '=' . str_replace(["\r", "\n"], [' ', ' '], $value);
}

function cb_diag_restia_to_local(?string $value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }

    try {
        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone('Europe/Prague'))
            ->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return '';
    }
}

function cb_diag_restia_get(mysqli $conn, string $activePosId, string $accessToken, string $fromZ, string $toZ): array
{
    $orders = [];
    $totalFirst = null;
    $page = 1;

    while (true) {
        $query = [
            'page' => $page,
            'limit' => CB_DIAG_LIMIT,
            'createdFrom' => $fromZ,
            'createdTo' => $toZ,
            'activePosId' => $activePosId,
        ];
        $headers = [];
        $url = 'https://apilite.restia.cz/api/orders?' . http_build_query($query);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
                $len = strlen($line);
                $line = trim($line);
                if ($line !== '' && stripos($line, 'HTTP/') !== 0) {
                    $headers[] = $line;
                }
                return $len;
            },
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $body = curl_exec($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if (!is_string($body) || $http < 200 || $http > 299) {
            throw new RuntimeException('Restia HTTP=' . (string)$http . ' err=' . $err);
        }

        $total = null;
        foreach ($headers as $header) {
            if (stripos($header, 'X-Total-Count:') === 0) {
                $total = (int)trim(substr($header, 14));
                break;
            }
        }
        if ($totalFirst === null) {
            $totalFirst = $total;
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Restia vrátila neplatný JSON.');
        }
        if (array_is_list($decoded)) {
            $pageOrders = $decoded;
        } elseif (isset($decoded['data']) && is_array($decoded['data']) && array_is_list($decoded['data'])) {
            $pageOrders = $decoded['data'];
        } elseif (isset($decoded['orders']) && is_array($decoded['orders']) && array_is_list($decoded['orders'])) {
            $pageOrders = $decoded['orders'];
        } else {
            $pageOrders = [];
        }

        foreach ($pageOrders as $order) {
            if (is_array($order)) {
                $orders[] = $order;
            }
        }

        $countPage = count($pageOrders);
        if ($countPage < CB_DIAG_LIMIT) {
            break;
        }
        if ($totalFirst !== null && $page * CB_DIAG_LIMIT >= $totalFirst) {
            break;
        }
        $page++;
    }

    return [
        'total_count' => $totalFirst,
        'orders' => $orders,
    ];
}

function cb_diag_db_orders(mysqli $conn, string $fromLocal, string $toLocal): array
{
    $stmt = $conn->prepare('
        SELECT restia_id_obj, restia_created_at, restia_order_number
        FROM objednavky_restia
        WHERE id_pob = ?
          AND restia_created_at IS NOT NULL
          AND restia_created_at >= ?
          AND restia_created_at < ?
        ORDER BY restia_created_at ASC, restia_id_obj ASC
    ');
    if ($stmt === false) {
        throw new RuntimeException('DB prepare selhal.');
    }

    $idPob = CB_DIAG_ID_POB;
    $stmt->bind_param('iss', $idPob, $fromLocal, $toLocal);
    $stmt->execute();
    $res = $stmt->get_result();

    $out = [];
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            $id = trim((string)($row['restia_id_obj'] ?? ''));
            if ($id === '') {
                continue;
            }
            $out[$id] = [
                'id' => $id,
                'restia_created_at' => (string)($row['restia_created_at'] ?? ''),
                'order_number' => (string)($row['restia_order_number'] ?? ''),
            ];
        }
        $res->free();
    }
    $stmt->close();

    return $out;
}

function cb_diag_day_counts_from_db(array $dbRows): array
{
    $out = [];
    foreach ($dbRows as $row) {
        $day = substr((string)($row['restia_created_at'] ?? ''), 0, 10);
        if ($day === '') {
            $day = '-';
        }
        $out[$day] = ($out[$day] ?? 0) + 1;
    }
    ksort($out);
    return $out;
}

function cb_diag_day_counts_from_restia(array $restiaRows): array
{
    $out = [];
    foreach ($restiaRows as $row) {
        $day = substr((string)($row['created_at_local'] ?? ''), 0, 10);
        if ($day === '') {
            $day = '-';
        }
        $out[$day] = ($out[$day] ?? 0) + 1;
    }
    ksort($out);
    return $out;
}

$lines = [];

try {
    $conn = cb_diag_db();
    $branch = $conn->query('SELECT nazev, restia_activePosId FROM pobocka WHERE id_pob = ' . (string)CB_DIAG_ID_POB . ' LIMIT 1')->fetch_assoc();
    if (!is_array($branch)) {
        throw new RuntimeException('Pobočka nenalezena.');
    }
    $activePosId = trim((string)($branch['restia_activePosId'] ?? ''));
    if ($activePosId === '') {
        throw new RuntimeException('Pobočka nemá restia_activePosId.');
    }

    $tokenRow = $conn->query('SELECT access_token FROM restia_token WHERE id_restia_token = 1 LIMIT 1')->fetch_assoc();
    $accessToken = trim((string)($tokenRow['access_token'] ?? ''));
    if ($accessToken === '') {
        throw new RuntimeException('Chybí access token.');
    }

    $tz = new DateTimeZone('Europe/Prague');
    $fromLocal = new DateTimeImmutable(CB_DIAG_FROM_LOCAL, $tz);
    $toLocal = new DateTimeImmutable(CB_DIAG_TO_LOCAL, $tz);
    $fromZ = $fromLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    $toZ = $toLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');

    $restia = cb_diag_restia_get($conn, $activePosId, $accessToken, $fromZ, $toZ);
    $restiaRows = [];
    foreach ((array)$restia['orders'] as $order) {
        if (!is_array($order)) {
            continue;
        }
        $id = trim((string)($order['id'] ?? ''));
        if ($id === '') {
            continue;
        }
        $restiaRows[$id] = [
            'id' => $id,
            'orderNumber' => (string)($order['orderNumber'] ?? ''),
            'createdAt' => (string)($order['createdAt'] ?? ''),
            'created_at_local' => cb_diag_restia_to_local(isset($order['createdAt']) ? (string)$order['createdAt'] : ''),
            'importedAt' => (string)($order['importedAt'] ?? ''),
            'imported_at_local' => cb_diag_restia_to_local(isset($order['importedAt']) ? (string)$order['importedAt'] : ''),
            'posImportedAt' => (string)($order['posImportedAt'] ?? ''),
            'pos_imported_at_local' => cb_diag_restia_to_local(isset($order['posImportedAt']) ? (string)$order['posImportedAt'] : ''),
            'closedAt' => (string)($order['closedAt'] ?? ''),
            'closed_at_local' => cb_diag_restia_to_local(isset($order['closedAt']) ? (string)$order['closedAt'] : ''),
            'statusUpdatedAt' => (string)($order['statusUpdatedAt'] ?? ''),
            'status_updated_at_local' => cb_diag_restia_to_local(isset($order['statusUpdatedAt']) ? (string)$order['statusUpdatedAt'] : ''),
        ];
    }
    uasort($restiaRows, static function (array $a, array $b): int {
        return strcmp((string)$a['created_at_local'], (string)$b['created_at_local'])
            ?: strcmp((string)$a['id'], (string)$b['id']);
    });

    $dbRows = cb_diag_db_orders($conn, CB_DIAG_FROM_LOCAL, CB_DIAG_TO_LOCAL);

    $restiaIds = array_fill_keys(array_keys($restiaRows), true);
    $dbIds = array_fill_keys(array_keys($dbRows), true);
    $onlyRestia = array_values(array_diff(array_keys($restiaIds), array_keys($dbIds)));
    $onlyDb = array_values(array_diff(array_keys($dbIds), array_keys($restiaIds)));
    sort($onlyRestia);
    sort($onlyDb);

    $lines[] = '=== RESTIA HRANICE 2025-10/2025-11 ===';
    $lines[] = cb_diag_line('generated_at', (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s'));
    $lines[] = cb_diag_line('id_pob', CB_DIAG_ID_POB);
    $lines[] = cb_diag_line('pobocka', (string)($branch['nazev'] ?? ''));
    $lines[] = cb_diag_line('activePosId', $activePosId);
    $lines[] = cb_diag_line('local_from', CB_DIAG_FROM_LOCAL);
    $lines[] = cb_diag_line('local_to', CB_DIAG_TO_LOCAL);
    $lines[] = cb_diag_line('restia_createdFrom', $fromZ);
    $lines[] = cb_diag_line('restia_createdTo', $toZ);
    $lines[] = cb_diag_line('restia_x_total_count', $restia['total_count'] ?? '');
    $lines[] = cb_diag_line('restia_rows_downloaded', count($restiaRows));
    $lines[] = cb_diag_line('db_rows', count($dbRows));
    $lines[] = cb_diag_line('only_restia_count', count($onlyRestia));
    $lines[] = cb_diag_line('only_db_count', count($onlyDb));
    $lines[] = '';

    $lines[] = '--- DAY_COUNTS_RESTIA_BY_CREATED_AT_LOCAL ---';
    foreach (cb_diag_day_counts_from_restia($restiaRows) as $day => $count) {
        $lines[] = $day . '=' . (string)$count;
    }
    $lines[] = '--- DAY_COUNTS_DB_BY_RESTIA_CREATED_AT ---';
    foreach (cb_diag_day_counts_from_db($dbRows) as $day => $count) {
        $lines[] = $day . '=' . (string)$count;
    }
    $lines[] = '';

    $lines[] = '--- ONLY_RESTIA_IDS ---';
    foreach ($onlyRestia as $id) {
        $row = $restiaRows[$id] ?? [];
        $lines[] = implode("\t", [
            'RESTIA_ONLY',
            'id=' . $id,
            'orderNumber=' . (string)($row['orderNumber'] ?? ''),
            'createdAt=' . (string)($row['createdAt'] ?? ''),
            'createdLocal=' . (string)($row['created_at_local'] ?? ''),
            'importedAt=' . (string)($row['importedAt'] ?? ''),
            'importedLocal=' . (string)($row['imported_at_local'] ?? ''),
            'posImportedAt=' . (string)($row['posImportedAt'] ?? ''),
            'posImportedLocal=' . (string)($row['pos_imported_at_local'] ?? ''),
            'closedAt=' . (string)($row['closedAt'] ?? ''),
            'closedLocal=' . (string)($row['closed_at_local'] ?? ''),
            'statusUpdatedAt=' . (string)($row['statusUpdatedAt'] ?? ''),
            'statusUpdatedLocal=' . (string)($row['status_updated_at_local'] ?? ''),
        ]);
    }
    $lines[] = '';

    $lines[] = '--- ONLY_DB_IDS ---';
    foreach ($onlyDb as $id) {
        $row = $dbRows[$id] ?? [];
        $lines[] = implode("\t", [
            'DB_ONLY',
            'id=' . $id,
            'orderNumber=' . (string)($row['order_number'] ?? ''),
            'restia_created_at=' . (string)($row['restia_created_at'] ?? ''),
        ]);
    }
    $lines[] = '';

    $lines[] = '--- RESTIA_ROWS ---';
    foreach ($restiaRows as $row) {
        $lines[] = implode("\t", [
            'RESTIA',
            'id=' . (string)$row['id'],
            'orderNumber=' . (string)$row['orderNumber'],
            'createdAt=' . (string)$row['createdAt'],
            'createdLocal=' . (string)$row['created_at_local'],
            'importedAt=' . (string)$row['importedAt'],
            'importedLocal=' . (string)$row['imported_at_local'],
            'posImportedAt=' . (string)$row['posImportedAt'],
            'posImportedLocal=' . (string)$row['pos_imported_at_local'],
            'closedAt=' . (string)$row['closedAt'],
            'closedLocal=' . (string)$row['closed_at_local'],
            'statusUpdatedAt=' . (string)$row['statusUpdatedAt'],
            'statusUpdatedLocal=' . (string)$row['status_updated_at_local'],
        ]);
    }

    file_put_contents(cb_diag_out_path(), implode("\n", $lines) . "\n", LOCK_EX);
    echo 'OK: ' . cb_diag_out_path() . PHP_EOL;
} catch (Throwable $e) {
    $lines[] = 'ERROR=' . $e->getMessage();
    file_put_contents(cb_diag_out_path(), implode("\n", $lines) . "\n", LOCK_EX);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
