<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

if (!isset($SECRETS['db']['local']) || !is_array($SECRETS['db']['local'])) {
    fwrite(STDERR, "Chybi konfigurace DB local v secrets.\n");
    exit(1);
}

$cfg = $SECRETS['db']['local'];
$conn = new mysqli(
    (string)($cfg['host'] ?? ''),
    (string)($cfg['user'] ?? ''),
    (string)($cfg['pass'] ?? ''),
    (string)($cfg['name'] ?? '')
);

if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . PHP_EOL);
    exit(1);
}

$conn->set_charset('utf8mb4');

$execute = in_array('--execute', $argv, true);
$fromTs = '2026-05-14 06:00:00';
$toTsExclusive = '2026-05-16 06:00:00';
$reportDate = '2026-05-14';

$summary = [
    'mode' => $execute ? 'execute' : 'dry-run',
    'workday_start' => $fromTs,
    'workday_end_exclusive' => $toTsExclusive,
    'report_date' => $reportDate,
];

$conn->begin_transaction();

try {
    $conn->query('DROP TEMPORARY TABLE IF EXISTS tmp_cleanup_restia_today_orders');
    $conn->query('DROP TEMPORARY TABLE IF EXISTS tmp_cleanup_restia_today_items');

    $createOrdersSql = "
        CREATE TEMPORARY TABLE tmp_cleanup_restia_today_orders (
            id_obj INT NOT NULL PRIMARY KEY
        ) ENGINE=InnoDB
        AS
        SELECT DISTINCT o.id_obj
        FROM objednavky_restia o
        LEFT JOIN obj_casy ca ON ca.id_obj = o.id_obj
        WHERE (
            COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at) >= ?
            AND COALESCE(ca.cas_vytvor, o.restia_created_at, o.restia_imported_at) < ?
        ) OR (
            ca.cas_vytvor IS NULL
            AND o.restia_created_at IS NULL
            AND o.restia_imported_at IS NULL
            AND ca.report = ?
        )
    ";
    $stmtCreateOrders = $conn->prepare($createOrdersSql);
    if ($stmtCreateOrders === false) {
        throw new RuntimeException('Nepodarilo se pripravit temporary table pro id_obj.');
    }
    $stmtCreateOrders->bind_param('sss', $fromTs, $toTsExclusive, $reportDate);
    $stmtCreateOrders->execute();
    $stmtCreateOrders->close();

    $createItemsSql = "
        CREATE TEMPORARY TABLE tmp_cleanup_restia_today_items (
            id_obj_polozka INT NOT NULL PRIMARY KEY
        ) ENGINE=InnoDB
        AS
        SELECT p.id_obj_polozka
        FROM obj_polozky p
        INNER JOIN tmp_cleanup_restia_today_orders t ON t.id_obj = p.id_obj
    ";
    if ($conn->query($createItemsSql) === false) {
        throw new RuntimeException('Nepodarilo se pripravit temporary table pro id_obj_polozka.');
    }

    $countQuery = static function (mysqli $conn, string $sql): int {
        $res = $conn->query($sql);
        if (!($res instanceof mysqli_result)) {
            throw new RuntimeException('Nepodarilo se spocitat zaznamy: ' . $sql);
        }
        $row = $res->fetch_assoc();
        $res->free();
        return (int)($row['cnt'] ?? 0);
    };

    $counts = [
        'objednavky_restia' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM tmp_cleanup_restia_today_orders'),
        'obj_polozky' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM tmp_cleanup_restia_today_items'),
        'obj_polozka_mod' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM obj_polozka_mod WHERE id_obj_polozka IN (SELECT id_obj_polozka FROM tmp_cleanup_restia_today_items)'),
        'obj_polozka_kds_tag' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM obj_polozka_kds_tag WHERE id_obj_polozka IN (SELECT id_obj_polozka FROM tmp_cleanup_restia_today_items)'),
        'obj_kuryr' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM obj_kuryr WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)'),
        'obj_sluzba' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM obj_sluzba WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)'),
        'obj_adresa' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM obj_adresa WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)'),
        'obj_casy' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM obj_casy WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)'),
        'obj_ceny' => $countQuery($conn, 'SELECT COUNT(*) AS cnt FROM obj_ceny WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)'),
        'obj_import' => $countQuery($conn, "SELECT COUNT(*) AS cnt FROM obj_import WHERE typ_importu = 'online' AND datum_od >= '" . $conn->real_escape_string($fromTs) . "' AND datum_od < '" . $conn->real_escape_string($toTsExclusive) . "'"),
        'online_restia' => $countQuery($conn, "SELECT COUNT(*) AS cnt FROM online_restia WHERE start >= '" . $conn->real_escape_string($fromTs) . "' AND start < '" . $conn->real_escape_string($toTsExclusive) . "'"),
    ];

    $summary['counts'] = $counts;

    if ($execute) {
        $deleteSqls = [
            'DELETE FROM obj_polozka_kds_tag WHERE id_obj_polozka IN (SELECT id_obj_polozka FROM tmp_cleanup_restia_today_items)',
            'DELETE FROM obj_polozka_mod WHERE id_obj_polozka IN (SELECT id_obj_polozka FROM tmp_cleanup_restia_today_items)',
            'DELETE FROM obj_polozky WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)',
            'DELETE FROM obj_kuryr WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)',
            'DELETE FROM obj_sluzba WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)',
            'DELETE FROM obj_adresa WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)',
            'DELETE FROM obj_casy WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)',
            'DELETE FROM obj_ceny WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)',
            'DELETE FROM objednavky_restia WHERE id_obj IN (SELECT id_obj FROM tmp_cleanup_restia_today_orders)',
            "DELETE FROM obj_import WHERE typ_importu = 'online' AND datum_od >= '" . $conn->real_escape_string($fromTs) . "' AND datum_od < '" . $conn->real_escape_string($toTsExclusive) . "'",
            "DELETE FROM online_restia WHERE start >= '" . $conn->real_escape_string($fromTs) . "' AND start < '" . $conn->real_escape_string($toTsExclusive) . "'",
        ];

        foreach ($deleteSqls as $sql) {
            if ($conn->query($sql) === false) {
                throw new RuntimeException('Mazani selhalo: ' . $conn->error);
            }
        }
    }

    if ($execute) {
        $conn->commit();
    } else {
        $conn->rollback();
    }
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
