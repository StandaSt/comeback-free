<?php
declare(strict_types=1);

/**
 * Nacte seznam zamestnancu pro HR vypisy.
 */
function hr_fetch_employees(mysqli $db, int $limit = 100): array
{
    $limit = max(1, min($limit, 500));
    $sql = "
        SELECT
            z.id_zamestnanec,
            z.osobni_cislo,
            z.stav,
            z.zadano,
            ou.jmeno,
            ou.prijmeni,
            pv.datum_nastupu,
            p.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.kod AS vztah_kod
        FROM hr_zamestnanec z
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_zamestnanec = z.id_zamestnanec
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_zamestnanec = z.id_zamestnanec
           AND pv.platny = 1
        LEFT JOIN hr_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_zamestnanec_pracoviste zp
            ON zp.id_zamestnanec = z.id_zamestnanec
           AND zp.platny = 1
           AND zp.hlavni = 1
        LEFT JOIN pobocka p
            ON p.id_pob = zp.id_pob
        LEFT JOIN hr_zamestnanec_zarazeni zz
            ON zz.id_zamestnanec = z.id_zamestnanec
           AND zz.platny = 1
           AND zz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = zz.id_slot
        ORDER BY z.id_zamestnanec DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = hr_normalize_employee_row($row);
    }
    $stmt->close();

    return $rows;
}

/**
 * Nacte detail jednoho zamestnance podle ID.
 */
function hr_fetch_employee(mysqli $db, int $id): ?array
{
    $sql = "
        SELECT
            z.id_zamestnanec,
            z.osobni_cislo,
            z.stav,
            z.zadano,
            ou.jmeno,
            ou.prijmeni,
            ou.datum_narozeni,
            ou.rodne_cislo,
            ou.pohlavi,
            ou.statni_obcanstvi,
            ou.misto_narozeni,
            pv.datum_nastupu,
            pv.datum_ukonceni,
            pv.uvazek,
            pv.hodin_tydne,
            pv.doba_urcita,
            pv.delka_zk_doby,
            p.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.kod AS vztah_kod,
            pvt.nazev AS vztah_nazev,
            tel.telefon,
            em.email
        FROM hr_zamestnanec z
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_zamestnanec = z.id_zamestnanec
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_zamestnanec = z.id_zamestnanec
           AND pv.platny = 1
        LEFT JOIN hr_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_zamestnanec_pracoviste zp
            ON zp.id_zamestnanec = z.id_zamestnanec
           AND zp.platny = 1
           AND zp.hlavni = 1
        LEFT JOIN pobocka p
            ON p.id_pob = zp.id_pob
        LEFT JOIN hr_zamestnanec_zarazeni zz
            ON zz.id_zamestnanec = z.id_zamestnanec
           AND zz.platny = 1
           AND zz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = zz.id_slot
        LEFT JOIN hr_telefon tel
            ON tel.id_zamestnanec = z.id_zamestnanec
           AND tel.platny = 1
           AND tel.hlavni = 1
        LEFT JOIN hr_email em
            ON em.id_zamestnanec = z.id_zamestnanec
           AND em.platny = 1
           AND em.hlavni = 1
        WHERE z.id_zamestnanec = ?
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? hr_normalize_employee_row($row) : null;
}

/**
 * Doplni radek zamestnance o hodnoty pripravene pro zobrazeni.
 */
function hr_normalize_employee_row(array $row): array
{
    $jmeno = trim((string)($row['jmeno'] ?? ''));
    $prijmeni = trim((string)($row['prijmeni'] ?? ''));
    $fullName = trim($prijmeni . ' ' . $jmeno);

    return $row + [
        'cele_jmeno' => $fullName !== '' ? $fullName : 'Bez jména',
        'inicialy' => hr_initials($jmeno, $prijmeni),
        'stav_label' => hr_stav_label((string)($row['stav'] ?? '')),
        'stav_badge' => ((string)($row['stav'] ?? '')) === 'aktivni' ? 'success' : 'neutral',
    ];
}

/**
 * Vytvori inicialy ze jmena a prijmeni.
 */
function hr_initials(string $jmeno, string $prijmeni): string
{
    $a = mb_substr(trim($jmeno), 0, 1);
    $b = mb_substr(trim($prijmeni), 0, 1);
    $out = mb_strtoupper($a . $b);
    return $out !== '' ? $out : '?';
}

/**
 * Prevede interni stav zamestnance na text pro zobrazeni.
 */
function hr_stav_label(string $stav): string
{
    return match ($stav) {
        'aktivni' => 'Aktivní',
        'preruseny' => 'Přerušený',
        'ukonceny' => 'Ukončený',
        default => 'Příprava',
    };
}
