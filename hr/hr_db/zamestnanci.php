<?php
declare(strict_types=1);

/**
 * DB dotazy pro seznam a detail zamestnancu v HR.
 */

/**
 * Nacte seznam aktivnich zamestnancu.
 */
function hr_fetch_employees(mysqli $db, int $limit = 100): array
{
    $limit = max(1, min($limit, 500));
    $sql = "
        SELECT
            p.id_person,
            p.osobni_cislo,
            p.overen,
            p.kompletni,
            CASE
                WHEN pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE() THEN 'aktivni'
                ELSE 'ukonceny'
            END AS stav,
            p.vytvoreno AS zadano,
            ou.jmeno,
            ou.druhe_jmeno,
            ou.prijmeni,
            pv.datum_nastupu,
            pv.id_pracovni_vztah,
            pob.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.nazev AS vztah_kod
        FROM hr_person p
        LEFT JOIN hr_osobni_udaje ou
            ON ou.id_person = p.id_person
           AND ou.platny = 1
        LEFT JOIN hr_pracovni_vztah pv
            ON pv.id_person = p.id_person
           AND pv.platny = 1
           AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
        LEFT JOIN hr_cis_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_pracoviste pp
            ON pp.id_person = p.id_person
           AND pp.platny = 1
           AND pp.hlavni = 1
        LEFT JOIN pobocka pob
            ON pob.id_pob = pp.id_pob
        LEFT JOIN hr_zarazeni pz
            ON pz.id_person = p.id_person
           AND pz.platny = 1
           AND pz.hlavni = 1
        LEFT JOIN cis_slot cs
            ON cs.id_slot = pz.id_slot
        WHERE p.aktivni = 1
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
 * Nacte aktualni pracovni pomer vcetne uvazku a mzdy.
 */
function hr_fetch_employee_work_relation(mysqli $db, int $idPerson): ?array
{
    $sql = '
        SELECT
            pv.id_pracovni_vztah,
            pv.id_pracovni_vztah_typ,
            pvt.nazev AS vztah,
            pv.datum_nastupu,
            pv.datum_ukonceni,
            u.id_pracovni_uvazek,
            u.uvazek,
            u.hodin_tydne,
            m.id_mzda,
            m.id_mzda_typ,
            m.castka AS mzda_castka
        FROM hr_pracovni_vztah pv
        INNER JOIN hr_cis_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_pracovni_uvazek u
            ON u.id_pracovni_vztah = pv.id_pracovni_vztah
           AND u.platny = 1
        LEFT JOIN hr_mzda m
            ON m.id_pracovni_vztah = pv.id_pracovni_vztah
           AND m.platny = 1
        WHERE pv.id_person = ?
          AND pv.platny = 1
        ORDER BY pv.id_pracovni_vztah DESC, u.id_pracovni_uvazek DESC, m.id_mzda DESC
        LIMIT 1
    ';

    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return is_array($row) ? $row : null;
}

/**
 * Nacte identifikatory aktualnich benefitu jednoho pracovniho vztahu.
 */
function hr_fetch_employee_work_benefit_ids(mysqli $db, int $idPracovniVztah): array
{
    $stmt = $db->prepare('SELECT id_cis_benefit FROM hr_benefit WHERE id_pracovni_vztah = ? AND platny = 1 ORDER BY id_cis_benefit');
    $stmt->bind_param('i', $idPracovniVztah);
    $stmt->execute();
    $result = $stmt->get_result();
    $ids = [];
    while ($row = $result->fetch_assoc()) {
        $ids[] = (int)$row['id_cis_benefit'];
    }
    $stmt->close();

    return $ids;
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
            p.overen,
            p.kompletni,
            CASE
                WHEN pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE() THEN 'aktivni'
                ELSE 'ukonceny'
            END AS stav,
            p.vytvoreno AS zadano,
            ou.jmeno,
            ou.druhe_jmeno,
            ou.prijmeni,
            ou.titul_pred,
            ou.titul_za,
            ou.datum_narozeni,
            ou.rodne_cislo,
            ou.pohlavi,
            ou.zdr_poj,
            ou.statni_obcanstvi,
            ou.misto_narozeni,
            pv.datum_nastupu,
            pv.datum_ukonceni,
            pob.nazev AS pracoviste,
            cs.slot AS zarazeni,
            pvt.nazev AS vztah_kod,
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
        LEFT JOIN hr_cis_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        LEFT JOIN hr_pracoviste pp
            ON pp.id_person = p.id_person
           AND pp.platny = 1
           AND pp.hlavni = 1
        LEFT JOIN pobocka pob
            ON pob.id_pob = pp.id_pob
        LEFT JOIN hr_zarazeni pz
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
 * Nacte aktualni doplnkove udaje pro editaci karty zamestnance.
 */
function hr_fetch_employee_edit_data(mysqli $db, int $idPerson): array
{
    $queries = [
        'adresa_op' => 'SELECT ulice, cp, mesto, psc, stat FROM hr_adresa WHERE id_person = ? AND typ = 0 AND platny = 1 ORDER BY id_adresa DESC LIMIT 1',
        'adresa_dorucovaci' => 'SELECT ulice, cp, mesto, psc, stat FROM hr_adresa WHERE id_person = ? AND typ = 1 AND platny = 1 ORDER BY id_adresa DESC LIMIT 1',
        'nouzovy_kontakt' => 'SELECT jmeno, vztah, telefon, email FROM hr_nouzovy_kontakt WHERE id_person = ? AND platny = 1 AND hlavni = 1 ORDER BY id_nouzovy_kontakt DESC LIMIT 1',
        'bankovni_ucet' => 'SELECT cislo_uctu, kod_banky, iban FROM hr_bankovni_ucet WHERE id_person = ? AND platny = 1 ORDER BY zmena DESC, id_bankovni_ucet DESC LIMIT 1',
    ];
    $data = [];
    foreach ($queries as $key => $sql) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $data[$key] = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    }
    return $data;
}

/**
 * Doplni radek zamestnance o hodnoty pripravene pro zobrazeni.
 */
function hr_normalize_employee_row(array $row): array
{
    $jmeno = trim((string)($row['jmeno'] ?? ''));
    $druheJmeno = trim((string)($row['druhe_jmeno'] ?? ''));
    $prijmeni = trim((string)($row['prijmeni'] ?? ''));
    $fullName = trim($prijmeni . ' ' . trim($jmeno . ' ' . $druheJmeno));

    return $row + [
        'cele_jmeno' => $fullName !== '' ? $fullName : 'Bez jména',
        'inicialy' => hr_initials($jmeno, $prijmeni),
        'stav_label' => hr_stav_label((string)($row['stav'] ?? '')),
        'stav_badge' => ((string)($row['stav'] ?? '')) === 'aktivni' ? 'hr_success' : 'hr_neutral',
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
