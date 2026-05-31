<?php
// lib/handle_dr_pracovni.php * K10 průběžné uložení pracovního reportu
declare(strict_types=1);

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !isset($_SERVER['HTTP_X_COMEBACK_DR_PRACOVNI'])
) {
    return;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db/db_dr_pracovni.php';
require_once __DIR__ . '/../db/db_dr_pracovni_osoby.php';

$sendJson = static function (int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
};

$currentUser = $_SESSION['cb_user'] ?? [];
$currentUserId = is_array($currentUser) ? (int)($currentUser['id_user'] ?? 0) : 0;
if ($currentUserId <= 0 || empty($_SESSION['login_ok'])) {
    $sendJson(401, ['ok' => false, 'err' => 'Nutne prihlaseni']);
}

$action = trim((string)($_POST['dr_action'] ?? ''));
$idPob = (int)($_POST['id_pob'] ?? 0);
$datum = trim((string)($_POST['datum_reportu'] ?? ''));
$idUser = (int)($_POST['id_user'] ?? 0);
$idSlot = (int)($_POST['id_slot'] ?? 0);
$idDrOsoby = (int)($_POST['id_dr_osoby'] ?? 0);

if ($action === '' || $idPob <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datum)) {
    $sendJson(422, ['ok' => false, 'err' => 'Neplatny pozadavek']);
}

$conn = db();
if (method_exists($conn, 'set_charset')) {
    $conn->set_charset('utf8mb4');
}

$stmtAllowed = $conn->prepare('SELECT 1 FROM user_pobocka WHERE id_user = ? AND id_pob = ? LIMIT 1');
if ($stmtAllowed === false) {
    $sendJson(500, ['ok' => false, 'err' => 'Nelze overit pobocku']);
}
$stmtAllowed->bind_param('ii', $currentUserId, $idPob);
$stmtAllowed->execute();
$allowedResult = $stmtAllowed->get_result();
$isAllowed = $allowedResult instanceof mysqli_result && $allowedResult->num_rows > 0;
if ($allowedResult instanceof mysqli_result) {
    $allowedResult->free();
}
$stmtAllowed->close();
if (!$isAllowed) {
    $sendJson(403, ['ok' => false, 'err' => 'Pobocka neni povolena']);
}

try {
    $idDr = cb_db_dr_pracovni_ensure($conn, $idPob, $datum, $currentUserId, null, null);

    $assertReportUser = static function (int $valueUserId, int $slotId) use ($conn, $sendJson, $idPob): void {
        if ($valueUserId <= 0 || !in_array($slotId, [1, 2], true)) {
            $sendJson(422, ['ok' => false, 'err' => 'Neplatna osoba']);
        }

        $stmtPerson = $conn->prepare('
            SELECT 1
            FROM user u
            INNER JOIN user_pobocka up ON up.id_user = u.id_user AND up.id_pob = ?
            INNER JOIN user_slot us ON us.id_user = u.id_user AND us.id_slot = ?
            WHERE u.id_user = ?
              AND u.aktivni = 1
            LIMIT 1
        ');
        if ($stmtPerson === false) {
            $sendJson(500, ['ok' => false, 'err' => 'Nelze overit osobu']);
        }
        $stmtPerson->bind_param('iii', $idPob, $slotId, $valueUserId);
        $stmtPerson->execute();
        $personResult = $stmtPerson->get_result();
        $personOk = $personResult instanceof mysqli_result && $personResult->num_rows > 0;
        if ($personResult instanceof mysqli_result) {
            $personResult->free();
        }
        $stmtPerson->close();
        if (!$personOk) {
            $sendJson(403, ['ok' => false, 'err' => 'Osoba neni povolena']);
        }
    };

    if ($action === 'update_user') {
        $field = trim((string)($_POST['field'] ?? ''));
        $valueUserId = (int)($_POST['value'] ?? 0);
        if (!in_array($field, ['oteviral', 'zaviral'], true)) {
            $sendJson(422, ['ok' => false, 'err' => 'Neplatne pole']);
        }
        if ($valueUserId > 0) {
            $assertReportUser($valueUserId, 1);
        }
        cb_db_dr_pracovni_update_user($conn, $idDr, $field, $valueUserId > 0 ? $valueUserId : null, $currentUserId);
        $sendJson(200, ['ok' => true, 'id_dr' => $idDr]);
    }

    if ($action === 'update_money') {
        $field = trim((string)($_POST['field'] ?? ''));
        $raw = str_replace(',', '.', trim((string)($_POST['value'] ?? '')));
        $value = $raw === '' ? null : (float)preg_replace('/[^0-9.]/', '', $raw);
        cb_db_dr_pracovni_update_money($conn, $idDr, $field, $value, $currentUserId);
        $sendJson(200, ['ok' => true, 'id_dr' => $idDr]);
    }

    if ($action === 'update_note') {
        $value = trim((string)($_POST['value'] ?? ''));
        cb_db_dr_pracovni_update_note($conn, $idDr, $value, $currentUserId);
        $sendJson(200, ['ok' => true, 'id_dr' => $idDr]);
    }

    if (in_array($action, ['add_person', 'delete_person', 'update_time', 'update_kuryr'], true)) {
        $assertReportUser($idUser, $idSlot);
    }

    $assertPersonRow = static function () use ($conn, $sendJson, $idDr, $idDrOsoby, $idUser, $idSlot): void {
        if ($idDrOsoby <= 0) {
            $sendJson(422, ['ok' => false, 'err' => 'Neplatny radek osoby']);
        }

        $stmt = $conn->prepare('
            SELECT 1
            FROM dr_pracovni_osoby
            WHERE id_dr_osoby = ?
              AND id_dr = ?
              AND id_user = ?
              AND id_slot = ?
            LIMIT 1
        ');
        if ($stmt === false) {
            $sendJson(500, ['ok' => false, 'err' => 'Nelze overit radek osoby']);
        }
        $stmt->bind_param('iiii', $idDrOsoby, $idDr, $idUser, $idSlot);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result instanceof mysqli_result && $result->num_rows > 0;
        if ($result instanceof mysqli_result) {
            $result->free();
        }
        $stmt->close();

        if (!$exists) {
            $sendJson(403, ['ok' => false, 'err' => 'Radek osoby nepatri k reportu']);
        }
    };

    if ($action === 'add_person') {
        cb_db_dr_pracovni_osoby_insert($conn, $idDr, $idUser, $idSlot, null, null, null, null);
        $rows = cb_db_dr_pracovni_osoby_list($conn, $idDr);
        $idDrOsoby = 0;
        foreach ($rows as $row) {
            if ((int)($row['id_user'] ?? 0) === $idUser && (int)($row['id_slot'] ?? 0) === $idSlot) {
                $idDrOsoby = (int)($row['id_dr_osoby'] ?? 0);
                break;
            }
        }
        $sendJson(200, ['ok' => true, 'id_dr' => $idDr, 'id_dr_osoby' => $idDrOsoby]);
    }

    if ($action === 'delete_person') {
        cb_db_dr_pracovni_osoby_delete($conn, $idDr, $idUser, $idSlot);
        $sendJson(200, ['ok' => true, 'id_dr' => $idDr]);
    }

    if ($action === 'update_time') {
        $assertPersonRow();
        $smenaOd = trim((string)($_POST['smena_od'] ?? ''));
        $smenaDo = trim((string)($_POST['smena_do'] ?? ''));
        $pauzaRaw = str_replace(',', '.', trim((string)($_POST['pauza'] ?? '')));
        $odpracovanoRaw = str_replace(',', '.', trim((string)($_POST['odpracovano'] ?? '')));

        if ($smenaOd !== '' && !preg_match('/^\d{2}:\d{2}$/', $smenaOd)) {
            $sendJson(422, ['ok' => false, 'err' => 'Neplatny cas od']);
        }
        if ($smenaDo !== '' && !preg_match('/^\d{2}:\d{2}$/', $smenaDo)) {
            $sendJson(422, ['ok' => false, 'err' => 'Neplatny cas do']);
        }
        $pauza = $pauzaRaw === '' ? null : (float)$pauzaRaw;
        $odpracovano = $odpracovanoRaw === '' ? null : (float)$odpracovanoRaw;

        cb_db_dr_pracovni_osoby_update_time(
            $conn,
            $idDrOsoby,
            $smenaOd === '' ? null : $smenaOd,
            $smenaDo === '' ? null : $smenaDo,
            $pauza,
            $odpracovano
        );
        $sendJson(200, ['ok' => true, 'id_dr' => $idDr]);
    }

    if ($action === 'update_kuryr') {
        $assertPersonRow();
        if ($idSlot !== 2) {
            $sendJson(422, ['ok' => false, 'err' => 'Radek neni kuryr']);
        }
        $manualRaw = trim((string)($_POST['rozvozu_manual'] ?? ''));
        $rozvozuManual = $manualRaw === '' ? null : max(0, (int)$manualRaw);
        $vlastniVuz = (int)($_POST['vlastni_vuz'] ?? 0) === 1 ? 1 : 0;
        $vyplatitPhm = (float)str_replace(',', '.', trim((string)($_POST['vyplatit_phm'] ?? '0')));

        cb_db_dr_pracovni_osoby_update_kuryr($conn, $idDrOsoby, $rozvozuManual, $vlastniVuz, $vyplatitPhm);
        $sendJson(200, ['ok' => true, 'id_dr' => $idDr]);
    }
} catch (Throwable $e) {
    $sendJson(500, ['ok' => false, 'err' => 'Ulozeni pracovního reportu selhalo']);
}

$sendJson(422, ['ok' => false, 'err' => 'Neplatna akce']);

// lib/handle_dr_pracovni.php * Konec souboru
