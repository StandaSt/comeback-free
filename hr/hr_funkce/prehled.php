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

    $result = $db->query("
        SELECT s.kod, COUNT(u.id_uchazec) AS cnt
        FROM hr_uchazec_stav s
        LEFT JOIN hr_uchazec u
            ON u.id_uchazec_stav = s.id_uchazec_stav
           AND u.aktivni = 1
        WHERE s.kod IN ('novy', 'v_procesu')
        GROUP BY s.kod
    ");
    while ($row = $result->fetch_assoc()) {
        $kod = (string)$row['kod'];
        if (array_key_exists($kod, $nabor)) {
            $nabor[$kod] = (int)$row['cnt'];
        }
    }
    $result->free();

    $zamestnanci = [
        'HPP' => 0,
        'DPC' => 0,
        'DPP' => 0,
    ];

    $result = $db->query("
        SELECT pvt.kod, COUNT(DISTINCT z.id_zamestnanec) AS cnt
        FROM hr_zamestnanec z
        INNER JOIN hr_pracovni_vztah pv
            ON pv.id_zamestnanec = z.id_zamestnanec
           AND pv.platny = 1
           AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni >= CURDATE())
        INNER JOIN hr_pracovni_vztah_typ pvt
            ON pvt.id_pracovni_vztah_typ = pv.id_pracovni_vztah_typ
        WHERE z.stav = 'aktivni'
          AND z.aktivni = 1
          AND pvt.kod IN ('HPP', 'DPC', 'DPP')
        GROUP BY pvt.kod
    ");
    while ($row = $result->fetch_assoc()) {
        $kod = (string)$row['kod'];
        if (array_key_exists($kod, $zamestnanci)) {
            $zamestnanci[$kod] = (int)$row['cnt'];
        }
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
