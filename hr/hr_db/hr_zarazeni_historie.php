<?php
declare(strict_types=1);

/** @return array{pozice:array<int,array{id_slot:int,slot:string}>,historie:array<int,array<string,mixed>>} */
function hr_zarazeni_historie(mysqli $db, int $idPerson): array
{
    $pozice = [];
    $result = $db->query('SELECT id_slot, slot FROM cis_slot WHERE aktivni = 1 ORDER BY slot, id_slot');
    while ($row = $result->fetch_assoc()) {
        $pozice[] = ['id_slot' => (int)$row['id_slot'], 'slot' => (string)$row['slot']];
    }
    $result->free();

    $stmt = $db->prepare('SELECT hz.id_zarazeni, hz.id_slot, cs.slot, hz.platnost_od, hz.platnost_do, hz.platny, hz.zruseno FROM hr_zarazeni hz JOIN cis_slot cs ON cs.id_slot = hz.id_slot WHERE hz.id_person = ? ORDER BY COALESCE(hz.platnost_od, \'1000-01-01\') DESC, hz.id_zarazeni DESC');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $historie = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id_zarazeni'] = (int)$row['id_zarazeni'];
        $row['id_slot'] = (int)$row['id_slot'];
        $row['platny'] = (int)$row['platny'];
        $historie[] = $row;
    }
    $stmt->close();
    return ['pozice' => $pozice, 'historie' => $historie];
}

function hr_zarazeni_zmenit(mysqli $db, int $idPerson, int $idSlot, string $platiOd, int $idUser): void
{
    cb_firemni_pristup_vyzaduj_osobu($db, $idUser, $idPerson);
    $stmt = $db->prepare('SELECT 1 FROM cis_slot WHERE id_slot = ? AND aktivni = 1 LIMIT 1');
    $stmt->bind_param('i', $idSlot);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    if (!$exists) {
        throw new CbUserVisibleException('Vyberte aktivní pozici.');
    }
    $denPred = (new DateTimeImmutable($platiOd))->modify('-1 day')->format('Y-m-d');
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE hr_zarazeni SET platnost_do = ? WHERE id_person = ? AND platny = 1 AND (platnost_od IS NULL OR platnost_od < ?) AND (platnost_do IS NULL OR platnost_do >= ?)');
        $stmt->bind_param('siss', $denPred, $idPerson, $platiOd, $platiOd);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('UPDATE hr_zarazeni SET platny = 0, zruseno = NOW() WHERE id_person = ? AND platny = 1 AND platnost_od >= ?');
        $stmt->bind_param('is', $idPerson, $platiOd);
        $stmt->execute();
        $stmt->close();
        $hlavni = 1;
        $stmt = $db->prepare('INSERT INTO hr_zarazeni (id_person, id_slot, hlavni, platnost_od, id_user_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, NOW(), 1)');
        $stmt->bind_param('iiisi', $idPerson, $idSlot, $hlavni, $platiOd, $idUser);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
