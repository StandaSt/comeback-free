<?php
// admin_testy/restia_testy/07_restia_probe_updated_filtry.php * Verze: V1 * Aktualizace: 05.05.2026
declare(strict_types=1);

/*
Rucni diagnostika Restia filtru pro objednavky.

- nic neimportuje
- nic neuklada do objednavkovych tabulek
- vola jen /api/orders pres existujici cb_restia_get()
- testuje pobocky Malesice a Prosek
- vystup zapisuje do admin_testy/restia_testy/restia_test.txt
*/

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../lib/app.php';
require_once __DIR__ . '/../../lib/system.php';
require_once __DIR__ . '/../../config/secrets.php';
require_once __DIR__ . '/../../lib/restia_access_exist.php';
require_once __DIR__ . '/../../lib/restia_client.php';
require_once __DIR__ . '/../../db/db_api_restia.php';

const CB_RESTIA_UPDATED_PROBE_VERZE = 'V1';
const CB_RESTIA_UPDATED_PROBE_TXT = __DIR__ . '/restia_test.txt';
const CB_RESTIA_UPDATED_PROBE_LIMIT = 20;

function cb_restia_updated_probe_now_local(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
}

function cb_restia_updated_probe_write(array $lines): void
{
    file_put_contents(CB_RESTIA_UPDATED_PROBE_TXT, implode("\n", $lines) . "\n", LOCK_EX);
}

function cb_restia_updated_probe_norm(string $value): string
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    $map = [
        'á' => 'a', 'ä' => 'a', 'č' => 'c', 'ď' => 'd', 'é' => 'e', 'ě' => 'e',
        'í' => 'i', 'ľ' => 'l', 'ĺ' => 'l', 'ň' => 'n', 'ó' => 'o', 'ô' => 'o',
        'ř' => 'r', 'š' => 's', 'ť' => 't', 'ú' => 'u', 'ů' => 'u', 'ý' => 'y',
        'ž' => 'z',
    ];
    return strtr($value, $map);
}

function cb_restia_updated_probe_workday_range(): array
{
    $tz = new DateTimeZone('Europe/Prague');
    $utc = new DateTimeZone('UTC');
    $now = new DateTimeImmutable('now', $tz);
    $todayStart = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $now->format('Y-m-d') . ' 06:00:00', $tz);
    if (!($todayStart instanceof DateTimeImmutable)) {
        throw new RuntimeException('Nepodarilo se urcit dnesni pracovni den.');
    }
    $from = ($now < $todayStart) ? $todayStart->modify('-1 day') : $todayStart;
    $today18 = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $from->format('Y-m-d') . ' 18:00:00', $tz);
    if (!($today18 instanceof DateTimeImmutable)) {
        throw new RuntimeException('Nepodarilo se urcit testovaci cas 18:00.');
    }

    return [
        'from_local' => $from->format('Y-m-d H:i:s'),
        'to_local' => $now->format('Y-m-d H:i:s'),
        'from_z' => $from->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z'),
        'to_z' => $now->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z'),
        'today18_local' => $today18->format('Y-m-d H:i:s'),
        'today18_z' => $today18->setTimezone($utc)->format('Y-m-d\TH:i:s.v\Z'),
    ];
}

function cb_restia_updated_probe_branches(mysqli $conn): array
{
    $res = $conn->query('
        SELECT id_pob, nazev, restia_activePosId
        FROM pobocka
        WHERE aktivni = 1
          AND restia_activePosId IS NOT NULL
          AND restia_activePosId <> ""
        ORDER BY id_pob
    ');
    if (!($res instanceof mysqli_result)) {
        throw new RuntimeException('DB dotaz na pobocky selhal.');
    }

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $name = trim((string)($row['nazev'] ?? ''));
        $norm = cb_restia_updated_probe_norm($name);
        if (mb_strpos($norm, 'malesice') === false && mb_strpos($norm, 'prosek') === false) {
            continue;
        }
        $out[] = [
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'nazev' => $name,
            'active_pos_id' => trim((string)($row['restia_activePosId'] ?? '')),
        ];
    }
    $res->free();

    return $out;
}

function cb_restia_updated_probe_extract_orders(array $json): array
{
    if (array_is_list($json)) {
        return $json;
    }
    if (isset($json['data']) && is_array($json['data']) && array_is_list($json['data'])) {
        return $json['data'];
    }
    if (isset($json['orders']) && is_array($json['orders']) && array_is_list($json['orders'])) {
        return $json['orders'];
    }
    return [];
}

function cb_restia_updated_probe_order_line(array $order): string
{
    $id = trim((string)($order['id'] ?? ''));
    $status = trim((string)($order['status'] ?? ''));
    $createdAt = trim((string)($order['createdAt'] ?? ''));
    $statusUpdatedAt = trim((string)($order['statusUpdatedAt'] ?? ''));
    $closedAt = trim((string)($order['closedAt'] ?? ''));

    return '    id=' . $id
        . ' | status=' . $status
        . ' | createdAt=' . $createdAt
        . ' | statusUpdatedAt=' . $statusUpdatedAt
        . ' | closedAt=' . ($closedAt !== '' ? $closedAt : 'NULL');
}

function cb_restia_updated_probe_variants(array $range, string $activePosId): array
{
    $base = [
        'page' => 1,
        'limit' => CB_RESTIA_UPDATED_PROBE_LIMIT,
        'activePosId' => $activePosId,
    ];

    return [
        'baseline_created' => $base + [
            'createdFrom' => $range['from_z'],
            'createdTo' => $range['to_z'],
        ],
        'created_plus_updatedFrom' => $base + [
            'createdFrom' => $range['from_z'],
            'createdTo' => $range['to_z'],
            'updatedFrom' => $range['from_z'],
        ],
        'created_plus_statusUpdatedFrom' => $base + [
            'createdFrom' => $range['from_z'],
            'createdTo' => $range['to_z'],
            'statusUpdatedFrom' => $range['from_z'],
        ],
        'only_updatedFrom' => $base + [
            'updatedFrom' => $range['from_z'],
        ],
        'only_statusUpdatedFrom' => $base + [
            'statusUpdatedFrom' => $range['from_z'],
        ],
        'only_updatedFrom_today18' => $base + [
            'updatedFrom' => $range['today18_z'],
        ],
        'only_statusUpdatedFrom_today18' => $base + [
            'statusUpdatedFrom' => $range['today18_z'],
        ],
    ];
}

$log = [];
$log[] = 'RESTIA UPDATED FILTER PROBE - START';
$log[] = 'verze: ' . CB_RESTIA_UPDATED_PROBE_VERZE;
$log[] = 'spusteno: ' . cb_restia_updated_probe_now_local();
$log[] = 'soubor: admin_testy/restia_testy/07_restia_probe_updated_filtry.php';
$log[] = 'vystup: ' . CB_RESTIA_UPDATED_PROBE_TXT;
$log[] = 'poznamka: diagnostika nic neimportuje a neuklada objednavky do DB';
$log[] = str_repeat('-', 80);

try {
    $conn = db();
    $range = cb_restia_updated_probe_workday_range();
    $branches = cb_restia_updated_probe_branches($conn);

    $log[] = 'pracovni den Praha od: ' . $range['from_local'];
    $log[] = 'pracovni den Praha do: ' . $range['to_local'];
    $log[] = 'test status zmen Praha od: ' . $range['today18_local'];
    $log[] = 'UTC od: ' . $range['from_z'];
    $log[] = 'UTC do: ' . $range['to_z'];
    $log[] = 'UTC test status zmen od: ' . $range['today18_z'];
    $log[] = 'limit na dotaz: ' . (string)CB_RESTIA_UPDATED_PROBE_LIMIT;
    $log[] = 'pobocky nalezeno: ' . (string)count($branches);
    $log[] = str_repeat('-', 80);

    if ($branches === []) {
        $log[] = 'Nenalezena aktivni pobocka Malešice/Malesice ani Prosek s restia_activePosId.';
    }

    foreach ($branches as $branch) {
        $idPob = (int)($branch['id_pob'] ?? 0);
        $name = (string)($branch['nazev'] ?? '');
        $activePosId = (string)($branch['active_pos_id'] ?? '');

        $log[] = '';
        $log[] = 'POBOCKA: ' . $name . ' | id_pob=' . (string)$idPob;
        $log[] = 'activePosId: ' . $activePosId;
        $log[] = str_repeat('-', 80);

        $variants = cb_restia_updated_probe_variants($range, $activePosId);
        foreach ($variants as $variantName => $query) {
            $log[] = '';
            $log[] = 'VARIANTA: ' . $variantName;
            $log[] = 'query: ' . json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $t0 = microtime(true);
            $res = cb_restia_get('/api/orders', $query, $activePosId, 'probe updated filtry ' . $variantName . ' id_pob=' . (string)$idPob);
            $ms = (int)round((microtime(true) - $t0) * 1000);

            $http = (int)($res['http_status'] ?? 0);
            $ok = (int)($res['ok'] ?? 0);
            $total = $res['total_count'] ?? null;
            $body = (string)($res['body'] ?? '');
            $json = json_decode($body, true);
            $orders = is_array($json) ? cb_restia_updated_probe_extract_orders($json) : [];
            $openCount = 0;
            foreach ($orders as $order) {
                if (is_array($order) && trim((string)($order['closedAt'] ?? '')) === '') {
                    $openCount++;
                }
            }

            $log[] = 'http_status: ' . (string)$http;
            $log[] = 'ok: ' . (string)$ok;
            $log[] = 'ms: ' . (string)$ms;
            $log[] = 'X-Total-Count: ' . ($total === null ? 'null' : (string)$total);
            $log[] = 'valid_json: ' . (is_array($json) ? '1' : '0');
            $log[] = 'pocet_v_body: ' . (string)count($orders);
            $log[] = 'neuzavrene_v_body: ' . (string)$openCount;
            if (trim((string)($res['chyba'] ?? '')) !== '') {
                $log[] = 'chyba: ' . trim((string)$res['chyba']);
            }

            if ($orders !== []) {
                $log[] = 'ukazka objednavek max 5:';
                $shown = 0;
                foreach ($orders as $order) {
                    if (!is_array($order)) {
                        continue;
                    }
                    $log[] = cb_restia_updated_probe_order_line($order);
                    $shown++;
                    if ($shown >= 5) {
                        break;
                    }
                }
            } else {
                $snippet = trim(mb_substr($body, 0, 700, 'UTF-8'));
                if ($snippet !== '') {
                    $log[] = 'body_preview:';
                    $log[] = $snippet;
                }
            }
        }
    }

    $user = $_SESSION['cb_user'] ?? null;
    $idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
    $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);
    if ($idUser > 0 && $idLogin > 0) {
        db_api_restia_flush($conn, $idUser, $idLogin);
        $log[] = '';
        $log[] = 'api_restia flush: OK';
    } else {
        $log[] = '';
        $log[] = 'api_restia flush: preskoceno, chybi session id_user/id_login';
    }
} catch (Throwable $e) {
    $log[] = '';
    $log[] = 'FATAL: ' . $e->getMessage();
    $log[] = 'file: ' . $e->getFile();
    $log[] = 'line: ' . (string)$e->getLine();
}

$log[] = '';
$log[] = 'END: ' . cb_restia_updated_probe_now_local();

cb_restia_updated_probe_write($log);

echo 'OK - zapsano do admin_testy/restia_testy/restia_test.txt' . PHP_EOL;
