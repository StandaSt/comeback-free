<?php
// lib/cron_nezadane_reporty.php * Kontrola chybejicich dennich reportu pro CRON
declare(strict_types=1);

/*
 * Spousteni: denne v 08:00 a 20:00 pres PHP CLI.
 * Kontroluje tri predchozi provozni dny (provozni den zacina v 06:00).
 *
 * $test = 1: vsechny zpravy dostane pouze id_user=1.
 * $test = 0: prijemci se urci podle zavirajicich instoru, vedoucich pobocky
 *            a pevnych prijemcu id_user=568 a id_user=1.
 */
$test = 0;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/format_datum_cas.php';
require_once __DIR__ . '/../../common/notifikace/notifikace_2fa.php';

$PROSTREDI = 'SERVER';

function cb_cron_nezadane_reporty_dates(): array
{
    $currentWorkday = cb_dt_workday_start(null, 6);
    $dates = [];
    for ($dayOffset = 1; $dayOffset <= 3; $dayOffset++) {
        $dates[] = $currentWorkday->modify('-' . $dayOffset . ' day')->format('Y-m-d');
    }
    sort($dates);

    return $dates;
}

function cb_cron_nezadane_reporty_branches(mysqli $conn): array
{
    $branches = [];
    $result = $conn->query("
        SELECT id_pob, nazev
        FROM pobocka
        WHERE aktivni = 1
          AND id_pob > 0
        ORDER BY id_pob ASC
    ");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            if ($idPob > 0) {
                $branches[$idPob] = trim((string)($row['nazev'] ?? ''));
            }
        }
        $result->free();
    }

    return $branches;
}

function cb_cron_nezadane_reporty_submitted(mysqli $conn, array $dates): array
{
    $submitted = [];
    if (count($dates) !== 3) {
        return $submitted;
    }

    $stmt = $conn->prepare("
        SELECT datum_reportu, id_pob
        FROM reporty_is
        WHERE platny = 1
          AND datum_reportu IN (?, ?, ?)
    ");
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit kontrolu platnych reportu.');
    }
    $stmt->bind_param('sss', $dates[0], $dates[1], $dates[2]);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $submitted[(string)$row['datum_reportu'] . ':' . (int)$row['id_pob']] = true;
        }
        $result->free();
    }
    $stmt->close();

    return $submitted;
}

function cb_cron_nezadane_reporty_closers(mysqli $conn, array $dates): array
{
    $closers = [];
    if (count($dates) !== 3) {
        return $closers;
    }

    $stmt = $conn->prepare("
        SELECT
            sp.datum,
            sp.id_pob,
            sp.id_user,
            DATE_ADD(
                CONCAT(sp.datum, ' ', sp.cas_do),
                INTERVAL CASE WHEN sp.cas_do <= sp.cas_od THEN 1 ELSE 0 END DAY
            ) AS end_dt
        FROM smeny_plan sp
        INNER JOIN `user` u ON u.id_user = sp.id_user AND u.aktivni = 1
        WHERE sp.id_slot = 1
          AND sp.datum IN (?, ?, ?)
        ORDER BY sp.datum ASC, sp.id_pob ASC, end_dt DESC, sp.id_user ASC
    ");
    if ($stmt === false) {
        throw new RuntimeException('Nepodarilo se pripravit vyber zavirajicich instoru.');
    }
    $stmt->bind_param('sss', $dates[0], $dates[1], $dates[2]);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $key = (string)$row['datum'] . ':' . (int)$row['id_pob'];
            $endDt = (string)($row['end_dt'] ?? '');
            $idUser = (int)($row['id_user'] ?? 0);
            if ($endDt === '' || $idUser <= 0) {
                continue;
            }
            if (!isset($closers[$key])) {
                $closers[$key] = ['end_dt' => $endDt, 'users' => [$idUser => $idUser]];
                continue;
            }
            if ($endDt === (string)$closers[$key]['end_dt']) {
                $closers[$key]['users'][$idUser] = $idUser;
            }
        }
        $result->free();
    }
    $stmt->close();

    return $closers;
}

function cb_cron_nezadane_reporty_leaders(mysqli $conn): array
{
    $leaders = [];
    $result = $conn->query("
        SELECT DISTINCT up.id_pob, u.id_user
        FROM user_pobocka up
        INNER JOIN `user` u ON u.id_user = up.id_user AND u.aktivni = 1
        INNER JOIN user_role ur ON ur.id_user = u.id_user AND ur.id_role = 5
        WHERE up.main = 1
        ORDER BY up.id_pob ASC, u.id_user ASC
    ");
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $idPob = (int)($row['id_pob'] ?? 0);
            $idUser = (int)($row['id_user'] ?? 0);
            if ($idPob > 0 && $idUser > 0) {
                $leaders[$idPob][$idUser] = $idUser;
            }
        }
        $result->free();
    }

    return $leaders;
}

function cb_cron_nezadane_reporty_date_label(string $date): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $date, new DateTimeZone('Europe/Prague'));
    return ($dt instanceof DateTimeImmutable) ? $dt->format('j.n.Y') : $date;
}

function cb_cron_nezadane_reporty_add_alert(
    array &$alertsByUser,
    int $idUser,
    int $idPob,
    string $branchName,
    string $date,
    string $reason
): void {
    if ($idUser <= 0 || $idPob <= 0 || $date === '' || $reason === '') {
        return;
    }

    $alertsByUser[$idUser]['branches'][$idPob]['branch'] = $branchName;
    $alertsByUser[$idUser]['branches'][$idPob]['dates'][$date] = $date;
    $alertsByUser[$idUser]['reasons'][$reason] = $reason;
}

try {
    $conn = db();
    $dates = cb_cron_nezadane_reporty_dates();
    $branches = cb_cron_nezadane_reporty_branches($conn);
    $submitted = cb_cron_nezadane_reporty_submitted($conn, $dates);
    $closers = cb_cron_nezadane_reporty_closers($conn, $dates);
    $leaders = cb_cron_nezadane_reporty_leaders($conn);

    $missingByBranch = [];
    foreach ($branches as $idPob => $branchName) {
        foreach ($dates as $date) {
            if (!isset($submitted[$date . ':' . $idPob])) {
                $missingByBranch[$idPob][] = $date;
            }
        }
    }

    if ($missingByBranch === []) {
        echo 'OK: za kontrolovane dny nejsou zadne chybejici denni reporty.' . PHP_EOL;
        exit(0);
    }

    $alertsByUser = [];
    foreach ($missingByBranch as $idPob => $missingDates) {
        $branchName = trim((string)($branches[$idPob] ?? ''));
        if ($branchName === '') {
            $branchName = 'ID ' . (string)$idPob;
        }

        foreach ($missingDates as $date) {
            if ($test === 1) {
                cb_cron_nezadane_reporty_add_alert(
                    $alertsByUser,
                    1,
                    (int)$idPob,
                    $branchName,
                    (string)$date,
                    'manager'
                );
                continue;
            }

            foreach (($closers[$date . ':' . $idPob]['users'] ?? []) as $idUser) {
                cb_cron_nezadane_reporty_add_alert(
                    $alertsByUser,
                    (int)$idUser,
                    (int)$idPob,
                    $branchName,
                    (string)$date,
                    'shift'
                );
            }
            foreach (($leaders[$idPob] ?? []) as $idUser) {
                cb_cron_nezadane_reporty_add_alert(
                    $alertsByUser,
                    (int)$idUser,
                    (int)$idPob,
                    $branchName,
                    (string)$date,
                    'leader'
                );
            }
            foreach ([568, 1] as $idManager) {
                cb_cron_nezadane_reporty_add_alert(
                    $alertsByUser,
                    $idManager,
                    (int)$idPob,
                    $branchName,
                    (string)$date,
                    'manager'
                );
            }
        }
    }

    $failed = false;
    foreach ($alertsByUser as $idUser => $userData) {
        $userBranches = (array)($userData['branches'] ?? []);
        $userReasons = (array)($userData['reasons'] ?? []);
        $reportCount = 0;
        foreach ($userBranches as $branchData) {
            $reportCount += count((array)($branchData['dates'] ?? []));
        }
        $lines = [
            $reportCount === 1
                ? 'Zjištěn chybějící denní report:'
                : 'Zjištěny chybějící denní reporty:',
        ];
        $structuredBranches = [];
        foreach ($userBranches as $idPob => $branchData) {
            $lines[] = '';
            $lines[] = (string)$branchData['branch'];
            $structuredDates = [];
            foreach ((array)($branchData['dates'] ?? []) as $date) {
                $lines[] = cb_cron_nezadane_reporty_date_label((string)$date);
                $structuredDates[] = (string)$date;
            }
            $structuredBranches[] = [
                'id_pob' => (int)$idPob,
                'branch' => (string)$branchData['branch'],
                'dates' => $structuredDates,
            ];
        }
        $lines[] = '';
        $lines[] = 'Tuto informaci jste dostal/a, protože:';
        $lines[] = '- jste měl/a daný den směnu';
        $lines[] = '- jste vedoucí pobočky';
        $lines[] = '- jste odpovědný manager';
        $lines[] = '';
        $lines[] = 'Zajistěte, aby se reporty opravdu zadávaly každý den.';

        $typ = ($test === 1) ? 'nezadane_reporty_test' : 'nezadane_reporty_cron';
        $pozn = json_encode([
            'id_user' => (int)$idUser,
            'mode' => ($test === 1) ? 'TEST' : 'OSTRY',
            'checked_dates' => $dates,
            'branches' => $structuredBranches,
            'reasons' => array_values($userReasons),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($pozn)) {
            throw new RuntimeException('Nepodarilo se pripravit data upozorneni.');
        }
        $result = cb_push_send_admin_info(
            [(int)$idUser],
            $typ,
            implode("\n", $lines),
            'Kritická chyba !',
            $pozn,
            null,
            cb_module_url('provoz') . 'mobil/nezadane_reporty.php'
        );

        $ok = (int)($result['ok'] ?? 0) === 1;
        $sent = (int)($result['odeslano'] ?? 0);
        echo ($ok ? 'OK' : 'CHYBA')
            . ': id_user=' . (string)$idUser
            . ' | pobocky=' . implode(',', array_keys($userBranches))
            . ' | odeslano=' . (string)$sent
            . PHP_EOL;
        if (!$ok) {
            $failed = true;
        }
    }

    exit($failed ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'CHYBA: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
