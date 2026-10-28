<?php
// Meni platnost archivni kontroly; obnova nesmi vytvorit druhou platnou verzi stejneho obdobi.
declare(strict_types=1);
require_once __DIR__ . '/db_kontrola_trzeb_kolize.php';

// Zdrojove reporty, objednavky a ulozene polozky se pri zneplatneni ani obnoveni nemeni.
function cb_kt_platnost_uloz(mysqli $db, int $id, int $branch, int $valid, int $user): void
{
    if (!cb_kt_platnost_pripravena($db)) { throw new CbUserVisibleException('Nejprve je potřeba přidat sloupce platnosti archivu.'); }
    $db->begin_transaction();
    try {
        cb_kt_archiv_zamkni($db, $branch);
        $detail = cb_kt_archiv_detail($db, $id, $branch);
        if ($valid === 1 && cb_kt_archiv_platne_ids($db, $branch, $detail['datum_od'], $detail['datum_do'], $id, true) !== []) {
            throw new CbUserVisibleException('Pro tuto pobočku a období již existuje platná kontrola. Nejprve ji označte jako neplatnou.');
        }
        $stmt = $db->prepare('UPDATE provoz_kontrola_trzeb SET platny=?,platnost_zmenil=?,platnost_zmeneno=NOW()
            WHERE id_kontrola=? AND id_pob=? AND platny<>?');
        $stmt->bind_param('iiiii', $valid, $user, $id, $branch, $valid);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
