<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$apply = in_array('--apply', $argv, true);

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    throw new RuntimeException('Chybi local DB konfigurace.');
}

$conn = new mysqli((string)$cfg['host'], (string)$cfg['user'], (string)$cfg['pass'], (string)$cfg['name']);
$conn->set_charset('utf8mb4');

function cb_mig_columns(mysqli $conn): array
{
    $res = $conn->query('SHOW COLUMNS FROM user_set LIKE "obdobi_%"');
    if (!($res instanceof mysqli_result)) {
        throw new RuntimeException('Nelze nacist sloupce user_set.');
    }

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $out[(string)$row['Field']] = (string)$row['Type'];
    }
    $res->free();

    return $out;
}

function cb_mig_print_rows(mysqli $conn, string $label): void
{
    echo '--- ' . $label . PHP_EOL;
    $res = $conn->query('SELECT id_user, obdobi_od, obdobi_do FROM user_set ORDER BY id_user LIMIT 20');
    if (!($res instanceof mysqli_result)) {
        throw new RuntimeException('Nelze nacist ukazku user_set.');
    }
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    $res->free();
}

echo 'Rezim: ' . ($apply ? 'APPLY' : 'DRY-RUN') . PHP_EOL;

$before = cb_mig_columns($conn);
echo '--- columns before' . PHP_EOL;
echo json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
cb_mig_print_rows($conn, 'sample before');

$needsOd = strtolower($before['obdobi_od'] ?? '') !== 'datetime';
$needsDo = strtolower($before['obdobi_do'] ?? '') !== 'datetime';

if (!$needsOd && !$needsDo) {
    echo 'Sloupce uz jsou DATETIME, neni co menit.' . PHP_EOL;
    exit(0);
}

echo 'Plan: ALTER user_set.obdobi_od/do na DATETIME NULL, existujici DATE hodnoty zustanou jako 00:00:00.' . PHP_EOL;
echo 'Plan: nasledne doplnit hodnotam s casem 00:00:00 cas 06:00:00.' . PHP_EOL;

if (!$apply) {
    echo 'Bez --apply nebyla DB zmenena.' . PHP_EOL;
    exit(0);
}

$conn->begin_transaction();
try {
    if ($needsOd) {
        $conn->query('ALTER TABLE user_set MODIFY obdobi_od DATETIME NULL');
    }
    if ($needsDo) {
        $conn->query('ALTER TABLE user_set MODIFY obdobi_do DATETIME NULL');
    }

    $conn->query("
        UPDATE user_set
        SET obdobi_od = DATE_ADD(DATE(obdobi_od), INTERVAL 6 HOUR)
        WHERE obdobi_od IS NOT NULL
          AND TIME(obdobi_od) = '00:00:00'
    ");
    $affectedOd = $conn->affected_rows;

    $conn->query("
        UPDATE user_set
        SET obdobi_do = DATE_ADD(DATE(obdobi_do), INTERVAL 6 HOUR)
        WHERE obdobi_do IS NOT NULL
          AND TIME(obdobi_do) = '00:00:00'
    ");
    $affectedDo = $conn->affected_rows;

    $conn->commit();
    echo 'ULOZENO affected_od=' . $affectedOd . ' affected_do=' . $affectedDo . PHP_EOL;
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}

$after = cb_mig_columns($conn);
echo '--- columns after' . PHP_EOL;
echo json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
cb_mig_print_rows($conn, 'sample after');
