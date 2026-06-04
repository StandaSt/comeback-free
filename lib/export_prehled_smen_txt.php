<?php
// lib/export_prehled_smen_txt.php * Verze: V1 * Aktualizace: 03.06.2026
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/secrets.php';
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/prehled_smen_data.php';

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}

if (empty($_SESSION['login_ok'])) {
    http_response_code(401);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nutné přihlášení';
    exit;
}

$data = ps_prehled_smen_data($_GET);
if ((string)$data['error'] !== '') {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo (string)$data['error'];
    exit;
}

$month = sprintf('%04d-%02d', (int)$data['selectedYear'], (int)$data['selectedMonth']);
$filename = 'prehled_smen_' . $month . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

echo 'Přehled za ' . (string)$data['monthLabel'] . ', celkem: ' . ps_num((float)$data['filteredHours']) . " hodin\n\n";
echo "měsíc\trok\tcelé jméno\tslot\todpracováno\t6-22\t22-6\tSo+Ne\tsvátek\n";

foreach ((array)$data['filteredRows'] as $row) {
    echo implode("\t", [
        (string)$row['mesic'],
        (string)$row['rok'],
        (string)$row['cele_jmeno'],
        ps_slot_label((int)$row['slot']),
        ps_num((float)$row['celkem']),
        ps_num((float)$row['den']),
        ps_num((float)$row['noc']),
        ps_num((float)$row['vikend']),
        ps_num((float)$row['svatek']),
    ]) . "\n";
}

/* lib/export_prehled_smen_txt.php * Verze: V1 * Aktualizace: 03.06.2026 */
?>
