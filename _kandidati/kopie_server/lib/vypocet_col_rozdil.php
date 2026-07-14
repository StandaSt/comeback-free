<?php
// lib/vypocet_col_rozdil.php * K10 vypocet rozdilu a COL
declare(strict_types=1);

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

function cb_vcr_col_cost(mysqli $conn, string $datumReportu, array $people): float
{
    $ids = [];
    foreach ($people as $person) {
        $idUser = (int)($person['id_user'] ?? 0);
        if ($idUser > 0) {
            $ids[$idUser] = true;
        }
    }
    if ($ids === []) {
        return 0.0;
    }

    $date = DateTimeImmutable::createFromFormat('Y-m-d', $datumReportu);
    if (!$date instanceof DateTimeImmutable) {
        return 0.0;
    }
    $monthStart = $date->modify('first day of this month')->format('Y-m-d');

    $idList = implode(',', array_map('intval', array_keys($ids)));
    $sql = '
        SELECT s.id_user, s.id_mzda_typ, s.naklad_col_hod, s.naklad_col_den
        FROM hr_sazby s
        INNER JOIN (
            SELECT id_user, MAX(platnost_od) AS platnost_od
            FROM hr_sazby
            WHERE id_user IN (' . $idList . ')
              AND platnost_od <= ?
              AND (platnost_do IS NULL OR platnost_do >= ?)
            GROUP BY id_user
        ) x ON x.id_user = s.id_user AND x.platnost_od = s.platnost_od
    ';

    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return 0.0;
    }
    $stmt->bind_param('ss', $monthStart, $monthStart);
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
    $fixedAdded = [];
    foreach ($people as $person) {
        $idUser = (int)($person['id_user'] ?? 0);
        $hours = cb_vcr_float($person['hours'] ?? 0);
        if ($idUser <= 0 || !isset($rates[$idUser])) {
            continue;
        }

        if ((int)$rates[$idUser]['type'] === 2) {
            if (!isset($fixedAdded[$idUser])) {
                $cost += (float)$rates[$idUser]['day'];
                $fixedAdded[$idUser] = true;
            }
            continue;
        }

        if ($hours > 0) {
            $cost += $hours * (float)$rates[$idUser]['hour'];
        }
    }

    return $cost;
}

function cb_vcr_col(mysqli $conn, string $datumReportu, array $restiaSummary, array $people): ?float
{
    $trzba = cb_vcr_money($restiaSummary, 'trzba');
    if ($trzba <= 0) {
        return null;
    }

    return cb_vcr_col_cost($conn, $datumReportu, $people) / $trzba;
}

function cb_vcr_col_bez_dph(mysqli $conn, string $datumReportu, array $restiaSummary, array $people): ?float
{
    $trzbaBezDph = cb_vcr_money($restiaSummary, 'trzba') / 1.12;
    if ($trzbaBezDph <= 0) {
        return null;
    }

    return cb_vcr_col_cost($conn, $datumReportu, $people) / $trzbaBezDph;
}

function cb_vypocet_col_rozdil(mysqli $conn, string $datumReportu, array $restiaSummary, array $cash, array $people): array
{
    $rozdil = cb_vcr_rozdil($restiaSummary, $cash);
    $col = cb_vcr_col($conn, $datumReportu, $restiaSummary, $people);
    $colBezDph = cb_vcr_col_bez_dph($conn, $datumReportu, $restiaSummary, $people);

    return [
        'rozdil' => $rozdil,
        'col_pomer' => $col,
        'col_bez_dph_pomer' => $colBezDph,
    ];
}

// lib/vypocet_col_rozdil.php * Konec souboru
