<?php
declare(strict_types=1);
require __DIR__ . '/../../config/secrets.php';
require __DIR__ . '/../../lib/app.php';
require __DIR__ . '/../../db/db_connect.php';
$conn = db_connect();
$res = $conn->query("SELECT id_import, id_pob, datum_od, datum_do, stav, poznamka FROM obj_import WHERE id_pob = 1 AND typ_importu = 'historie' ORDER BY id_import DESC LIMIT 5");
while ($row = $res->fetch_assoc()) { echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL; }