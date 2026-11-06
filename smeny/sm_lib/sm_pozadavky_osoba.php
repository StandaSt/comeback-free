<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

// Ucel: Dostupnost patri osobe; MAIN slouzi pouze ke kontrole kolizi HPP volna.
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';
require_once __DIR__ . '/sm_pozadavky_sloty.php';

/*
 * Nacte osobu, rovnocenne sloty a casovou mnozinu MAIN bez volby pobocky z GET nebo POST.
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

    $sql = '
        SELECT
            hp.id_person,
            hp.id_firma,
            ' . cb_osoba_jmeno_sql('ou.id_person',true) . ' AS jmeno,
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

    foreach (['id_person', 'id_firma', 'je_hpp'] as $key) {
        $row[$key] = (int)($row[$key] ?? 0);
    }
    // Testovani prav ponecha sloty zdrojoveho profilu, osobu a autora zadani nadale urcuje volajici.
    $row['id_person_sloty']=$row['id_person'];
    $row['sloty']=cb_smeny_pozadavky_sloty($db,$row['id_person']);
    $row['main_pobocky']=cb_smeny_pozadavky_main($db,$row['id_person']);
    return $row;
}
