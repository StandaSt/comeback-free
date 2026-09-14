<?php
// lib/restia_katalog.php * Verzovaná synchronizace katalogu Restia
declare(strict_types=1);

/*
 * Sdílená logika pro ruční spuštění z Administrace a noční CRON.
 * Každá změna kategorie nebo položky uzavře původní verzi a založí novou.
 * Ceny a alergeny jsou neměnné děti konkrétní verze položky.
 */

require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/restia_access_exist.php';
require_once __DIR__ . '/restia_client.php';
require_once __DIR__ . '/../db/db_api_restia.php';

function cb_restia_katalog_text(array $source, array $keys): string
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && is_scalar($source[$key])) {
            $value = trim((string)$source[$key]);
            if ($value !== '') {
                return $value;
            }
        }
    }
    return '';
}

function cb_restia_katalog_nullable(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

function cb_restia_katalog_branches(mysqli $conn): array
{
    $result = $conn->query("
        SELECT id_pob, nazev, restia_activePosId
        FROM pobocka
        WHERE aktivni = 1
          AND id_pob > 0
          AND restia_activePosId IS NOT NULL
          AND restia_activePosId <> ''
        ORDER BY id_pob ASC
    ");
    $branches = [];
    while ($row = $result->fetch_assoc()) {
        $branches[] = [
            'id_pob' => (int)$row['id_pob'],
            'nazev' => trim((string)$row['nazev']),
            'active_pos_id' => trim((string)$row['restia_activePosId']),
        ];
    }
    $result->free();
    return $branches;
}

function cb_restia_katalog_menu_id(array $branch): string
{
    $activePosId = (string)$branch['active_pos_id'];
    $response = cb_restia_get(
        '/api/menu',
        ['activePosId' => $activePosId],
        $activePosId,
        'katalog menu id_pob=' . (int)$branch['id_pob']
    );
    if ((int)($response['ok'] ?? 0) !== 1) {
        throw new RuntimeException(trim((string)($response['chyba'] ?? 'Restia nevrátila seznam menu.')));
    }
    $decoded = json_decode((string)($response['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    $menus = array_is_list($decoded) ? $decoded : (array)($decoded['data'] ?? []);
    foreach ($menus as $menu) {
        if (is_array($menu)) {
            $menuId = trim((string)($menu['id'] ?? ''));
            if ($menuId !== '') {
                return $menuId;
            }
        }
    }
    throw new RuntimeException('Restia nevrátila žádné menu pro pobočku ' . (string)$branch['nazev'] . '.');
}

function cb_restia_katalog_menu_data(array $branch, string $menuId): array
{
    $activePosId = (string)$branch['active_pos_id'];
    $response = cb_restia_get(
        '/api/menu/' . rawurlencode($menuId),
        ['activePosId' => $activePosId],
        $activePosId,
        'katalog detail id_pob=' . (int)$branch['id_pob']
    );
    if ((int)($response['ok'] ?? 0) !== 1) {
        throw new RuntimeException(trim((string)($response['chyba'] ?? 'Restia nevrátila detail menu.')));
    }
    $decoded = json_decode((string)($response['body'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
    $data = is_array($decoded['data'] ?? null) ? $decoded['data'] : $decoded;
    if (!is_array($data['categories'] ?? null)) {
        throw new RuntimeException('Restia v detailu menu nevrátila kategorie.');
    }
    return $data;
}

function cb_restia_katalog_hidden(array $node): int
{
    foreach (['_general', 'generic', 'restia', 'damejidlo', 'wolt', 'bolt'] as $channel) {
        $value = $node[$channel] ?? null;
        if (is_array($value) && (!empty($value['isHidden']) || !empty($value['hidden']))) {
            return 1;
        }
    }
    return (!empty($node['isHidden']) || !empty($node['hidden'])) ? 1 : 0;
}

function cb_restia_katalog_allergens(array $dish): array
{
    $general = is_array($dish['_general'] ?? null) ? $dish['_general'] : [];
    $source = is_array($general['allergens'] ?? null)
        ? $general['allergens']
        : (is_array($dish['allergens'] ?? null) ? $dish['allergens'] : []);
    $values = [];
    foreach ($source as $item) {
        $value = is_scalar($item)
            ? trim((string)$item)
            : (is_array($item) ? cb_restia_katalog_text($item, ['id', 'code', 'value']) : '');
        if ($value !== '') {
            $values[$value] = $value;
        }
    }
    ksort($values, SORT_NATURAL);
    return array_values($values);
}

function cb_restia_katalog_price_rows(array $dish, string $defaultPosCode): array
{
    $general = is_array($dish['_general'] ?? null) ? $dish['_general'] : [];
    $baseSizes = is_array($general['sizes'] ?? null) ? array_values($general['sizes']) : [];
    if ($baseSizes === [] && isset($general['price'])) {
        $baseSizes[] = $general;
    }

    $rows = [];
    foreach (['generic', 'restia', 'damejidlo', 'wolt', 'bolt'] as $channel) {
        if (!is_array($dish[$channel] ?? null)) {
            continue;
        }
        $channelNode = $dish[$channel];
        $channelSizes = is_array($channelNode['sizes'] ?? null) ? array_values($channelNode['sizes']) : [];
        $sourceSizes = $channelSizes !== [] ? $channelSizes : $baseSizes;
        if ($sourceSizes === []) {
            continue;
        }

        foreach ($sourceSizes as $index => $channelSize) {
            if (!is_array($channelSize)) {
                continue;
            }
            $sizeName = cb_restia_katalog_text($channelSize, ['size', 'label', 'name']);
            $baseSize = is_array($baseSizes[$index] ?? null) ? $baseSizes[$index] : [];
            foreach ($baseSizes as $candidate) {
                if (
                    $sizeName !== ''
                    && is_array($candidate)
                    && cb_restia_katalog_text($candidate, ['size', 'label', 'name']) === $sizeName
                ) {
                    $baseSize = $candidate;
                    break;
                }
            }
            $size = array_replace($baseSize, $channelSize);
            $sizeId = cb_restia_katalog_text($channelSize, ['id']);
            if ($sizeId === '') {
                $sizeId = cb_restia_katalog_text($baseSize, ['id']);
            }
            if ($sizeId === '') {
                $sizeId = '__default__';
            }
            $price = isset($size['price']) ? (int)$size['price'] : (int)($channelNode['price'] ?? ($general['price'] ?? 0));
            $packing = isset($size['packing']) ? (int)$size['packing'] : (int)($channelNode['packing'] ?? ($general['packing'] ?? 0));
            $posCode = cb_restia_katalog_text($size, ['posCode']);
            if ($posCode === '') {
                $posCode = $defaultPosCode;
            }
            $vat = $channelNode['vat'] ?? ($general['vat'] ?? null);
            $vatRestaurant = $channelNode['vatInRestaurant'] ?? ($general['vatInRestaurant'] ?? null);
            $rows[$channel . '|' . $sizeId] = [
                'kanal' => $channel,
                'size_id' => $sizeId,
                'size_popis' => $sizeName,
                'pos_code' => $posCode,
                'cena_hl' => max(0, $price),
                'balne_hl' => max(0, $packing),
                'vat' => $vat === null || $vat === '' ? null : (string)$vat,
                'vat_v_restauraci' => $vatRestaurant === null || $vatRestaurant === '' ? null : (string)$vatRestaurant,
            ];
        }
    }
    ksort($rows, SORT_STRING);
    return array_values($rows);
}

function cb_restia_katalog_current_category(mysqli $conn, int $idPob, string $menuId, string $restiaId): ?array
{
    $stmt = $conn->prepare("
        SELECT id_res_kategorie, nazev_kategorie, poradi_kategorie, skryta
        FROM res_kategorie
        WHERE id_pob = ? AND id_restia_menu = ? AND id_restia_kategorie = ?
          AND aktivni = 1 AND platnost_do IS NULL
        ORDER BY id_res_kategorie DESC LIMIT 1
    ");
    $stmt->bind_param('iss', $idPob, $menuId, $restiaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $result->free();
    $stmt->close();
    return $row;
}

function cb_restia_katalog_category_version(
    mysqli $conn,
    int $idPob,
    string $menuId,
    string $restiaId,
    string $name,
    int $order,
    int $hidden,
    array &$stats
): int {
    $current = cb_restia_katalog_current_category($conn, $idPob, $menuId, $restiaId);
    if (
        is_array($current)
        && (string)$current['nazev_kategorie'] === $name
        && (int)$current['poradi_kategorie'] === $order
        && (int)$current['skryta'] === $hidden
    ) {
        return (int)$current['id_res_kategorie'];
    }
    if (is_array($current)) {
        $id = (int)$current['id_res_kategorie'];
        $stmt = $conn->prepare('UPDATE res_kategorie SET aktivni = 0, platnost_do = NOW(3) WHERE id_res_kategorie = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        $stats['kategorie_zmenene']++;
    } else {
        $stats['kategorie_nove']++;
    }
    $stmt = $conn->prepare("
        INSERT INTO res_kategorie
            (id_pob, id_restia_menu, id_restia_kategorie, nazev_kategorie, poradi_kategorie,
             skryta, aktivni, platnost_od, platnost_do, vytvoreno, zmeneno)
        VALUES (?, ?, ?, ?, ?, ?, 1, NOW(3), NULL, NOW(3), NOW(3))
    ");
    $stmt->bind_param('isssii', $idPob, $menuId, $restiaId, $name, $order, $hidden);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function cb_restia_katalog_current_item(mysqli $conn, int $idPob, string $restiaId): ?array
{
    $stmt = $conn->prepare("
        SELECT id_res_polozka, id_res_kategorie, nazev, nazev_en, popis, popis_en,
               pos_code, image_url, skryta
        FROM res_polozky
        WHERE id_pob = ? AND restia_polozka_id = ?
          AND aktivni = 1 AND platnost_do IS NULL
        ORDER BY id_res_polozka DESC LIMIT 1
    ");
    $stmt->bind_param('is', $idPob, $restiaId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $result->free();
    $stmt->close();
    return $row;
}

function cb_restia_katalog_item_children(mysqli $conn, int $idItem): array
{
    $prices = [];
    $stmt = $conn->prepare("
        SELECT kanal, size_id, COALESCE(size_popis, '') AS size_popis,
               COALESCE(pos_code, '') AS pos_code, cena_hl, balne_hl,
               CAST(vat AS CHAR) AS vat, CAST(vat_v_restauraci AS CHAR) AS vat_v_restauraci
        FROM res_cena WHERE id_res_polozka = ? AND aktivni = 1
        ORDER BY kanal, size_id
    ");
    $stmt->bind_param('i', $idItem);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $prices[] = [
            'kanal' => (string)$row['kanal'],
            'size_id' => (string)$row['size_id'],
            'size_popis' => (string)$row['size_popis'],
            'pos_code' => (string)$row['pos_code'],
            'cena_hl' => (int)$row['cena_hl'],
            'balne_hl' => (int)$row['balne_hl'],
            'vat' => $row['vat'] === null ? null : rtrim(rtrim((string)$row['vat'], '0'), '.'),
            'vat_v_restauraci' => $row['vat_v_restauraci'] === null ? null : rtrim(rtrim((string)$row['vat_v_restauraci'], '0'), '.'),
        ];
    }
    $result->free();
    $stmt->close();

    $allergens = [];
    $stmt = $conn->prepare('SELECT alergen FROM res_alergen WHERE id_res_polozka = ? ORDER BY alergen');
    $stmt->bind_param('i', $idItem);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allergens[] = (string)$row['alergen'];
    }
    $result->free();
    $stmt->close();
    return ['prices' => $prices, 'allergens' => $allergens];
}

function cb_restia_katalog_item_version(
    mysqli $conn,
    int $idPob,
    int $idCategory,
    array $item,
    array &$stats
): int {
    $current = cb_restia_katalog_current_item($conn, $idPob, (string)$item['restia_id']);
    $same = false;
    if (is_array($current)) {
        $children = cb_restia_katalog_item_children($conn, (int)$current['id_res_polozka']);
        $same = (int)$current['id_res_kategorie'] === $idCategory
            && (string)$current['nazev'] === (string)$item['nazev']
            && ($current['nazev_en'] === null ? null : (string)$current['nazev_en']) === $item['nazev_en']
            && ($current['popis'] === null ? null : (string)$current['popis']) === $item['popis']
            && ($current['popis_en'] === null ? null : (string)$current['popis_en']) === $item['popis_en']
            && ($current['pos_code'] === null ? null : (string)$current['pos_code']) === $item['pos_code']
            && ($current['image_url'] === null ? null : (string)$current['image_url']) === $item['image_url']
            && (int)$current['skryta'] === (int)$item['skryta']
            && $children['prices'] === $item['prices']
            && $children['allergens'] === $item['allergens'];
    }
    if ($same) {
        $stats['polozky_beze_zmeny']++;
        return (int)$current['id_res_polozka'];
    }
    if (is_array($current)) {
        $oldId = (int)$current['id_res_polozka'];
        $stmt = $conn->prepare('UPDATE res_polozky SET aktivni = 0, platnost_do = NOW(3) WHERE id_res_polozka = ? LIMIT 1');
        $stmt->bind_param('i', $oldId);
        $stmt->execute();
        $stmt->close();
        $stats['polozky_zmenene']++;
    } else {
        $stats['polozky_nove']++;
    }

    $stmt = $conn->prepare("
        INSERT INTO res_polozky
            (id_res_kategorie, id_pob, restia_polozka_id, nazev, nazev_en, popis, popis_en,
             pos_code, image_url, skryta, aktivni, platnost_od, platnost_do, vytvoreno, zmeneno)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(3), NULL, NOW(3), NOW(3))
    ");
    $stmt->bind_param(
        'iisssssssi',
        $idCategory,
        $idPob,
        $item['restia_id'],
        $item['nazev'],
        $item['nazev_en'],
        $item['popis'],
        $item['popis_en'],
        $item['pos_code'],
        $item['image_url'],
        $item['skryta']
    );
    $stmt->execute();
    $idItem = (int)$conn->insert_id;
    $stmt->close();

    $stmt = $conn->prepare("
        INSERT INTO res_cena
            (id_res_polozka, kanal, size_id, size_popis, pos_code, cena_hl, balne_hl,
             vat, vat_v_restauraci, mena, aktivni, vytvoreno, zmeneno)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'CZK', 1, NOW(3), NOW(3))
    ");
    foreach ($item['prices'] as $price) {
        $stmt->bind_param(
            'issssiiss',
            $idItem,
            $price['kanal'],
            $price['size_id'],
            $price['size_popis'],
            $price['pos_code'],
            $price['cena_hl'],
            $price['balne_hl'],
            $price['vat'],
            $price['vat_v_restauraci']
        );
        $stmt->execute();
        $stats['ceny_nove']++;
    }
    $stmt->close();

    $stmt = $conn->prepare('INSERT INTO res_alergen (id_res_polozka, alergen, vytvoreno) VALUES (?, ?, NOW(3))');
    foreach ($item['allergens'] as $allergen) {
        $stmt->bind_param('is', $idItem, $allergen);
        $stmt->execute();
    }
    $stmt->close();
    return $idItem;
}

function cb_restia_katalog_parse_item(array $dish): ?array
{
    $restiaId = trim((string)($dish['id'] ?? ''));
    if ($restiaId === '') {
        return null;
    }
    $general = is_array($dish['_general'] ?? null) ? $dish['_general'] : [];
    $name = cb_restia_katalog_text($general, ['name', 'label', 'title']);
    if ($name === '') {
        $name = cb_restia_katalog_text($dish, ['name', 'label', 'title']);
    }
    if ($name === '') {
        $name = 'Položka ' . $restiaId;
    }
    $posCode = cb_restia_katalog_text($general, ['posCode']);
    if ($posCode === '') {
        $posCode = cb_restia_katalog_text($dish, ['posCode']);
    }
    return [
        'restia_id' => $restiaId,
        'nazev' => $name,
        'nazev_en' => cb_restia_katalog_nullable(cb_restia_katalog_text($general, ['nameEN', 'nameEn', 'labelEn', 'titleEn'])),
        'popis' => cb_restia_katalog_nullable(cb_restia_katalog_text($general, ['description', 'desc'])),
        'popis_en' => cb_restia_katalog_nullable(cb_restia_katalog_text($general, ['descriptionEN', 'descriptionEn', 'descEn'])),
        'pos_code' => cb_restia_katalog_nullable($posCode),
        'image_url' => cb_restia_katalog_nullable(cb_restia_katalog_text($general, ['imageUrl', 'image'])),
        'skryta' => cb_restia_katalog_hidden($dish),
        'prices' => cb_restia_katalog_price_rows($dish, $posCode),
        'allergens' => cb_restia_katalog_allergens($dish),
    ];
}

function cb_restia_katalog_close_missing(mysqli $conn, int $idPob, array $seenCategories, array $seenItems, array &$stats): void
{
    $categories = $conn->query("SELECT id_res_kategorie, id_restia_menu, id_restia_kategorie FROM res_kategorie WHERE id_pob = {$idPob} AND aktivni = 1 AND platnost_do IS NULL");
    while ($row = $categories->fetch_assoc()) {
        $categoryKey = (string)$row['id_restia_menu'] . '|' . (string)$row['id_restia_kategorie'];
        if (!isset($seenCategories[$categoryKey])) {
            $id = (int)$row['id_res_kategorie'];
            $stmt = $conn->prepare('UPDATE res_kategorie SET aktivni = 0, platnost_do = NOW(3) WHERE id_res_kategorie = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $stats['kategorie_ukoncene']++;
        }
    }
    $categories->free();

    $items = $conn->query("SELECT id_res_polozka, restia_polozka_id FROM res_polozky WHERE id_pob = {$idPob} AND aktivni = 1 AND platnost_do IS NULL");
    while ($row = $items->fetch_assoc()) {
        if (!isset($seenItems[(string)$row['restia_polozka_id']])) {
            $id = (int)$row['id_res_polozka'];
            $stmt = $conn->prepare('UPDATE res_polozky SET aktivni = 0, platnost_do = NOW(3) WHERE id_res_polozka = ? LIMIT 1');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $stats['polozky_ukoncene']++;
        }
    }
    $items->free();
}

function cb_restia_katalog_sync_branch(mysqli $conn, array $branch): array
{
    $menuId = cb_restia_katalog_menu_id($branch);
    $menu = cb_restia_katalog_menu_data($branch, $menuId);
    $stats = [
        'pobocka' => (string)$branch['nazev'],
        'kategorie_nove' => 0,
        'kategorie_zmenene' => 0,
        'kategorie_ukoncene' => 0,
        'polozky_nove' => 0,
        'polozky_zmenene' => 0,
        'polozky_beze_zmeny' => 0,
        'polozky_ukoncene' => 0,
        'polozky_stazene' => 0,
        'ceny_nove' => 0,
    ];
    $seenCategories = [];
    $seenItems = [];

    $conn->begin_transaction();
    try {
        $categoryOrder = 0;
        foreach ((array)$menu['categories'] as $category) {
            if (!is_array($category)) {
                continue;
            }
            $categoryOrder++;
            $restiaCategoryId = trim((string)($category['id'] ?? ($category['value'] ?? '')));
            if ($restiaCategoryId === '') {
                continue;
            }
            $general = is_array($category['_general'] ?? null) ? $category['_general'] : [];
            $categoryName = cb_restia_katalog_text($general, ['name', 'label', 'title']);
            if ($categoryName === '') {
                $categoryName = cb_restia_katalog_text($category, ['label', 'name', 'title']);
            }
            if ($categoryName === '') {
                $categoryName = 'Kategorie ' . $categoryOrder;
            }
            $idCategory = cb_restia_katalog_category_version(
                $conn,
                (int)$branch['id_pob'],
                $menuId,
                $restiaCategoryId,
                $categoryName,
                $categoryOrder,
                cb_restia_katalog_hidden($category),
                $stats
            );
            $seenCategories[$menuId . '|' . $restiaCategoryId] = true;

            foreach ((array)($category['dishes'] ?? []) as $dish) {
                if (!is_array($dish)) {
                    continue;
                }
                $item = cb_restia_katalog_parse_item($dish);
                if ($item === null) {
                    continue;
                }
                $stats['polozky_stazene']++;
                cb_restia_katalog_item_version($conn, (int)$branch['id_pob'], $idCategory, $item, $stats);
                $seenItems[(string)$item['restia_id']] = true;
            }
        }
        if ($seenCategories === [] || $seenItems === []) {
            throw new RuntimeException('Restia vrátila prázdný katalog; stávající položky nebyly změněny.');
        }
        cb_restia_katalog_close_missing($conn, (int)$branch['id_pob'], $seenCategories, $seenItems, $stats);
        $conn->commit();
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
    return $stats;
}

function cb_restia_katalog_sync_one(int $idPob, ?int $idUser = null, ?int $idLogin = null): array
{
    set_time_limit(0);
    $conn = db();
    $branch = null;
    foreach (cb_restia_katalog_branches($conn) as $candidate) {
        if ((int)$candidate['id_pob'] === $idPob) {
            $branch = $candidate;
            break;
        }
    }
    if (!is_array($branch)) {
        throw new RuntimeException('Pobočka není určena pro načítání katalogu Restia.');
    }

    $lockResult = $conn->query("SELECT GET_LOCK('cb_restia_katalog_sync', 0) AS acquired");
    $lock = $lockResult->fetch_assoc();
    $lockResult->free();
    if ((int)($lock['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Synchronizace katalogu Restia již běží.');
    }

    try {
        return cb_restia_katalog_sync_branch($conn, $branch);
    } finally {
        db_api_restia_flush($conn, $idUser, $idLogin);
        $conn->query("SELECT RELEASE_LOCK('cb_restia_katalog_sync')");
    }
}

function cb_restia_katalog_sync_all(?int $idUser = null, ?int $idLogin = null): array
{
    set_time_limit(0);
    $conn = db();
    $lockResult = $conn->query("SELECT GET_LOCK('cb_restia_katalog_sync', 0) AS acquired");
    $lock = $lockResult->fetch_assoc();
    $lockResult->free();
    if ((int)($lock['acquired'] ?? 0) !== 1) {
        throw new RuntimeException('Synchronizace katalogu Restia již běží.');
    }

    $results = [];
    try {
        foreach (cb_restia_katalog_branches($conn) as $branch) {
            try {
                $results[] = cb_restia_katalog_sync_branch($conn, $branch);
            } catch (Throwable $error) {
                $results[] = [
                    'pobocka' => (string)$branch['nazev'],
                    'chyba' => $error->getMessage(),
                ];
            }
        }
        db_api_restia_flush($conn, $idUser, $idLogin);
    } finally {
        $conn->query("SELECT RELEASE_LOCK('cb_restia_katalog_sync')");
    }
    return $results;
}

function cb_restia_katalog_summary(array $results): string
{
    $lines = [];
    foreach ($results as $result) {
        if (isset($result['chyba'])) {
            $lines[] = (string)$result['pobocka'] . ': CHYBA – ' . (string)$result['chyba'];
            continue;
        }
        $lines[] = (string)$result['pobocka']
            . ': nové ' . (int)$result['polozky_nove']
            . ', změněné ' . (int)$result['polozky_zmenene']
            . ', ukončené ' . (int)$result['polozky_ukoncene']
            . ', beze změny ' . (int)$result['polozky_beze_zmeny'];
    }
    return implode(' | ', $lines);
}

function cb_restia_katalog_has_errors(array $results): bool
{
    foreach ($results as $result) {
        if (isset($result['chyba'])) {
            return true;
        }
    }
    return false;
}

function cb_restia_katalog_counts(array $results): array
{
    $downloaded = 0;
    $updated = 0;
    foreach ($results as $result) {
        if (isset($result['chyba'])) {
            continue;
        }
        $downloaded += (int)($result['polozky_stazene'] ?? 0);
        $updated += (int)($result['polozky_nove'] ?? 0)
            + (int)($result['polozky_zmenene'] ?? 0)
            + (int)($result['polozky_ukoncene'] ?? 0);
    }
    return ['stazeno' => $downloaded, 'aktualizovano' => $updated];
}
