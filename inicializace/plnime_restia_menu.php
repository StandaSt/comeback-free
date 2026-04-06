<?php
// inicializace/plnime_restia_menu.php * Verze: V1 * Aktualizace: 02.04.2026
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

const CB_RESTIA_MENU_DEFAULT_ID = '762f8daa-ca39-4d8f-ae4a-d22b4d106e88';

$cbRestiaMenuEmbedMode = (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php');

if (!function_exists('cb_restia_menu_h')) {
    function cb_restia_menu_h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cb_restia_menu_txt_path')) {
    function cb_restia_menu_txt_path(): string
    {
        return __DIR__ . '/' . pathinfo(__FILE__, PATHINFO_FILENAME) . '.txt';
    }
}

if (!function_exists('cb_restia_menu_log')) {
    function cb_restia_menu_log(string $line): void
    {
        @file_put_contents(cb_restia_menu_txt_path(), $line . "\n", FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('cb_restia_menu_now')) {
    function cb_restia_menu_now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('Y-m-d H:i:s');
    }
}

if (!function_exists('cb_restia_menu_now_cs')) {
    function cb_restia_menu_now_cs(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format('d.m.Y H:i:s');
    }
}

if (!function_exists('cb_restia_menu_json')) {
    function cb_restia_menu_json(mixed $value): ?string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return null;
        }
        return $json;
    }
}

if (!function_exists('cb_restia_menu_get_auth')) {
    function cb_restia_menu_get_auth(): array
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

if (!function_exists('cb_restia_menu_get_branches')) {
    function cb_restia_menu_get_branches(mysqli $conn): array
    {
        $sql = '
            SELECT id_pob, nazev, restia_activePosId
            FROM pobocka
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

if (!function_exists('cb_restia_menu_get_branch_by_id')) {
    function cb_restia_menu_get_branch_by_id(mysqli $conn, int $idPob): array
    {
        if ($idPob <= 0) {
            throw new RuntimeException('Neplatna pobocka.');
        }

        $stmt = $conn->prepare('
            SELECT id_pob, nazev, restia_activePosId
            FROM pobocka
            WHERE id_pob = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('DB dotaz na pobocku selhal.');
        }

        $stmt->bind_param('i', $idPob);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
        if ($res instanceof mysqli_result) {
            $res->free();
        }
        $stmt->close();

        if (!is_array($row)) {
            throw new RuntimeException('Vybrana pobocka neexistuje.');
        }

        $activePosId = trim((string)($row['restia_activePosId'] ?? ''));
        if ($activePosId === '') {
            throw new RuntimeException('Vybrana pobocka nema vyplnene restia_activePosId.');
        }

        return [
            'id_pob' => (int)($row['id_pob'] ?? 0),
            'nazev' => trim((string)($row['nazev'] ?? '')),
            'active_pos_id' => $activePosId,
        ];
    }
}

if (!function_exists('cb_restia_menu_pick_default_branch')) {
    function cb_restia_menu_pick_default_branch(mysqli $conn): array
    {
        $branches = cb_restia_menu_get_branches($conn);
        foreach ($branches as $branch) {
            if ((bool)($branch['enabled'] ?? false) === true) {
                return [
                    'id_pob' => (int)($branch['id_pob'] ?? 0),
                    'nazev' => (string)($branch['nazev'] ?? ''),
                    'active_pos_id' => (string)($branch['active_pos_id'] ?? ''),
                ];
            }
        }
        throw new RuntimeException('Neni zadna pobocka s vyplnenym restia_activePosId.');
    }
}

if (!function_exists('cb_restia_menu_status_map')) {
    function cb_restia_menu_status_map(mysqli $conn): array
    {
        $sql = '
            SELECT id_pob, COUNT(*) AS cnt
            FROM res_kategorie
            GROUP BY id_pob
        ';
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            return [];
        }

        $out = [];
        while ($row = $res->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            if ($idPob <= 0) {
                continue;
            }
            $out[$idPob] = ((int)($row['cnt'] ?? 0) > 0);
        }
        $res->free();
        return $out;
    }
}

if (!function_exists('cb_restia_menu_find_menu_id')) {
    function cb_restia_menu_find_menu_id(mysqli $conn, int $idPob): string
    {
        $stmt = $conn->prepare('
            SELECT raw_json
            FROM objednavky_restia
            WHERE id_pob = ?
              AND COALESCE(raw_json, "") <> ""
            ORDER BY id_obj DESC
            LIMIT 400
        ');
        if ($stmt !== false) {
            $stmt->bind_param('i', $idPob);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res instanceof mysqli_result) {
                while ($row = $res->fetch_assoc()) {
                    $raw = (string)($row['raw_json'] ?? '');
                    if ($raw === '') {
                        continue;
                    }
                    $json = json_decode($raw, true);
                    if (!is_array($json)) {
                        continue;
                    }
                    $menuId = trim((string)($json['profile']['menuId'] ?? ''));
                    if ($menuId !== '') {
                        $res->free();
                        $stmt->close();
                        return $menuId;
                    }
                }
                $res->free();
            }
            $stmt->close();
        }
        return CB_RESTIA_MENU_DEFAULT_ID;
    }
}

if (!function_exists('cb_restia_menu_channel_hidden')) {
    function cb_restia_menu_channel_hidden(array $dish): int
    {
        foreach (['isHidden', 'hidden'] as $k) {
            if (!empty($dish[$k])) {
                return 1;
            }
        }
        foreach (['generic', 'wolt', 'bolt', 'foodora', 'restia'] as $channel) {
            if (!isset($dish[$channel]) || !is_array($dish[$channel])) {
                continue;
            }
            if (!empty($dish[$channel]['isHidden']) || !empty($dish[$channel]['hidden'])) {
                return 1;
            }
        }
        return 0;
    }
}

if (!function_exists('cb_restia_menu_first_text')) {
    function cb_restia_menu_first_text(array $src, array $keys): string
    {
        foreach ($keys as $k) {
            if (isset($src[$k])) {
                $v = trim((string)$src[$k]);
                if ($v !== '') {
                    return $v;
                }
            }
        }
        return '';
    }
}

if (!function_exists('cb_restia_menu_collect_price_rows')) {
    function cb_restia_menu_collect_price_rows(array $dish, string $defaultPosCode): array
    {
        $rows = [];
        foreach (['generic', 'wolt', 'bolt', 'foodora', 'restia'] as $channel) {
            $node = $dish[$channel] ?? null;
            if (!is_array($node)) {
                continue;
            }
            $sizes = $node['sizes'] ?? null;
            if (is_array($sizes) && array_is_list($sizes)) {
                foreach ($sizes as $size) {
                    if (!is_array($size)) {
                        continue;
                    }
                    $sizeId = trim((string)($size['id'] ?? ''));
                    if ($sizeId === '') {
                        $sizeId = '__default__';
                    }
                    $price = isset($size['price']) ? (int)$size['price'] : (isset($node['price']) ? (int)$node['price'] : 0);
                    $packing = isset($size['packing']) ? (int)$size['packing'] : (isset($node['packing']) ? (int)$node['packing'] : 0);
                    $rows[] = [
                        'kanal' => $channel,
                        'size_id' => $sizeId,
                        'size_popis' => cb_restia_menu_first_text($size, ['size', 'label', 'name']),
                        'pos_code' => cb_restia_menu_first_text($size, ['posCode']) ?: $defaultPosCode,
                        'cena_hl' => max(0, $price),
                        'balne_hl' => max(0, $packing),
                        'vat' => isset($node['vat']) ? (string)$node['vat'] : null,
                        'vat_v_restauraci' => isset($node['vatInRestaurant']) ? (string)$node['vatInRestaurant'] : null,
                        'raw_json' => cb_restia_menu_json($size),
                    ];
                }
            } elseif (isset($node['price'])) {
                $rows[] = [
                    'kanal' => $channel,
                    'size_id' => '__default__',
                    'size_popis' => '',
                    'pos_code' => cb_restia_menu_first_text($node, ['posCode']) ?: $defaultPosCode,
                    'cena_hl' => max(0, (int)$node['price']),
                    'balne_hl' => max(0, (int)($node['packing'] ?? 0)),
                    'vat' => isset($node['vat']) ? (string)$node['vat'] : null,
                    'vat_v_restauraci' => isset($node['vatInRestaurant']) ? (string)$node['vatInRestaurant'] : null,
                    'raw_json' => cb_restia_menu_json($node),
                ];
            }
        }

        $uniq = [];
        foreach ($rows as $r) {
            $k = (string)$r['kanal'] . '|' . (string)$r['size_id'];
            $uniq[$k] = $r;
        }
        return array_values($uniq);
    }
}

if (!function_exists('cb_restia_menu_collect_allergens')) {
    function cb_restia_menu_collect_allergens(array $dish): array
    {
        $src = [];
        if (isset($dish['allergens']) && is_array($dish['allergens'])) {
            $src = $dish['allergens'];
        } elseif (isset($dish['generic']) && is_array($dish['generic']) && isset($dish['generic']['allergens']) && is_array($dish['generic']['allergens'])) {
            $src = $dish['generic']['allergens'];
        }

        $out = [];
        foreach ($src as $one) {
            if (is_scalar($one)) {
                $v = trim((string)$one);
            } elseif (is_array($one)) {
                $v = trim((string)($one['id'] ?? ($one['code'] ?? ($one['value'] ?? ''))));
            } else {
                $v = '';
            }
            if ($v !== '') {
                $out[$v] = $v;
            }
        }
        return array_values($out);
    }
}

if (!function_exists('cb_restia_menu_upsert_kategorie')) {
    function cb_restia_menu_upsert_kategorie(mysqli $conn, int $idPob, string $activePosId, string $menuId, string $restiaCatId, string $nazev, int $poradi, int $skryta): int
    {
        $sql = '
            INSERT INTO res_kategorie (
                id_pob, restia_active_pos_id, restia_menu_id, restia_kategorie_id, nazev, poradi, skryta, aktivni, vytvoreno, zmeneno
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(3), NOW(3))
            ON DUPLICATE KEY UPDATE
                id_res_kategorie = LAST_INSERT_ID(id_res_kategorie),
                restia_active_pos_id = VALUES(restia_active_pos_id),
                nazev = VALUES(nazev),
                poradi = VALUES(poradi),
                skryta = VALUES(skryta),
                aktivni = 1,
                zmeneno = NOW(3)
        ';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: res_kategorie.');
        }
        $stmt->bind_param('issssii', $idPob, $activePosId, $menuId, $restiaCatId, $nazev, $poradi, $skryta);
        $stmt->execute();
        $stmt->close();
        return (int)$conn->insert_id;
    }
}

if (!function_exists('cb_restia_menu_upsert_polozka')) {
    function cb_restia_menu_upsert_polozka(mysqli $conn, int $idResKat, string $restiaPolozkaId, string $nazev, ?string $nazevEn, ?string $popis, ?string $popisEn, ?string $posCode, ?string $imageUrl, ?string $rawJson, int $skryta): int
    {
        $sql = '
            INSERT INTO res_polozky (
                id_res_kategorie, restia_polozka_id, nazev, nazev_en, popis, popis_en, pos_code, image_url, raw_json, skryta, aktivni, vytvoreno, zmeneno
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(3), NOW(3))
            ON DUPLICATE KEY UPDATE
                id_res_polozka = LAST_INSERT_ID(id_res_polozka),
                nazev = VALUES(nazev),
                nazev_en = VALUES(nazev_en),
                popis = VALUES(popis),
                popis_en = VALUES(popis_en),
                pos_code = VALUES(pos_code),
                image_url = VALUES(image_url),
                raw_json = VALUES(raw_json),
                skryta = VALUES(skryta),
                aktivni = 1,
                zmeneno = NOW(3)
        ';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: res_polozky.');
        }
        $stmt->bind_param('issssssssi', $idResKat, $restiaPolozkaId, $nazev, $nazevEn, $popis, $popisEn, $posCode, $imageUrl, $rawJson, $skryta);
        $stmt->execute();
        $stmt->close();
        return (int)$conn->insert_id;
    }
}

if (!function_exists('cb_restia_menu_sync_prices')) {
    function cb_restia_menu_sync_prices(mysqli $conn, int $idResPolozka, array $prices): int
    {
        $deactivate = $conn->prepare('UPDATE res_cena SET aktivni = 0, zmeneno = NOW(3) WHERE id_res_polozka = ?');
        if ($deactivate === false) {
            throw new RuntimeException('DB prepare selhal: res_cena deactivate.');
        }
        $deactivate->bind_param('i', $idResPolozka);
        $deactivate->execute();
        $deactivate->close();

        $sql = '
            INSERT INTO res_cena (
                id_res_polozka, kanal, size_id, size_popis, pos_code, cena_hl, balne_hl, vat, vat_v_restauraci, mena, raw_json, aktivni, vytvoreno, zmeneno
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "CZK", ?, 1, NOW(3), NOW(3))
            ON DUPLICATE KEY UPDATE
                size_popis = VALUES(size_popis),
                pos_code = VALUES(pos_code),
                cena_hl = VALUES(cena_hl),
                balne_hl = VALUES(balne_hl),
                vat = VALUES(vat),
                vat_v_restauraci = VALUES(vat_v_restauraci),
                mena = VALUES(mena),
                raw_json = VALUES(raw_json),
                aktivni = 1,
                zmeneno = NOW(3)
        ';
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('DB prepare selhal: res_cena upsert.');
        }

        $count = 0;
        foreach ($prices as $p) {
            $kanal = (string)($p['kanal'] ?? '');
            $sizeId = (string)($p['size_id'] ?? '');
            if ($kanal === '' || $sizeId === '') {
                continue;
            }
            $sizePopis = (string)($p['size_popis'] ?? '');
            $posCode = (string)($p['pos_code'] ?? '');
            $cenaHl = (int)($p['cena_hl'] ?? 0);
            $balneHl = (int)($p['balne_hl'] ?? 0);
            $vat = (string)($p['vat'] ?? '');
            $vat = ($vat === '') ? null : $vat;
            $vatRest = (string)($p['vat_v_restauraci'] ?? '');
            $vatRest = ($vatRest === '') ? null : $vatRest;
            $rawJson = (string)($p['raw_json'] ?? '');
            $rawJson = ($rawJson === '') ? null : $rawJson;
            $stmt->bind_param('issssiisss', $idResPolozka, $kanal, $sizeId, $sizePopis, $posCode, $cenaHl, $balneHl, $vat, $vatRest, $rawJson);
            $stmt->execute();
            $count++;
        }
        $stmt->close();
        return $count;
    }
}

if (!function_exists('cb_restia_menu_sync_allergens')) {
    function cb_restia_menu_sync_allergens(mysqli $conn, int $idResPolozka, array $allergens): int
    {
        $del = $conn->prepare('DELETE FROM res_alergen WHERE id_res_polozka = ?');
        if ($del === false) {
            throw new RuntimeException('DB prepare selhal: res_alergen delete.');
        }
        $del->bind_param('i', $idResPolozka);
        $del->execute();
        $del->close();

        $ins = $conn->prepare('INSERT INTO res_alergen (id_res_polozka, alergen, vytvoreno) VALUES (?, ?, NOW(3))');
        if ($ins === false) {
            throw new RuntimeException('DB prepare selhal: res_alergen insert.');
        }
        $cnt = 0;
        foreach ($allergens as $a) {
            $a = trim((string)$a);
            if ($a === '') {
                continue;
            }
            $ins->bind_param('is', $idResPolozka, $a);
            $ins->execute();
            $cnt++;
        }
        $ins->close();
        return $cnt;
    }
}

if (!function_exists('cb_restia_menu_deactivate_missing')) {
    function cb_restia_menu_deactivate_missing(mysqli $conn, int $idPob, string $menuId, array $seenCats, array $seenItemsByCat): void
    {
        if ($seenCats === []) {
            $stmt = $conn->prepare('UPDATE res_kategorie SET aktivni = 0, zmeneno = NOW(3) WHERE id_pob = ? AND restia_menu_id = ?');
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: deactivate all categories.');
            }
            $stmt->bind_param('is', $idPob, $menuId);
            $stmt->execute();
            $stmt->close();
            return;
        }

        $catIds = array_map('intval', array_values($seenCats));
        $catList = implode(',', $catIds);
        $sqlCat = 'UPDATE res_kategorie SET aktivni = 0, zmeneno = NOW(3) WHERE id_pob = ? AND restia_menu_id = ? AND id_res_kategorie NOT IN (' . $catList . ')';
        $stmtCat = $conn->prepare($sqlCat);
        if ($stmtCat === false) {
            throw new RuntimeException('DB prepare selhal: deactivate missing categories.');
        }
        $stmtCat->bind_param('is', $idPob, $menuId);
        $stmtCat->execute();
        $stmtCat->close();

        foreach ($catIds as $catId) {
            $seenItems = $seenItemsByCat[$catId] ?? [];
            if ($seenItems === []) {
                $stmt = $conn->prepare('UPDATE res_polozky SET aktivni = 0, zmeneno = NOW(3) WHERE id_res_kategorie = ?');
                if ($stmt === false) {
                    throw new RuntimeException('DB prepare selhal: deactivate category items.');
                }
                $stmt->bind_param('i', $catId);
                $stmt->execute();
                $stmt->close();
                continue;
            }
            $itemIds = array_map('intval', array_values($seenItems));
            $itemList = implode(',', $itemIds);
            $sql = 'UPDATE res_polozky SET aktivni = 0, zmeneno = NOW(3) WHERE id_res_kategorie = ? AND id_res_polozka NOT IN (' . $itemList . ')';
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                throw new RuntimeException('DB prepare selhal: deactivate missing items.');
            }
            $stmt->bind_param('i', $catId);
            $stmt->execute();
            $stmt->close();
        }

        $stmtPrice = $conn->prepare('
            UPDATE res_cena c
            JOIN res_polozky p ON p.id_res_polozka = c.id_res_polozka
            JOIN res_kategorie k ON k.id_res_kategorie = p.id_res_kategorie
            SET c.aktivni = 0, c.zmeneno = NOW(3)
            WHERE k.id_pob = ?
              AND k.restia_menu_id = ?
              AND p.aktivni = 0
        ');
        if ($stmtPrice === false) {
            throw new RuntimeException('DB prepare selhal: deactivate prices for inactive items.');
        }
        $stmtPrice->bind_param('is', $idPob, $menuId);
        $stmtPrice->execute();
        $stmtPrice->close();
    }
}

if (!function_exists('cb_restia_menu_import_once')) {
    function cb_restia_menu_import_once(mysqli $conn, array $auth, array $branch, string $menuId): array
    {
        $t0 = (int)round(microtime(true) * 1000);
        $idPob = (int)$branch['id_pob'];
        $activePosId = (string)$branch['active_pos_id'];

        $res = cb_restia_get('/api/menu/' . $menuId, [
            'activePosId' => $activePosId,
        ], $activePosId, 'menu import id_pob=' . $idPob . ' menu=' . $menuId);

        if ((int)($res['ok'] ?? 0) !== 1) {
            $http = (int)($res['http_status'] ?? 0);
            $msg = trim((string)($res['chyba'] ?? 'Restia vratila chybu.'));
            throw new RuntimeException('Restia menu chyba HTTP=' . $http . ' msg=' . $msg);
        }

        $decoded = json_decode((string)($res['body'] ?? ''), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Restia menu vratila neplatny JSON.');
        }

        $menuData = $decoded['data'] ?? null;
        if (!is_array($menuData)) {
            $menuData = $decoded;
        }
        $categories = $menuData['categories'] ?? null;
        if (!is_array($categories) || !array_is_list($categories)) {
            $categories = [];
        }

        $countKat = 0;
        $countPol = 0;
        $countCen = 0;
        $countAlerg = 0;
        $seenCats = [];
        $seenItemsByCat = [];

        $conn->begin_transaction();
        try {
            $catOrder = 0;
            foreach ($categories as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $catOrder++;
                $restiaCatId = trim((string)($cat['id'] ?? ($cat['value'] ?? '')));
                if ($restiaCatId === '') {
                    continue;
                }
                $catName = cb_restia_menu_first_text($cat, ['label', 'name', 'title']);
                if ($catName === '') {
                    $catName = 'Kategorie ' . $catOrder;
                }
                $catHidden = 0;
                if ((isset($cat['generic']) && is_array($cat['generic']) && !empty($cat['generic']['isHidden'])) || (isset($cat['wolt']) && is_array($cat['wolt']) && !empty($cat['wolt']['isHidden']))) {
                    $catHidden = 1;
                }

                $idResKat = cb_restia_menu_upsert_kategorie($conn, $idPob, $activePosId, $menuId, $restiaCatId, $catName, $catOrder, $catHidden);
                if ($idResKat <= 0) {
                    throw new RuntimeException('Nelze ziskat id_res_kategorie pro ' . $restiaCatId . '.');
                }
                $seenCats[$idResKat] = $idResKat;
                $seenItemsByCat[$idResKat] = [];
                $countKat++;

                $dishes = $cat['dishes'] ?? [];
                if (!is_array($dishes) || !array_is_list($dishes)) {
                    $dishes = [];
                }
                foreach ($dishes as $dish) {
                    if (!is_array($dish)) {
                        continue;
                    }
                    $restiaDishId = trim((string)($dish['id'] ?? ''));
                    if ($restiaDishId === '') {
                        continue;
                    }
                    $name = cb_restia_menu_first_text($dish, ['label', 'name', 'title']);
                    if ($name === '') {
                        $name = 'Polozka ' . $restiaDishId;
                    }
                    $nameEn = cb_restia_menu_first_text($dish, ['labelEn', 'nameEn', 'titleEn']);
                    $desc = '';
                    $descEn = '';
                    if (isset($dish['generic']) && is_array($dish['generic'])) {
                        $desc = cb_restia_menu_first_text($dish['generic'], ['description', 'desc']);
                        $descEn = cb_restia_menu_first_text($dish['generic'], ['descriptionEn', 'descEn']);
                    }
                    $posCode = '';
                    if (isset($dish['generic']) && is_array($dish['generic'])) {
                        $posCode = cb_restia_menu_first_text($dish['generic'], ['posCode']);
                    }
                    if ($posCode === '') {
                        $posCode = cb_restia_menu_first_text($dish, ['posCode']);
                    }
                    $img = cb_restia_menu_first_text($dish, ['imageUrl', 'image']);
                    $rawDish = cb_restia_menu_json($dish);
                    $hiddenDish = cb_restia_menu_channel_hidden($dish);

                    $idResPol = cb_restia_menu_upsert_polozka(
                        $conn,
                        $idResKat,
                        $restiaDishId,
                        $name,
                        ($nameEn === '' ? null : $nameEn),
                        ($desc === '' ? null : $desc),
                        ($descEn === '' ? null : $descEn),
                        ($posCode === '' ? null : $posCode),
                        ($img === '' ? null : $img),
                        $rawDish,
                        $hiddenDish
                    );
                    if ($idResPol <= 0) {
                        throw new RuntimeException('Nelze ziskat id_res_polozka pro ' . $restiaDishId . '.');
                    }
                    $seenItemsByCat[$idResKat][$idResPol] = $idResPol;
                    $countPol++;

                    $prices = cb_restia_menu_collect_price_rows($dish, $posCode);
                    $countCen += cb_restia_menu_sync_prices($conn, $idResPol, $prices);

                    $allergens = cb_restia_menu_collect_allergens($dish);
                    $countAlerg += cb_restia_menu_sync_allergens($conn, $idResPol, $allergens);
                }
            }

            cb_restia_menu_deactivate_missing($conn, $idPob, $menuId, $seenCats, $seenItemsByCat);
            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        db_api_restia_flush($conn, (int)$auth['id_user'], (int)$auth['id_login']);

        $ms = (int)round(microtime(true) * 1000) - $t0;
        return [
            'kategorie' => $countKat,
            'polozky' => $countPol,
            'ceny' => $countCen,
            'alergeny' => $countAlerg,
            'ms' => $ms,
        ];
    }
}

$conn = db();
$message = '';
$ok = 0;
$branchName = '';
$branchActivePosId = '';
$menuIdInput = '';
$branchOptions = [];
$statusMap = [];
$selectedBranchId = (int)($_POST['cb_id_pob'] ?? 0);

try {
    $branchOptions = cb_restia_menu_get_branches($conn);
    $statusMap = cb_restia_menu_status_map($conn);

    if ($selectedBranchId <= 0) {
        foreach ($branchOptions as $branchOpt) {
            if ((bool)($branchOpt['enabled'] ?? false) === true) {
                $selectedBranchId = (int)($branchOpt['id_pob'] ?? 0);
                break;
            }
        }
    }
    if ($selectedBranchId > 0) {
        $branch = cb_restia_menu_get_branch_by_id($conn, $selectedBranchId);
        $branchName = (string)$branch['nazev'];
        $branchActivePosId = (string)($branch['active_pos_id'] ?? '');
        $menuIdInput = cb_restia_menu_find_menu_id($conn, (int)$branch['id_pob']);
    }
} catch (Throwable $e) {
    $menuIdInput = CB_RESTIA_MENU_DEFAULT_ID;
}

$action = trim((string)($_POST['cb_action'] ?? 'start'));
$run = (
    isset($_POST['run_restia_menu'])
    && (string)$_POST['run_restia_menu'] === '1'
    && $action === 'start'
);

if ($run) {
    try {
        $auth = cb_restia_menu_get_auth();
        if ($selectedBranchId > 0) {
            $branch = cb_restia_menu_get_branch_by_id($conn, $selectedBranchId);
        } else {
            $branch = cb_restia_menu_pick_default_branch($conn);
        }
        $branchName = (string)$branch['nazev'];
        $branchActivePosId = (string)($branch['active_pos_id'] ?? '');
        $menuId = cb_restia_menu_find_menu_id($conn, (int)$branch['id_pob']);
        if ($menuId === '') {
            $menuId = CB_RESTIA_MENU_DEFAULT_ID;
        }

        cb_restia_menu_log('-----');
        cb_restia_menu_log('START MENU IMPORT: ' . cb_restia_menu_now_cs());
        cb_restia_menu_log('POBOCKA: ' . $branchName . ' | ID_POBOCKA: ' . (string)$branch['id_pob']);
        cb_restia_menu_log('ACTIVE_POS_ID: ' . (string)$branch['active_pos_id']);
        cb_restia_menu_log('MENU_ID: ' . $menuId);

        $res = cb_restia_menu_import_once($conn, $auth, $branch, $menuId);
        $ok = 1;
        $message = 'Menu import OK.';
        cb_restia_menu_log(
            'VYSLEDEK: kategorie=' . (string)$res['kategorie']
            . ' / polozky=' . (string)$res['polozky']
            . ' / ceny=' . (string)$res['ceny']
            . ' / alergeny=' . (string)$res['alergeny']
            . ' / cas=' . (string)$res['ms'] . ' ms'
        );
        cb_restia_menu_log('KONEC MENU IMPORTU: ' . cb_restia_menu_now_cs() . ' / OK');
        $menuIdInput = $menuId;
    } catch (Throwable $e) {
        $ok = 0;
        $message = 'Menu import selhal: ' . $e->getMessage();
        cb_restia_menu_log('KONEC MENU IMPORTU: ' . cb_restia_menu_now_cs() . ' / ERR / ' . $e->getMessage());
    }
}
?>
<?php if (!$cbRestiaMenuEmbedMode): ?>
<!doctype html>
<html lang="cs"><head><meta charset="utf-8"><title>Restia import menu</title><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>
<?php endif; ?>
<div class="table-wrap ram_normal bg_bila zaobleni_12 odstup_vnitrni_10">
  <h2 class="card_title txt_seda text_24 text_tucny odstup_vnejsi_0">Restia import menu</h2>
  <p class="card_text txt_seda">Pobočka: <?= cb_restia_menu_h($branchName) ?> | restia_activePosId: <?= cb_restia_menu_h($branchActivePosId) ?></p>
  <p class="card_text txt_seda">Výběr pobočky (stav menu podle DB):</p>
  <div class="card_actions gap_8 displ_flex">
    <form method="post" class="odstup_vnejsi_0 displ_inline_flex">
      <input type="hidden" name="run_restia_menu" value="1">
      <input type="hidden" name="cb_action" value="start" id="cb_restia_menu_action">
      <select name="cb_id_pob" class="card_select ram_sedy txt_seda bg_bila zaobleni_8 vyska_32" style="min-width:320px; margin-right:8px;" onchange="var a=document.getElementById('cb_restia_menu_action');if(a){a.value='select_branch';}this.form.submit();">
        <?php foreach ($branchOptions as $branchOpt): ?>
          <?php
          $idPobOpt = (int)($branchOpt['id_pob'] ?? 0);
          $nameOpt = (string)($branchOpt['nazev'] ?? '');
          $enabledOpt = ((bool)($branchOpt['enabled'] ?? false) === true);
          $menuOkOpt = (bool)($statusMap[$idPobOpt] ?? false);
          $labelOpt = $nameOpt !== '' ? $nameOpt : ('Pobočka #' . (string)$idPobOpt);
          $labelOpt .= $menuOkOpt ? ' | menu OK' : ' | bez menu';
          if (!$enabledOpt) {
              $labelOpt .= ' (chybí restia_activePosId)';
          }
          ?>
          <option value="<?= cb_restia_menu_h((string)$idPobOpt) ?>"<?= $idPobOpt === $selectedBranchId ? ' selected' : '' ?><?= $enabledOpt ? '' : ' disabled style="color:#9ca3af;"' ?>><?= cb_restia_menu_h($labelOpt) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="card_btn cursor_ruka ram_btn bg_bila zaobleni_6 vyska_28 card_btn_primary displ_inline_flex">Spustit import</button>
    </form>
  </div>
  <?php if ($message !== ''): ?>
    <p class="card_text <?= $ok === 1 ? 'txt_zelena' : 'txt_cervena' ?> text_tucny odstup_horni_10"><?= cb_restia_menu_h($message) ?></p>
  <?php endif; ?>
  <div style="margin-top:16px; text-align:right;">
    <form method="post" action="<?= cb_restia_menu_h((string)cb_url('/index.php')) ?>" class="odstup_vnejsi_0 displ_inline_flex">
      <input type="hidden" name="back_admin_init" value="1">
      <button type="submit" class="card_btn cursor_ruka ram_btn zaobleni_6 vyska_28 displ_inline_flex" style="background:var(--clr_ruzova_4); border-color:var(--clr_ruzova_1); color:var(--clr_cervena);">Zpět</button>
    </form>
  </div>
</div>
<?php if (!$cbRestiaMenuEmbedMode): ?></body></html><?php endif; ?>
