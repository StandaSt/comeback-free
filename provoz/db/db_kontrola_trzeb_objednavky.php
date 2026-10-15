<?php
// Cte trzby z objednavek za provozni dny; klasifikace odpovida dennim reportum.
declare(strict_types=1);

// Kuryr je overen pres EXISTS, aby vice zaznamu kuryrů neznasobilo castku objednavky.
function cb_kt_objednavky(mysqli $db, int $branch, string $from, string $to): array
{
    $stmt = $db->prepare("SELECT cp.kod AS platforma, p.nazev AS platba, d.nazev AS doruceni,
        c.cena_celk,
        EXISTS(SELECT 1 FROM obj_kuryr k WHERE k.id_obj=o.id_obj AND TRIM(k.jmeno)='Wolt Kurýr') AS wolt_kuryr
        FROM objednavky_restia o
        INNER JOIN obj_casy ca ON ca.id_obj=o.id_obj
        LEFT JOIN cis_obj_platforma cp ON cp.id_platforma=o.id_platforma
        LEFT JOIN cis_obj_platby p ON p.id_platba=o.id_platba
        LEFT JOIN cis_doruceni d ON d.id_doruceni=o.id_doruceni
        LEFT JOIN cis_obj_stav s ON s.id_stav=o.id_stav
        LEFT JOIN obj_ceny c ON c.id_obj=o.id_obj
        WHERE o.id_pob=? AND ca.report BETWEEN ? AND ?
        AND COALESCE(s.nazev,'') NOT IN ('canceled','rejected','expired','not_accepted','cancel_accepted')");
    $stmt->bind_param('iss', $branch, $from, $to);
    $stmt->execute();
    $result = $stmt->get_result();
    $values = array_fill_keys(['wolt', 'bolt', 'damejidlo', 'web', 'wolt_cash', 'dj_cash', 'trzba'], 0);
    $count = $missingPrices = 0;
    while ($row = $result->fetch_assoc()) {
        $count++;
        $amount = cb_kt_halere($row['cena_celk'] === null ? null : (string)$row['cena_celk']);
        if ($amount === null) { $missingPrices++; continue; }
        $values['trzba'] += $amount;
        $platform = $row['platforma'];
        $payment = $row['platba'] ?? '';
        if ($platform === 'wolt' && $payment !== 'cash') { $values['wolt'] += $amount; }
        if ($platform === 'bolt') { $values['bolt'] += $amount; }
        if (in_array($platform, ['foodora', 'damejidlo'], true)) {
            $values[$payment === 'cash' ? 'dj_cash' : 'damejidlo'] += $amount;
        }
        if ($platform === 'generic' && $payment === 'online') { $values['web'] += $amount; }
        if (in_array($platform, ['generic', 'manual'], true) && $payment === 'cash'
            && $row['doruceni'] === 'delivery' && (int)$row['wolt_kuryr'] === 1) {
            $values['wolt_cash'] += $amount;
        }
    }
    $stmt->close();
    // Chybejici cenu nelze vydavat za nulu ani za uplny kontrolni soucet.
    if ($missingPrices > 0) { $values = array_fill_keys(array_keys($values), null); }
    return ['values' => $values, 'count' => $count, 'missing_prices' => $missingPrices];
}
