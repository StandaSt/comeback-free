<?php
// Nastavení zavírací doby poboček a globálních dnů bez provozu.
declare(strict_types=1);

require_once __DIR__ . '/report_promenne.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';

/** @return array<int,array{key:string,label:string}> */
function cb_pobocka_provoz_weekdays(): array
{
    return [
        1 => ['key' => 'end_po', 'label' => 'Pondělí'],
        2 => ['key' => 'end_ut', 'label' => 'Úterý'],
        3 => ['key' => 'end_st', 'label' => 'Středa'],
        4 => ['key' => 'end_ct', 'label' => 'Čtvrtek'],
        5 => ['key' => 'end_pa', 'label' => 'Pátek'],
        6 => ['key' => 'end_so', 'label' => 'Sobota'],
        7 => ['key' => 'end_ne', 'label' => 'Neděle'],
    ];
}

/** @return string[] */
function cb_pobocka_provoz_closing_time_options(): array
{
    $options = [];
    foreach ([20, 21, 22, 23, 0, 1, 2, 3, 4] as $hour) {
        foreach ([0, 15, 30, 45] as $minute) {
            if ($hour === 4 && $minute > 0) {
                continue;
            }
            $options[] = sprintf('%02d:%02d', $hour, $minute);
        }
    }

    return $options;
}

function cb_pobocka_provoz_token(): string
{
    if (empty($_SESSION['cb_pobocka_provoz_token'])) {
        $_SESSION['cb_pobocka_provoz_token'] = bin2hex(random_bytes(16));
    }

    return (string)$_SESSION['cb_pobocka_provoz_token'];
}

function cb_pobocka_provoz_user_id(): int
{
    $user = $_SESSION['cb_user'] ?? [];
    return is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
}

function cb_pobocka_provoz_require_permission(): void
{
    if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(CB_REPORT_PROMENNE_PRAVO)) {
        throw new CbUserVisibleException('Nemáte právo upravovat nastavení reportu.');
    }
}

/** @return array<string,mixed>|null */
function cb_pobocka_provoz_branch(mysqli $conn, int $idPob, int $idUser): ?array
{
    if ($idPob <= 0 || $idUser <= 0) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT p.id_pob, p.nazev, p.end_po, p.end_ut, p.end_st, p.end_ct, p.end_pa, p.end_so, p.end_ne
        FROM hr_person hp
        INNER JOIN pobocka p ON p.id_pob = ? AND p.aktivni = 1
        WHERE hp.id_person = ? AND (hp.pristup_vsechny_pobocky = 1 OR EXISTS (
            SELECT 1 FROM hr_pracoviste prac WHERE prac.id_person = hp.id_person AND prac.id_pob = p.id_pob AND prac.platny = 1
        ))
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit načtení pobočky.');
    }
    $stmt->bind_param('ii', $idPob, $idUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result instanceof mysqli_result ? $result->fetch_assoc() : null;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
}

/** @return array<int,array{mesic:int,den:int}> */
function cb_pobocka_provoz_closed_days(mysqli $conn): array
{
    $result = $conn->query('SELECT mesic, den FROM pobocka_zavreno ORDER BY mesic ASC, den ASC');
    $days = [];
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $month = (int)($row['mesic'] ?? 0);
            $day = (int)($row['den'] ?? 0);
            if (checkdate($month, $day, 2000)) {
                $days[] = ['mesic' => $month, 'den' => $day];
            }
        }
        $result->free();
    }

    return $days;
}

/** @return array<string,true> */
function cb_pobocka_provoz_closed_date_set(mysqli $conn, string $from, string $to): array
{
    $fromDate = cb_pobocka_provoz_valid_date($from);
    $toDate = cb_pobocka_provoz_valid_date($to);
    if ($fromDate === '' || $toDate === '' || $fromDate > $toDate) {
        return [];
    }

    $rules = [];
    foreach (cb_pobocka_provoz_closed_days($conn) as $day) {
        $rules[sprintf('%02d-%02d', $day['mesic'], $day['den'])] = true;
    }
    if ($rules === []) {
        return [];
    }

    $set = [];
    $tz = new DateTimeZone('Europe/Prague');
    $cursor = new DateTimeImmutable($fromDate, $tz);
    $last = new DateTimeImmutable($toDate, $tz);
    while ($cursor <= $last) {
        if (isset($rules[$cursor->format('m-d')])) {
            $set[$cursor->format('Y-m-d')] = true;
        }
        $cursor = $cursor->modify('+1 day');
    }

    return $set;
}

function cb_pobocka_provoz_is_closed_date(mysqli $conn, string $date): bool
{
    if (cb_pobocka_provoz_valid_date($date) === '') {
        return false;
    }

    $dateValue = new DateTimeImmutable($date, new DateTimeZone('Europe/Prague'));
    $month = (int)$dateValue->format('n');
    $day = (int)$dateValue->format('j');
    $stmt = $conn->prepare('SELECT 1 FROM pobocka_zavreno WHERE mesic = ? AND den = ? LIMIT 1');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit kontrolu zavřeného dne.');
    }
    $stmt->bind_param('ii', $month, $day);
    $stmt->execute();
    $result = $stmt->get_result();
    $closed = $result instanceof mysqli_result && $result->num_rows > 0;
    if ($result instanceof mysqli_result) {
        $result->free();
    }
    $stmt->close();

    return $closed;
}

function cb_pobocka_provoz_valid_date(string $value): string
{
    $value = trim($value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Europe/Prague'));
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
}

function cb_pobocka_provoz_valid_time(string $value, string $label): string
{
    $value = trim($value);
    if (!in_array($value, cb_pobocka_provoz_closing_time_options(), true)) {
        throw new CbUserVisibleException('Čas pro den ' . $label . ' musí být mezi 20:00 a 04:00 po 15 minutách.');
    }

    return $value . ':00';
}

/** @return array{mesic:int,den:int,label:string} */
function cb_pobocka_provoz_valid_closed_day(array $data): array
{
    $month = (int)($data['mesic'] ?? 0);
    $day = (int)($data['den'] ?? 0);
    if (!checkdate($month, $day, 2000)) {
        throw new CbUserVisibleException('Vyberte platný den a měsíc.');
    }

    return [
        'mesic' => $month,
        'den' => $day,
        'label' => sprintf('%02d.%02d.', $day, $month),
    ];
}

function cb_pobocka_provoz_log(array $payload): void
{
    if (!cb_user_akce_zapis($payload + [
        'id_user_akce_typ' => 14,
        'modul' => 'provoz',
        'zdroj' => 'nastaveni_reportu',
    ])) {
        throw new RuntimeException('Změnu se nepodařilo zapsat do protokolu akcí.');
    }
}

function cb_pobocka_provoz_save_hours(mysqli $conn, array $data): string
{
    $idPob = (int)($data['zr_id_pob'] ?? 0);
    $branch = cb_pobocka_provoz_branch($conn, $idPob, cb_pobocka_provoz_user_id());
    if (!is_array($branch)) {
        throw new CbUserVisibleException('Vybraná pobočka není dostupná.');
    }

    $times = [];
    $old = [];
    foreach (cb_pobocka_provoz_weekdays() as $day) {
        $key = (string)$day['key'];
        $times[$key] = cb_pobocka_provoz_valid_time((string)($data[$key] ?? ''), (string)$day['label']);
        $old[$key] = substr((string)($branch[$key] ?? ''), 0, 5);
    }

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('
            UPDATE pobocka
            SET end_po = ?, end_ut = ?, end_st = ?, end_ct = ?, end_pa = ?, end_so = ?, end_ne = ?
            WHERE id_pob = ? AND aktivni = 1
        ');
        if ($stmt === false) {
            throw new RuntimeException('Nelze připravit uložení zavírací doby.');
        }
        $stmt->bind_param(
            'sssssssi',
            $times['end_po'],
            $times['end_ut'],
            $times['end_st'],
            $times['end_ct'],
            $times['end_pa'],
            $times['end_so'],
            $times['end_ne'],
            $idPob
        );
        $stmt->execute();
        if ($stmt->affected_rows > 1) {
            throw new RuntimeException('Bylo změněno neočekávané množství poboček.');
        }
        $stmt->close();

        cb_pobocka_provoz_log([
            'objekt' => 'pobocka',
            'id_objektu' => $idPob,
            'pole' => 'zaviraci_doba',
            'hodnota_old' => json_encode($old, JSON_UNESCAPED_UNICODE),
            'hodnota_new' => json_encode(array_map(static fn(string $time): string => substr($time, 0, 5), $times), JSON_UNESCAPED_UNICODE),
            'detail' => ['pobocka' => (string)($branch['nazev'] ?? '')],
        ]);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return 'Zavírací doba pobočky byla uložena.';
}

function cb_pobocka_provoz_add_closed_date(mysqli $conn, array $data): string
{
    $closedDay = cb_pobocka_provoz_valid_closed_day($data);
    $month = $closedDay['mesic'];
    $day = $closedDay['den'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT IGNORE INTO pobocka_zavreno (mesic, den) VALUES (?, ?)');
        if ($stmt === false) {
            throw new RuntimeException('Nelze připravit uložení zavřeného dne.');
        }
        $stmt->bind_param('ii', $month, $day);
        $stmt->execute();
        $inserted = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$inserted) {
            throw new CbUserVisibleException('Tento zavřený den už je v seznamu.');
        }

        cb_pobocka_provoz_log([
            'objekt' => 'pobocka_zavreno',
            'pole' => 'den_mesic',
            'hodnota_old' => '',
            'hodnota_new' => $closedDay['label'],
        ]);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return 'Zavřený den byl přidán.';
}

function cb_pobocka_provoz_remove_closed_date(mysqli $conn, array $data): string
{
    $closedDay = cb_pobocka_provoz_valid_closed_day($data);
    $month = $closedDay['mesic'];
    $day = $closedDay['den'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('DELETE FROM pobocka_zavreno WHERE mesic = ? AND den = ?');
        if ($stmt === false) {
            throw new RuntimeException('Nelze připravit odstranění zavřeného dne.');
        }
        $stmt->bind_param('ii', $month, $day);
        $stmt->execute();
        $removed = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$removed) {
            throw new CbUserVisibleException('Zavřený den už v seznamu není.');
        }

        cb_pobocka_provoz_log([
            'objekt' => 'pobocka_zavreno',
            'pole' => 'den_mesic',
            'hodnota_old' => $closedDay['label'],
            'hodnota_new' => '',
        ]);
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    return 'Zavřený den byl odstraněn.';
}

function cb_pobocka_provoz_save_request(array $data): string
{
    cb_pobocka_provoz_require_permission();
    if (!hash_equals(cb_pobocka_provoz_token(), (string)($data['token'] ?? ''))) {
        throw new CbUserVisibleException('Neplatný bezpečnostní token.');
    }

    $action = trim((string)($data['provoz_action'] ?? ''));
    return match ($action) {
        'save_hours' => cb_pobocka_provoz_save_hours(db(), $data),
        'add_closed_date' => cb_pobocka_provoz_add_closed_date(db(), $data),
        'remove_closed_date' => cb_pobocka_provoz_remove_closed_date(db(), $data),
        default => throw new CbUserVisibleException('Neplatná akce nastavení provozu.'),
    };
}

function cb_pobocka_provoz_handle_json_request(): void
{
    header('Content-Type: application/json; charset=utf-8');
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutné přihlášení.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $data = [];
    parse_str((string)file_get_contents('php://input'), $data);
    try {
        $message = cb_pobocka_provoz_save_request(is_array($data) ? $data : []);
        $_SESSION['cb_pobocka_provoz_flash'] = ['typ' => 'ok', 'text' => $message];
        echo json_encode(['ok' => true, 'text' => $message], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        $message = $e instanceof CbUserVisibleException ? $e->getMessage() : cb_chyba_uzivatel($e, [
            'module' => 'PROVOZ',
            'action' => 'Uložení nastavení provozu',
        ]);
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => $message], JSON_UNESCAPED_UNICODE);
    }
}
