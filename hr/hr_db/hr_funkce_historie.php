<?php
declare(strict_types=1);

/*
 * Ucel souboru: Nacita a meni jedinou firemni funkci zamestnance a z ni synchronizuje roli v IS.
 * Provozni funkce patri osobe se slotem; manazerska pozice nema slot ani MAIN. Top chrani Admin.
 * Cil zmeny kontroluje podle jednotneho osobniho rozsahu HR.
 */
require_once __DIR__ . '/hr_nastaveni_ciselniky.php';
require_once __DIR__ . '/hr_zamestnanci_pristup.php';
require_once __DIR__ . '/hr_mista_zapis.php';

/** @return array{funkce:array<int,array{id_funkce:int,nazev:string,id_role:int}>,historie:array<int,array<string,mixed>>} */
function hr_funkce_historie(mysqli $db, int $idPerson): array
{
    $catalogTable = cb_hr_schema_table($db, 'cis_funkce');
    $catalogPk = cb_hr_cis_funkce_pk($db);
    $functionTable = cb_hr_schema_table($db, 'funkce');
    $functionPk = cb_hr_funkce_pk($db);
    $functionFk = cb_hr_funkce_fk($db);

    $funkce = [];
    // Prechodne zaznamy se nenabizeji; Top ma vlastni priznak pouze_admin, ktery chrani UI i zapis.
    $result = $db->query("SELECT {$catalogPk} AS id_funkce, nazev, id_role, zarazeni, pouze_admin FROM {$catalogTable} WHERE aktivni = 1 AND {$catalogPk} NOT IN (1, 5) ORDER BY id_role DESC, nazev");
    while ($row = $result->fetch_assoc()) {
        $funkce[] = ['id_funkce' => (int)$row['id_funkce'], 'nazev' => (string)$row['nazev'], 'id_role' => (int)$row['id_role'], 'zarazeni'=>$row['zarazeni'], 'pouze_admin'=>(int)$row['pouze_admin']];
    }
    $result->free();

    $stmt = $db->prepare("SELECT hf.{$functionPk} AS id_hr_funkce, hf.{$functionFk} AS id_funkce, cf.nazev, cf.id_role, cf.zarazeni, cf.pouze_admin, hf.v_treninku, hf.platnost_od, hf.platnost_do, hf.platny FROM {$functionTable} hf JOIN {$catalogTable} cf ON cf.{$catalogPk} = hf.{$functionFk} WHERE hf.id_person = ? AND hf.{$functionFk} <> 1 ORDER BY hf.platny DESC, COALESCE(hf.platnost_od, '1000-01-01') DESC, hf.{$functionPk} DESC");
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
function hr_funkce_zmenit(mysqli $db, int $idPerson, ?int $idFunkce, bool $vTreninku, string $platiOd, int $idUser, string $druh = 'manazerske', bool $ownTransaction = true): void
{
    hr_zamestnanci_vyzaduj_osobu($db, $idUser, $idPerson);
    $catalogTable = cb_hr_schema_table($db, 'cis_funkce');
    $catalogPk = cb_hr_cis_funkce_pk($db);
    $functionTable = cb_hr_schema_table($db, 'funkce');
    $functionFk = cb_hr_funkce_fk($db);

    if ($idFunkce !== null) {
        $stmt = $db->prepare("SELECT id_role,zarazeni,pouze_admin FROM {$catalogTable} WHERE {$catalogPk} = ? AND aktivni = 1 AND {$catalogPk} NOT IN (1, 5) LIMIT 1");
        $stmt->bind_param('i', $idFunkce);
        $stmt->execute();
        $catalog = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($catalog)) {
            throw new CbUserVisibleException('Vyberte platnou funkci.');
        }
        if ($catalog['zarazeni']!==$druh) throw new CbUserVisibleException('Funkce nepatří do tohoto typu zařazení.');
        if ((int)$catalog['pouze_admin']===1 && !hr_ciselnik_je_admin($db)) throw new CbUserVisibleException('Pozici Top manažer smí přidělit pouze Admin.');
        hr_funkce_roli_vyzaduj($db, (int)$catalog['id_role']);
        if ($catalog['zarazeni'] === 'manazerske' && !hr_ciselnik_je_admin($db) && !cb_pravo_ma(316)) {
            throw new CbUserVisibleException('Nemáte právo přidělit manažerskou funkci.');
        }
    }

    if ($ownTransaction) $db->begin_transaction();
    try {
        cb_hr_osobu_zamkni($db,$idPerson);
        $stmt=$db->prepare("SELECT f.*,c.id_role,c.zarazeni,c.pouze_admin FROM {$functionTable} f JOIN {$catalogTable} c ON c.{$catalogPk}=f.{$functionFk} WHERE f.id_person=? AND f.platny=1 FOR UPDATE");
        $stmt->bind_param('i',$idPerson); $stmt->execute(); $old=$stmt->get_result()->fetch_assoc(); $stmt->close();
        if ($old && (int)$old['pouze_admin']===1 && !hr_ciselnik_je_admin($db)) throw new CbUserVisibleException('Pozici Top manažer smí měnit nebo odebrat pouze Admin.');
        if ($old) hr_funkce_roli_vyzaduj($db,(int)$old['id_role']);
        if ($old && $old['zarazeni']==='manazerske' && !hr_ciselnik_je_admin($db) && !cb_pravo_ma(316)) throw new CbUserVisibleException('Nemáte právo měnit manažerskou pozici.');
        if ($old && !empty($old['platnost_od']) && $old['platnost_od']>$platiOd) throw new CbUserVisibleException('Nejprve upravte již naplánovanou funkci.');
        if ($idFunkce===null && $old && $old['zarazeni']!==$druh) throw new CbUserVisibleException('Tento blok nemůže odebrat jiný typ zařazení.');
        $denPred = (new DateTimeImmutable($platiOd))->modify('-1 day')->format('Y-m-d');
        $stmt = $db->prepare("UPDATE {$functionTable} SET platnost_do = CASE WHEN platnost_od IS NULL OR platnost_od<=? THEN ? ELSE platnost_do END, platny = 0 WHERE id_person = ? AND platny = 1");
        $stmt->bind_param('ssi', $denPred, $denPred, $idPerson);
        $stmt->execute();
        $stmt->close();
        if ($idFunkce !== null) {
            $training = $vTreninku ? 1 : 0;
            $stmt = $db->prepare("INSERT INTO {$functionTable} (id_person, {$functionFk}, hlavni, v_treninku, platnost_od, id_person_zadal, platny) VALUES (?, ?, 1, ?, ?, ?, 1)");
            $stmt->bind_param('iiisi', $idPerson, $idFunkce, $training, $platiOd, $idUser);
            $stmt->execute();
            $stmt->close();
        }

        hr_mista_over_casovou_osu($db,$idPerson,$platiOd);
        hr_pristupovy_profil_synchronizovat($db, $idPerson, $idUser);
        hr_update_employee_completeness($db, $idPerson);
        if ($ownTransaction) $db->commit();
    } catch (Throwable $e) {
        if ($ownTransaction) $db->rollback();
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
