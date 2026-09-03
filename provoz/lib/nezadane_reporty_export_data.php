<?php
// lib/nezadane_reporty_export_data.php * Data pro e-mailovy export nezadanych dennich reportu
declare(strict_types=1);

require_once __DIR__ . '/format_datum_cas.php';

const CB_NEZADANE_REPORTY_EXPORT_PRAVO = 211;

function cb_nezadane_reporty_export_ma_pravo(): bool
{
    return function_exists('cb_pravo_ma') && cb_pravo_ma(CB_NEZADANE_REPORTY_EXPORT_PRAVO);
}

function cb_nezadane_reporty_export_period(string $scope): array
{
    $currentWorkday = cb_dt_workday_start(null, 6);
    if ($scope === 'previous') {
        $periodEnd = $currentWorkday->modify('first day of this month')->modify('-1 day');
        $periodStart = $periodEnd->modify('first day of this month');
    } elseif ($scope === 'current') {
        $periodStart = $currentWorkday->modify('first day of this month');
        $periodEnd = $currentWorkday;
    } else {
        throw new RuntimeException('Neplatné období exportu.');
    }

    return [
        'scope' => $scope,
        'from' => $periodStart->format('Y-m-d'),
        'to' => $periodEnd->format('Y-m-d'),
        'label' => cb_dt_format_month_year_cs($periodStart->format('Y-m-d')),
    ];
}

function cb_nezadane_reporty_export_recipients(mysqli $conn): array
{
    $result = $conn->query("
        SELECT id_user, jmeno, prijmeni, email
        FROM user
        WHERE id_role < 4
          AND TRIM(email) <> ''
        ORDER BY id_role ASC, prijmeni ASC, jmeno ASC, id_user ASC
    ");

    $recipients = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $email = trim((string)($row['email'] ?? ''));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            $recipients[] = [
                'id_user' => (int)($row['id_user'] ?? 0),
                'name' => trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? '')),
                'email' => $email,
            ];
        }
        $result->free();
    }

    return $recipients;
}

function cb_nezadane_reporty_export_recipient(mysqli $conn, int $idUser): ?array
{
    if ($idUser <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id_user, jmeno, prijmeni, email
        FROM user
        WHERE id_user = ?
          AND id_role < 4
          AND TRIM(email) <> ''
        LIMIT 1
    ");
    if ($stmt === false) {
        return null;
    }

    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? ($result->fetch_assoc() ?: null) : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    if (!is_array($row)) {
        return null;
    }

    $email = trim((string)($row['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return null;
    }

    return [
        'id_user' => (int)($row['id_user'] ?? 0),
        'name' => trim((string)($row['jmeno'] ?? '') . ' ' . (string)($row['prijmeni'] ?? '')),
        'email' => $email,
    ];
}

function cb_nezadane_reporty_export_rows(mysqli $conn, string $scope): array
{
    $period = cb_nezadane_reporty_export_period($scope);
    $from = (string)$period['from'];
    $to = (string)$period['to'];

    $branches = [];
    $branchResult = $conn->query("
        SELECT id_pob, nazev
        FROM pobocka
        WHERE aktivni = 1
          AND id_pob > 0
        ORDER BY id_pob ASC
    ");
    if ($branchResult instanceof mysqli_result) {
        while ($row = $branchResult->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            if ($idPob > 0) {
                $branches[$idPob] = trim((string)($row['nazev'] ?? ''));
            }
        }
        $branchResult->free();
    }

    $submitted = [];
    $stmtReports = $conn->prepare("
        SELECT datum_reportu, id_pob
        FROM reporty_is
        WHERE platny = 1
          AND datum_reportu >= ?
          AND datum_reportu <= ?
    ");
    if ($stmtReports !== false) {
        $stmtReports->bind_param('ss', $from, $to);
        $stmtReports->execute();
        $resultReports = $stmtReports->get_result();
        if ($resultReports instanceof mysqli_result) {
            while ($row = $resultReports->fetch_assoc()) {
                $submitted[(string)$row['datum_reportu'] . ':' . (int)$row['id_pob']] = true;
            }
            $resultReports->free();
        }
        $stmtReports->close();
    }

    $closingGroups = [];
    $stmtClosers = $conn->prepare("
        SELECT
            sp.datum,
            sp.id_pob,
            TRIM(CONCAT_WS(' ', u.jmeno, u.prijmeni)) AS full_name,
            DATE_ADD(
                CONCAT(sp.datum, ' ', sp.cas_do),
                INTERVAL CASE WHEN sp.cas_do <= sp.cas_od THEN 1 ELSE 0 END DAY
            ) AS end_dt
        FROM smeny_plan sp
        INNER JOIN user u ON u.id_user = sp.id_user
        WHERE sp.id_slot = 1
          AND sp.datum >= ?
          AND sp.datum <= ?
        HAVING full_name <> ''
        ORDER BY sp.datum ASC, sp.id_pob ASC, end_dt DESC, full_name ASC
    ");
    if ($stmtClosers !== false) {
        $stmtClosers->bind_param('ss', $from, $to);
        $stmtClosers->execute();
        $resultClosers = $stmtClosers->get_result();
        if ($resultClosers instanceof mysqli_result) {
            while ($row = $resultClosers->fetch_assoc()) {
                $key = (string)$row['datum'] . ':' . (int)$row['id_pob'];
                $name = trim((string)($row['full_name'] ?? ''));
                $endDt = trim((string)($row['end_dt'] ?? ''));
                if ($name === '' || $endDt === '') {
                    continue;
                }
                if (!isset($closingGroups[$key]) || strcmp($endDt, (string)$closingGroups[$key]['end_dt']) > 0) {
                    $closingGroups[$key] = ['end_dt' => $endDt, 'names' => [$name => $name]];
                } elseif ($endDt === (string)$closingGroups[$key]['end_dt']) {
                    $closingGroups[$key]['names'][$name] = $name;
                }
            }
            $resultClosers->free();
        }
        $stmtClosers->close();
    }

    $closers = [];
    foreach ($closingGroups as $key => $group) {
        $names = array_values((array)($group['names'] ?? []));
        $closers[$key] = $names !== [] ? implode(', ', $names) : '—';
    }

    $rows = [];
    $tz = new DateTimeZone('Europe/Prague');
    $day = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $from . ' 06:00:00', $tz);
    $lastDay = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $to . ' 06:00:00', $tz);
    if (!($day instanceof DateTimeImmutable) || !($lastDay instanceof DateTimeImmutable)) {
        throw new RuntimeException('Období exportu se nepodařilo připravit.');
    }

    while ($day <= $lastDay) {
        $date = $day->format('Y-m-d');
        foreach ($branches as $idPob => $branchName) {
            $key = $date . ':' . $idPob;
            if (isset($submitted[$key])) {
                continue;
            }
            $rows[] = [
                'date' => $date,
                'date_label' => $day->format('j.n.Y'),
                'weekday' => cb_dt_weekday_name_cs($day),
                'branch' => $branchName !== '' ? $branchName : ('Pobočka ' . $idPob),
                'closer' => $closers[$key] ?? '—',
            ];
        }
        $day = $day->modify('+1 day');
    }

    return ['period' => $period, 'rows' => $rows];
}
