<?php
declare(strict_types=1);

/*
 * Ucel souboru: Nacita historii pracovnich slotu a pridava soubezne sloty zamestnance.
 */

/** @return array{pozice:array<int,array{id_slot:int,slot:string}>,historie:array<int,array<string,mixed>>} */
function hr_zarazeni_historie(mysqli $db, int $idPerson): array
{
    $slotTable = cb_hr_schema_table($db, 'slot');
    $slotPk = cb_hr_slot_pk($db);
    $pozice = [];
    $result = $db->query('SELECT id_slot, slot FROM cis_slot WHERE aktivni = 1 ORDER BY slot, id_slot');
    while ($row = $result->fetch_assoc()) {
        $pozice[] = ['id_slot' => (int)$row['id_slot'], 'slot' => (string)$row['slot']];
    }
    $result->free();

    $stmt = $db->prepare("SELECT hz.{$slotPk} AS id_zarazeni, hz.id_slot, hz.hlavni, cs.slot, hz.platnost_od, hz.platnost_do, hz.platny, hz.zruseno FROM {$slotTable} hz JOIN cis_slot cs ON cs.id_slot = hz.id_slot WHERE hz.id_person = ? ORDER BY COALESCE(hz.platnost_od, '1000-01-01') DESC, hz.{$slotPk} DESC");
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
    $slotTable = cb_hr_schema_table($db, 'slot');
    cb_firemni_pristup_vyzaduj_osobu($db, $idUser, $idPerson);
    $stmt = $db->prepare('SELECT 1 FROM cis_slot WHERE id_slot = ? AND aktivni = 1 LIMIT 1');
    $stmt->bind_param('i', $idSlot);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    if (!$exists) {
        throw new CbUserVisibleException('Vyberte aktivní pracovní slot.');
    }
    $db->begin_transaction();
    try {
        // Novy slot nerusi ostatni sloty; stejny aktivni slot se nesmi vlozit podruhe.
        $stmt = $db->prepare("SELECT 1 FROM {$slotTable} WHERE id_person = ? AND id_slot = ? AND platny = 1 AND (platnost_do IS NULL OR platnost_do >= ?) LIMIT 1");
        $stmt->bind_param('iis', $idPerson, $idSlot, $platiOd);
        $stmt->execute();
        $duplicate = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if ($duplicate) {
            throw new CbUserVisibleException('Tento slot už zaměstnanec má aktivní.');
        }
        $stmt = $db->prepare("SELECT 1 FROM {$slotTable} WHERE id_person = ? AND hlavni = 1 AND platny = 1 LIMIT 1");
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $hlavni = $stmt->get_result()->fetch_row() === null ? 1 : 0;
        $stmt->close();
        $stmt = $db->prepare("INSERT INTO {$slotTable} (id_person, id_slot, hlavni, platnost_od, id_user_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, NOW(), 1)");
        $stmt->bind_param('iiisi', $idPerson, $idSlot, $hlavni, $platiOd, $idUser);
        $stmt->execute();
        $stmt->close();
        // Alespon jeden aktualni slot nebo funkce je podminkou kompletni karty.
        hr_update_employee_completeness($db, $idPerson);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
