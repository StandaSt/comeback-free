<?php
declare(strict_types=1);

// admin_testy/02_restia_probe_endpointy.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/restia_client.php';
require_once __DIR__ . '/../db/db_api_restia.php';

const CB_RESTIA_PROBE_VERZE = 'V1';
const CB_RESTIA_PROBE_TXT = __DIR__ . '/02_restia_probe_endpointy.txt';

function cb_probe_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
}

function cb_probe_h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function cb_probe_txt_write(array $lines): void
{
    file_put_contents(CB_RESTIA_PROBE_TXT, implode("\n", $lines) . "\n", LOCK_EX);
}

function cb_probe_json_summary(string $body): array
{
    $out = [
        'valid_json' => 0,
        'root_type' => 'unknown',
        'count' => null,
        'keys' => [],
        'hits' => [],
    ];

    $json = json_decode($body, true);

    if (!is_array($json)) {
        return $out;
    }

    $out['valid_json'] = 1;
    $out['root_type'] = array_is_list($json) ? 'list' : 'object';
    $out['count'] = count($json);

    if (!array_is_list($json)) {
        $out['keys'] = array_slice(array_keys($json), 0, 25);
    }

    $needles = [
        'profile',
        'profiles',
        'posId',
        'activePosId',
        'menuId',
        'branch',
        'branches',
        'store',
        'stores',
        'shop',
        'shops',
        'name',
        'id',
    ];

    $bodyLower = mb_strtolower($body, 'UTF-8');
    foreach ($needles as $needle) {
        if (mb_strpos($bodyLower, mb_strtolower($needle, 'UTF-8')) !== false) {
            $out['hits'][] = $needle;
        }
    }

    return $out;
}

function cb_probe_body_preview(string $body, int $max = 1200): string
{
    $body = trim($body);
    if ($body === '') {
        return '';
    }

    if (mb_strlen($body, 'UTF-8') <= $max) {
        return $body;
    }

    return mb_substr($body, 0, $max, 'UTF-8') . "\n...[zkráceno]...";
}

function cb_probe_get_logged_ids(): array
{
    $user = $_SESSION['cb_user'] ?? null;
    $idUser = 0;
    $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);

    if (is_array($user)) {
        $idUser = (int)($user['id_user'] ?? 0);
    }

    return [
        'id_user' => $idUser,
        'id_login' => $idLogin,
    ];
}

function cb_probe_candidates(): array
{
    $from = (new DateTimeImmutable('-30 days', new DateTimeZone('UTC')))->setTime(0, 0, 0)->format('Y-m-d\TH:i:s\Z');
    $to = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTime(23, 59, 59)->format('Y-m-d\TH:i:s\Z');

    return [
        ['endpoint' => '/api/profiles',     'query' => []],
        ['endpoint' => '/api/profile',      'query' => []],
        ['endpoint' => '/api/pos',          'query' => []],
        ['endpoint' => '/api/positions',    'query' => []],
        ['endpoint' => '/api/branches',     'query' => []],
        ['endpoint' => '/api/stores',       'query' => []],
        ['endpoint' => '/api/shops',        'query' => []],
        ['endpoint' => '/api/eshops',       'query' => []],
        ['endpoint' => '/api/menu',         'query' => []],
        ['endpoint' => '/api/menus',        'query' => []],
        ['endpoint' => '/api/orders',       'query' => ['page' => 1, 'limit' => 20, 'createdFrom' => $from, 'createdTo' => $to]],
        ['endpoint' => '/api/orders',       'query' => ['page' => 1, 'limit' => 20]],
        ['endpoint' => '/api/orders',       'query' => ['page' => 1, 'limit' => 20, 'status' => 'delivered']],
        ['endpoint' => '/api/orders',       'query' => ['page' => 1, 'limit' => 20, 'source' => 'all']],
        ['endpoint' => '/api/orders',       'query' => ['page' => 1, 'limit' => 20, 'includeProfile' => '1']],
        ['endpoint' => '/api/orders',       'query' => ['page' => 1, 'limit' => 20, 'withProfile' => '1']],
    ];
}

$log = [];
$log[] = 'RESTIA PROBE ENDPOINTY – START';
$log[] = 'kdy: ' . cb_probe_now();
$log[] = 'verze: ' . CB_RESTIA_PROBE_VERZE;
$log[] = 'soubor: admin_testy/02_restia_probe_endpointy.php';
$log[] = str_repeat('-', 60);

try {
    $ids = cb_probe_get_logged_ids();
    $conn = db();

    $log[] = 'id_user: ' . (string)$ids['id_user'];
    $log[] = 'id_login: ' . (string)$ids['id_login'];
    $log[] = str_repeat('-', 60);

    $candidates = cb_probe_candidates();

    foreach ($candidates as $i => $cfg) {
        $endpoint = (string)$cfg['endpoint'];
        $query = is_array($cfg['query']) ? $cfg['query'] : [];

        $log[] = '';
        $log[] = 'TEST #' . (string)($i + 1);
        $log[] = 'endpoint: ' . $endpoint;
        $log[] = 'query: ' . json_encode($query, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $res = cb_restia_get(
            $endpoint,
            $query,
            null,
            'probe endpointy bez activePosId'
        );

        $http = (int)($res['http_status'] ?? 0);
        $ok = (int)($res['ok'] ?? 0);
        $total = $res['total_count'] ?? null;
        $body = (string)($res['body'] ?? '');
        $chyba = trim((string)($res['chyba'] ?? ''));

        $sum = cb_probe_json_summary($body);

        $log[] = 'ok: ' . (string)$ok;
        $log[] = 'http_status: ' . (string)$http;
        $log[] = 'total_count: ' . ($total === null ? 'null' : (string)$total);
        $log[] = 'valid_json: ' . (string)$sum['valid_json'];
        $log[] = 'root_type: ' . (string)$sum['root_type'];
        $log[] = 'count: ' . ($sum['count'] === null ? 'null' : (string)$sum['count']);
        $log[] = 'keys: ' . json_encode($sum['keys'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $log[] = 'hits: ' . json_encode($sum['hits'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($chyba !== '') {
            $log[] = 'chyba: ' . $chyba;
        }

        $log[] = 'BODY PREVIEW:';
        $log[] = cb_probe_body_preview($body);
        $log[] = str_repeat('-', 60);
    }

    if ($ids['id_user'] > 0 && $ids['id_login'] > 0) {
        db_api_restia_flush($conn, (int)$ids['id_user'], (int)$ids['id_login']);
        $log[] = '';
        $log[] = 'api_restia flush: OK';
    } else {
        $log[] = '';
        $log[] = 'api_restia flush: přeskočeno (chybí id_user nebo id_login)';
    }

} catch (Throwable $e) {
    $log[] = '';
    $log[] = 'FATAL: ' . $e->getMessage();
    $log[] = 'file: ' . $e->getFile();
    $log[] = 'line: ' . (string)$e->getLine();
}

$log[] = '';
$log[] = 'TXT: ' . CB_RESTIA_PROBE_TXT;
$log[] = 'END: ' . cb_probe_now();

cb_probe_txt_write($log);
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Restia probe endpointy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f5f7fb; color: #1f2933; }
        .box { background: #fff; border: 1px solid #d9e2ec; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
        pre { white-space: pre-wrap; word-break: break-word; background: #0f172a; color: #e5e7eb; padding: 14px; border-radius: 10px; overflow: auto; }
        code { background: #eef2f7; padding: 2px 6px; border-radius: 6px; }
    </style>
</head>
<body>
<div class="box">
    <h1>Restia probe endpointy</h1>
    <p>TXT log: <code><?= cb_probe_h(CB_RESTIA_PROBE_TXT) ?></code></p>
</div>

<div class="box">
    <pre><?= cb_probe_h(implode("\n", $log)) ?></pre>
</div>
</body>
</html>