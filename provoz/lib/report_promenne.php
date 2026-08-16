<?php
declare(strict_types=1);

const CB_REPORT_PROMENNE_PRAVO = 208;

function cb_report_promenne_user_id(): int
{
    $user = $_SESSION['cb_user'] ?? [];
    return is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
}

function cb_report_promenne_token(): string
{
    if (empty($_SESSION['cb_report_promenne_token'])) {
        $_SESSION['cb_report_promenne_token'] = bin2hex(random_bytes(16));
    }

    return (string)$_SESSION['cb_report_promenne_token'];
}

function cb_report_promenne_normalize_money(string $value): string
{
    $value = str_replace(["\xc2\xa0", ' ', ','], ['', '', '.'], trim($value));
    if ($value === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
        throw new InvalidArgumentException('Neplatná hodnota Wolt drive.');
    }

    return number_format((float)$value, 2, '.', '');
}

function cb_report_promenne_active(mysqli $conn): ?array
{
    $res = $conn->query('
        SELECT id_report_promenne, wolt_drive, plati_od, id_zadal, zadano, aktivni
        FROM report_promenne
        WHERE aktivni = 1
        ORDER BY plati_od DESC, id_report_promenne DESC
        LIMIT 1
    ');
    $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->free();
    }

    return is_array($row) ? $row : null;
}

function cb_report_promenne_for_date(mysqli $conn, string $date): ?array
{
    $stmt = $conn->prepare('
        SELECT id_report_promenne, wolt_drive, plati_od, id_zadal, zadano, aktivni
        FROM report_promenne
        WHERE plati_od <= ?
        ORDER BY plati_od DESC, id_report_promenne DESC
        LIMIT 1
    ');
    if ($stmt === false) {
        throw new RuntimeException('Nelze připravit načtení proměnných reportu.');
    }

    $stmt->bind_param('s', $date);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
    if ($res instanceof mysqli_result) {
        $res->free();
    }
    $stmt->close();

    return is_array($row) ? $row : null;
}

function cb_report_promenne_save_wolt_drive(mysqli $conn, string $woltDrive, string $platiOd, int $idZadal): void
{
    $conn->begin_transaction();
    try {
        $conn->query('UPDATE report_promenne SET aktivni = 0 WHERE aktivni = 1');

        $stmt = $conn->prepare('
            INSERT INTO report_promenne (wolt_drive, plati_od, id_zadal, zadano, aktivni)
            VALUES (?, ?, ?, NOW(), 1)
        ');
        if ($stmt === false) {
            throw new RuntimeException('Nelze připravit uložení proměnných reportu.');
        }

        $stmt->bind_param('dsi', $woltDrive, $platiOd, $idZadal);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function cb_report_promenne_save_request(array $data): void
{
    if (!function_exists('cb_pravo_ma') || !cb_pravo_ma(CB_REPORT_PROMENNE_PRAVO)) {
        throw new RuntimeException('Nemáte právo upravovat nastavení reportu.');
    }

    if (!hash_equals(cb_report_promenne_token(), (string)($data['token'] ?? ''))) {
        throw new RuntimeException('Neplatný bezpečnostní token.');
    }

    $mode = (string)($data['plati_mode'] ?? 'hned');
    $platiOd = date('Y-m-d');
    if ($mode === 'datum') {
        $platiOd = trim((string)($data['plati_od'] ?? ''));
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $platiOd);
        if (!$dt instanceof DateTimeImmutable || $dt->format('Y-m-d') !== $platiOd) {
            throw new InvalidArgumentException('Neplatné datum platnosti.');
        }
    }

    $woltDrive = cb_report_promenne_normalize_money((string)($data['wolt_drive'] ?? ''));
    cb_report_promenne_save_wolt_drive(db(), $woltDrive, $platiOd, cb_report_promenne_user_id());
}

function cb_report_promenne_handle_post(): void
{
    if ((string)($_POST['cb_akce'] ?? '') !== 'ulozit_report_promenne') {
        return;
    }

    try {
        cb_report_promenne_save_request($_POST);
        $_SESSION['cb_report_promenne_flash'] = ['typ' => 'ok', 'text' => 'Nastavení reportu bylo uloženo.'];
    } catch (Throwable $e) {
        $_SESSION['cb_report_promenne_flash'] = ['typ' => 'err', 'text' => 'Chyba nastavení reportu: ' . $e->getMessage()];
    }
}

function cb_report_promenne_handle_json_request(): void
{
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutné přihlášení.'], JSON_UNESCAPED_UNICODE);
        return;
    }

    $raw = (string)file_get_contents('php://input');
    $data = [];
    parse_str($raw, $data);

    try {
        cb_report_promenne_save_request(is_array($data) ? $data : []);
        echo json_encode(['ok' => true, 'text' => 'Nastavení reportu bylo uloženo.'], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Chyba nastavení reportu: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
}
