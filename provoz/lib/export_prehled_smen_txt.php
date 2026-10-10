<?php
// Vytvari TXT souhrn, detail i pracovniky po pobockach ze stavajicich hodin s kontrolou prav.
// lib/export_prehled_smen_txt.php * Verze: V2 * Aktualizace: 04.06.2026
declare(strict_types=1);

require_once __DIR__ . '/../../common/lib/session_boot.php';

require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
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

// Prime stazeni musi mit stejna prava jako stranka, jeste pred nactenim dat.
if (!cb_pravo_ma(200) || !cb_pravo_ma(209)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Přístup odepřen';
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
$scope = (string)($_GET['ps_scope'] ?? 'summary');
if (!in_array($scope, ['summary', 'detail', 'branches'], true)) $scope = 'summary';
$expanded = (string)($_GET['ps_variant'] ?? 'brief') === 'expanded';
$prefix = $scope === 'branches' ? 'pobocky_' . ($expanded ? 'rozsirene_' : 'strucne_') : ($scope === 'detail' ? 'detail_' : '');
$filename = 'prehled_smen_' . $prefix . $month . '.txt';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

echo 'Přehled za ' . (string)$data['monthLabel'] . ', celkem: ' . ps_num((float)$data['filteredHours']) . " hodin\n\n";

if ($scope === 'branches') {
    // Oba vystupy pouzivaji stejne soucty existujicich detailu; lisi se jen poctem sloupcu.
    $headers = ['zaměstnanec', 'pobočka', 'odpracováno'];
    $columns = $expanded ? ['celkem', 'den', 'noc', 'vikend', 'svatek'] : ['celkem'];
    if ($expanded) $headers = array_merge($headers, ['6-22', '22-6', 'So+Ne', 'svátek']);
    echo implode("\t", $headers) . "\n";
    foreach (ps_pracovnici_pobocky((array)$data['filteredRows']) as $row) {
        $values = [$row['cele_jmeno'], $row['pobocka']];
        foreach ($columns as $column) $values[] = ps_num((float)$row[$column]);
        echo implode("\t", $values) . "\n";
    }
} elseif ($scope === 'detail') {
    echo "celé jméno\tslot\tdatum\tpobočka\todpracováno\t6-22\t22-6\tSo+Ne\tsvátek\n";
    foreach ((array)$data['filteredRows'] as $row) {
        $detailRows = isset($row['detail_rows']) && is_array($row['detail_rows']) ? $row['detail_rows'] : [];
        foreach ($detailRows as $detailRow) {
            $branchName = trim((string)($detailRow['pobocka'] ?? ''));
            if ($branchName === '' && (int)($detailRow['id_pob'] ?? 0) > 0) {
                $branchName = 'ID ' . (string)(int)$detailRow['id_pob'];
            }
            echo implode("\t", [
                (string)$row['cele_jmeno'],
                ps_slot_label((int)$row['slot'], (array)$data['slotLabels']),
                (string)($detailRow['datum'] ?? ''),
                $branchName !== '' ? $branchName : '-',
                ps_num((float)($detailRow['celkem'] ?? 0.0)),
                ps_num((float)($detailRow['den'] ?? 0.0)),
                ps_num((float)($detailRow['noc'] ?? 0.0)),
                ps_num((float)($detailRow['vikend'] ?? 0.0)),
                ps_num((float)($detailRow['svatek'] ?? 0.0)),
            ]) . "\n";
        }
    }
} else {
    echo "měsíc\trok\tcelé jméno\tslot\todpracováno\t6-22\t22-6\tSo+Ne\tsvátek\n";
    foreach ((array)$data['filteredRows'] as $row) {
        echo implode("\t", [
            (string)$row['mesic'],
            (string)$row['rok'],
            (string)$row['cele_jmeno'],
            ps_slot_label((int)$row['slot'], (array)$data['slotLabels']),
            ps_num((float)$row['celkem']),
            ps_num((float)$row['den']),
            ps_num((float)$row['noc']),
            ps_num((float)$row['vikend']),
            ps_num((float)$row['svatek']),
        ]) . "\n";
    }
}

/* lib/export_prehled_smen_txt.php * Verze: V2 * Aktualizace: 04.06.2026 */
?>
