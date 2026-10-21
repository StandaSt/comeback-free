<?php
// Meni pouze platnost konkretni archivni kontroly v overene pobocce, vcetne autora zmeny.
declare(strict_types=1);

// Zdrojove reporty, objednavky a ulozene polozky se pri zneplatneni ani obnoveni nemeni.
function cb_kt_platnost_uloz(mysqli $db, int $id, int $branch, int $valid, int $user): void
{
    if (!cb_kt_platnost_pripravena($db)) { throw new CbUserVisibleException('Nejprve je potřeba přidat sloupce platnosti archivu.'); }
    cb_kt_archiv_detail($db, $id, $branch);
    $stmt = $db->prepare('UPDATE provoz_kontrola_trzeb SET platny=?,platnost_zmenil=?,platnost_zmeneno=NOW()
        WHERE id_kontrola=? AND id_pob=? AND platny<>?');
    $stmt->bind_param('iiiii', $valid, $user, $id, $branch, $valid);
    $stmt->execute();
    $stmt->close();
}
