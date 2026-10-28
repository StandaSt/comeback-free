<?php
// Ulozi novou kontrolu; potvrzene starsi verze jen zneplatni ve stejne transakci.
declare(strict_types=1);
require_once __DIR__ . '/db_kontrola_trzeb_kolize.php';

// Unikatni token zabrani dvema zaznamum pri opakovanem odeslani stejneho formulare.
function cb_kt_archiv_uloz(mysqli $db, array $snapshot, array $rows, string $token, int $user, string $name, ?array $confirmed = null): int
{
    $branch = $snapshot['branch'];
    $from = $snapshot['from'];
    $to = $snapshot['to'];
    $sources = json_encode($snapshot['sources'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $db->begin_transaction();
    try {
        cb_kt_archiv_zamkni($db, $branch);
        // Opakovane odeslani jiz ulozeneho formulare nesmi zneplatnit jeho vlastni vysledek.
        $stmt = $db->prepare('SELECT id_kontrola FROM provoz_kontrola_trzeb WHERE formular_token=? AND ulozil=? AND id_pob=? FOR UPDATE');
        $stmt->bind_param('sii', $token, $user, $branch); $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($existing) { $db->commit(); return (int)$existing['id_kontrola']; }
        $activeIds = cb_kt_archiv_platne_ids($db, $branch, $from, $to, 0, true);
        cb_kt_archiv_over_souhlas($activeIds, $confirmed);
        if ($activeIds !== []) {
            if (!cb_kt_platnost_pripravena($db)) { throw new CbUserVisibleException('Archiv nemá připravené sloupce platnosti.'); }
            $stmt = $db->prepare('UPDATE provoz_kontrola_trzeb SET platny=0,platnost_zmenil=?,platnost_zmeneno=NOW() WHERE id_pob=? AND datum_od=? AND datum_do=? AND platny=1');
            $stmt->bind_param('iiss', $user, $branch, $from, $to); $stmt->execute(); $stmt->close();
        }
        $stmt = $db->prepare('INSERT INTO provoz_kontrola_trzeb
            (id_pob, datum_od, datum_do, ulozil, ulozil_jmeno, ulozeno, formular_token, podklady)
            VALUES (?, ?, ?, ?, ?, NOW(), ?, ?)');
        $stmt->bind_param('ississs', $branch, $from, $to, $user, $name, $token, $sources);
        $stmt->execute();
        $id = (int)$db->insert_id;
        $stmt->close();
        $stmt = $db->prepare('INSERT INTO provoz_kontrola_trzeb_radky
            (id_kontrola, polozka, poradi, odevzdane, spocitane, poznamka) VALUES (?, ?, ?, ?, ?, ?)');
        $order = 0;
        foreach ($rows as $key => $row) {
            $order++;
            $left = cb_kt_decimal($row['left']);
            $right = cb_kt_decimal($row['right']);
            $note = $row['note'];
            $stmt->bind_param('isisss', $id, $key, $order, $left, $right, $note);
            $stmt->execute();
        }
        $stmt->close();
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1062) {
            $stmt = $db->prepare('SELECT id_kontrola FROM provoz_kontrola_trzeb WHERE formular_token=? AND ulozil=? AND id_pob=?');
            $stmt->bind_param('sii', $token, $user, $branch);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existing) { return (int)$existing['id_kontrola']; }
        }
        throw $e;
    }
}
