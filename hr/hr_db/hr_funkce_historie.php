<?php
declare(strict_types=1);

/*
 * Ucel souboru: Nacita a meni jedinou firemni funkci zamestnance a z ni synchronizuje roli v IS.
 */

/** @return array{funkce:array<int,array{id_funkce:int,nazev:string,id_role:int}>,historie:array<int,array<string,mixed>>} */
function hr_funkce_historie(mysqli $db, int $idPerson): array
{
    $catalogTable = cb_hr_schema_table($db, 'cis_funkce');
    $catalogPk = cb_hr_cis_funkce_pk($db);
    $functionTable = cb_hr_schema_table($db, 'funkce');
    $functionPk = cb_hr_funkce_pk($db);
    $functionFk = cb_hr_funkce_fk($db);

    $funkce = [];
    // Obecny prevzaty zaznam a pevny Top manager nejsou volby pro personalistu.
    $result = $db->query("SELECT {$catalogPk} AS id_funkce, nazev, id_role FROM {$catalogTable} WHERE aktivni = 1 AND {$catalogPk} NOT IN (1, 5, 6) ORDER BY id_role DESC, nazev");
    while ($row = $result->fetch_assoc()) {
        $funkce[] = ['id_funkce' => (int)$row['id_funkce'], 'nazev' => (string)$row['nazev'], 'id_role' => (int)$row['id_role']];
    }
    $result->free();

    $stmt = $db->prepare("SELECT hf.{$functionPk} AS id_hr_funkce, hf.{$functionFk} AS id_funkce, cf.nazev, cf.id_role, hf.v_treninku, hf.platnost_od, hf.platnost_do, hf.platny FROM {$functionTable} hf JOIN {$catalogTable} cf ON cf.{$catalogPk} = hf.{$functionFk} WHERE hf.id_person = ? AND hf.{$functionFk} <> 1 ORDER BY hf.platny DESC, COALESCE(hf.platnost_od, '1000-01-01') DESC, hf.{$functionPk} DESC");
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $historie = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        foreach (['id_hr_funkce', 'id_funkce', 'id_role', 'v_treninku', 'platny'] as $key) {
            $row[$key] = (int)$row[$key];
        }
        $historie[] = $row;
    }
    $stmt->close();

    return ['funkce' => $funkce, 'historie' => $historie];
}

/**
 * Ulozi prave jednu funkci, pripadne funkci odebere; role se vzdy odvozuje automaticky.
 */
function hr_funkce_zmenit(mysqli $db, int $idPerson, ?int $idFunkce, bool $vTreninku, string $platiOd, int $idUser): void
{
    cb_firemni_pristup_vyzaduj_osobu($db, $idUser, $idPerson);
    $catalogTable = cb_hr_schema_table($db, 'cis_funkce');
    $catalogPk = cb_hr_cis_funkce_pk($db);
    $functionTable = cb_hr_schema_table($db, 'funkce');
    $functionFk = cb_hr_funkce_fk($db);

    if ($idFunkce === null) {
        $slotTable = cb_hr_schema_table($db, 'slot');
        $stmt = $db->prepare("SELECT 1 FROM {$slotTable} WHERE id_person = ? AND platny = 1 AND (platnost_od IS NULL OR platnost_od <= ?) AND (platnost_do IS NULL OR platnost_do >= ?) LIMIT 1");
        $stmt->bind_param('iss', $idPerson, $platiOd, $platiOd);
        $stmt->execute();
        $hasSlot = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if (!$hasSlot) {
            throw new CbUserVisibleException('Funkci nelze odebrat, dokud zaměstnanec nemá žádný pracovní slot.');
        }
    }

    if ($idFunkce !== null) {
        $stmt = $db->prepare("SELECT id_role FROM {$catalogTable} WHERE {$catalogPk} = ? AND aktivni = 1 AND {$catalogPk} NOT IN (1, 5, 6) LIMIT 1");
        $stmt->bind_param('i', $idFunkce);
        $stmt->execute();
        $catalog = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($catalog)) {
            throw new CbUserVisibleException('Vyberte platnou funkci.');
        }
        if ((int)$catalog['id_role'] === 3 && !cb_pravo_ma(316)) {
            throw new CbUserVisibleException('Nemáte právo přidělit manažerskou funkci.');
        }
    }

    $db->begin_transaction();
    try {
        $denPred = (new DateTimeImmutable($platiOd))->modify('-1 day')->format('Y-m-d');
        $stmt = $db->prepare("UPDATE {$functionTable} SET platnost_do = ?, platny = 0 WHERE id_person = ? AND platny = 1");
        $stmt->bind_param('si', $denPred, $idPerson);
        $stmt->execute();
        $stmt->close();
        if ($idFunkce !== null) {
            $training = $vTreninku ? 1 : 0;
            $stmt = $db->prepare("INSERT INTO {$functionTable} (id_person, {$functionFk}, hlavni, v_treninku, platnost_od, id_person_zadal, platny) VALUES (?, ?, 1, ?, ?, ?, 1)");
            $stmt->bind_param('iiisi', $idPerson, $idFunkce, $training, $platiOd, $idUser);
            $stmt->execute();
            $stmt->close();
        }

        hr_pristupovy_profil_synchronizovat($db, $idPerson, $idUser);
        hr_update_employee_completeness($db, $idPerson);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Nastavi jedinou nejvyssi roli: pevny Admin/Top manager, zachovany Fransizant,
 * jinak role prislusne funkce nebo Zamestnanec bez funkce.
 */
function hr_pristupovy_profil_synchronizovat(mysqli $db, int $idPerson, ?int $idPersonZadal): int
{
    $stmt = $db->prepare('SELECT id_user FROM hr_person WHERE id_person = ? LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $person = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($person)) {
        throw new CbUserVisibleException('Zaměstnanec nebyl nalezen.');
    }

    $idUser = (int)($person['id_user'] ?? 0);
    if ($idUser === 1) {
        $idRole = 1;
    } elseif ($idUser === 57) {
        $idRole = 2;
    } else {
        $stmt = $db->prepare('SELECT 1 FROM hr_pristupovy_profil WHERE id_person = ? AND id_role = 4 LIMIT 1');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $jeFransizant = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if ($jeFransizant) {
            $idRole = 4;
        } else {
            $functionTable = cb_hr_schema_table($db, 'funkce');
            $functionFk = cb_hr_funkce_fk($db);
            $catalogTable = cb_hr_schema_table($db, 'cis_funkce');
            $catalogPk = cb_hr_cis_funkce_pk($db);
            $stmt = $db->prepare("SELECT cf.id_role FROM {$functionTable} hf JOIN {$catalogTable} cf ON cf.{$catalogPk} = hf.{$functionFk} WHERE hf.id_person = ? AND hf.platny = 1 AND (hf.platnost_od IS NULL OR hf.platnost_od <= CURDATE()) AND (hf.platnost_do IS NULL OR hf.platnost_do >= CURDATE()) ORDER BY cf.id_role ASC LIMIT 1");
            $stmt->bind_param('i', $idPerson);
            $stmt->execute();
            $role = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $idRole = is_array($role) ? (int)$role['id_role'] : 9;
        }
    }

    $stmt = $db->prepare('DELETE FROM hr_pristupovy_profil WHERE id_person = ?');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare('INSERT INTO hr_pristupovy_profil (id_person, id_role, sub_role, id_person_zadal) VALUES (?, ?, NULL, ?)');
    $stmt->bind_param('iii', $idPerson, $idRole, $idPersonZadal);
    $stmt->execute();
    $stmt->close();
    return $idRole;
}
