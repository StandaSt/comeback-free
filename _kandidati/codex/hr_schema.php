<?php
declare(strict_types=1);

require __DIR__ . '/../../www/config/secrets.php';

$cfg = $SECRETS['db']['local'] ?? $SECRETS['db'] ?? null;
if (!is_array($cfg)) {
    fwrite(STDERR, "DB config missing\n");
    exit(1);
}

$db = new mysqli($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name']);
if ($db->connect_error) {
    fwrite(STDERR, $db->connect_error . "\n");
    exit(1);
}

$db->set_charset('utf8mb4');

$tables = [];
$result = $db->query("SHOW TABLES LIKE 'hr\\_%'");
if ($result === false) {
    fwrite(STDERR, $db->error . "\n");
    exit(1);
}

while ($row = $result->fetch_row()) {
    $tables[] = (string)$row[0];
}

foreach ($tables as $table) {
    echo "TABLE {$table}\n";
    $columns = $db->query('SHOW COLUMNS FROM `' . $db->real_escape_string($table) . '`');
    if ($columns === false) {
        echo "  ERROR {$db->error}\n";
        continue;
    }

    while ($column = $columns->fetch_assoc()) {
        echo '  ' . $column['Field'] . ' ' . $column['Type'] . ' ' . $column['Null'] . ' ' . $column['Key'] . ' ' . $column['Default'] . "\n";
    }
}

echo "\nLOOKUPS\n";
$lookupSql = [
    'hr_pracovni_vztah_typ' => 'SELECT id_pracovni_vztah_typ, kod, nazev FROM hr_pracovni_vztah_typ ORDER BY poradi, id_pracovni_vztah_typ',
    'hr_email_typ' => 'SELECT id_email_typ, nazev FROM hr_email_typ ORDER BY poradi, id_email_typ',
    'hr_telefon_typ' => 'SELECT id_telefon_typ, nazev FROM hr_telefon_typ ORDER BY poradi, id_telefon_typ',
    'pobocka' => 'SELECT id_pob, kod, nazev FROM pobocka ORDER BY id_pob LIMIT 20',
    'cis_slot' => 'SELECT id_slot, slot FROM cis_slot ORDER BY id_slot LIMIT 20',
    'user' => 'SELECT id_user, jmeno, prijmeni FROM `user` ORDER BY id_user LIMIT 5',
];

foreach ($lookupSql as $name => $sql) {
    echo "LOOKUP {$name}\n";
    $result = $db->query($sql);
    if ($result === false) {
        echo "  ERROR {$db->error}\n";
        continue;
    }

    while ($row = $result->fetch_assoc()) {
        echo '  ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
