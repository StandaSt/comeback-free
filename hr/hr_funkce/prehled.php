<?php
declare(strict_types=1);

/**
 * Nacte data pro hlavni prehled HR modulu.
 */
function hr_fetch_dashboard(mysqli $db): array
{
    $nabor = [
        'novy' => 0,
        'v_procesu' => 0,
    ];

    // Spocita verejne dotazniky podle pevnych ID stavu VD.
    $result = $db->query("
        SELECT
            SUM(CASE WHEN id_vd_stav = 1 THEN 1 ELSE 0 END) AS novy,
            SUM(CASE WHEN id_vd_stav IN (3,7,8,9,10) THEN 1 ELSE 0 END) AS v_procesu
        FROM hr_vd
        WHERE id_person IS NULL
          AND aktivni = 1
    ");
    if ($row = $result->fetch_assoc()) {
        $nabor['novy'] = (int)($row['novy'] ?? 0);
        $nabor['v_procesu'] = (int)($row['v_procesu'] ?? 0);
    }
    $result->free();

    $zamestnanci = [
        'HPP' => 0,
        'DPC' => 0,
        'DPP' => 0,
    ];

    // Spocita aktivni zamestnance podle pevnych ID typu pracovniho vztahu.
    $result = $db->query("
        SELECT
            COUNT(DISTINCT CASE WHEN pv.id_pracovni_vztah_typ = 1 THEN p.id_person END) AS HPP,
            COUNT(DISTINCT CASE WHEN pv.id_pracovni_vztah_typ = 3 THEN p.id_person END) AS DPC,
            COUNT(DISTINCT CASE WHEN pv.id_pracovni_vztah_typ = 2 THEN p.id_person END) AS DPP
        FROM hr_person p
        INNER JOIN hr_pracovni_vztah pv
            ON pv.id_person = p.id_person
           AND pv.platny = 1
           AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
        WHERE p.aktivni = 1
    ");
    if ($row = $result->fetch_assoc()) {
        $zamestnanci['HPP'] = (int)($row['HPP'] ?? 0);
        $zamestnanci['DPC'] = (int)($row['DPC'] ?? 0);
        $zamestnanci['DPP'] = (int)($row['DPP'] ?? 0);
    }
    $result->free();

    $kReseni = [
        'koncici_smlouvy' => 0,
        'zdravotni_prohlidky' => 0,
        'bozp' => 0,
    ];

    $result = $db->query("
        SELECT COUNT(*) AS cnt
        FROM hr_pracovni_vztah
        WHERE platny = 1
          AND datum_ukonceni IS NOT NULL
          AND datum_ukonceni BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    if ($row = $result->fetch_assoc()) {
        $kReseni['koncici_smlouvy'] = (int)$row['cnt'];
    }
    $result->free();

    return [
        'nabor' => $nabor,
        'zamestnanci' => $zamestnanci,
        'pozadavky' => [
            'celkem' => 0,
            'instor' => 0,
            'kuryr' => 0,
        ],
        'k_reseni' => $kReseni,
        'dokumenty' => hr_fetch_dashboard_documents($db, 5),
        'lekarske_prohlidky' => [],
        'skoleni' => [],
        'dovolene' => [],
        'latest' => hr_fetch_employees($db, 5),
    ];
}
