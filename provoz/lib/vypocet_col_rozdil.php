<?php
// Pocita rozdil pokladny a COL; fixni mzdy zahrnuje denne podle MAIN pobocky i bez smeny.
declare(strict_types=1);

require_once __DIR__ . '/report_promenne.php';
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

function cb_vcr_float(mixed $value): float
{
    if ($value === null || $value === '') {
        return 0.0;
    }

    $raw = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], (string)$value);
    $clean = preg_replace('/[^0-9.\-]/', '', $raw);
    if ($clean === null || $clean === '' || $clean === '-' || $clean === '.') {
        return 0.0;
    }

    return (float)$clean;
}

function cb_vcr_money(array $row, string $key): float
{
    return array_key_exists($key, $row) ? cb_vcr_float($row[$key]) : 0.0;
}

function cb_vcr_has_cash_values(array $cash): bool
{
    return array_key_exists('hotovost', $cash)
        && $cash['hotovost'] !== null
        && array_key_exists('terminal', $cash)
        && $cash['terminal'] !== null
        && array_key_exists('stravenky', $cash)
        && $cash['stravenky'] !== null;
}

function cb_vcr_rozdil(array $restiaSummary, array $cash): ?float
{
    if (!cb_vcr_has_cash_values($cash)) {
        return null;
    }

    /*
     * Rozdil ma odpovidat historickemu vypoctu ze starych reportu.
     *
     * Logika je zamerne jednoducha:
     * 1. Secteme prijmy, ktere maji realne "sedet" proti pokladne:
     *    - online kanaly Wolt, Bolt, DJ a web
     *    - Wolt drive cash
     *    - hotovost, terminal a stravenky z pokladny
     * 2. K tomu pricteme vydaje z pokladny.
     * 3. Nakonec odecteme celkovou trzbu z Restie.
     *
     * Dulezite:
     * - wolt_cash se do rozdilu zapocitava, protoze tak odpovidaji stare reporty
     * - dj_cash se sem naopak zamerne nepridava, protoze ve starych reportech
     *   se do rozdilu stejnym zpusobem nepromital
     */
    $income = cb_vcr_money($restiaSummary, 'wolt')
        + cb_vcr_money($restiaSummary, 'bolt')
        + cb_vcr_money($restiaSummary, 'dj')
        + cb_vcr_money($restiaSummary, 'web')
        + cb_vcr_money($restiaSummary, 'wolt_cash')
        + cb_vcr_money($cash, 'terminal')
        + cb_vcr_money($cash, 'stravenky')
        + cb_vcr_money($cash, 'hotovost');

    $expenses = cb_vcr_money($cash, 'vydaje_benzin')
        + cb_vcr_money($cash, 'vydaje_auta')
        + cb_vcr_money($cash, 'vydaje_suroviny')
        + cb_vcr_money($cash, 'vydaje_ostatni')
        + cb_vcr_money($cash, 'vydaje_phm_soukrome');

    return $income + $expenses - cb_vcr_money($restiaSummary, 'trzba');
}

function cb_vcr_people_from_rows(array $rows): array
{
    $people = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $idUser = (int)($row['id_user'] ?? 0);
        $hours = cb_vcr_float($row['odpracovano'] ?? 0);
        if ($idUser > 0 && $hours > 0) {
            $people[] = ['id_user' => $idUser, 'hours' => $hours];
        }
    }

    return $people;
}

function cb_vcr_people_from_post(array $post): array
{
    $people = [];
    foreach (['instor', 'kuryr'] as $type) {
        $ids = $post[$type . '_id_user'] ?? [];
        $hours = $post[$type . '_hodiny'] ?? [];
        if (!is_array($ids) || !is_array($hours)) {
            continue;
        }
        foreach ($ids as $index => $idUserRaw) {
            $idUser = (int)$idUserRaw;
            $worked = cb_vcr_float($hours[$index] ?? 0);
            if ($idUser > 0 && $worked > 0) {
                $people[] = ['id_user' => $idUser, 'hours' => $worked];
            }
        }
    }

    return $people;
}

function cb_vcr_cash_from_post(array $post): array
{
    return [
        'hotovost' => array_key_exists('pokladna_hotovost', $post) ? cb_vcr_float($post['pokladna_hotovost']) : null,
        'terminal' => array_key_exists('pokladna_terminal', $post) ? cb_vcr_float($post['pokladna_terminal']) : null,
        'stravenky' => array_key_exists('pokladna_stravenky', $post) ? cb_vcr_float($post['pokladna_stravenky']) : null,
        'vydaje_benzin' => cb_vcr_float($post['vydaje_benzin'] ?? 0),
        'vydaje_auta' => cb_vcr_float($post['vydaje_auta'] ?? 0),
        'vydaje_suroviny' => cb_vcr_float($post['vydaje_suroviny'] ?? 0),
        'vydaje_ostatni' => cb_vcr_float($post['vydaje_ostatni'] ?? 0),
        'vydaje_phm_soukrome' => cb_vcr_float($post['vydaje_phm_soukrome'] ?? 0),
    ];
}

function cb_vcr_cash_from_draft(?array $draft): array
{
    if (!is_array($draft)) {
        return [
            'hotovost' => null,
            'terminal' => null,
            'stravenky' => null,
        ];
    }

    return [
        'hotovost' => array_key_exists('hotovost', $draft) ? $draft['hotovost'] : null,
        'terminal' => array_key_exists('terminal', $draft) ? $draft['terminal'] : null,
        'stravenky' => array_key_exists('stravenky', $draft) ? $draft['stravenky'] : null,
        'vydaje_benzin' => $draft['vydaje_benzin'] ?? 0,
        'vydaje_auta' => $draft['vydaje_auta'] ?? 0,
        'vydaje_suroviny' => $draft['vydaje_suroviny'] ?? 0,
        'vydaje_ostatni' => $draft['vydaje_ostatni'] ?? 0,
        'vydaje_phm_soukrome' => $draft['vydaje_phm_soukrome'] ?? 0,
    ];
}

// Fix patri denne na MAIN platnou k datu reportu, hodinove naklady pouze do odpracovanych smen.
function cb_vcr_col_cost(mysqli $conn, string $datumReportu, array $people, int $idPob): float
{
    $ids = [];
    foreach ($people as $person) {
        $idUser = (int)($person['id_user'] ?? 0);
        if ($idUser > 0) {
            $ids[$idUser] = true;
        }
    }
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $datumReportu);
    if (!$date instanceof DateTimeImmutable) {
        return 0.0;
    }
    $monthStart = $date->modify('first day of this month')->format('Y-m-d');

    $idList = $ids === [] ? '0' : implode(',', array_map('intval', array_keys($ids)));
    $mainSql = cb_hr_main_sql($conn);
    $sql = '
        SELECT s.id_user, s.id_mzda_typ, s.naklad_col_hod, s.naklad_col_den
        FROM hr_sazby s
        INNER JOIN (
            SELECT id_user, MAX(platnost_od) AS platnost_od
            FROM hr_sazby
            WHERE platnost_od <= ?
              AND (platnost_do IS NULL OR platnost_do >= ?)
            GROUP BY id_user
        ) x ON x.id_user = s.id_user AND x.platnost_od = s.platnost_od
        WHERE (s.id_mzda_typ <> 2 AND s.id_user IN (' . $idList . '))
           OR (s.id_mzda_typ = 2 AND EXISTS (
               SELECT 1 FROM hr_person p
               INNER JOIN ' . $mainSql . ' m ON m.id_person = p.id_person
               WHERE p.id_user = s.id_user AND m.id_pob = ?
                 AND m.platny = 1 AND m.zruseno IS NULL
                 AND (m.platnost_od IS NULL OR m.platnost_od <= ?)
                 AND (m.platnost_do IS NULL OR m.platnost_do >= ?)
           ))
    ';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return 0.0;
    }
    $stmt->bind_param('ssiss', $monthStart, $monthStart, $idPob, $datumReportu, $datumReportu);
    $stmt->execute();
    $result = $stmt->get_result();
    $rates = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $idUser = (int)($row['id_user'] ?? 0);
            if ($idUser > 0) {
                $rates[$idUser] = [
                    'type' => (int)($row['id_mzda_typ'] ?? 1),
                    'hour' => cb_vcr_float($row['naklad_col_hod'] ?? 0),
                    'day' => cb_vcr_float($row['naklad_col_den'] ?? 0),
                ];
            }
        }
        $result->free();
    }
    $stmt->close();

    $cost = 0.0;
    // Fixni sazby se sectou jednou i pri prazdnem reportu; smeny je uz nepricitaji.
    foreach ($rates as $rate) {
        if ((int)$rate['type'] === 2) {
            $cost += (float)$rate['day'];
        }
    }
    foreach ($people as $person) {
        $idUser = (int)($person['id_user'] ?? 0);
        $hours = cb_vcr_float($person['hours'] ?? 0);
        if ($idUser <= 0 || !isset($rates[$idUser])) {
            continue;
        }

        if ((int)$rates[$idUser]['type'] === 2) {
            continue;
        }

        if ($hours > 0) {
            $cost += $hours * (float)$rates[$idUser]['hour'];
        }
    }

    return $cost;
}

function cb_vcr_col_phm_cost(array $cash): float
{
    return cb_vcr_money($cash, 'vydaje_benzin')
        + cb_vcr_money($cash, 'vydaje_phm_soukrome');
}

function cb_vcr_col_wolt_drive_cost(mysqli $conn, string $datumReportu, array $restiaSummary): float
{
    $promenne = cb_report_promenne_for_date($conn, $datumReportu);
    $sazba = is_array($promenne) ? (float)($promenne['wolt_drive'] ?? 0) : 0.0;
    $pocet = max(0, (int)($restiaSummary['woltdrive_count'] ?? 0));

    return $pocet * $sazba;
}

// COL vztahuje mzdove naklady pobocky, PHM a Wolt Drive k trzbe bez DPH.
function cb_vcr_col(mysqli $conn, string $datumReportu, array $restiaSummary, array $cash, array $people, int $idPob): ?float
{
    $trzbaBezDph = cb_vcr_money($restiaSummary, 'trzba') / 1.12;
    if ($trzbaBezDph <= 0) {
        return null;
    }

    return (
        cb_vcr_col_cost($conn, $datumReportu, $people, $idPob)
        + cb_vcr_col_phm_cost($cash)
        + cb_vcr_col_wolt_drive_cost($conn, $datumReportu, $restiaSummary)
    ) / $trzbaBezDph;
}

// Sdileny vypocet pro zobrazeni, prubezny prepocet i finalni ulozeni reportu.
function cb_vypocet_col_rozdil(mysqli $conn, string $datumReportu, array $restiaSummary, array $cash, array $people, int $idPob): array
{
    $rozdil = cb_vcr_rozdil($restiaSummary, $cash);
    $col = cb_vcr_col($conn, $datumReportu, $restiaSummary, $cash, $people, $idPob);

    return [
        'rozdil' => $rozdil,
        'col_pomer' => $col,
    ];
}

// lib/vypocet_col_rozdil.php * Konec souboru
