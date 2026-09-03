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

/**
 * Nacte posledni platne podminky domluvene s uchazecem.
 */
function hr_nacti_vd_podminky(mysqli $db, int $idVd): ?array
{
    if ($idVd <= 0) {
        return null;
    }

    $stmt = $db->prepare('
        SELECT p.*, vztah.nazev AS vztah_nazev
        FROM hr_vd_podminky p
        LEFT JOIN hr_cis_pracovni_vztah_typ vztah
            ON vztah.id_pracovni_vztah_typ = p.id_pracovni_vztah_typ
        WHERE p.id_vd = ?
          AND p.platny = 1
        ORDER BY p.id_vd_podminky DESC
        LIMIT 1
    ');
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $podminky = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($podminky)) {
        return null;
    }

    $pobockyMask = (int)($podminky['pobocky_mask'] ?? 0);
    $oblasti = [];
    $result = $db->query("SELECT id_pob, oblast FROM pobocka WHERE id_pob > 0 AND oblast <> '' ORDER BY CASE WHEN oblast = 'Praha' THEN 0 WHEN oblast = 'Plzeň' THEN 1 ELSE 2 END, oblast, id_pob");
    while ($row = $result->fetch_assoc()) {
        $idPob = (int)$row['id_pob'];
        if ($idPob <= 62 && ($pobockyMask & (1 << $idPob)) !== 0) {
            $oblasti[(string)$row['oblast']] = true;
        }
    }
    $result->free();

    $slotyMask = (int)($podminky['sloty_mask'] ?? 0);
    $pozice = [];
    $result = $db->query('SELECT id_slot, slot FROM cis_slot ORDER BY id_slot');
    while ($row = $result->fetch_assoc()) {
        $idSlot = (int)$row['id_slot'];
        if ($idSlot <= 62 && ($slotyMask & (1 << $idSlot)) !== 0) {
            $pozice[] = (string)$row['slot'];
        }
    }
    $result->free();

    $podminky['oblasti_label'] = $oblasti !== [] ? implode(', ', array_keys($oblasti)) : '-';
    $podminky['pozice_label'] = $pozice !== [] ? implode(', ', $pozice) : '-';

    return $podminky;
}
