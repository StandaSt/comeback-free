<?php
declare(strict_types=1);

/**
 * Nacte seznam aktivnich zamestnancu z jednotne tabulky osob.
 */
function hr_fetch_employees(mysqli $db, int $limit = 100): array
{
    $limit = max(1, min($limit, 500));
    $sql = "
        SELECT
            p.id_person,
            p.osobni_cislo,
            CASE p.vztah
                WHEN 2 THEN 'aktivni'
                WHEN 3 THEN 'ukonceny'
                ELSE 'priprava'
            END AS stav,
            p.zadano,
            p.jmeno,
            p.prijmeni,
            pv.datum_nastupu,
            pob.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.kod AS vztah_kod
        FROM hr_person p
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_person = p.id_person
           AND pv.platny = 1
           AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
        LEFT JOIN hr_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_person_pracoviste pp
            ON pp.id_person = p.id_person
           AND pp.platny = 1
           AND pp.hlavni = 1
        LEFT JOIN pobocka pob
            ON pob.id_pob = pp.id_pob
        LEFT JOIN hr_person_zarazeni pz
            ON pz.id_person = p.id_person
           AND pz.platny = 1
           AND pz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = pz.id_slot
        WHERE p.vztah = 2
          AND p.aktivni = 1
        ORDER BY p.id_person DESC
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
 * Nacte detail jednoho zamestnance podle id_person.
 */
function hr_fetch_employee(mysqli $db, int $id): ?array
{
    $sql = "
        SELECT
            p.id_person,
            p.osobni_cislo,
            CASE p.vztah
                WHEN 2 THEN 'aktivni'
                WHEN 3 THEN 'ukonceny'
                ELSE 'priprava'
            END AS stav,
            p.zadano,
            p.jmeno,
            p.prijmeni,
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
            pob.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.kod AS vztah_kod,
            pvt.nazev AS vztah_nazev,
            tel.telefon,
            em.email
        FROM hr_person p
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = p.id_person
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_person = p.id_person
           AND pv.platny = 1
        LEFT JOIN hr_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_person_pracoviste pp
            ON pp.id_person = p.id_person
           AND pp.platny = 1
           AND pp.hlavni = 1
        LEFT JOIN pobocka pob
            ON pob.id_pob = pp.id_pob
        LEFT JOIN hr_person_zarazeni pz
            ON pz.id_person = p.id_person
           AND pz.platny = 1
           AND pz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = pz.id_slot
        LEFT JOIN hr_telefon tel
            ON tel.id_person = p.id_person
           AND tel.platny = 1
           AND tel.hlavni = 1
        LEFT JOIN hr_email em
            ON em.id_person = p.id_person
           AND em.platny = 1
           AND em.hlavni = 1
        WHERE p.id_person = ?
          AND p.vztah = 2
          AND p.aktivni = 1
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
