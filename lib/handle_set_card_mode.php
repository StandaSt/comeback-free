<?php
// lib/handle_set_card_mode.php * Verze: V1 * Aktualizace: 23.04.2026
declare(strict_types=1);

if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_CARD_MODE'])
) {
    header('Content-Type: application/json; charset=utf-8');

    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;
    if ($idUser <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutne prihlaseni'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Neplatny JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idKarta = (int)($data['id_karta'] ?? 0);
    $mode = trim((string)($data['mode'] ?? ''));
    if ($idKarta <= 0 || !in_array($mode, ['mini', 'maxi', 'nano'], true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatny vstup'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $conn = db();
        if ($mode === 'nano') {
            $maxNano = 9;
            $stmtCnt = $conn->prepare('SELECT COUNT(*) FROM user_nano WHERE id_user = ? AND id_nano <> ?');
            if (!$stmtCnt) {
                throw new RuntimeException('prepare count user_nano failed');
            }
            $stmtCnt->bind_param('ii', $idUser, $idKarta);
            $stmtCnt->execute();
            $stmtCnt->bind_result($nanoCount);
            $stmtCnt->fetch();
            $stmtCnt->close();

            if ((int)$nanoCount >= $maxNano) {
                http_response_code(409);
                echo json_encode(['ok' => false, 'err' => 'Nano režim je omezen na 9 karet. Desátou kartu nelze přidat.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $stmt = $conn->prepare('INSERT IGNORE INTO user_nano (id_user, id_nano) VALUES (?, ?)');
            if (!$stmt) {
                throw new RuntimeException('prepare insert user_nano failed');
            }
            $stmt->bind_param('ii', $idUser, $idKarta);
            $stmt->execute();
            $stmt->close();

            $stmtUnlock = $conn->prepare('UPDATE user_card_set SET col = NULL, line = NULL WHERE id_user = ? AND id_karta = ?');
            if (!$stmtUnlock) {
                throw new RuntimeException('prepare unlock card position failed');
            }
            $stmtUnlock->bind_param('ii', $idUser, $idKarta);
            $stmtUnlock->execute();
            $stmtUnlock->close();
        } else {
            $stmt = $conn->prepare('DELETE FROM user_nano WHERE id_user = ? AND id_nano = ?');
            if (!$stmt) {
                throw new RuntimeException('prepare delete user_nano failed');
            }
            $stmt->bind_param('ii', $idUser, $idKarta);
            $stmt->execute();
            $stmt->close();
        }

        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'err' => 'Ulozeni rezimu karty selhalo'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
