<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Prague');

require_once __DIR__ . '/../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    throw new RuntimeException('Chybi DB konfigurace $SECRETS["db"]["local"].');
}

$conn = new mysqli(
    (string)($cfg['host'] ?? ''),
    (string)($cfg['user'] ?? ''),
    (string)($cfg['pass'] ?? ''),
    (string)($cfg['name'] ?? '')
);

if ($conn->connect_error) {
    throw new RuntimeException('DB connect error: ' . $conn->connect_error);
}

if (!$conn->set_charset('utf8mb4')) {
    throw new RuntimeException('Nepodarilo se nastavit utf8mb4.');
}

$fkName = 'fk_obj_polozka_kds_tag_obj_polozka';

$stmtFk = $conn->prepare('
    SELECT 1
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND CONSTRAINT_NAME = ?
    LIMIT 1
');
if ($stmtFk === false) {
    throw new RuntimeException('DB prepare selhal: kontrola FK.');
}

$stmtFk->bind_param('s', $fkName);
$stmtFk->execute();
$resFk = $stmtFk->get_result();
$fkExists = ($resFk instanceof mysqli_result) ? ($resFk->fetch_row() !== null) : false;
if ($resFk instanceof mysqli_result) {
    $resFk->free();
}
$stmtFk->close();

if ($fkExists) {
    echo 'FK uz existuje: ' . $fkName . PHP_EOL;
    $conn->close();
    exit(0);
}

$sqlOrphans = '
    SELECT COUNT(*) AS cnt
    FROM obj_polozka_kds_tag t
    LEFT JOIN obj_polozky p ON p.id_obj_polozka = t.id_obj_polozka
    WHERE p.id_obj_polozka IS NULL
';

$resOrphans = $conn->query($sqlOrphans);
if (!($resOrphans instanceof mysqli_result)) {
    throw new RuntimeException('DB dotaz na sirotky selhal.');
}

$orphansRow = $resOrphans->fetch_assoc();
$orphansCount = (int)($orphansRow['cnt'] ?? 0);
$resOrphans->free();

if ($orphansCount > 0) {
    throw new RuntimeException(
        'Nelze doplnit FK, protoze v obj_polozka_kds_tag existuje ' . $orphansCount . ' sirotku.'
    );
}

$sqlAddFk = '
    ALTER TABLE obj_polozka_kds_tag
    ADD CONSTRAINT ' . $fkName . '
    FOREIGN KEY (id_obj_polozka)
    REFERENCES obj_polozky (id_obj_polozka)
    ON DELETE CASCADE
    ON UPDATE CASCADE
';

if ($conn->query($sqlAddFk) === false) {
    throw new RuntimeException('ALTER TABLE selhal: ' . $conn->error);
}

echo 'OK: FK doplnen ' . $fkName . PHP_EOL;

$conn->close();
