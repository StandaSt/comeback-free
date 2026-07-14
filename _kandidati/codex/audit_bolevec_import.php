<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    throw new RuntimeException('Chybi local DB konfigurace.');
}

$conn = new mysqli(
    (string)($cfg['host'] ?? ''),
    (string)($cfg['user'] ?? ''),
    (string)($cfg['pass'] ?? ''),
    (string)($cfg['name'] ?? '')
);
$conn->set_charset('utf8mb4');
$idPob = 6;

function q(mysqli $conn, string $sql): array
{
    $res = $conn->query($sql);
    if (!($res instanceof mysqli_result)) {
        throw new RuntimeException($conn->error . "\nSQL: " . $sql);
    }
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    $res->free();
    return $rows;
}

function one(mysqli $conn, string $sql): array
{
    $rows = q($conn, $sql);
    return $rows[0] ?? [];
}

function out(string $title, array $rows): void
{
    echo "\n## " . $title . "\n";
    if ($rows === []) {
        echo "(bez zaznamu)\n";
        return;
    }
    foreach ($rows as $row) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
    }
}

function has_table(mysqli $conn, string $table): bool
{
    $tableEsc = $conn->real_escape_string($table);
    $row = one($conn, "SHOW TABLES LIKE '{$tableEsc}'");
    return $row !== [];
}

function has_col(mysqli $conn, string $table, string $col): bool
{
    $tableEsc = $conn->real_escape_string($table);
    $colEsc = $conn->real_escape_string($col);
    $row = one($conn, "SHOW COLUMNS FROM `{$tableEsc}` LIKE '{$colEsc}'");
    return $row !== [];
}

$id = (int)$idPob;

out('Pobočka', q($conn, "SELECT id_pob, nazev, restia_activePosId, prvni_obj FROM pobocka WHERE id_pob = {$id}"));

out('Objednávky Bolevec - rozsah a počty', q($conn, "
    SELECT
        COUNT(*) AS obj_count,
        COUNT(DISTINCT restia_id_obj) AS distinct_restia_id,
        COUNT(DISTINCT restia_order_number) AS distinct_order_number,
        MIN(restia_created_at) AS min_restia_created_at,
        MAX(restia_created_at) AS max_restia_created_at,
        MIN(id_obj) AS min_id_obj,
        MAX(id_obj) AS max_id_obj,
        SUM(CASE WHEN id_zak = 0 THEN 1 ELSE 0 END) AS anonym_obj,
        SUM(CASE WHEN id_zak > 0 THEN 1 ELSE 0 END) AS parovane_obj
    FROM objednavky_restia
    WHERE id_pob = {$id}
"));

out('Obj_import Bolevec - souhrn historie', q($conn, "
    SELECT
        COUNT(*) AS import_rows,
        SUM(CASE WHEN stav = 'ok' THEN 1 ELSE 0 END) AS ok_rows,
        SUM(CASE WHEN stav <> 'ok' THEN 1 ELSE 0 END) AS non_ok_rows,
        MIN(datum_od) AS min_datum_od,
        MAX(datum_do) AS max_datum_do,
        SUM(COALESCE(pocet_restia, 0)) AS restia_sum,
        SUM(COALESCE(pocet_is, 0)) AS is_sum,
        SUM(COALESCE(rozdil, 0)) AS rozdil_sum,
        SUM(COALESCE(pocet_chyb, 0)) AS chyby_sum
    FROM obj_import
    WHERE id_pob = {$id} AND typ_importu = 'historie'
"));

out('Obj_import Bolevec - nedokončené/chybné dny', q($conn, "
    SELECT id_import, datum_od, datum_do, stav, pocet_restia, pocet_is, rozdil, pocet_chyb, poznamka
    FROM obj_import
    WHERE id_pob = {$id}
      AND typ_importu = 'historie'
      AND (stav <> 'ok' OR COALESCE(rozdil, 0) <> 0 OR COALESCE(pocet_chyb, 0) <> 0)
    ORDER BY datum_od ASC
    LIMIT 50
"));

out('Obj_import Bolevec - duplicitní denní intervaly', q($conn, "
    SELECT datum_od, datum_do, COUNT(*) AS cnt
    FROM obj_import
    WHERE id_pob = {$id} AND typ_importu = 'historie'
    GROUP BY datum_od, datum_do
    HAVING cnt > 1
    ORDER BY datum_od ASC
    LIMIT 50
"));

out('Kontrolní přehledy Bolevec', q($conn, "
    SELECT rok, mesic, obj_restia, obj_is, rozdil, stav, posledni_kontrola, chyba_text
    FROM kontrolni_prehledy
    WHERE id_pob = {$id}
    ORDER BY rok ASC, mesic ASC
"));

out('Kontrolní přehledy Bolevec - rozdíly', q($conn, "
    SELECT rok, mesic, obj_restia, obj_is, rozdil, stav, chyba_text
    FROM kontrolni_prehledy
    WHERE id_pob = {$id} AND COALESCE(rozdil, 0) <> 0
    ORDER BY rok ASC, mesic ASC
"));

out('Duplicity objednávek', q($conn, "
    SELECT 'restia_id_obj' AS typ, COUNT(*) AS dup_groups, COALESCE(SUM(cnt - 1), 0) AS dup_extra
    FROM (
        SELECT restia_id_obj, COUNT(*) AS cnt
        FROM objednavky_restia
        WHERE id_pob = {$id}
        GROUP BY restia_id_obj
        HAVING cnt > 1
    ) x
    UNION ALL
    SELECT 'restia_order_number' AS typ, COUNT(*) AS dup_groups, COALESCE(SUM(cnt - 1), 0) AS dup_extra
    FROM (
        SELECT restia_order_number, COUNT(*) AS cnt
        FROM objednavky_restia
        WHERE id_pob = {$id} AND COALESCE(restia_order_number, '') <> ''
        GROUP BY restia_order_number
        HAVING cnt > 1
    ) y
"));

$oneToOne = ['obj_adresa', 'obj_casy', 'obj_ceny', 'obj_restia_detail'];
foreach ($oneToOne as $table) {
    if (!has_table($conn, $table)) {
        out($table . ' - tabulka chybí', [['table' => $table, 'status' => 'missing']]);
        continue;
    }
    out($table . ' - návaznost 1:1', q($conn, "
        SELECT
            '{$table}' AS tabulka,
            COUNT(o.id_obj) AS obj_count,
            SUM(CASE WHEN t.id_obj IS NULL THEN 1 ELSE 0 END) AS missing_rows,
            SUM(CASE WHEN t.cnt > 1 THEN 1 ELSE 0 END) AS duplicate_orders
        FROM objednavky_restia o
        LEFT JOIN (
            SELECT id_obj, COUNT(*) AS cnt
            FROM `{$table}`
            GROUP BY id_obj
        ) t ON t.id_obj = o.id_obj
        WHERE o.id_pob = {$id}
    "));
}

out('Položky a vazby', q($conn, "
    SELECT
        COUNT(o.id_obj) AS obj_count,
        SUM(CASE WHEN p.item_cnt IS NULL THEN 1 ELSE 0 END) AS objednavky_bez_polozek,
        COALESCE(SUM(p.item_cnt), 0) AS polozky_total
    FROM objednavky_restia o
    LEFT JOIN (
        SELECT id_obj, COUNT(*) AS item_cnt
        FROM obj_polozky
        GROUP BY id_obj
    ) p ON p.id_obj = o.id_obj
    WHERE o.id_pob = {$id}
"));

out('Položky - orphan kontroly', q($conn, "
    SELECT 'obj_polozky bez objednavky' AS kontrola, COUNT(*) AS cnt
    FROM obj_polozky p
    LEFT JOIN objednavky_restia o ON o.id_obj = p.id_obj
    WHERE o.id_obj IS NULL
    UNION ALL
    SELECT 'obj_polozka_mod bez polozky' AS kontrola, COUNT(*) AS cnt
    FROM obj_polozka_mod m
    LEFT JOIN obj_polozky p ON p.id_obj_polozka = m.id_obj_polozka
    WHERE p.id_obj_polozka IS NULL
    UNION ALL
    SELECT 'obj_polozka_kds_tag bez polozky' AS kontrola, COUNT(*) AS cnt
    FROM obj_polozka_kds_tag t
    LEFT JOIN obj_polozky p ON p.id_obj_polozka = t.id_obj_polozka
    WHERE p.id_obj_polozka IS NULL
"));

out('Kurýr/služba', q($conn, "
    SELECT
        COUNT(o.id_obj) AS obj_count,
        SUM(CASE WHEN k.id_obj IS NOT NULL THEN 1 ELSE 0 END) AS objednavky_s_kuryrem,
        SUM(CASE WHEN s.id_obj IS NOT NULL THEN 1 ELSE 0 END) AS objednavky_se_sluzbou
    FROM objednavky_restia o
    LEFT JOIN (SELECT DISTINCT id_obj FROM obj_kuryr) k ON k.id_obj = o.id_obj
    LEFT JOIN (SELECT DISTINCT id_obj FROM obj_sluzba) s ON s.id_obj = o.id_obj
    WHERE o.id_pob = {$id}
"));

out('Zákazníci - vazby a pocet_obj', q($conn, "
    SELECT
        COUNT(o.id_obj) AS obj_count,
        SUM(CASE WHEN o.id_zak > 0 AND z.id_zak IS NULL THEN 1 ELSE 0 END) AS chybi_zakaznik,
        SUM(CASE WHEN o.id_zak = 0 THEN 1 ELSE 0 END) AS anonym_obj,
        SUM(CASE WHEN o.id_zak > 0 THEN 1 ELSE 0 END) AS parovane_obj
    FROM objednavky_restia o
    LEFT JOIN zakaznik z ON z.id_zak = o.id_zak
    WHERE o.id_pob = {$id}
"));

out('Zákazníci - nesoulad pocet_obj pro zákazníky Bolevce', q($conn, "
    SELECT x.id_zak, x.real_count, z.pocet_obj, z.telefon, z.email
    FROM (
        SELECT id_zak, COUNT(*) AS real_count
        FROM objednavky_restia
        WHERE id_pob = {$id}
        GROUP BY id_zak
    ) x
    LEFT JOIN zakaznik z ON z.id_zak = x.id_zak
    WHERE z.id_zak IS NULL OR COALESCE(z.pocet_obj, -1) <> x.real_count
    ORDER BY ABS(COALESCE(z.pocet_obj, 0) - x.real_count) DESC
    LIMIT 30
"));

out('Obj_ceny - sloupce', q($conn, 'SHOW COLUMNS FROM obj_ceny'));

$priceSelect = [
    'COUNT(o.id_obj) AS obj_count',
    'SUM(CASE WHEN c.id_obj IS NULL THEN 1 ELSE 0 END) AS chybi_ceny',
];
foreach (['cena_celk', 'cena_obj', 'cena_menu', 'cena_doprava', 'cena_sleva', 'cena_produkty', 'cena_platba'] as $col) {
    if (has_col($conn, 'obj_ceny', $col)) {
        $colEsc = $conn->real_escape_string($col);
        $priceSelect[] = "ROUND(SUM(COALESCE(c.`{$colEsc}`, 0)), 2) AS suma_{$colEsc}";
    }
}
out('Ceny - základní sanity check', q($conn, "
    SELECT
        " . implode(",\n        ", $priceSelect) . "
    FROM objednavky_restia o
    LEFT JOIN obj_ceny c ON c.id_obj = o.id_obj
    WHERE o.id_pob = {$id}
"));

out('Obj_import posledních 10 dní', q($conn, "
    SELECT datum_od, datum_do, stav, pocet_restia, pocet_is, rozdil, pocet_chyb
    FROM obj_import
    WHERE id_pob = {$id} AND typ_importu = 'historie'
    ORDER BY datum_od DESC
    LIMIT 10
"));

echo "\nDONE\n";
