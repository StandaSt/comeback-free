<?php
require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'];
$conn = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . PHP_EOL);
    exit(1);
}
$conn->set_charset('utf8mb4');

function hasColumn(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare hasColumn failed: ' . $conn->error);
    }
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    $stmt->close();
    return $ok;
}

function hasIndex(mysqli $conn, string $table, string $index): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare hasIndex failed: ' . $conn->error);
    }
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    $stmt->close();
    return $ok;
}

function hasForeignKey(mysqli $conn, string $table, string $fk): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = "FOREIGN KEY" LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('prepare hasForeignKey failed: ' . $conn->error);
    }
    $stmt->bind_param('ss', $table, $fk);
    $stmt->execute();
    $res = $stmt->get_result();
    $ok = $res instanceof mysqli_result && $res->num_rows > 0;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    $stmt->close();
    return $ok;
}

function runSql(mysqli $conn, string $sql): void
{
    if (!$conn->query($sql)) {
        throw new RuntimeException($conn->error . ' | SQL: ' . $sql);
    }
}

$conn->begin_transaction();
try {
    foreach (['zak_jmeno', 'zak_telefon', 'zak_email', 'zak_poznamka', 'import', 'import_zmeneno'] as $column) {
        if (hasColumn($conn, 'objednavky_restia', $column)) {
            runSql($conn, 'ALTER TABLE `objednavky_restia` DROP COLUMN `' . $column . '`');
        }
    }

    if (!hasColumn($conn, 'objednavky_restia', 'restia_imported_at')) {
        runSql($conn, 'ALTER TABLE `objednavky_restia` ADD COLUMN `restia_imported_at` DATETIME(3) NULL DEFAULT CURRENT_TIMESTAMP(3) AFTER `obj_pozn`');
    }

    foreach (['ix_obj_polozka_posid', 'ix_obj_polozka_obj_pos', 'ix_obj_polozky_restia_item_id', 'ix_obj_polozky_posid_zadano'] as $index) {
        if (hasIndex($conn, 'obj_polozky', $index)) {
            runSql($conn, 'ALTER TABLE `obj_polozky` DROP INDEX `' . $index . '`');
        }
    }

    foreach (['restia_item_id', 'pos_id', 'nazev', 'actual_label', 'creator_id', 'is_packaging', 'main_item_id'] as $column) {
        if (hasColumn($conn, 'obj_polozky', $column)) {
            runSql($conn, 'ALTER TABLE `obj_polozky` DROP COLUMN `' . $column . '`');
        }
    }

    if (!hasColumn($conn, 'obj_polozky', 'id_res_polozka')) {
        runSql($conn, 'ALTER TABLE `obj_polozky` ADD COLUMN `id_res_polozka` BIGINT(20) UNSIGNED NOT NULL AFTER `id_obj`');
    }

    if (!hasIndex($conn, 'obj_polozky', 'ix_obj_polozky_res_polozka')) {
        runSql($conn, 'ALTER TABLE `obj_polozky` ADD INDEX `ix_obj_polozky_res_polozka` (`id_res_polozka`)');
    }

    if (!hasIndex($conn, 'obj_polozky', 'ix_obj_polozky_obj_res')) {
        runSql($conn, 'ALTER TABLE `obj_polozky` ADD INDEX `ix_obj_polozky_obj_res` (`id_obj`, `id_res_polozka`)');
    }

    if (!hasForeignKey($conn, 'obj_polozky', 'fk_obj_polozky_res_polozky')) {
        runSql($conn, 'ALTER TABLE `obj_polozky` ADD CONSTRAINT `fk_obj_polozky_res_polozky` FOREIGN KEY (`id_res_polozka`) REFERENCES `res_polozky` (`id_res_polozka`) ON DELETE RESTRICT ON UPDATE RESTRICT');
    }

    $conn->commit();
    echo "OK\n";
} catch (Throwable $e) {
    $conn->rollback();
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
