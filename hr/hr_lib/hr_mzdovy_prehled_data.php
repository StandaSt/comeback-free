<?php
declare(strict_types=1);

/*
 * Data první čtecí verze mzdového přehledu. Všechny hodnoty jsou načítané
 * z existující evidence HR a z finálně uložených denních reportů IS.
 * Tato vrstva neprovádí uzávěrku, nepřijímá ruční bonusy ani do DB nezapisuje.
 */

function hr_mzdovy_prehled_nazev_firmy(mysqli $db): string
{
    $result = $db->query("SELECT GROUP_CONCAT(obchodni_jmeno ORDER BY id_firma SEPARATOR ' · ') AS obchodni_jmeno FROM firma WHERE aktivni = 1 AND platnost_do IS NULL");
    if (!$result instanceof mysqli_result) {
        return '';
    }

    $row = $result->fetch_assoc();
    return trim((string)($row['obchodni_jmeno'] ?? ''));
}

function hr_mzdovy_prehled_data(mysqli $db, array $request): array
{
    $rawPeriod = trim((string)($request['mzd_obdobi'] ?? ''));
    if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $rawPeriod) !== 1) {
        $rawPeriod = (new DateTimeImmutable('first day of last month'))->format('Y-m');
    }

    $periodStart = DateTimeImmutable::createFromFormat('!Y-m-d', $rawPeriod . '-01');
    if (!$periodStart instanceof DateTimeImmutable) {
        throw new RuntimeException('Neplatné mzdové období.');
    }
    $periodEnd = $periodStart->modify('last day of this month');
    $from = $periodStart->format('Y-m-d');
    $to = $periodEnd->format('Y-m-d');

    $sql = '
        SELECT
            hp.id_person,
            u.jmeno,
            u.prijmeni,
            TRIM(CONCAT(COALESCE(u.prijmeni, ""), " ", COALESCE(u.jmeno, ""))) AS cele_jmeno,
            (
                SELECT CASE WHEN prac.id_pob = 0 THEN \'Výroba\' ELSE p.nazev END
                FROM hr_pracoviste prac
                LEFT JOIN pobocka p ON p.id_pob = prac.id_pob
                WHERE prac.id_person = hp.id_person AND prac.platny = 1
                  AND (prac.platnost_od IS NULL OR prac.platnost_od <= ?)
                  AND (prac.platnost_do IS NULL OR prac.platnost_do >= ?)
                ORDER BY prac.hlavni DESC, COALESCE(prac.platnost_od, \'1000-01-01\') DESC, prac.id_pracoviste DESC
                LIMIT 1
            ) AS pobocka,
            (
                SELECT prac.id_pob
                FROM hr_pracoviste prac
                WHERE prac.id_person = hp.id_person AND prac.platny = 1
                  AND (prac.platnost_od IS NULL OR prac.platnost_od <= ?)
                  AND (prac.platnost_do IS NULL OR prac.platnost_do >= ?)
                ORDER BY prac.hlavni DESC, COALESCE(prac.platnost_od, \'1000-01-01\') DESC, prac.id_pracoviste DESC
                LIMIT 1
            ) AS id_pob,
            (
                SELECT cs.slot
                FROM hr_zarazeni z
                INNER JOIN cis_slot cs ON cs.id_slot = z.id_slot
                WHERE z.id_person = hp.id_person AND z.platny = 1
                  AND (z.platnost_od IS NULL OR z.platnost_od <= ?)
                  AND (z.platnost_do IS NULL OR z.platnost_do >= ?)
                ORDER BY z.hlavni DESC, COALESCE(z.platnost_od, \'1000-01-01\') DESC, z.id_zarazeni DESC
                LIMIT 1
            ) AS zarazeni,
            (
                SELECT pvt.nazev
                FROM hr_pracovni_vztah pv
                INNER JOIN hr_cis_pracovni_vztah_typ pvt ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
                WHERE pv.id_person = hp.id_person AND pv.platny = 1
                  AND pv.datum_nastupu <= ?
                  AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= ?)
                ORDER BY pv.datum_nastupu DESC, pv.id_pracovni_vztah DESC
                LIMIT 1
            ) AS pracovni_vztah,
            (
                SELECT u.uvazek
                FROM hr_pracovni_uvazek u
                INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = u.id_pracovni_vztah
                WHERE pv.id_person = hp.id_person AND pv.platny = 1 AND u.platny = 1
                  AND (u.platnost_od IS NULL OR u.platnost_od <= ?)
                  AND (u.platnost_do IS NULL OR u.platnost_do >= ?)
                ORDER BY u.platnost_od DESC, u.id_pracovni_uvazek DESC
                LIMIT 1
            ) AS uvazek,
            (
                SELECT mt.nazev
                FROM hr_mzda m
                INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = m.id_pracovni_vztah
                INNER JOIN cis_mzda_typ mt ON mt.id_mzda_typ = m.id_mzda_typ
                WHERE pv.id_person = hp.id_person AND pv.platny = 1 AND m.platny = 1
                  AND (m.platnost_od IS NULL OR m.platnost_od <= ?)
                  AND (m.platnost_do IS NULL OR m.platnost_do >= ?)
                ORDER BY m.platnost_od DESC, m.id_mzda DESC
                LIMIT 1
            ) AS mzda_typ,
            (
                SELECT m.castka
                FROM hr_mzda m
                INNER JOIN hr_pracovni_vztah pv ON pv.id_pracovni_vztah = m.id_pracovni_vztah
                WHERE pv.id_person = hp.id_person AND pv.platny = 1 AND m.platny = 1
                  AND (m.platnost_od IS NULL OR m.platnost_od <= ?)
                  AND (m.platnost_do IS NULL OR m.platnost_do >= ?)
                ORDER BY m.platnost_od DESC, m.id_mzda DESC
                LIMIT 1
            ) AS mzda_castka,
            COALESCE(hodiny.odpracovano, 0) AS odpracovano
        FROM hr_person hp
        INNER JOIN user u ON u.id_user = hp.id_user
        LEFT JOIN (
            SELECT ro.id_user, SUM(ro.odpracovano) AS odpracovano
            FROM reporty_is_osoby ro
            INNER JOIN reporty_is r ON r.id_reportu = ro.id_reportu
            WHERE r.platny = 1 AND r.datum_reportu BETWEEN ? AND ?
            GROUP BY ro.id_user
        ) hodiny ON hodiny.id_user = hp.id_user
        INNER JOIN firma f ON f.id_firma = hp.id_firma AND f.aktivni = 1 AND f.platnost_do IS NULL
        WHERE u.aktivni = 1
          AND EXISTS (
              SELECT 1 FROM hr_pracovni_vztah pv
              WHERE pv.id_person = hp.id_person AND pv.platny = 1
                AND pv.datum_nastupu <= ?
                AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= ?)
          )
        ORDER BY u.prijmeni ASC, u.jmeno ASC, hp.id_person ASC
    ';

    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        throw new RuntimeException('Mzdový přehled se nepodařilo připravit.');
    }
    $stmt->bind_param(
        'ssssssssssssssssss',
        $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $from, $to, $to, $from
    );
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['cele_jmeno'] = trim((string)($row['cele_jmeno'] ?? ''));
        if ($row['cele_jmeno'] === '') {
            $row['cele_jmeno'] = 'Zaměstnanec #' . (string)(int)$row['id_person'];
        }
        $row['odpracovano'] = (float)($row['odpracovano'] ?? 0);
        $rows[] = $row;
    }
    $stmt->close();

    $branches = [];
    $result = $db->query('SELECT p.id_pob, p.nazev, f.obchodni_jmeno AS firma FROM pobocka p INNER JOIN firma f ON f.id_firma = p.id_firma WHERE p.aktivni = 1 AND f.aktivni = 1 AND f.platnost_do IS NULL ORDER BY p.id_pob');
    while ($branch = $result->fetch_assoc()) {
        $branches[] = [
            'id_pob' => (int)$branch['id_pob'],
            'name' => (string)$branch['nazev'],
            'firma' => (string)$branch['firma'],
        ];
    }
    $result->free();

    $selectedBranch = trim((string)($request['mzd_pobocka'] ?? ''));
    $branchIds = array_map(static fn(array $branch): string => (string)$branch['id_pob'], $branches);
    if ($selectedBranch !== '' && !in_array($selectedBranch, $branchIds, true)) {
        $selectedBranch = '';
    }
    if ($selectedBranch !== '') {
        $rows = array_values(array_filter(
            $rows,
            static fn(array $row): bool => (string)($row['id_pob'] ?? '') === $selectedBranch
        ));
    }

    $totalHours = 0.0;
    foreach ($rows as $row) {
        $totalHours += (float)$row['odpracovano'];
    }

    return [
        'period' => $rawPeriod,
        'period_label' => $periodStart->format('m/Y'),
        'branches' => $branches,
        'branch' => $selectedBranch,
        'rows' => $rows,
        'total_hours' => $totalHours,
    ];
}
