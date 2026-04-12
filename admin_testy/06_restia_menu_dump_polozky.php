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

const CB_RESTIA_MENU_DUMP_TXT = __DIR__ . '/../log/06_restia_menu_dump_polozky.txt';

if (!function_exists('cb_restia_menu_dump_h')) {
    function cb_restia_menu_dump_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_menu_dump_now')) {
    function cb_restia_menu_dump_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_restia_menu_dump_log_init')) {
    function cb_restia_menu_dump_log_init(): void
    {
        file_put_contents(CB_RESTIA_MENU_DUMP_TXT, '');
    }
}

if (!function_exists('cb_restia_menu_dump_log')) {
    function cb_restia_menu_dump_log(string $line): void
    {
        file_put_contents(CB_RESTIA_MENU_DUMP_TXT, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_menu_dump_first_text')) {
    function cb_restia_menu_dump_first_text(array $src, array $keys): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $src)) {
                continue;
            }
            $value = trim((string)$src[$key]);
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }
}

if (!function_exists('cb_restia_menu_dump_get_auth')) {
    function cb_restia_menu_dump_get_auth(): array
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

if (!function_exists('cb_restia_menu_dump_get_branches')) {
    function cb_restia_menu_dump_get_branches(mysqli $conn): array
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
            if ($activePosId === '') {
                continue;
            }
            $out[] = [
                'id_pob' => (int)($row['id_pob'] ?? 0),
                'nazev' => trim((string)($row['nazev'] ?? '')),
                'active_pos_id' => $activePosId,
            ];
        }
        $res->free();
        return $out;
    }
}

if (!function_exists('cb_restia_menu_dump_pick_branch')) {
    function cb_restia_menu_dump_pick_branch(array $branches): array
    {
        foreach ($branches as $branch) {
            if (!is_array($branch)) {
                continue;
            }
            if ((int)($branch['id_pob'] ?? 0) > 0) {
                return $branch;
            }
        }
        throw new RuntimeException('Neni zadna pobocka s restia_activePosId.');
    }
}

if (!function_exists('cb_restia_menu_dump_decode_menu')) {
    function cb_restia_menu_dump_decode_menu(string $body): array
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return [];
        }
        $data = $decoded['data'] ?? null;
        if (!is_array($data)) {
            $data = $decoded;
        }
        return is_array($data) ? $data : [];
    }
}

if (!function_exists('cb_restia_menu_dump_price_rows')) {
    function cb_restia_menu_dump_price_rows(array $dish, string $defaultPosCode): array
    {
        $rows = [];
        foreach (['generic', 'wolt', 'bolt', 'foodora', 'restia'] as $channel) {
            $node = $dish[$channel] ?? null;
            if (!is_array($node)) {
                continue;
            }
            $sizes = $node['sizes'] ?? null;
            if (!is_array($sizes) || !array_is_list($sizes)) {
                continue;
            }
            foreach ($sizes as $size) {
                if (!is_array($size)) {
                    continue;
                }
                $rows[] = [
                    'kanal' => $channel,
                    'size_id' => trim((string)($size['id'] ?? '__default__')),
                    'size_popis' => cb_restia_menu_dump_first_text($size, ['size', 'label', 'name']),
                    'pos_code' => cb_restia_menu_dump_first_text($size, ['posCode']) ?: $defaultPosCode,
                    'cena_hl' => isset($size['price']) ? (int)$size['price'] : (isset($node['price']) ? (int)$node['price'] : 0),
                    'balne_hl' => isset($size['packing']) ? (int)$size['packing'] : (isset($node['packing']) ? (int)$node['packing'] : 0),
                    'vat' => isset($node['vat']) ? (string)$node['vat'] : '',
                    'vat_v_restauraci' => isset($node['vatInRestaurant']) ? (string)$node['vatInRestaurant'] : '',
                ];
            }
        }
        return $rows;
    }
}

if (!function_exists('cb_restia_menu_dump_collect_sample')) {
    function cb_restia_menu_dump_collect_sample(array $decoded): array
    {
        $menuData = $decoded['data'] ?? null;
        if (!is_array($menuData)) {
            $menuData = $decoded;
        }

        $categories = $menuData['categories'] ?? [];
        if (!is_array($categories) || !array_is_list($categories)) {
            $categories = [];
        }

        $lines = [];
        $catCount = 0;
        $dishCount = 0;

        foreach ($categories as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $catCount++;
            if ($catCount > 3) {
                break;
            }

            $catId = trim((string)($cat['id'] ?? ''));
            $catName = '';
            if (isset($cat['_general']) && is_array($cat['_general'])) {
                $catName = cb_restia_menu_dump_first_text($cat['_general'], ['name', 'label', 'title']);
            }
            if ($catName === '') {
                $catName = cb_restia_menu_dump_first_text($cat, ['label', 'name', 'title']);
            }
            $lines[] = 'CAT ' . $catCount . ' | id=' . $catId . ' | name=' . $catName;

            $dishes = $cat['dishes'] ?? [];
            if (!is_array($dishes) || !array_is_list($dishes)) {
                continue;
            }

            $dishInCat = 0;
            foreach ($dishes as $dish) {
                if (!is_array($dish)) {
                    continue;
                }
                $dishInCat++;
                $dishCount++;
                if ($dishInCat > 4) {
                    break;
                }

                $dishId = trim((string)($dish['id'] ?? ''));
                $dishName = '';
                if (isset($dish['_general']) && is_array($dish['_general'])) {
                    $dishName = cb_restia_menu_dump_first_text($dish['_general'], ['name', 'label', 'title']);
                }
                if ($dishName === '') {
                    $dishName = cb_restia_menu_dump_first_text($dish, ['label', 'name', 'title']);
                }
                $posCode = '';
                if (isset($dish['_general']) && is_array($dish['_general'])) {
                    $posCode = cb_restia_menu_dump_first_text($dish['_general'], ['posCode']);
                }
                if ($posCode === '') {
                    $posCode = cb_restia_menu_dump_first_text($dish, ['posCode']);
                }
                $hidden = 0;
                foreach (['generic', 'wolt', 'bolt', 'foodora', 'restia'] as $channel) {
                    if (isset($dish[$channel]) && is_array($dish[$channel]) && (!empty($dish[$channel]['isHidden']) || !empty($dish[$channel]['hidden']))) {
                        $hidden = 1;
                        break;
                    }
                }

                $priceRows = cb_restia_menu_dump_price_rows($dish, $posCode);
                $lines[] = '  DISH ' . $dishInCat . ' | id=' . $dishId . ' | name=' . $dishName . ' | posCode=' . $posCode . ' | hidden=' . (string)$hidden . ' | prices=' . (string)count($priceRows);
                if ($priceRows !== []) {
                    $first = $priceRows[0];
                    $lines[] = '    PRICE | kanal=' . $first['kanal'] . ' | size=' . $first['size_id'] . ' | cena=' . (string)$first['cena_hl'] . ' | balne=' . (string)$first['balne_hl'];
                }
            }
        }

        return [
            'cat_count' => $catCount,
            'dish_count' => $dishCount,
            'lines' => $lines,
        ];
    }
}

$conn = db();
$auth = cb_restia_menu_dump_get_auth();
$branches = cb_restia_menu_dump_get_branches($conn);
$branch = cb_restia_menu_dump_pick_branch($branches);
$activePosId = (string)$branch['active_pos_id'];
$branchName = (string)$branch['nazev'];
$idPob = (int)$branch['id_pob'];

cb_restia_menu_dump_log_init();
cb_restia_menu_dump_log('START: ' . cb_restia_menu_dump_now());
cb_restia_menu_dump_log('POBOCKA: ' . $branchName . ' | ID_POBOCKA: ' . (string)$idPob);
cb_restia_menu_dump_log('ACTIVE_POS_ID: ' . $activePosId);

$lookup = cb_restia_get('/api/menu', ['activePosId' => $activePosId], $activePosId, 'menu dump lookup id_pob=' . (string)$idPob);
if ((int)($lookup['ok'] ?? 0) !== 1) {
    cb_restia_menu_dump_log('LOOKUP ERR HTTP=' . (string)($lookup['http_status'] ?? 0) . ' MSG=' . trim((string)($lookup['chyba'] ?? '')));
    throw new RuntimeException('Menu lookup selhal.');
}

$lookupDecoded = cb_restia_menu_dump_decode_menu((string)($lookup['body'] ?? ''));
$menuId = '';
if (isset($lookupDecoded['id']) && is_string($lookupDecoded['id']) && trim($lookupDecoded['id']) !== '') {
    $menuId = trim($lookupDecoded['id']);
} elseif (isset($lookupDecoded[0]) && is_array($lookupDecoded[0])) {
    $menuId = trim((string)($lookupDecoded[0]['id'] ?? ''));
}
if ($menuId === '') {
    throw new RuntimeException('Menu ID se nepodarilo zjistit.');
}

cb_restia_menu_dump_log('MENU_ID: ' . $menuId);

$detail = cb_restia_get('/api/menu/' . $menuId, ['activePosId' => $activePosId], $activePosId, 'menu dump detail id_pob=' . (string)$idPob . ' menu=' . $menuId);
if ((int)($detail['ok'] ?? 0) !== 1) {
    cb_restia_menu_dump_log('DETAIL ERR HTTP=' . (string)($detail['http_status'] ?? 0) . ' MSG=' . trim((string)($detail['chyba'] ?? '')));
    throw new RuntimeException('Menu detail selhal.');
}

$decoded = cb_restia_menu_dump_decode_menu((string)($detail['body'] ?? ''));
$sample = cb_restia_menu_dump_collect_sample($decoded);

cb_restia_menu_dump_log('SUMMARY: categories=' . (string)$sample['cat_count'] . ' dishes=' . (string)$sample['dish_count']);
foreach ($sample['lines'] as $line) {
    cb_restia_menu_dump_log($line);
}
cb_restia_menu_dump_log('KONEC: ' . cb_restia_menu_dump_now());

?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Restia menu dump položky</title>
</head>
<body>
  <div style="max-width:1100px; margin:24px auto; font-family:Arial,sans-serif;">
    <h1 style="margin:0 0 12px 0;">Restia menu dump položky</h1>
    <p style="margin:0 0 10px 0;">TXT log: <?= cb_restia_menu_dump_h(basename(CB_RESTIA_MENU_DUMP_TXT)) ?></p>
    <p style="margin:0 0 10px 0;">Pobočka: <strong><?= cb_restia_menu_dump_h($branchName) ?></strong> | activePosId: <strong><?= cb_restia_menu_dump_h($activePosId) ?></strong></p>
    <p style="margin:0 0 10px 0;">Menu ID: <strong><?= cb_restia_menu_dump_h($menuId) ?></strong></p>
    <p style="margin:0 0 16px 0;">Kategorie: <strong><?= cb_restia_menu_dump_h((string)$sample['cat_count']) ?></strong> | Položky: <strong><?= cb_restia_menu_dump_h((string)$sample['dish_count']) ?></strong></p>
    <pre style="white-space:pre-wrap; background:#fff; border:1px solid #ccc; padding:12px; line-height:1.4;"><?= cb_restia_menu_dump_h(implode("\n", $sample['lines'])) ?></pre>
  </div>
</body>
</html>
