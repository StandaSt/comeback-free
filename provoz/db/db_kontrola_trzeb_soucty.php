<?php
// Souhrn pouze ulozenych spocitanych castek platnych kontrol za presne shodne obdobi.
declare(strict_types=1);

// Zpracuje archivni radky v halerich; duplicita nebo prazdna castka nesmi vytvorit zavadejici soucet.
function cb_kt_ulozene_soucty(array $records): array
{
    $byBranch = [];
    foreach ($records as $record) {
        $branch = (int)$record['id_pob']; $id = (int)$record['id_kontrola'];
        $byBranch[$branch][$id][$record['polozka'] ?? ''] = cb_kt_halere($record['spocitane']);
    }
    $result = ['trzba'=>0, 'stravenky'=>0, 'branches'=>[], 'duplicates'=>[], 'incomplete'=>false];
    foreach ($byBranch as $branch=>$controls) {
        if (count($controls) !== 1) { $result['duplicates'][] = $branch; continue; }
        $result['branches'][] = $branch;
        $values = reset($controls);
        foreach (['trzba','stravenky'] as $key) {
            if (!isset($values[$key])) { $result[$key] = null; $result['incomplete'] = true; }
            elseif ($result[$key] !== null) { $result[$key] += $values[$key]; }
        }
    }
    if ($result['duplicates'] !== []) { $result['trzba'] = $result['stravenky'] = null; }
    return $result;
}

// ID musi byt z povoleneho rozsahu stranky; zadne reporty ani zive objednavky sem nevstupuji.
function cb_kt_archiv_soucty(mysqli $db, array $branches, string $from, string $to): array
{
    $ids = array_values(array_filter(array_map('intval', $branches), static fn(int $id): bool => $id > 0));
    if ($ids === [] || !cb_kt_archiv_pripraven($db)) { return cb_kt_ulozene_soucty([]); }
    $valid = cb_kt_platnost_pripravena($db) ? ' AND k.platny=1' : '';
    $stmt = $db->prepare("SELECT k.id_kontrola,k.id_pob,r.polozka,r.spocitane FROM provoz_kontrola_trzeb k
        LEFT JOIN provoz_kontrola_trzeb_radky r ON r.id_kontrola=k.id_kontrola AND r.polozka IN ('trzba','stravenky')
        WHERE k.id_pob IN (" . implode(',', $ids) . ') AND k.datum_od=? AND k.datum_do=?' . $valid . ' ORDER BY k.id_pob,k.id_kontrola');
    $stmt->bind_param('ss', $from, $to); $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    return cb_kt_ulozene_soucty($rows);
}
