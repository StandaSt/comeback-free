<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    fwrite(STDERR, "Missing local DB config.\n");
    exit(1);
}

$conn = new mysqli((string)$cfg['host'], (string)$cfg['user'], (string)$cfg['pass'], (string)$cfg['name']);
if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . "\n");
    exit(1);
}
$conn->set_charset('utf8mb4');

function q(mysqli $conn, string $title, string $sql): void
{
    echo "\n--- " . $title . "\n";
    $res = $conn->query($sql);
    if (!($res instanceof mysqli_result)) {
        echo "ERR: " . $conn->error . "\n";
        return;
    }
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
    $res->free();
}

q($conn, 'counts', "
    SELECT 'objednavky_restia' AS tabulka, COUNT(*) AS cnt FROM objednavky_restia
    UNION ALL SELECT 'obj_restia_detail', COUNT(*) FROM obj_restia_detail
    UNION ALL SELECT 'obj_casy', COUNT(*) FROM obj_casy
    UNION ALL SELECT 'obj_ceny', COUNT(*) FROM obj_ceny
    UNION ALL SELECT 'obj_adresa', COUNT(*) FROM obj_adresa
    UNION ALL SELECT 'obj_kuryr', COUNT(*) FROM obj_kuryr
    UNION ALL SELECT 'obj_sluzba', COUNT(*) FROM obj_sluzba
    UNION ALL SELECT 'obj_polozky', COUNT(*) FROM obj_polozky
    UNION ALL SELECT 'obj_polozka_mod', COUNT(*) FROM obj_polozka_mod
    UNION ALL SELECT 'obj_polozka_kds_tag', COUNT(*) FROM obj_polozka_kds_tag
    UNION ALL SELECT 'zakaznik', COUNT(*) FROM zakaznik
    UNION ALL SELECT 'obj_import', COUNT(*) FROM obj_import
    UNION ALL SELECT 'kontrolni_prehledy', COUNT(*) FROM kontrolni_prehledy
");

q($conn, 'obj_import_summary', "
    SELECT id_pob, stav, COUNT(*) AS dnu, MIN(datum_od) AS od, MAX(datum_od) AS do_dne,
           SUM(pocet_restia) AS restia, SUM(pocet_is) AS is_pocet, SUM(rozdil) AS rozdil,
           SUM(pocet_obj) AS pocet_obj, SUM(pocet_novych) AS novych, SUM(pocet_zmenenych) AS zmenenych, SUM(pocet_chyb) AS chyb
    FROM obj_import
    GROUP BY id_pob, stav
    ORDER BY id_pob, stav
");

q($conn, 'daily_diffs', "
    SELECT id_import, id_pob, datum_od, datum_do, stav, pocet_restia, pocet_is, rozdil, pocet_obj, pocet_novych, pocet_zmenenych, pocet_chyb
    FROM obj_import
    WHERE COALESCE(rozdil, 0) <> 0 OR COALESCE(pocet_chyb, 0) <> 0 OR stav <> 'ok'
    ORDER BY id_import
    LIMIT 100
");

q($conn, 'orders_by_branch', "
    SELECT o.id_pob, p.nazev, COUNT(*) AS objednavky, MIN(o.restia_created_at) AS prvni, MAX(o.restia_created_at) AS posledni
    FROM objednavky_restia o
    LEFT JOIN pobocka p ON p.id_pob = o.id_pob
    GROUP BY o.id_pob, p.nazev
    ORDER BY o.id_pob
");

q($conn, 'required_1to1_missing', "
    SELECT 'obj_restia_detail' AS tabulka, COUNT(*) AS chybi FROM objednavky_restia o LEFT JOIN obj_restia_detail d ON d.id_obj = o.id_obj WHERE d.id_obj IS NULL
    UNION ALL SELECT 'obj_casy', COUNT(*) FROM objednavky_restia o LEFT JOIN obj_casy x ON x.id_obj = o.id_obj WHERE x.id_obj IS NULL
    UNION ALL SELECT 'obj_ceny', COUNT(*) FROM objednavky_restia o LEFT JOIN obj_ceny x ON x.id_obj = o.id_obj WHERE x.id_obj IS NULL
");

q($conn, 'orphan_children', "
    SELECT 'obj_restia_detail' AS tabulka, COUNT(*) AS sirotci FROM obj_restia_detail x LEFT JOIN objednavky_restia o ON o.id_obj = x.id_obj WHERE o.id_obj IS NULL
    UNION ALL SELECT 'obj_casy', COUNT(*) FROM obj_casy x LEFT JOIN objednavky_restia o ON o.id_obj = x.id_obj WHERE o.id_obj IS NULL
    UNION ALL SELECT 'obj_ceny', COUNT(*) FROM obj_ceny x LEFT JOIN objednavky_restia o ON o.id_obj = x.id_obj WHERE o.id_obj IS NULL
    UNION ALL SELECT 'obj_adresa', COUNT(*) FROM obj_adresa x LEFT JOIN objednavky_restia o ON o.id_obj = x.id_obj WHERE o.id_obj IS NULL
    UNION ALL SELECT 'obj_kuryr', COUNT(*) FROM obj_kuryr x LEFT JOIN objednavky_restia o ON o.id_obj = x.id_obj WHERE o.id_obj IS NULL
    UNION ALL SELECT 'obj_sluzba', COUNT(*) FROM obj_sluzba x LEFT JOIN objednavky_restia o ON o.id_obj = x.id_obj WHERE o.id_obj IS NULL
    UNION ALL SELECT 'obj_polozky', COUNT(*) FROM obj_polozky x LEFT JOIN objednavky_restia o ON o.id_obj = x.id_obj WHERE o.id_obj IS NULL
");

q($conn, 'detail_quality', "
    SELECT
      COUNT(*) AS detail_rows,
      SUM(restia_raw_json IS NULL OR restia_raw_json = '') AS empty_json,
      SUM(restia_payload_hash IS NULL OR restia_payload_hash = '') AS empty_hash,
      SUM(CHAR_LENGTH(restia_payload_hash) <> 64) AS bad_hash_len
    FROM obj_restia_detail
");

q($conn, 'order_key_quality', "
    SELECT
      COUNT(*) AS objednavky,
      SUM(restia_id_obj IS NULL OR restia_id_obj = '') AS empty_restia_id,
      COUNT(DISTINCT restia_id_obj) AS distinct_restia_id,
      SUM(restia_created_at IS NULL) AS empty_created_at,
      SUM(id_zak IS NULL) AS null_id_zak,
      SUM(id_zak = 0) AS anonymni
    FROM objednavky_restia
");

q($conn, 'customer_quality', "
    SELECT
      COUNT(*) AS zakaznici,
      SUM(id_zak = 0) AS anonymni_radky,
      SUM(telefon IS NULL OR telefon = '') AS bez_telefonu,
      SUM(telefon_norm IS NULL OR telefon_norm = '') AS bez_telefon_norm,
      SUM(pocet_obj) AS soucet_pocet_obj
    FROM zakaznik
");

q($conn, 'customer_count_compare', "
    SELECT
      (SELECT COUNT(*) FROM objednavky_restia) AS objednavky,
      (SELECT COALESCE(SUM(pocet_obj), 0) FROM zakaznik) AS zakaznik_pocet_obj,
      (SELECT COUNT(*) FROM objednavky_restia WHERE id_zak = 0) AS objednavky_anonym,
      (SELECT COALESCE(pocet_obj, 0) FROM zakaznik WHERE id_zak = 0 LIMIT 1) AS anonym_pocet_obj
");

q($conn, 'platform_delivery_status', "
    SELECT
      SUM(id_platforma IS NULL OR id_platforma = 0) AS bez_platformy,
      SUM(id_stav IS NULL OR id_stav = 0) AS bez_stavu,
      SUM(id_platba IS NULL OR id_platba = 0) AS bez_platby,
      SUM(id_doruceni IS NULL OR id_doruceni = 0) AS bez_doruceni
    FROM objednavky_restia
");

q($conn, 'items_summary', "
    SELECT
      COUNT(*) AS polozky,
      COUNT(DISTINCT id_obj) AS objednavky_s_polozkou
    FROM obj_polozky
");

q($conn, 'daily_diff_recount', "
    SELECT
      i.id_import,
      i.id_pob,
      i.datum_od,
      i.datum_do,
      i.pocet_restia,
      i.pocet_is,
      i.rozdil,
      (SELECT COUNT(*) FROM objednavky_restia o WHERE o.id_pob = i.id_pob AND o.restia_created_at >= i.datum_od AND o.restia_created_at <= i.datum_do) AS count_saved_interval,
      (SELECT COUNT(*) FROM objednavky_restia o WHERE o.id_pob = i.id_pob AND o.restia_created_at >= DATE_SUB(i.datum_od, INTERVAL 2 HOUR) AND o.restia_created_at <= DATE_ADD(i.datum_do, INTERVAL 2 HOUR)) AS count_saved_interval_plus_2h
    FROM obj_import i
    WHERE COALESCE(i.rozdil, 0) <> 0
    ORDER BY i.id_import
");

q($conn, 'boundary_orders_for_diff_days', "
    SELECT
      o.id_pob,
      o.id_obj,
      o.restia_id_obj,
      o.restia_created_at,
      o.restia_order_number,
      o.short_code
    FROM objednavky_restia o
    JOIN obj_import i ON i.id_pob = o.id_pob
    WHERE COALESCE(i.rozdil, 0) <> 0
      AND o.restia_created_at >= DATE_SUB(i.datum_od, INTERVAL 2 HOUR)
      AND o.restia_created_at <= DATE_ADD(i.datum_do, INTERVAL 2 HOUR)
      AND NOT (o.restia_created_at >= i.datum_od AND o.restia_created_at <= i.datum_do)
    ORDER BY i.id_import, o.restia_created_at
    LIMIT 100
");

q($conn, 'raw_json_optional_data_markers', "
    SELECT
      SUM(restia_raw_json LIKE '%modifier%') AS ma_modifier,
      SUM(restia_raw_json LIKE '%modifiers%') AS ma_modifiers,
      SUM(restia_raw_json LIKE '%kds%') AS ma_kds,
      SUM(restia_raw_json LIKE '%tag%') AS ma_tag,
      SUM(restia_raw_json LIKE '%tags%') AS ma_tags
    FROM obj_restia_detail
");

q($conn, 'raw_json_kds_item_keys_sample', "
    SELECT
      o.id_obj,
      JSON_KEYS(JSON_EXTRACT(d.restia_raw_json, '$.items[0]')) AS first_item_keys
    FROM obj_restia_detail d
    JOIN objednavky_restia o ON o.id_obj = d.id_obj
    WHERE d.restia_raw_json LIKE '%kds%' OR d.restia_raw_json LIKE '%tag%'
    LIMIT 10
");

q($conn, 'raw_json_item_key_counts', "
    SELECT
      SUM(JSON_CONTAINS_PATH(d.restia_raw_json, 'one', '$.items[0].KDSTags')) AS first_kds_tags_upper,
      SUM(JSON_CONTAINS_PATH(d.restia_raw_json, 'one', '$.items[0].kdsTags')) AS first_kds_tags_camel,
      SUM(JSON_CONTAINS_PATH(d.restia_raw_json, 'one', '$.items[0].kitchenTags')) AS first_kitchen_tags,
      SUM(JSON_CONTAINS_PATH(d.restia_raw_json, 'one', '$.items[0].tags')) AS first_tags
    FROM obj_restia_detail d
");

q($conn, 'raw_json_tag_snippets', "
    SELECT
      o.id_obj,
      SUBSTRING(d.restia_raw_json, GREATEST(1, LOCATE('tag', LOWER(d.restia_raw_json)) - 80), 220) AS snippet
    FROM obj_restia_detail d
    JOIN objednavky_restia o ON o.id_obj = d.id_obj
    WHERE LOCATE('tag', LOWER(d.restia_raw_json)) > 0
    LIMIT 5
");
