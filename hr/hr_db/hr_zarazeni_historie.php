<?php
declare(strict_types=1);

/* Audit HR se potvrzuje spolecne se zmenou; neuspesny pokus zustava odlisnou udalosti. */
require_once __DIR__ . '/../hr_lib/hr_audit.php';

/*
 * Ucel souboru: Nacita historii, pridava a ukoncuje pracovni sloty; funkci ponechava osobe.
 * Slot nema vlastni funkci; zarazeni osoby a MAIN se overuji po cele ucinnosti.
 * Cil zmeny musi spadat do jednotneho osobniho rozsahu HR.
 */
require_once __DIR__ . '/hr_zamestnanci_pristup.php';
require_once __DIR__ . '/hr_mista_zapis.php';

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

// Ukonceni slotu umozni prechod na manazerskou pozici bez mazani historie a bez zruseni ostatnich slotu.
function hr_zarazeni_ukoncit(mysqli $db, int $idPerson, int $idZarazeni, string $od, int $idUser): void
{
    hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
    cb_hr_osobu_zamkni($db,$idPerson);
    $stmt=$db->prepare('SELECT * FROM hr_slot WHERE id_hr_slot=? AND id_person=? AND platny=1 AND zruseno IS NULL FOR UPDATE');
    $stmt->bind_param('ii',$idZarazeni,$idPerson); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row || (!empty($row['platnost_do']) && $row['platnost_do']<$od)) throw new CbUserVisibleException('Vyberte platný slot k ukončení.');
    if (!empty($row['platnost_od']) && $row['platnost_od']>=$od) {
        $stmt=$db->prepare('UPDATE hr_slot SET platny=0,zruseno=NOW() WHERE id_hr_slot=? AND id_person=?');
        $stmt->bind_param('ii',$idZarazeni,$idPerson);
    } else {
        $pred=(new DateTimeImmutable($od))->modify('-1 day')->format('Y-m-d');
        $stmt=$db->prepare('UPDATE hr_slot SET platnost_do=? WHERE id_hr_slot=? AND id_person=?');
        $stmt->bind_param('sii',$pred,$idZarazeni,$idPerson);
    }
    $stmt->execute(); $stmt->close();
}

function hr_zarazeni_zmenit(mysqli $db, int $idPerson, int $idSlot, string $platiOd, int $idUser, bool $ownTransaction = true): void
{
    $slotTable = cb_hr_schema_table($db, 'slot');
    hr_zamestnanci_vyzaduj_osobu($db, $idUser, $idPerson);
    $stmt = $db->prepare('SELECT 1 FROM cis_slot WHERE id_slot = ? AND aktivni = 1 LIMIT 1');
    $stmt->bind_param('i', $idSlot);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    if (!$exists) {
        throw new CbUserVisibleException('Vyberte aktivní pracovní slot.');
    }
    if ($ownTransaction) hr_audit_transaction_begin($db);
    try {
        cb_hr_osobu_zamkni($db,$idPerson);
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
        hr_mista_over_casovou_osu($db,$idPerson,$platiOd);
        hr_update_employee_completeness($db, $idPerson);
        if ($ownTransaction) hr_audit_transaction_commit($db);
    } catch (Throwable $e) {
        if ($ownTransaction) hr_audit_transaction_rollback($db);
        throw $e;
    }
}
