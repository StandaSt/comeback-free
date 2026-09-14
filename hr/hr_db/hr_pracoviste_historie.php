<?php
declare(strict_types=1);

/** @return array{pobocky:array<int,array{id_pob:int,nazev:string}>,historie:array<int,array<string,mixed>>} */
function hr_pracoviste_historie(mysqli $db, int $idPerson, int $idUser): array
{
    cb_firemni_pristup_vyzaduj_osobu($db, $idUser, $idPerson);
    $stmt = $db->prepare('SELECT id_firma FROM hr_person WHERE id_person = ? LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $idFirma = (int)($stmt->get_result()->fetch_assoc()['id_firma'] ?? 0);
    $stmt->close();

    $stmt = $db->prepare('SELECT id_pob, nazev FROM pobocka WHERE id_firma = ? AND aktivni = 1 ORDER BY id_pob');
    $stmt->bind_param('i', $idFirma);
    $stmt->execute();
    $pobocky = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $pobocky[] = ['id_pob' => (int)$row['id_pob'], 'nazev' => (string)$row['nazev']];
    }
    $stmt->close();

    $stmt = $db->prepare('SELECT hp.id_pracoviste, hp.id_pob, p.nazev, hp.hlavni, hp.platnost_od, hp.platnost_do, hp.platny, hp.zruseno FROM hr_pracoviste hp JOIN pobocka p ON p.id_pob = hp.id_pob WHERE hp.id_person = ? ORDER BY COALESCE(hp.platnost_od, \'1000-01-01\') DESC, hp.hlavni DESC, hp.id_pracoviste DESC');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $historie = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['id_pracoviste'] = (int)$row['id_pracoviste'];
        $row['id_pob'] = (int)$row['id_pob'];
        $row['hlavni'] = (int)$row['hlavni'];
        $row['platny'] = (int)$row['platny'];
        $historie[] = $row;
    }
    $stmt->close();
    return ['pobocky' => $pobocky, 'historie' => $historie];
}

/** @param int[] $idPobocky */
function hr_pracoviste_zmenit(mysqli $db, int $idPerson, array $idPobocky, int $idPobHlavni, string $platiOd, int $idUser): void
{
    cb_firemni_pristup_vyzaduj_osobu($db, $idUser, $idPerson);
    if ($idPobocky === [] || !in_array($idPobHlavni, $idPobocky, true)) {
        throw new RuntimeException('Vyberte alespoň jednu pobočku a jednu z nich jako hlavní.');
    }
    $stmt = $db->prepare('SELECT id_firma FROM hr_person WHERE id_person = ? LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $idFirma = (int)($stmt->get_result()->fetch_assoc()['id_firma'] ?? 0);
    $stmt->close();
    $marks = implode(',', array_fill(0, count($idPobocky), '?'));
    $types = 'i' . str_repeat('i', count($idPobocky));
    $stmt = $db->prepare('SELECT COUNT(*) AS pocet FROM pobocka WHERE id_firma = ? AND aktivni = 1 AND id_pob IN (' . $marks . ')');
    $refs = [&$types, &$idFirma];
    foreach ($idPobocky as $i => $id) { $refs[] = &$idPobocky[$i]; }
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $pocet = (int)($stmt->get_result()->fetch_assoc()['pocet'] ?? 0);
    $stmt->close();
    if ($pocet !== count($idPobocky)) {
        throw new RuntimeException('Vyberte pouze aktivní pobočky zaměstnancovy firmy.');
    }

    $denPred = (new DateTimeImmutable($platiOd))->modify('-1 day')->format('Y-m-d');
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE hr_pracoviste SET platnost_do = ? WHERE id_person = ? AND platny = 1 AND (platnost_od IS NULL OR platnost_od < ?) AND (platnost_do IS NULL OR platnost_do >= ?)');
        $stmt->bind_param('siss', $denPred, $idPerson, $platiOd, $platiOd);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('UPDATE hr_pracoviste SET platny = 0, zruseno = NOW() WHERE id_person = ? AND platny = 1 AND platnost_od >= ?');
        $stmt->bind_param('is', $idPerson, $platiOd);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('INSERT INTO hr_pracoviste (id_person, id_pob, hlavni, platnost_od, id_user_zadal, vytvoreno, platny) VALUES (?, ?, ?, ?, ?, NOW(), 1)');
        foreach ($idPobocky as $idPob) {
            $hlavni = $idPob === $idPobHlavni ? 1 : 0;
            $stmt->bind_param('iiisi', $idPerson, $idPob, $hlavni, $platiOd, $idUser);
            $stmt->execute();
        }
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
