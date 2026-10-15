<?php
// Cteni seznamu a nemenneho detailu ulozenych kontrol trzeb.
declare(strict_types=1);

// Kontrola pritomnosti schematu je docasny provozni stav pred rucnim spustenim SQL.
function cb_kt_archiv_pripraven(mysqli $db): bool
{
    $result = $db->query("SELECT COUNT(*) AS n FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('provoz_kontrola_trzeb','provoz_kontrola_trzeb_radky')");
    return (int)$result->fetch_assoc()['n'] === 2;
}

// Dotaz je omezen povolenou pobockou jeste pred nactenim archivnich dat.
function cb_kt_archiv_seznam(mysqli $db, int $branch, int $page): array
{
    $stmt = $db->prepare('SELECT COUNT(*) AS n FROM provoz_kontrola_trzeb WHERE id_pob=?');
    $stmt->bind_param('i', $branch);
    $stmt->execute();
    $count = (int)$stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    $page = min(max(1, $page), max(1, (int)ceil($count / 30)));
    $offset = ($page - 1) * 30;
    $stmt = $db->prepare('SELECT id_kontrola, datum_od, datum_do, ulozil_jmeno, ulozeno
        FROM provoz_kontrola_trzeb WHERE id_pob=? ORDER BY ulozeno DESC, id_kontrola DESC LIMIT 30 OFFSET ?');
    $stmt->bind_param('ii', $branch, $offset);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return ['rows' => $rows, 'count' => $count, 'page' => $page];
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
