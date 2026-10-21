<?php
// Strankovane cteni archivu za vybrane povolene pobocky a nemenne zdrojove polozky detailu.
declare(strict_types=1);

// Kontrola pritomnosti schematu je docasny provozni stav pred rucnim spustenim SQL.
function cb_kt_archiv_pripraven(mysqli $db): bool
{
    $result = $db->query("SELECT COUNT(*) AS n FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('provoz_kontrola_trzeb','provoz_kontrola_trzeb_radky')");
    return (int)$result->fetch_assoc()['n'] === 2;
}

// Dotaz je omezen povolenou pobockou jeste pred nactenim archivnich dat.
function cb_kt_archiv_seznam(mysqli $db, int|array $branch, int $page, bool $invalid = false, int $per = 20): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$branch), static fn(int $id): bool => $id > 0)));
    $per = in_array($per, [20,50,100,500], true) ? $per : 20;
    if ($ids === []) { return ['rows'=>[], 'count'=>0, 'page'=>1, 'per'=>$per, 'pages'=>1]; }
    // ID pochazeji z overeneho rozsahu a do SQL vstupuji vyhradne jako cela cisla.
    $scope = 'id_pob IN (' . implode(',', $ids) . ')';
    $ready = cb_kt_platnost_pripravena($db);
    $where = $ready && !$invalid ? ' AND platny=1' : '';
    $valid = $ready ? 'platny' : '1 AS platny';
    $stmt = $db->prepare('SELECT COUNT(*) AS n FROM provoz_kontrola_trzeb WHERE ' . $scope . $where);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    $pages = max(1, (int)ceil($count / $per));
    $page = min(max(1, $page), $pages);
    $offset = ($page - 1) * $per;
    $stmt = $db->prepare('SELECT id_kontrola, id_pob, datum_od, datum_do, ulozil_jmeno, ulozeno, ' . $valid . '
        FROM provoz_kontrola_trzeb WHERE ' . $scope . $where . ' ORDER BY ulozeno DESC, id_kontrola DESC LIMIT ? OFFSET ?');
    $stmt->bind_param('ii', $per, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return ['rows' => $rows, 'count' => $count, 'page' => $page, 'per'=>$per, 'pages'=>$pages];
}

// Do rucniho pridani sloupcu lze archiv cist; zmenu platnosti nepovolime.
function cb_kt_platnost_pripravena(mysqli $db): bool
{
    static $cache = [];
    $key = spl_object_id($db);
    return $cache[$key] ??= (int)$db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()
        AND TABLE_NAME='provoz_kontrola_trzeb' AND COLUMN_NAME IN ('platny','platnost_zmenil','platnost_zmeneno')")->fetch_row()[0] === 3;
}

// Detail vraci ulozene hodnoty; zdrojove reporty ani objednavky znovu neprepocitava.
function cb_kt_archiv_detail(mysqli $db, int $id, int $branch): array
{
    $stmt = $db->prepare('SELECT * FROM provoz_kontrola_trzeb WHERE id_kontrola=? AND id_pob=?');
    $stmt->bind_param('ii', $id, $branch);
    $stmt->execute();
    $header = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$header) { throw new CbUserVisibleException('Uložená kontrola nebyla nalezena.'); }
    $stmt = $db->prepare('SELECT polozka, odevzdane, spocitane, poznamka FROM provoz_kontrola_trzeb_radky
        WHERE id_kontrola=? ORDER BY poradi');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $header['rows'] = $rows;
    return $header;
}
