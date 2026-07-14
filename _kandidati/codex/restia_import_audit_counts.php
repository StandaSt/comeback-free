<?php
declare(strict_types=1);

require __DIR__ . '/../../config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? null;
if (!is_array($cfg)) {
    fwrite(STDERR, "Missing local DB config.\n");
    exit(1);
}

$conn = new mysqli(
    (string)$cfg['host'],
    (string)$cfg['user'],
    (string)$cfg['pass'],
    (string)$cfg['name']
);

if ($conn->connect_error) {
    fwrite(STDERR, $conn->connect_error . "\n");
    exit(1);
}

$queries = [
    'obj_import_by_type_status' => '
        SELECT typ_importu, stav, COUNT(*) AS cnt, MIN(datum_od) AS min_od, MAX(datum_od) AS max_od
        FROM obj_import
        GROUP BY typ_importu, stav
        ORDER BY typ_importu, stav
    ',
    'obj_import_by_branch_type' => '
        SELECT id_pob, typ_importu, COUNT(*) AS cnt, MIN(datum_od) AS min_od, MAX(datum_od) AS max_od
        FROM obj_import
        GROUP BY id_pob, typ_importu
        ORDER BY id_pob, typ_importu
    ',
    'branches' => '
        SELECT id_pob, nazev, prvni_obj, restia_activePosId
        FROM pobocka
        WHERE id_pob > 0
        ORDER BY id_pob
    ',
];

foreach ($queries as $name => $sql) {
    echo "--- {$name}\n";
    $res = $conn->query($sql);
    if (!($res instanceof mysqli_result)) {
        echo "ERR: {$conn->error}\n";
        continue;
    }
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
    }
    $res->free();
}
