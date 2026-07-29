<?php
declare(strict_types=1);

/**
 * DB dotaz pro detail jednoho verejneho dotazniku.
 */

/**
 * Nacte jeden verejny dotaznik pro detail naboru.
 */
function hr_nacti_vd_detail(mysqli $db, int $idVd): ?array
{
    if ($idVd <= 0) {
        return null;
    }

    $stmt = $db->prepare("
        SELECT
            vd.id_vd,
            vd.id_vd_stav,
            vd.id_vd_zdroj,
            vd.id_person,
            vd.jmeno,
            vd.prijmeni,
            vd.telefon,
            vd.email,
            vd.id_slot,
            vd.pracoviste_preference,
            vd.mozny_nastup,
            vd.ocekavana_mzda,
            vd.povidani,
            vd.odeslano AS zadano,
            vd.upraveno,
            s.nazev AS stav_nazev,
            z.nazev AS zdroj_nazev,
            cs.slot AS pozice
        FROM hr_vd vd
        INNER JOIN hr_cis_vd_stav s
            ON s.id_vd_stav = vd.id_vd_stav
        LEFT JOIN hr_cis_vd_zdroj z
            ON z.id_vd_zdroj = vd.id_vd_zdroj
        LEFT JOIN cis_slot cs
            ON cs.id_slot = vd.id_slot
        WHERE vd.id_vd = ?
          AND vd.aktivni = 1
        LIMIT 1
    ");
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? hr_normalizuj_radek_vd($row) : null;
}
