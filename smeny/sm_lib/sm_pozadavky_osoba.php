<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

// Ucel: Dostupnost a HPP volno pouziji vybranou MAIN; vice MAIN nikdy nezkracujeme na nahodny zaznam.
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

/*
 * Nacte hlavni slot a pobocku prihlasene osoby pro pozadavky na smeny v obou fazich HR migrace.
 * Test prava prevezme pracovni profil, ale osoba a autor zadani zustanou USER 1.
 */

/** @return array<string,mixed>|null */
function cb_smeny_pozadavky_osoba(mysqli $db, ?int $idUser = null): ?array
{
    $sessionUser = $_SESSION['cb_user'] ?? [];
    $idUser ??= is_array($sessionUser) ? (int)($sessionUser['id_user'] ?? 0) : 0;
    if (function_exists('cb_prebrani_prav_profil') && ($profile = cb_prebrani_prav_profil($idUser)) !== null) {
        $source=(int)$profile['id_user_zdroj'];
        $sourcePerson=$source>1?cb_smeny_pozadavky_osoba($db,$source):null;
        return $sourcePerson===null?null:array_replace($sourcePerson,['id_person'=>1,'jmeno'=>cb_osoba_jmeno($sessionUser,true)]);
    }
    if ($idUser <= 0) {
        return null;
    }

    $stmt=$db->prepare('SELECT id_person FROM hr_person WHERE id_user=? AND aktivni=1');
    $stmt->bind_param('i',$idUser); $stmt->execute(); $personId=(int)($stmt->get_result()->fetch_assoc()['id_person']??0); $stmt->close();
    $mainIds=cb_hr_main_ids($db,$personId);
    $selected=(int)($_POST['id_pob']??$_GET['id_pob']??0);
    if ($selected===0 && count($mainIds)===1) $selected=$mainIds[0];
    if (!in_array($selected,$mainIds,true)) $selected=0;
    $slotTable = cb_hr_schema_table($db, 'slot');
    $slotPk = cb_hr_slot_pk($db);
    $sql = '
        SELECT
            hp.id_person,
            hp.id_firma,
            ' . cb_osoba_jmeno_sql('ou.id_person',true) . ' AS jmeno,
            pracoviste.id_pob,
            pob.nazev AS pobocka,
            pob.end_po,
            pob.end_ut,
            pob.end_st,
            pob.end_ct,
            pob.end_pa,
            pob.end_so,
            pob.end_ne,
            zarazeni.id_slot,
            slot.slot AS pozice,
            CASE WHEN EXISTS (
                SELECT 1
                FROM hr_pracovni_vztah pv
                WHERE pv.id_person = hp.id_person
                  AND pv.id_pracovni_vztah_typ = 1
                  AND pv.platny = 1
                  AND (pv.datum_nastupu IS NULL OR pv.datum_nastupu <= CURDATE())
                  AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
            ) THEN 1 ELSE 0 END AS je_hpp
        FROM hr_person hp
        LEFT JOIN ' . cb_osoby_zdroj_sql() . ' ou
          ON ou.id_person=hp.id_person
        LEFT JOIN ' . cb_hr_main_sql($db) . ' pracoviste
          ON pracoviste.id_person=hp.id_person AND pracoviste.id_pob=' . $selected . '
            AND ' . cb_hr_ucinne_sql('pracoviste') . '
        LEFT JOIN pobocka pob ON pob.id_pob = pracoviste.id_pob
        LEFT JOIN ' . $slotTable . ' zarazeni
          ON zarazeni.' . $slotPk . ' = (
              SELECT MAX(za2.' . $slotPk . ')
              FROM ' . $slotTable . ' za2
              WHERE za2.id_person = hp.id_person
                AND za2.hlavni = 1
                AND za2.platny = 1
                AND (za2.platnost_od IS NULL OR za2.platnost_od <= CURDATE())
                AND (za2.platnost_do IS NULL OR za2.platnost_do >= CURDATE())
          )
        LEFT JOIN cis_slot slot ON slot.id_slot = zarazeni.id_slot
        WHERE hp.id_user = ? AND hp.aktivni = 1
        ORDER BY hp.id_person DESC
        LIMIT 1
    ';
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    foreach (['id_person', 'id_firma', 'id_pob', 'id_slot', 'je_hpp'] as $key) {
        $row[$key] = (int)($row[$key] ?? 0);
    }
    $row['main_pobocky']=$mainIds===[]?[]:$db->query('SELECT id_pob,nazev FROM pobocka WHERE id_pob IN ('.implode(',',$mainIds).') ORDER BY nazev')->fetch_all(MYSQLI_ASSOC);
    return $row;
}
