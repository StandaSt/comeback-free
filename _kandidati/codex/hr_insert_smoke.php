<?php
declare(strict_types=1);

require __DIR__ . '/../../www/lib/session_boot.php';
require __DIR__ . '/../../www/config/secrets.php';
require __DIR__ . '/../../www/lib/app.php';
require __DIR__ . '/../../hr/hr_includes/hr_data.php';

$_SESSION['cb_user'] = ['id_user' => 1];

$db = db();
$id = hr_insert_employee($db, [
    'jmeno' => 'Test',
    'prijmeni' => 'Codex',
    'osobni_cislo' => 'TEST-CODEX-' . date('His'),
    'stav' => 'priprava',
    'id_pracovni_vztah_typ' => 1,
    'datum_nastupu' => date('Y-m-d'),
    'id_pob' => 1,
    'id_slot' => 1,
    'telefon' => '+420 777 000 000',
    'email' => 'test.codex@example.com',
], hr_current_user_id());

$employee = hr_fetch_employee($db, $id);
echo ($employee !== null ? 'INSERT_OK ' . $id : 'INSERT_MISSING') . "\n";

foreach ([
    'hr_email',
    'hr_telefon',
    'hr_zamestnanec_zarazeni',
    'hr_zamestnanec_pracoviste',
    'hr_pracovni_vztah',
    'hr_osobni_udaje',
    'hr_zamestnanec',
] as $table) {
    $stmt = $db->prepare("DELETE FROM {$table} WHERE id_zamestnanec = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

echo "CLEANED\n";
