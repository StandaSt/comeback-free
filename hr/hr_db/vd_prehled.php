<?php
declare(strict_types=1);

/**
 * DB dotazy pro prehledove bloky verejnych dotazniku v naboru.
 */

/**
 * Nacte vsechny skupiny uchazecu pro stranku Nabor.
 */
function hr_nacti_nabor_prehled(mysqli $db): array
{
    return [
        'nepotvrzene_dotazniky' => hr_nacti_vd_podle_stavu($db, [HR_VD_STAV_NEPOTVRZENO]),
        'nove_dotazniky' => hr_nacti_vd_podle_stavu($db, [
            HR_VD_STAV_NOVY,
            HR_VD_STAV_POHOVOR_POZDEJI,
            HR_VD_STAV_NELZE_SE_DOVOLAT,
        ]),
        'domluvene_pohovory' => hr_nacti_domluvene_pohovory($db),
        'ceka_na_vstupni_dotaznik' => hr_nacti_cekajici_vstupni_dotaznik($db),
        'ceka_na_smlouvu' => hr_nacti_vd_podle_stavu($db, [HR_VD_STAV_SMLUVA_ODESLANA]),
        'expirovane_dotazniky' => hr_nacti_vd_podle_stavu($db, [HR_VD_STAV_VD_NEPOTVRZENO]),
    ];
}

/**
 * Nacte aktivni VD podle ID stavu.
 */
function hr_nacti_vd_podle_stavu(mysqli $db, array $stavy): array
{
    $stavy = array_values(array_filter($stavy, static fn ($stav) => is_int($stav) && $stav >= 0));
    if ($stavy === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($stavy), '?'));
    $sql = "
        SELECT
            vd.id_vd,
            vd.jmeno,
            vd.prijmeni,
            vd.telefon,
            vd.email,
            vd.pracoviste_preference,
            vd.odeslano AS zadano,
            MAX(t.platnost_do) AS platnost_do,
            COALESCE(MAX(a.akce_kdy), vd.upraveno, vd.odeslano) AS posledni_aktivita,
            COALESCE(s.nazev, CASE WHEN vd.id_vd_stav = 0 THEN 'Nepotvrzeno' ELSE '-' END) AS stav_nazev,
            cs.slot AS pozice
        FROM hr_vd vd
        LEFT JOIN hr_cis_vd_stav s
            ON s.id_vd_stav = vd.id_vd_stav
        LEFT JOIN cis_slot cs
            ON cs.id_slot = vd.id_slot
        LEFT JOIN hr_vd_akce a
            ON a.id_vd = vd.id_vd
        LEFT JOIN hr_vd_token t
            ON t.id_vd = vd.id_vd
           AND t.aktivni = 1
           AND t.pouzito IS NULL
        WHERE vd.aktivni = 1
          AND vd.id_vd_stav IN ({$placeholders})
        GROUP BY
            vd.id_vd,
            vd.jmeno,
            vd.prijmeni,
            vd.telefon,
            vd.email,
            vd.pracoviste_preference,
            vd.id_vd_stav,
            vd.odeslano,
            vd.upraveno,
            s.nazev,
            cs.slot
        ORDER BY vd.odeslano DESC, vd.id_vd DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($stavy)), ...$stavy);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalizuj_radek_vd($row);
    }
    $stmt->close();

    return $rows;
}

/**
 * Nacte VD s domluvenym pohovorem.
 */
function hr_nacti_domluvene_pohovory(mysqli $db): array
{
    $sql = "
        SELECT
            vd.id_vd,
            vd.jmeno,
            vd.prijmeni,
            vd.telefon,
            vd.email,
            vd.pracoviste_preference,
            vd.odeslano AS zadano,
            COALESCE(MAX(a.akce_kdy), vd.upraveno, vd.odeslano) AS posledni_aktivita,
            MAX(CASE WHEN a.id_vd_akce_typ = 3 THEN a.akce_kdy ELSE NULL END) AS planovano_na,
            s.nazev AS stav_nazev,
            cs.slot AS pozice
        FROM hr_vd vd
        INNER JOIN hr_cis_vd_stav s
            ON s.id_vd_stav = vd.id_vd_stav
        LEFT JOIN cis_slot cs
            ON cs.id_slot = vd.id_slot
        LEFT JOIN hr_vd_akce a
            ON a.id_vd = vd.id_vd
        WHERE vd.aktivni = 1
          AND vd.id_vd_stav = " . HR_VD_STAV_POHOVOR_DOMLUVEN . "
        GROUP BY
            vd.id_vd,
            vd.jmeno,
            vd.prijmeni,
            vd.telefon,
            vd.email,
            vd.pracoviste_preference,
            vd.odeslano,
            vd.upraveno,
            s.nazev,
            cs.slot
        ORDER BY planovano_na ASC, vd.id_vd ASC
    ";

    $result = $db->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalizuj_radek_vd($row);
    }
    $result->free();

    return $rows;
}

/**
 * Nacte VD, u kterych cekame na vyplneni nastupniho dotazniku.
 */
function hr_nacti_cekajici_vstupni_dotaznik(mysqli $db): array
{
    $sql = "
        SELECT
            vd.id_vd,
            vd.jmeno,
            vd.prijmeni,
            vd.telefon,
            vd.email,
            vd.pracoviste_preference,
            vd.odeslano AS zadano,
            COALESCE(MAX(a.akce_kdy), vd.upraveno, vd.odeslano) AS posledni_aktivita,
            MAX(CASE WHEN a.id_vd_akce_typ = 7 THEN a.akce_kdy ELSE NULL END) AS odeslano,
            s.nazev AS stav_nazev,
            cs.slot AS pozice
        FROM hr_vd vd
        INNER JOIN hr_cis_vd_stav s
            ON s.id_vd_stav = vd.id_vd_stav
        LEFT JOIN cis_slot cs
            ON cs.id_slot = vd.id_slot
        LEFT JOIN hr_vd_akce a
            ON a.id_vd = vd.id_vd
        WHERE vd.aktivni = 1
          AND vd.id_vd_stav = " . HR_VD_STAV_NASTUPNI_DOTAZNIK_ODESLAN . "
        GROUP BY
            vd.id_vd,
            vd.jmeno,
            vd.prijmeni,
            vd.telefon,
            vd.email,
            vd.pracoviste_preference,
            vd.odeslano,
            vd.upraveno,
            s.nazev,
            cs.slot
        ORDER BY odeslano DESC, vd.id_vd DESC
    ";

    $result = $db->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalizuj_radek_vd($row);
    }
    $result->free();

    return $rows;
}
