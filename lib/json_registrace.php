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
        echo json_encode(['ok' => true, 'paired' => false], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $paired = false;
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
        $stmt->close();
    }

    if ($paired && !$loginOk && $cbAuthOk) {
        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_auth_ok']);
    }

    echo json_encode(['ok' => true, 'paired' => $paired], JSON_UNESCAPED_UNICODE);
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
