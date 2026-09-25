<?php
declare(strict_types=1);

/*
 * Načte pracovní kontext přihlášené osoby pro zadávání požadavků na směny.
 */

/** @return array<string,mixed>|null */
function cb_smeny_pozadavky_osoba(mysqli $db): ?array
{
    $sessionUser = $_SESSION['cb_user'] ?? [];
    $idUser = is_array($sessionUser) ? (int)($sessionUser['id_user'] ?? 0) : 0;
    if ($idUser <= 0) {
        return null;
    }

    $sql = '
        SELECT
            hp.id_person,
            hp.id_firma,
            TRIM(CONCAT_WS(" ", ou.jmeno, ou.prijmeni)) AS jmeno,
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
        LEFT JOIN hr_osobni_udaje ou
          ON ou.id_osobni_udaje = (
              SELECT MAX(ou2.id_osobni_udaje)
              FROM hr_osobni_udaje ou2
              WHERE ou2.id_person = hp.id_person AND ou2.platny = 1
          )
        LEFT JOIN hr_pracoviste pracoviste
          ON pracoviste.id_pracoviste = (
              SELECT MAX(pr2.id_pracoviste)
              FROM hr_pracoviste pr2
              WHERE pr2.id_person = hp.id_person
                AND pr2.hlavni = 1
                AND pr2.platny = 1
                AND (pr2.platnost_od IS NULL OR pr2.platnost_od <= CURDATE())
                AND (pr2.platnost_do IS NULL OR pr2.platnost_do >= CURDATE())
          )
        LEFT JOIN pobocka pob ON pob.id_pob = pracoviste.id_pob
        LEFT JOIN hr_zarazeni zarazeni
          ON zarazeni.id_zarazeni = (
              SELECT MAX(za2.id_zarazeni)
              FROM hr_zarazeni za2
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
    return $row;
}
