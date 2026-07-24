<?php
declare(strict_types=1);

/**
 * Nacte vsechny skupiny uchazecu pro stranku Nabor.
 */
function hr_nacti_nabor_prehled(mysqli $db): array
{
    return [
        'nepotvrzene_dotazniky' => hr_nacti_vd_podle_stavu($db, [0]),
        'nove_dotazniky' => hr_nacti_vd_podle_stavu($db, [1]),
        'domluvene_pohovory' => hr_nacti_domluvene_pohovory($db),
        'ceka_na_vstupni_dotaznik' => hr_nacti_cekajici_vstupni_dotaznik($db),
        'ceka_na_smlouvu' => hr_nacti_vd_podle_stavu($db, [9]),
    ];
}

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
 * Nacte historii akci k jednomu VD.
 */
function hr_nacti_vd_akce(mysqli $db, int $idVd): array
{
    if ($idVd <= 0) {
        return [];
    }

    $stmt = $db->prepare("
        SELECT
            a.id_vd_akce,
            a.id_vd_akce_typ,
            a.id_person_zadal,
            a.akce_kdy,
            a.poznamka,
            t.nazev AS akce_typ_nazev,
            ou.jmeno AS zadal_jmeno,
            ou.prijmeni AS zadal_prijmeni
        FROM hr_vd_akce a
        INNER JOIN hr_cis_vd_akce_typ t
            ON t.id_vd_akce_typ = a.id_vd_akce_typ
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = a.id_person_zadal
           AND ou.platny = 1
        WHERE a.id_vd = ?
        ORDER BY a.akce_kdy DESC, a.id_vd_akce DESC
    ");
    $stmt->bind_param('i', $idVd);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $zadal = trim((string)($row['zadal_prijmeni'] ?? '') . ' ' . (string)($row['zadal_jmeno'] ?? ''));
        $rows[] = $row + [
            'zadal_label' => $zadal !== '' ? $zadal : '-',
            'poznamka' => trim((string)($row['poznamka'] ?? '')) !== '' ? (string)$row['poznamka'] : '-',
        ];
    }
    $stmt->close();

    return $rows;
}

/**
 * Nacte aktivni stavy VD pro vyber ve formulari.
 */
function hr_nacti_vd_stavy(mysqli $db): array
{
    return hr_fetch_lookup($db, 'hr_cis_vd_stav', 'id_vd_stav', 'nazev', 'id_vd_stav');
}

/**
 * Nacte aktivni typy akci VD pro vyber ve formulari.
 */
function hr_nacti_vd_akce_typy(mysqli $db): array
{
    return hr_fetch_lookup($db, 'hr_cis_vd_akce_typ', 'id_vd_akce_typ', 'nazev', 'id_vd_akce_typ');
}

/**
 * Ulozi akci personalisty a nastavi aktualni stav VD.
 */
function hr_uloz_vd_akci(mysqli $db, int $idVd, int $idVdStav, int $idVdAkceTyp, string $akceKdy, string $poznamka, int $idPersonZadal): void
{
    if ($idVd <= 0 || $idVdStav <= 0 || $idVdAkceTyp <= 0) {
        throw new RuntimeException('Chybí povinné údaje pro uložení akce.');
    }
    if ($idPersonZadal <= 0) {
        throw new RuntimeException('Chybí HR osoba přihlášeného uživatele.');
    }
    if ($akceKdy === '' || strtotime($akceKdy) === false) {
        throw new RuntimeException('Vyplňte datum a čas akce.');
    }

    $akceKdyDb = date('Y-m-d H:i:s', strtotime($akceKdy));
    $poznamkaDb = trim($poznamka) !== '' ? trim($poznamka) : null;

    $db->begin_transaction();
    try {
        // Nastavi aktualni stav verejneho dotazniku.
        $stmt = $db->prepare("
            UPDATE hr_vd
            SET id_vd_stav = ?,
                upraveno = NOW()
            WHERE id_vd = ?
              AND aktivni = 1
        ");
        $stmt->bind_param('ii', $idVdStav, $idVd);
        $stmt->execute();
        if ($stmt->affected_rows < 1) {
            throw new RuntimeException('Veřejný dotazník nebyl nalezen.');
        }
        $stmt->close();

        // Zapise udalost do historie naboru.
        $stmt = $db->prepare("
            INSERT INTO hr_vd_akce (id_vd, id_vd_akce_typ, id_person_zadal, akce_kdy, poznamka)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiiss', $idVd, $idVdAkceTyp, $idPersonZadal, $akceKdyDb, $poznamkaDb);
        $stmt->execute();
        $stmt->close();

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * Vrati pocet uchazecu ve spravnem ceskem tvaru.
 */
function hr_pocet_uchazecu_text(int $pocet): string
{
    if ($pocet === 1) {
        return '1 uchazeč';
    }

    if ($pocet >= 2 && $pocet <= 4) {
        return $pocet . ' uchazeči';
    }

    return $pocet . ' uchazečů';
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
          AND vd.id_vd_stav = 3
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
          AND vd.id_vd_stav = 7
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

/**
 * Doplni radek VD o hodnoty pripravene pro zobrazeni.
 */
function hr_normalizuj_radek_vd(array $row): array
{
    $jmeno = trim((string)($row['jmeno'] ?? ''));
    $prijmeni = trim((string)($row['prijmeni'] ?? ''));
    $celeJmeno = trim($prijmeni . ' ' . $jmeno);

    return $row + [
        'cele_jmeno' => $celeJmeno !== '' ? $celeJmeno : 'Bez jména',
        'pozice' => trim((string)($row['pozice'] ?? '')) !== '' ? (string)$row['pozice'] : '-',
        'pracoviste_preference' => trim((string)($row['pracoviste_preference'] ?? '')) !== '' ? (string)$row['pracoviste_preference'] : '-',
        'telefon' => trim((string)($row['telefon'] ?? '')) !== '' ? (string)$row['telefon'] : '-',
        'email' => trim((string)($row['email'] ?? '')) !== '' ? (string)$row['email'] : '-',
        'stav_nazev' => trim((string)($row['stav_nazev'] ?? '')) !== '' ? (string)$row['stav_nazev'] : '-',
        'zdroj_nazev' => trim((string)($row['zdroj_nazev'] ?? '')) !== '' ? (string)$row['zdroj_nazev'] : '-',
        'planovano_na' => (string)($row['planovano_na'] ?? ''),
        'odeslano' => (string)($row['odeslano'] ?? ''),
        'posledni_aktivita' => (string)($row['posledni_aktivita'] ?? ''),
    ];
}
