<?php
/* =========================
   0a) Registrace zarizeni (JSON)
   ========================= */
if (isset($_GET['action']) && (string)$_GET['action'] === 'registrace_check') {
    header('Content-Type: application/json; charset=utf-8');

    $loginOk = !empty($_SESSION['login_ok']);
    $cbAuthOk = !empty($_SESSION['cb_auth_ok']);
    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

    if ((!$loginOk && !$cbAuthOk) || $idUser <= 0) {
        echo json_encode([
            'ok' => true,
            'paired' => false,
            'debug' => [
                'login_ok' => $loginOk,
                'cb_auth_ok' => $cbAuthOk,
                'id_user' => $idUser,
                'reason' => 'missing_login_flow_session',
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paired = false;
    $deviceId = 0;
    $stmt = db()->prepare('
        SELECT id
        FROM push_zarizeni
        WHERE id_user=? AND aktivni=1
        LIMIT 1
    ');
    if ($stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->store_result();
        $paired = ($stmt->num_rows > 0);
        $stmt->bind_result($deviceId);
        if ($paired) {
            $stmt->fetch();
            $deviceId = (int)$deviceId;
        }
        $stmt->close();
    }

    $loginPromoted = false;
    if ($paired && !$loginOk && $cbAuthOk) {
        $loginToken = (string)($_SESSION['cb_token'] ?? '');
        if ($loginToken === '') {
            echo json_encode([
                'ok' => false,
                'err' => 'Chybí token pro dokončení přihlášení.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_auth_ok']);
        require_once __DIR__ . '/smeny_graphql.php';
        try {
            cb_login_finalize_after_ok($loginToken, 20);
        } catch (Throwable $e) {
            unset($_SESSION['login_ok']);
            $_SESSION['cb_auth_ok'] = 1;
            echo json_encode([
                'ok' => false,
                'err' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $loginPromoted = true;
    }

    echo json_encode([
        'ok' => true,
        'paired' => $paired,
        'debug' => [
            'login_ok' => !empty($_SESSION['login_ok']),
            'cb_auth_ok' => !empty($_SESSION['cb_auth_ok']),
            'id_user' => $idUser,
            'device_id' => $deviceId,
            'login_promoted' => $loginPromoted,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['action']) && (string)$_GET['action'] === 'registrace_abort') {
    header('Content-Type: application/json; charset=utf-8');

    $loginOk = !empty($_SESSION['login_ok']);
    $cbAuthOk = !empty($_SESSION['cb_auth_ok']);
    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = (is_array($cbUser) && isset($cbUser['id_user'])) ? (int)$cbUser['id_user'] : 0;

    if (($loginOk || $cbAuthOk) && $idUser > 0) {
        $stmt = db()->prepare('
            UPDATE push_parovani
            SET aktivni=0
            WHERE id_user=? AND aktivni=1 AND pouzito_kdy IS NULL
        ');
        if ($stmt) {
            $stmt->bind_param('i', $idUser);
            $stmt->execute();
            $stmt->close();
        }
    }

    $_SESSION = [];
    session_destroy();

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
