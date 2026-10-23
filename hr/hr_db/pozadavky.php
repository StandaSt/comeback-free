<?php
declare(strict_types=1);

// Ucel rozsahu: HR pozadavky cte ze vsech MAIN; vytvoreni se vzdy vztahuje ke konkretni vybrane MAIN.
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

/**
 * DB operace pro HR pozadavky pobocky a vyber aktualni hlavni pobocky pro HR pristup.
 * Pri prebrani prav pouziva MAIN ze snimku profilu, autor pozadavku zustava vlastni.
 */

// Nacte celou mnozinu MAIN; pri prevzeti prav pouzije skutecnou zdrojovou osobu.
function hr_main_pobocky_uzivatele(mysqli $db,int $idUser): array
{
    if (($profile=cb_prebrani_prav_profil($idUser))!==null) $idUser=(int)$profile['id_user_zdroj'];
    $stmt=$db->prepare('SELECT id_person FROM hr_person WHERE id_user=? AND aktivni=1');
    $stmt->bind_param('i',$idUser); $stmt->execute(); $id=(int)($stmt->get_result()->fetch_assoc()['id_person']??0); $stmt->close();
    $ids=cb_hr_main_ids($db,$id);
    if ($ids===[]) return [];
    return $db->query('SELECT id_pob,nazev FROM pobocka WHERE id_pob IN ('.implode(',',$ids).') ORDER BY nazev')->fetch_all(MYSQLI_ASSOC);
}

// Jedina MAIN se predvyplni; pri vice MAIN ji musi formular vyslovne vybrat a server overit clenstvi.
function hr_nacti_hlavni_pobocku_uzivatele(mysqli $db,int $idUser): array
{
    $branches=hr_main_pobocky_uzivatele($db,$idUser);
    $selected=(int)($_POST['id_pob']??$_GET['id_pob']??0);
    if (count($branches)===1 && $selected===0) return $branches[0];
    foreach ($branches as $branch) if ((int)$branch['id_pob']===$selected) return $branch;
    throw new CbUserVisibleException($branches===[]?'Nemáte nastavenou MAIN pobočku.':'Vyberte jednu ze svých MAIN poboček.');
}

/**
 * Ulozi HR pozadavek na tolik radku, kolik zamestnancu je pozadovano.
 */
function hr_uloz_pozadavek(mysqli $db, int $idPob, int $idSlot, int $pocet, string $upresneni, int $zadalUser): void
{
    if ($zadalUser <= 0) {
        throw new CbUserVisibleException('Přihlášení vypršelo. Přihlaste se prosím znovu.');
    }
    if ($idPob <= 0 || $idSlot <= 0 || $pocet <= 0) {
        throw new CbUserVisibleException('Doplňte počet a požadovanou pozici.');
    }

    $stmt = $db->prepare("
        INSERT INTO hr_pozadavek (id_pob, id_slot, id_pozadavek_stav, upresneni, id_user_zadal, zadano)
        VALUES (?, ?, 1, ?, ?, NOW())
    ");

    for ($i = 0; $i < $pocet; $i++) {
        $stmt->bind_param('iisi', $idPob, $idSlot, $upresneni, $zadalUser);
        $stmt->execute();
    }

    $stmt->close();
}

/**
 * Zrusi otevreny HR pozadavek zadany konkretnim HR uzivatelem.
 */
function hr_zrus_pozadavek(mysqli $db, int $idPozadavek, int $zrusilUser): void
{
    if ($zrusilUser <= 0) {
        throw new CbUserVisibleException('Přihlášení vypršelo. Přihlaste se prosím znovu.');
    }

    $stmt = $db->prepare("
        UPDATE hr_pozadavek
        SET id_pozadavek_stav = 4,
            uzavreno = NOW(),
            id_user_uzavrel = ?
        WHERE id_pozadavek = ?
          AND id_pozadavek_stav = 1
          AND id_user_zadal = ?
    ");
    $stmt->bind_param('iii', $zrusilUser, $idPozadavek, $zrusilUser);
    $stmt->execute();
    $stmt->close();
}

/**
 * Nacte HR pozadavky pro jednu pobocku podle stavu.
 */
function hr_nacti_pozadavky_pobocky_podle_stavu(mysqli $db, int $idPob, int $stav): array
{
    $stmt = $db->prepare("
        SELECT
            hp.id_pozadavek,
            hp.id_slot,
            hp.upresneni,
            hp.id_user_zadal,
            hp.zadano,
            hp.id_pob,
            cs.slot
        FROM hr_pozadavek hp
        INNER JOIN cis_slot cs
            ON cs.id_slot = hp.id_slot
        WHERE hp.id_pob = ?
          AND hp.id_pozadavek_stav = ?
        ORDER BY hp.zadano ASC, hp.id_pozadavek ASC
    ");
    $stmt->bind_param('ii', $idPob, $stav);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row + [
            'upresneni' => trim((string)($row['upresneni'] ?? '')) !== '' ? (string)$row['upresneni'] : '-',
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * Nacte HR pozadavky vsech pobocek podle stavu.
 */
function hr_nacti_pozadavky_podle_stavu(mysqli $db, int $stav): array
{
    $stmt = $db->prepare("
        SELECT
            hp.id_pozadavek,
            hp.id_slot,
            hp.upresneni,
            hp.id_user_zadal,
            hp.zadano,
            hp.id_pob,
            cs.slot,
            p.nazev AS pobocka
        FROM hr_pozadavek hp
        INNER JOIN cis_slot cs
            ON cs.id_slot = hp.id_slot
        INNER JOIN pobocka p
            ON p.id_pob = hp.id_pob
        WHERE hp.id_pozadavek_stav = ?
        ORDER BY p.nazev ASC, hp.zadano ASC, hp.id_pozadavek ASC
    ");
    $stmt->bind_param('i', $stav);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row + [
            'upresneni' => trim((string)($row['upresneni'] ?? '')) !== '' ? (string)$row['upresneni'] : '-',
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * Nacte otevrene HR pozadavky pro jednu pobocku.
 */
function hr_nacti_nove_pozadavky_pobocky(mysqli $db, int $idPob): array
{
    return hr_nacti_pozadavky_pobocky_podle_stavu($db, $idPob, 1);
}
