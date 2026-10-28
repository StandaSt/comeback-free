<?php
// Cte platne denni reporty jako levou stranu kontroly, bez dvojiteho zapocteni starsich verzi.
// Před prvním objednávkovým dnem podklady ani chybějící reporty nezapočítává.
declare(strict_types=1);
require_once __DIR__ . '/../lib/pobocka_provoz.php';

// Vrati presne halerove soucty, ID podkladu a chybejici dny ci nekompletni podklady.
function cb_kt_reporty(mysqli $db, int $branch, string $from, string $to): array
{
    $stmt = $db->prepare('SELECT r.id_reportu, r.datum_reportu, pk.id_reportu AS pokladna_id,
        ri.id_reportu AS restia_id, pk.hotovost, pk.terminal, pk.stravenky, pk.rozdil,
        pk.vydaje_benzin, pk.vydaje_auta, pk.vydaje_suroviny, pk.vydaje_ostatni, pk.vydaje_phm_soukrome,
        ri.wolt, ri.bolt, ri.damejidlo, ri.web, ri.wolt_cash, ri.dj_cash, ri.trzba
        FROM reporty_is r
        LEFT JOIN reporty_is_pokladna pk ON pk.id_reportu=r.id_reportu
        LEFT JOIN reporty_is_restia ri ON ri.id_reportu=r.id_reportu
        WHERE r.id_pob=? AND r.datum_reportu BETWEEN ? AND ? AND r.platny=1
        AND NOT EXISTS (SELECT 1 FROM reporty_is newer WHERE newer.id_pob=r.id_pob
            AND newer.datum_reportu=r.datum_reportu AND newer.platny=1 AND newer.id_reportu>r.id_reportu)
        AND ' . cb_pobocka_provoz_report_sql() . ' ORDER BY r.datum_reportu');
    $stmt->bind_param('iss', $branch, $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $sums = array_fill_keys(array_keys(cb_kt_polozky()), 0);
    $dates = $ids = $incomplete = [];
    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['datum_reportu'];
        $ids[] = (int)$row['id_reportu'];
        if ($row['pokladna_id'] === null || $row['restia_id'] === null) { $incomplete[] = $row['datum_reportu']; }
        foreach ($sums as $key => $sum) {
            $value = cb_kt_halere($row[$key] === null ? null : (string)$row[$key]);
            $sums[$key] = $sum === null || $value === null ? null : $sum + $value;
        }
    }
    $stmt->close();
    $missing = [];
    for ($day = new DateTimeImmutable($from); $day->format('Y-m-d') <= $to; $day = $day->modify('+1 day')) {
        if (cb_pobocka_provoz_report_started($db,$branch,$day->format('Y-m-d')) && !in_array($day->format('Y-m-d'), $dates, true)) { $missing[] = $day->format('Y-m-d'); }
    }
    if ($ids === []) { $sums = array_fill_keys(array_keys($sums), null); }
    return ['values' => $sums, 'report_ids' => $ids, 'missing' => $missing, 'incomplete' => $incomplete];
}
