<?php
declare(strict_types=1);

/**
 * Nacte vsechny skupiny uchazecu pro stranku Nabor.
 */
function hr_nacti_nabor_prehled(mysqli $db): array
{
    return [
        'nove_dotazniky' => hr_nacti_uchazece_podle_stavu($db, ['novy']),
        'domluvene_pohovory' => hr_nacti_domluvene_pohovory($db),
        'ceka_na_vstupni_dotaznik' => hr_nacti_cekajici_vstupni_dotaznik($db),
        'ceka_na_smlouvu' => hr_nacti_uchazece_podle_stavu($db, ['dotaznik_vyplnen']),
    ];
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
 * Nacte aktivni uchazece podle kodu jejich stavu.
 */
function hr_nacti_uchazece_podle_stavu(mysqli $db, array $stavy): array
{
    $stavy = array_values(array_filter($stavy, static fn ($stav) => is_string($stav) && $stav !== ''));
    if ($stavy === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($stavy), '?'));
    $sql = "
        SELECT
            u.id_uchazec,
            u.jmeno,
            u.prijmeni,
            u.telefon,
            u.email,
            u.pracoviste_preference,
            u.zadano,
            u.posledni_aktivita,
            s.nazev AS stav_nazev,
            cs.slot AS pozice
        FROM hr_uchazec u
        INNER JOIN hr_uchazec_stav s
            ON s.id_uchazec_stav = u.id_uchazec_stav
        LEFT JOIN cis_slot cs
            ON cs.id_slot = u.id_slot
        WHERE u.aktivni = 1
          AND s.kod IN ({$placeholders})
        ORDER BY u.zadano DESC, u.id_uchazec DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param(str_repeat('s', count($stavy)), ...$stavy);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalizuj_radek_uchazece($row);
    }
    $stmt->close();

    return $rows;
}

/**
 * Nacte naplanovane pohovory, ktere jeste neprobehly a nejsou zrusene.
 */
function hr_nacti_domluvene_pohovory(mysqli $db): array
{
    $sql = "
        SELECT
            u.id_uchazec,
            u.jmeno,
            u.prijmeni,
            u.telefon,
            u.email,
            u.pracoviste_preference,
            u.zadano,
            u.posledni_aktivita,
            s.nazev AS stav_nazev,
            cs.slot AS pozice,
            a.planovano_na,
            a.predmet,
            at.nazev AS aktivita_typ
        FROM hr_uchazec_aktivita a
        INNER JOIN hr_uchazec_aktivita_typ at
            ON at.id_uchazec_aktivita_typ = a.id_uchazec_aktivita_typ
        INNER JOIN hr_uchazec u
            ON u.id_uchazec = a.id_uchazec
           AND u.aktivni = 1
        INNER JOIN hr_uchazec_stav s
            ON s.id_uchazec_stav = u.id_uchazec_stav
        LEFT JOIN cis_slot cs
            ON cs.id_slot = u.id_slot
        WHERE at.kod IN ('pohovor_telefon', 'pohovor_osobni', 'pohovor_online')
          AND a.planovano_na IS NOT NULL
          AND a.provedeno_kdy IS NULL
          AND a.zruseno IS NULL
        ORDER BY a.planovano_na ASC, a.id_uchazec_aktivita ASC
    ";

    $result = $db->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalizuj_radek_uchazece($row);
    }
    $result->free();

    return $rows;
}

/**
 * Nacte uchazece, u kterych cekame na vyplneni nastupniho dotazniku.
 */
function hr_nacti_cekajici_vstupni_dotaznik(mysqli $db): array
{
    $sql = "
        SELECT
            u.id_uchazec,
            u.jmeno,
            u.prijmeni,
            u.telefon,
            u.email,
            u.pracoviste_preference,
            u.zadano,
            u.posledni_aktivita,
            s.nazev AS stav_nazev,
            cs.slot AS pozice,
            d.odeslano,
            d.stav AS dotaznik_stav
        FROM hr_uchazec_dotaznik d
        INNER JOIN hr_dotaznik_typ dt
            ON dt.id_dotaznik_typ = d.id_dotaznik_typ
        INNER JOIN hr_uchazec u
            ON u.id_uchazec = d.id_uchazec
           AND u.aktivni = 1
        INNER JOIN hr_uchazec_stav s
            ON s.id_uchazec_stav = u.id_uchazec_stav
        LEFT JOIN cis_slot cs
            ON cs.id_slot = u.id_slot
        WHERE dt.kod = 'nastupni'
          AND d.stav IN ('pripraven', 'odeslan', 'otevren', 'rozpracovan')
        ORDER BY COALESCE(d.odeslano, d.zadano) DESC, d.id_uchazec_dotaznik DESC
    ";

    $result = $db->query($sql);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalizuj_radek_uchazece($row);
    }
    $result->free();

    return $rows;
}

/**
 * Doplni radek uchazece o hodnoty pripravene pro zobrazeni.
 */
function hr_normalizuj_radek_uchazece(array $row): array
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
    ];
}
