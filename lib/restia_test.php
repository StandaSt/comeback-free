<?php
// lib/restia_schema_probe.php * Verze: V4 * Aktualizace: 01.04.2026
declare(strict_types=1);

/*
 * RESTIA - SCHEMA PROBE
 *
 * Účel:
 * - spustí se bez parametrů
 * - samo si najde pobočku s vyplněným restia_activePosId
 * - samo si zkusí orders i menu
 * - nic neukládá do DB
 * - vše zapisuje do _kandidati/restia_probe.txt
 *
 * Výchozí chování:
 * - pobočka: první aktivní pobočka s vyplněným restia_activePosId
 * - orders: včerejší UTC den 00:00:00Z až 23:59:59Z
 * - pages: 3
 * - limit: 100
 * - menu_id: vezme se z URL ?menu_id=... nebo z konstanty níže
 *
 * Volitelné parametry:
 * - id_pob=...
 * - od=...   (UTC ISO Z)
 * - do=...   (UTC ISO Z)
 * - limit=...
 * - pages=...
 * - menu_id=...
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/restia_access_exist.php';
require_once __DIR__ . '/restia_client.php';

const CB_RESTIA_PROBE_DEFAULT_MENU_ID = '762f8daa-ca39-4d8f-ae4a-d22b4d106e88';
const CB_RESTIA_PROBE_DEFAULT_LIMIT = 100;
const CB_RESTIA_PROBE_DEFAULT_PAGES = 3;

if (!function_exists('cb_restia_probe_txt_path')) {
    function cb_restia_probe_txt_path(): string
    {
        return __DIR__ . '/../_kandidati/restia_probe.txt';
    }
}

if (!function_exists('cb_restia_probe_txt_write')) {
    function cb_restia_probe_txt_write(string $txt): void
    {
        file_put_contents(cb_restia_probe_txt_path(), $txt, LOCK_EX);
    }
}

if (!function_exists('cb_restia_probe_now_local')) {
    function cb_restia_probe_now_local(): string
    {
        $dt = new DateTimeImmutable('now', new DateTimeZone('Europe/Prague'));
        return $dt->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_restia_probe_default_range_utc')) {
    function cb_restia_probe_default_range_utc(): array
    {
        $utc = new DateTimeZone('UTC');
        $todayUtc = new DateTimeImmutable('today', $utc);
        $from = $todayUtc->modify('-1 day')->setTime(0, 0, 0, 0);
        $to = $todayUtc->modify('-1 day')->setTime(23, 59, 59, 999000);

        return [
            'od' => $from->format('Y-m-d\TH:i:s.v\Z'),
            'do' => $to->format('Y-m-d\TH:i:s.v\Z'),
        ];
    }
}

if (!function_exists('cb_restia_probe_type')) {
    function cb_restia_probe_type(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return 'bool';
        }

        if (is_int($value)) {
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_string($value)) {
            return 'string';
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                return 'array';
            }
            return 'object';
        }

        return gettype($value);
    }
}

if (!function_exists('cb_restia_probe_sample')) {
    function cb_restia_probe_sample(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            $s = trim($value);
            if ($s === '') {
                return '[empty string]';
            }
            if (mb_strlen($s) > 140) {
                $s = mb_substr($s, 0, 137) . '...';
            }
            return $s;
        }

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }
            if (array_is_list($value)) {
                return '[items:' . (string)count($value) . ']';
            }
            return '{keys:' . implode(',', array_keys($value)) . '}';
        }

        return gettype($value);
    }
}

if (!function_exists('cb_restia_probe_register')) {
    function cb_restia_probe_register(array &$map, string $path, mixed $value): void
    {
        if (!isset($map[$path])) {
            $map[$path] = [
                'count' => 0,
                'types' => [],
                'samples' => [],
            ];
        }

        $map[$path]['count']++;

        $type = cb_restia_probe_type($value);
        if (!isset($map[$path]['types'][$type])) {
            $map[$path]['types'][$type] = 0;
        }
        $map[$path]['types'][$type]++;

        $sample = cb_restia_probe_sample($value);
        if ($sample !== '') {
            if (!in_array($sample, $map[$path]['samples'], true)) {
                if (count($map[$path]['samples']) < 5) {
                    $map[$path]['samples'][] = $sample;
                }
            }
        }
    }
}

if (!function_exists('cb_restia_probe_walk')) {
    function cb_restia_probe_walk(array &$map, string $path, mixed $value): void
    {
        cb_restia_probe_register($map, $path, $value);

        if (!is_array($value)) {
            return;
        }

        if (array_is_list($value)) {
            foreach ($value as $item) {
                cb_restia_probe_walk($map, $path . '[]', $item);
            }
            return;
        }

        foreach ($value as $key => $subValue) {
            cb_restia_probe_walk($map, $path . '.' . (string)$key, $subValue);
        }
    }
}

if (!function_exists('cb_restia_probe_extract_orders')) {
    function cb_restia_probe_extract_orders(array $json): array
    {
        if (isset($json['data']) && is_array($json['data']) && array_is_list($json['data'])) {
            return $json['data'];
        }

        if (isset($json['orders']) && is_array($json['orders']) && array_is_list($json['orders'])) {
            return $json['orders'];
        }

        if (array_is_list($json)) {
            return $json;
        }

        return [];
    }
}

if (!function_exists('cb_restia_probe_get_pobocka_auto')) {
    function cb_restia_probe_get_pobocka_auto(mysqli $conn, int $forcedIdPob = 0): array
    {
        if ($forcedIdPob > 0) {
            $stmt = $conn->prepare('
                SELECT id_pob, nazev, restia_activePosId
                FROM pobocka
                WHERE id_pob = ?
                LIMIT 1
            ');

            if ($stmt === false) {
                throw new RuntimeException('DB: prepare selhal pro pobocka.');
            }

            $stmt->bind_param('i', $forcedIdPob);
            $stmt->execute();

            $res = $stmt->get_result();
            if ($res === false) {
                $stmt->close();
                throw new RuntimeException('DB: get_result selhal pro pobocka.');
            }

            $row = $res->fetch_assoc();
            $res->free();
            $stmt->close();

            if (!is_array($row)) {
                throw new RuntimeException('DB: pobočka s id_pob=' . (string)$forcedIdPob . ' nenalezena.');
            }

            $activePosId = trim((string)($row['restia_activePosId'] ?? ''));
            if ($activePosId === '') {
                throw new RuntimeException('DB: pobočka s id_pob=' . (string)$forcedIdPob . ' nemá restia_activePosId.');
            }

            return [
                'id_pob' => (int)$row['id_pob'],
                'nazev' => (string)($row['nazev'] ?? ''),
                'active_pos_id' => $activePosId,
                'auto' => 0,
            ];
        }

        $sql = '
            SELECT id_pob, nazev, restia_activePosId
            FROM pobocka
            WHERE aktivni = 1
              AND restia_activePosId IS NOT NULL
              AND restia_activePosId <> ""
            ORDER BY id_pob ASC
            LIMIT 1
        ';

        $res = $conn->query($sql);
        if ($res === false) {
            throw new RuntimeException('DB: nelze načíst automatickou pobočku.');
        }

        $row = $res->fetch_assoc();
        $res->free();

        if (!is_array($row)) {
            throw new RuntimeException('DB: není žádná aktivní pobočka s vyplněným restia_activePosId.');
        }

        return [
            'id_pob' => (int)$row['id_pob'],
            'nazev' => (string)($row['nazev'] ?? ''),
            'active_pos_id' => trim((string)($row['restia_activePosId'] ?? '')),
            'auto' => 1,
        ];
    }
}

if (!function_exists('cb_restia_probe_render_types')) {
    function cb_restia_probe_render_types(array $types): string
    {
        $out = [];
        foreach ($types as $type => $count) {
            $out[] = (string)$type . ':' . (string)$count;
        }
        return implode(',', $out);
    }
}

if (!function_exists('cb_restia_probe_render_section')) {
    function cb_restia_probe_render_section(string $title, array $map): string
    {
        if ($map === []) {
            return $title . "\n" . str_repeat('-', mb_strlen($title)) . "\nbez dat\n\n";
        }

        ksort($map);

        $out = $title . "\n" . str_repeat('-', mb_strlen($title)) . "\n";
        foreach ($map as $path => $info) {
            $out .=
                $path .
                ' || count=' . (string)$info['count'] .
                ' || types=' . cb_restia_probe_render_types($info['types']) .
                ' || sample=' . implode(' | ', $info['samples']) .
                "\n";
        }
        $out .= "\n";

        return $out;
    }
}

try {
    $conn = db();

    $forcedIdPob = (int)($_GET['id_pob'] ?? 0);
    $pob = cb_restia_probe_get_pobocka_auto($conn, $forcedIdPob);

    $range = cb_restia_probe_default_range_utc();
    $od = trim((string)($_GET['od'] ?? $range['od']));
    $do = trim((string)($_GET['do'] ?? $range['do']));

    $limit = (int)($_GET['limit'] ?? CB_RESTIA_PROBE_DEFAULT_LIMIT);
    if ($limit < 1) {
        $limit = 1;
    }
    if ($limit > 100) {
        $limit = 100;
    }

    $pages = (int)($_GET['pages'] ?? CB_RESTIA_PROBE_DEFAULT_PAGES);
    if ($pages < 1) {
        $pages = 1;
    }
    if ($pages > 20) {
        $pages = 20;
    }

    $menuId = trim((string)($_GET['menu_id'] ?? CB_RESTIA_PROBE_DEFAULT_MENU_ID));

    $out = '';
    $out .= "RESTIA PROBE\n";
    $out .= 'kdy: ' . cb_restia_probe_now_local() . "\n";
    $out .= 'id_pob: ' . (string)$pob['id_pob'] . "\n";
    $out .= 'pobocka: ' . (string)$pob['nazev'] . "\n";
    $out .= 'activePosId: ' . (string)$pob['active_pos_id'] . "\n";
    $out .= 'auto_pobocka: ' . ((int)$pob['auto'] === 1 ? 'ano' : 'ne') . "\n";
    $out .= 'od_utc: ' . $od . "\n";
    $out .= 'do_utc: ' . $do . "\n";
    $out .= 'limit: ' . (string)$limit . "\n";
    $out .= 'pages: ' . (string)$pages . "\n";
    $out .= 'menu_id: ' . $menuId . "\n\n";

    $ordersMap = [];
    $ordersInfo = [];
    $ordersTotal = 0;

    for ($page = 1; $page <= $pages; $page++) {
        $res = cb_restia_get(
            '/api/orders',
            [
                'page' => $page,
                'limit' => $limit,
                'createdFrom' => $od,
                'createdTo' => $do,
                'activePosId' => $pob['active_pos_id'],
            ],
            $pob['active_pos_id'],
            'probe orders'
        );

        if ((int)($res['ok'] ?? 0) !== 1) {
            $ordersInfo[] = [
                'page' => $page,
                'http_status' => (int)($res['http_status'] ?? 0),
                'total_count' => (string)($res['total_count'] ?? ''),
                'count' => 0,
                'ms' => (int)($res['ms'] ?? 0),
                'bytes_in' => (int)($res['bytes_in'] ?? 0),
                'chyba' => (string)($res['chyba'] ?? 'Restia orders vratila chybu.'),
            ];
            break;
        }

        $body = (string)($res['body'] ?? '');
        $json = json_decode($body, true);

        if (!is_array($json)) {
            $ordersInfo[] = [
                'page' => $page,
                'http_status' => (int)($res['http_status'] ?? 0),
                'total_count' => (string)($res['total_count'] ?? ''),
                'count' => 0,
                'ms' => (int)($res['ms'] ?? 0),
                'bytes_in' => (int)($res['bytes_in'] ?? 0),
                'chyba' => 'Orders nejsou validni JSON.',
            ];
            break;
        }

        cb_restia_probe_walk($ordersMap, 'orders_response', $json);

        $orders = cb_restia_probe_extract_orders($json);
        $countOrders = count($orders);
        $ordersTotal += $countOrders;

        foreach ($orders as $order) {
            if (is_array($order)) {
                cb_restia_probe_walk($ordersMap, 'order', $order);
            }
        }

        $ordersInfo[] = [
            'page' => $page,
            'http_status' => (int)($res['http_status'] ?? 0),
            'total_count' => (string)($res['total_count'] ?? ''),
            'count' => $countOrders,
            'ms' => (int)($res['ms'] ?? 0),
            'bytes_in' => (int)($res['bytes_in'] ?? 0),
            'chyba' => '',
        ];

        if ($countOrders < $limit) {
            break;
        }
    }

    $out .= "ORDERS STRANKY\n";
    $out .= "--------------\n";
    foreach ($ordersInfo as $row) {
        $out .=
            'page=' . (string)$row['page'] .
            ' http=' . (string)$row['http_status'] .
            ' total_count=' . (string)$row['total_count'] .
            ' pocet=' . (string)$row['count'] .
            ' ms=' . (string)$row['ms'] .
            ' bytes_in=' . (string)$row['bytes_in'];

        if ((string)$row['chyba'] !== '') {
            $out .= ' chyba=' . (string)$row['chyba'];
        }

        $out .= "\n";
    }
    $out .= 'celkem_objednavek_ve_vzorku=' . (string)$ordersTotal . "\n\n";

    $out .= cb_restia_probe_render_section('ORDERS CESTY', $ordersMap);

    $menuMap = [];

    if ($menuId !== '') {
        $resMenu = cb_restia_get(
            '/api/menu/' . $menuId,
            [
                'activePosId' => $pob['active_pos_id'],
            ],
            $pob['active_pos_id'],
            'probe menu'
        );

        if ((int)($resMenu['ok'] ?? 0) === 1) {
            $bodyMenu = (string)($resMenu['body'] ?? '');
            $jsonMenu = json_decode($bodyMenu, true);

            if (is_array($jsonMenu)) {
                cb_restia_probe_walk($menuMap, 'menu', $jsonMenu);

                $out .= "MENU INFO\n";
                $out .= "---------\n";
                $out .=
                    'http=' . (string)($resMenu['http_status'] ?? 0) .
                    ' ms=' . (string)($resMenu['ms'] ?? 0) .
                    ' bytes_in=' . (string)($resMenu['bytes_in'] ?? 0) . "\n\n";
            } else {
                $out .= "MENU INFO\n";
                $out .= "---------\n";
                $out .= "chyba=Menu neni validni JSON\n\n";
            }
        } else {
            $out .= "MENU INFO\n";
            $out .= "---------\n";
            $out .= 'chyba=' . (string)($resMenu['chyba'] ?? 'Restia menu vratila chybu.') . "\n\n";
        }
    } else {
        $out .= "MENU INFO\n";
        $out .= "---------\n";
        $out .= "chyba=Chybi menu_id\n\n";
    }

    $out .= cb_restia_probe_render_section('MENU CESTY', $menuMap);

    cb_restia_probe_txt_write($out);

    header('Content-Type: text/plain; charset=utf-8');
    echo "OK - zapsano do _kandidati/restia_probe.txt\n";
    exit;
} catch (Throwable $e) {
    $msg = "CHYBA: " . $e->getMessage() . "\n";
    cb_restia_probe_txt_write($msg);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

// lib/restia_schema_probe.php * Verze: V4 * Aktualizace: 01.04.2026
// Počet řádků: 431
// Konec souboru
