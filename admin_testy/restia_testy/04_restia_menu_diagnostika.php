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

if (!function_exists('cb_restia_menu_diag_h')) {
    function cb_restia_menu_diag_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_menu_diag_txt_path')) {
    function cb_restia_menu_diag_txt_path(): string
    {
        return dirname(__DIR__) . '/log/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt';
    }
}

if (!function_exists('cb_restia_menu_diag_log')) {
    function cb_restia_menu_diag_log(string $line): void
    {
        @file_put_contents(cb_restia_menu_diag_txt_path(), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_menu_diag_now')) {
    function cb_restia_menu_diag_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_restia_menu_diag_collect_summary')) {
    function cb_restia_menu_diag_collect_summary(array $decoded): array
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
                    if (isset($dish[$key])) {
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
                }

                foreach (['allergens', 'allergen', 'allergy'] as $key) {
                    if (isset($dish[$key])) {
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

if (!function_exists('cb_restia_menu_diag_get_auth')) {
    function cb_restia_menu_diag_get_auth(): array
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

$conn = db();
$rows = [];

try {
    $auth = cb_restia_menu_diag_get_auth();
    $q = $conn->query('SELECT id_pob, nazev, restia_activePosId FROM pobocka ORDER BY id_pob ASC');
    if (!($q instanceof mysqli_result)) {
        throw new RuntimeException('DB dotaz na pobocky selhal.');
    }

    cb_restia_menu_diag_log('-----');
    cb_restia_menu_diag_log('START: ' . cb_restia_menu_diag_now());

    while ($row = $q->fetch_assoc()) {
        $idPob = (int)($row['id_pob'] ?? 0);
        $nazev = trim((string)($row['nazev'] ?? ''));
        $activePosId = trim((string)($row['restia_activePosId'] ?? ''));

        if ($idPob <= 0 || $activePosId === '') {
            cb_restia_menu_diag_log('POBOCKA: ' . $nazev . ' | id_pob=' . $idPob . ' | activePosId=NE');
            continue;
        }

        $res = cb_restia_get('/api/menu', [], $activePosId, 'diag menu id_pob=' . $idPob);
        $http = (int)($res['http_status'] ?? 0);
        $ok = (int)($res['ok'] ?? 0);
        $body = (string)($res['body'] ?? '');
        $decoded = json_decode($body, true);
        $menuIds = [];

        if (is_array($decoded)) {
            $list = array_is_list($decoded)
                ? $decoded
                : (isset($decoded['data']) && is_array($decoded['data']) && array_is_list($decoded['data']) ? $decoded['data'] : []);
            foreach ($list as $menu) {
                if (!is_array($menu)) {
                    continue;
                }
                $mid = trim((string)($menu['id'] ?? ''));
                if ($mid !== '') {
                    $menuIds[] = $mid;
                }
            }
        }

        $detailHttp = 0;
        $detailOk = 0;
        $detailSignature = '';
        $detailMenuId = count($menuIds) > 0 ? (string)$menuIds[0] : '';
        if ($detailMenuId !== '') {
            $detail = cb_restia_get('/api/menu/' . $detailMenuId, ['activePosId' => $activePosId], $activePosId, 'diag detail id_pob=' . $idPob);
            $detailHttp = (int)($detail['http_status'] ?? 0);
            $detailOk = (int)($detail['ok'] ?? 0);
            $detailDecoded = json_decode((string)($detail['body'] ?? ''), true);
            if (is_array($detailDecoded)) {
                $summary = cb_restia_menu_diag_collect_summary($detailDecoded);
                $detailSignature = 'cats=' . (string)$summary['categories']
                    . ' dishes=' . (string)$summary['dishes']
                    . ' prices=' . (string)$summary['prices']
                    . ' alerg=' . (string)$summary['allergens']
                    . ' firstCats=' . (string)$summary['first_cats']
                    . ' firstDishes=' . (string)$summary['first_dishes'];
            } else {
                $detailSignature = 'neplatny JSON';
            }
            if ((int)($detail['ok'] ?? 0) !== 1) {
                $detailSignature .= ' | err=' . trim((string)($detail['chyba'] ?? ''));
            }
        }

        cb_restia_menu_diag_log(
            'POBOCKA: ' . $nazev
            . ' | id_pob=' . $idPob
            . ' | activePosId=' . $activePosId
            . ' | http=' . $http
            . ' | ok=' . $ok
            . ' | menu=' . implode(',', $menuIds)
            . ' | detail_http=' . $detailHttp
            . ' | detail_ok=' . $detailOk
            . ' | detail=' . $detailSignature
            . ' | err=' . trim((string)($res['chyba'] ?? ''))
        );

        $rows[] = [
            'id_pob' => $idPob,
            'nazev' => $nazev,
            'activePosId' => $activePosId,
            'http' => $http,
            'ok' => $ok,
            'menuIds' => $menuIds,
            'detailHttp' => $detailHttp,
            'detailOk' => $detailOk,
            'detailSignature' => $detailSignature,
            'chyba' => trim((string)($res['chyba'] ?? '')),
        ];
    }
    $q->free();

    cb_restia_menu_diag_log('KONEC: ' . cb_restia_menu_diag_now());
} catch (Throwable $e) {
    cb_restia_menu_diag_log('CHYBA: ' . $e->getMessage());
    throw $e;
}
?>
<!doctype html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <title>Restia menu diagnostika</title>
</head>
<body>
  <h1>Restia menu diagnostika</h1>
  <p>TXT log: <?= cb_restia_menu_diag_h(basename(cb_restia_menu_diag_txt_path())) ?></p>
  <table border="1" cellpadding="4" cellspacing="0">
    <thead>
      <tr>
        <th>Pobočka</th>
        <th>activePosId</th>
        <th>HTTP</th>
        <th>OK</th>
        <th>Menu ID</th>
        <th>Detail</th>
        <th>Chyba</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= cb_restia_menu_diag_h((string)$r['nazev']) ?></td>
          <td><?= cb_restia_menu_diag_h((string)$r['activePosId']) ?></td>
          <td><?= cb_restia_menu_diag_h((string)$r['http']) ?></td>
          <td><?= cb_restia_menu_diag_h((string)$r['ok']) ?></td>
          <td><?= cb_restia_menu_diag_h(implode(',', (array)$r['menuIds'])) ?></td>
          <td><?= cb_restia_menu_diag_h((string)$r['detailSignature']) ?></td>
          <td><?= cb_restia_menu_diag_h((string)$r['chyba']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
