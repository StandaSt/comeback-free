<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../lib/app.php';
require_once __DIR__ . '/../lib/system.php';
require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/../lib/restia_access_exist.php';
require_once __DIR__ . '/../lib/restia_client.php';
require_once __DIR__ . '/../db/db_api_restia.php';

const CB_RESTIA_MENU_PROBE_TXT = __DIR__ . '/../log/05_restia_menu_probe_varianty.txt';

if (!function_exists('cb_restia_menu_probe_h')) {
    function cb_restia_menu_probe_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_menu_probe_now')) {
    function cb_restia_menu_probe_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_restia_menu_probe_txt_write')) {
    function cb_restia_menu_probe_txt_write(array $lines): void
    {
        file_put_contents(CB_RESTIA_MENU_PROBE_TXT, implode("\n", $lines) . "\n", LOCK_EX);
    }
}

if (!function_exists('cb_restia_menu_probe_log')) {
    function cb_restia_menu_probe_log(string $line): void
    {
        file_put_contents(CB_RESTIA_MENU_PROBE_TXT, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_menu_probe_get_auth')) {
    function cb_restia_menu_probe_get_auth(): array
    {
        $user = $_SESSION['cb_user'] ?? null;
        $idUser = (int)(is_array($user) ? ($user['id_user'] ?? 0) : 0);
        $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);

        if ($idUser <= 0 || $idLogin <= 0) {
            throw new RuntimeException('Chybi prihlaseny uzivatel nebo id_login.');
        }

        return [
            'id_user' => $idUser,
            'id_login' => $idLogin,
        ];
    }
}

if (!function_exists('cb_restia_menu_probe_branchs')) {
    function cb_restia_menu_probe_branchs(mysqli $conn): array
    {
        $sql = '
            SELECT id_pob, nazev, restia_activePosId
            FROM pobocka
            WHERE aktivni = 1
            ORDER BY id_pob ASC
        ';

        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('DB dotaz na pobocky selhal.');
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $activePosId = trim((string)($row['restia_activePosId'] ?? ''));
            $out[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'nazev' => trim((string)($row['nazev'] ?? '')),
                'active_pos_id' => $activePosId,
                'enabled' => ($activePosId !== ''),
            ];
        }
        $res->free();

        return $out;
    }
}

if (!function_exists('cb_restia_menu_probe_decode_list')) {
    function cb_restia_menu_probe_decode_list(string $body): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return $decoded;
        }

        if (isset($decoded['data']) && is_array($decoded['data']) && array_is_list($decoded['data'])) {
            return $decoded['data'];
        }

        return [];
    }
}

if (!function_exists('cb_restia_menu_probe_menu_ids')) {
    function cb_restia_menu_probe_menu_ids(array $list): array
    {
        $ids = [];
        foreach ($list as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = trim((string)($item['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }
}

if (!function_exists('cb_restia_menu_probe_detail_summary')) {
    function cb_restia_menu_probe_detail_summary(array $decoded): array
    {
        $menuData = $decoded['data'] ?? null;
        if (!is_array($menuData)) {
            $menuData = $decoded;
        }

        $categories = $menuData['categories'] ?? [];
        if (!is_array($categories) || !array_is_list($categories)) {
            $categories = [];
        }

        $catCount = 0;
        $dishCount = 0;
        $priceCount = 0;
        $allergenCount = 0;
        $firstCats = [];
        $firstDishes = [];

        foreach ($categories as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $catCount++;
            $catName = trim((string)($cat['name'] ?? ($cat['label'] ?? ($cat['title'] ?? ''))));
            if ($catName !== '' && count($firstCats) < 5) {
                $firstCats[] = $catName;
            }

            $dishes = $cat['dishes'] ?? [];
            if (!is_array($dishes) || !array_is_list($dishes)) {
                continue;
            }

            foreach ($dishes as $dish) {
                if (!is_array($dish)) {
                    continue;
                }
                $dishCount++;
                $dishName = trim((string)($dish['name'] ?? ($dish['label'] ?? ($dish['title'] ?? ''))));
                if ($dishName !== '' && count($firstDishes) < 8) {
                    $firstDishes[] = $dishName;
                }

                foreach (['prices', 'priceRows', 'price', 'menuPrices'] as $key) {
                    if (!isset($dish[$key])) {
                        continue;
                    }
                    if (is_array($dish[$key])) {
                        if (array_is_list($dish[$key])) {
                            $priceCount += count($dish[$key]);
                        } elseif ($dish[$key] !== []) {
                            $priceCount++;
                        }
                    } elseif ($dish[$key] !== null && $dish[$key] !== '') {
                        $priceCount++;
                    }
                }

                foreach (['allergens', 'allergen', 'allergy'] as $key) {
                    if (!isset($dish[$key])) {
                        continue;
                    }
                    if (is_array($dish[$key])) {
                        if (array_is_list($dish[$key])) {
                            $allergenCount += count($dish[$key]);
                        } elseif ($dish[$key] !== []) {
                            $allergenCount++;
                        }
                    } elseif ($dish[$key] !== null && $dish[$key] !== '') {
                        $allergenCount++;
                    }
                }
            }
        }

        return [
            'categories' => $catCount,
            'dishes' => $dishCount,
            'prices' => $priceCount,
            'allergens' => $allergenCount,
            'first_cats' => implode(' | ', $firstCats),
            'first_dishes' => implode(' | ', $firstDishes),
        ];
    }
}

if (!function_exists('cb_restia_menu_probe_first_object_dump')) {
    function cb_restia_menu_probe_first_object_dump(array $decoded): array
    {
        $menuData = $decoded['data'] ?? null;
        if (!is_array($menuData)) {
            $menuData = $decoded;
        }

        $categories = $menuData['categories'] ?? [];
        if (!is_array($categories) || !array_is_list($categories) || $categories === []) {
            return ['cat_keys' => '', 'dish_keys' => '', 'cat_json' => '', 'dish_json' => ''];
        }

        $cat = $categories[0];
        if (!is_array($cat)) {
            return ['cat_keys' => '', 'dish_keys' => '', 'cat_json' => '', 'dish_json' => ''];
        }

        $catKeys = implode(',', array_slice(array_keys($cat), 0, 80));
        $catJson = json_encode($cat, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($catJson)) {
            $catJson = '';
        }

        $dishes = $cat['dishes'] ?? [];
        if (!is_array($dishes) || !array_is_list($dishes) || $dishes === []) {
            return ['cat_keys' => $catKeys, 'dish_keys' => '', 'cat_json' => $catJson, 'dish_json' => ''];
        }

        $dish = $dishes[0];
        if (!is_array($dish)) {
            return ['cat_keys' => $catKeys, 'dish_keys' => '', 'cat_json' => $catJson, 'dish_json' => ''];
        }

        $dishKeys = implode(',', array_slice(array_keys($dish), 0, 120));
        $dishJson = json_encode($dish, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($dishJson)) {
            $dishJson = '';
        }

        return [
            'cat_keys' => $catKeys,
            'dish_keys' => $dishKeys,
            'cat_json' => $catJson,
            'dish_json' => $dishJson,
        ];
    }
}

$conn = db();
$log = [];

try {
    $auth = cb_restia_menu_probe_get_auth();
    $branches = cb_restia_menu_probe_branchs($conn);

    $lookupVariants = [
        ['name' => 'base', 'query' => []],
        ['name' => 'activePosId_q', 'query' => ['activePosId' => '%%ACTIVE_POS_ID%%']],
        ['name' => 'posId_q', 'query' => ['posId' => '%%ID_POB%%']],
        ['name' => 'id_pob_q', 'query' => ['id_pob' => '%%ID_POB%%']],
        ['name' => 'branchId_q', 'query' => ['branchId' => '%%ID_POB%%']],
        ['name' => 'storeId_q', 'query' => ['storeId' => '%%ID_POB%%']],
    ];

    $detailVariants = [
        ['name' => 'base', 'query' => []],
        ['name' => 'activePosId_q', 'query' => ['activePosId' => '%%ACTIVE_POS_ID%%']],
        ['name' => 'posId_q', 'query' => ['posId' => '%%ID_POB%%']],
        ['name' => 'id_pob_q', 'query' => ['id_pob' => '%%ID_POB%%']],
    ];

    cb_restia_menu_probe_txt_write([
        'RESTIA MENU PROBE VARIANTY',
        'START: ' . cb_restia_menu_probe_now(),
        'POCET POBOCEK: ' . (string)count($branches),
        str_repeat('-', 80),
    ]);

    foreach ($branches as $branch) {
        $idPob = (int)($branch['id_pob'] ?? 0);
        $nazev = trim((string)($branch['nazev'] ?? ''));
        $activePosId = trim((string)($branch['active_pos_id'] ?? ''));

        if ($idPob <= 0 || $activePosId === '') {
            cb_restia_menu_probe_log('POBOCKA: ' . $nazev . ' | id_pob=' . $idPob . ' | activePosId=NE');
            continue;
        }

        cb_restia_menu_probe_log('POBOCKA: ' . $nazev . ' | id_pob=' . $idPob . ' | activePosId=' . $activePosId);

        $uniqueMenuIds = [];
        $lookupResults = [];

        foreach ($lookupVariants as $variant) {
            $query = $variant['query'];
            foreach ($query as $k => $v) {
                if ($v === '%%ACTIVE_POS_ID%%') {
                    $query[$k] = $activePosId;
                } elseif ($v === '%%ID_POB%%') {
                    $query[$k] = $idPob;
                }
            }

            $res = cb_restia_get(
                '/api/menu',
                $query,
                $activePosId,
                'menu probe lookup ' . $variant['name'] . ' id_pob=' . (string)$idPob
            );

            $body = (string)($res['body'] ?? '');
            $menuIds = cb_restia_menu_probe_menu_ids(cb_restia_menu_probe_decode_list($body));
            foreach ($menuIds as $mid) {
                $uniqueMenuIds[$mid] = true;
            }

            $lookupResults[] = [
                'name' => (string)$variant['name'],
                'http' => (int)($res['http_status'] ?? 0),
                'ok' => (int)($res['ok'] ?? 0),
                'count' => count($menuIds),
                'menuIds' => $menuIds,
                'err' => trim((string)($res['chyba'] ?? '')),
            ];
        }

        cb_restia_menu_probe_log('  LOOKUP VARIANTY:');
        foreach ($lookupResults as $r) {
            cb_restia_menu_probe_log(
                '    ' . $r['name']
                . ' | http=' . (string)$r['http']
                . ' | ok=' . (string)$r['ok']
                . ' | menuCount=' . (string)$r['count']
                . ' | menu=' . implode(',', $r['menuIds'])
                . ' | err=' . $r['err']
            );
        }

        $menuIds = array_keys($uniqueMenuIds);
        sort($menuIds);
        $menuIds = array_slice($menuIds, 0, 5);

        if ($menuIds === []) {
            cb_restia_menu_probe_log('  DETAIL: zadne menuId z lookup variant nebylo k dispozici.');
            cb_restia_menu_probe_log(str_repeat('-', 80));
            continue;
        }

        cb_restia_menu_probe_log('  DETAIL VARIANTY:');
        foreach ($menuIds as $menuId) {
            foreach ($detailVariants as $variant) {
                $query = $variant['query'];
                foreach ($query as $k => $v) {
                    if ($v === '%%ACTIVE_POS_ID%%') {
                        $query[$k] = $activePosId;
                    } elseif ($v === '%%ID_POB%%') {
                        $query[$k] = $idPob;
                    }
                }

                $detail = cb_restia_get(
                    '/api/menu/' . $menuId,
                    $query,
                    $activePosId,
                    'menu probe detail ' . $variant['name'] . ' id_pob=' . (string)$idPob
                );

                $detailHttp = (int)($detail['http_status'] ?? 0);
                $detailOk = (int)($detail['ok'] ?? 0);
                $detailErr = trim((string)($detail['chyba'] ?? ''));
                $detailBody = (string)($detail['body'] ?? '');
                $detailDecoded = json_decode($detailBody, true);
                $summary = [
                    'categories' => 0,
                    'dishes' => 0,
                    'prices' => 0,
                    'allergens' => 0,
                    'first_cats' => '',
                    'first_dishes' => '',
                ];
                if (is_array($detailDecoded)) {
                    $summary = cb_restia_menu_probe_detail_summary($detailDecoded);
                    $dump = cb_restia_menu_probe_first_object_dump($detailDecoded);
                    cb_restia_menu_probe_log('      FIRST CAT KEYS: ' . (string)$dump['cat_keys']);
                    cb_restia_menu_probe_log('      FIRST DISH KEYS: ' . (string)$dump['dish_keys']);
                    cb_restia_menu_probe_log('      FIRST CAT JSON: ' . (string)$dump['cat_json']);
                    cb_restia_menu_probe_log('      FIRST DISH JSON: ' . (string)$dump['dish_json']);
                }

                cb_restia_menu_probe_log(
                    '    menu=' . $menuId
                    . ' | ' . $variant['name']
                    . ' | http=' . (string)$detailHttp
                    . ' | ok=' . (string)$detailOk
                    . ' | cats=' . (string)$summary['categories']
                    . ' | dishes=' . (string)$summary['dishes']
                    . ' | prices=' . (string)$summary['prices']
                    . ' | alerg=' . (string)$summary['allergens']
                    . ' | firstCats=' . (string)$summary['first_cats']
                    . ' | firstDishes=' . (string)$summary['first_dishes']
                    . ' | err=' . $detailErr
                );
            }
        }

        cb_restia_menu_probe_log(str_repeat('-', 80));
    }

    cb_restia_menu_probe_log('KONEC: ' . cb_restia_menu_probe_now());
} catch (Throwable $e) {
    cb_restia_menu_probe_log('FATAL: ' . $e->getMessage());
    cb_restia_menu_probe_log('FILE: ' . $e->getFile());
    cb_restia_menu_probe_log('LINE: ' . (string)$e->getLine());
    throw $e;
}

$log = @file(CB_RESTIA_MENU_PROBE_TXT, FILE_IGNORE_NEW_LINES) ?: [];
?>
<!doctype html>
<html lang="cs">
<head>
    <meta charset="utf-8">
    <title>Restia menu probe varianty</title>
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
    <h1>Restia menu probe varianty</h1>
    <p>TXT log: <code><?= cb_restia_menu_probe_h(basename(CB_RESTIA_MENU_PROBE_TXT)) ?></code></p>
</div>

<div class="box">
    <pre><?= cb_restia_menu_probe_h(implode("\n", $log)) ?></pre>
</div>
</body>
</html>
