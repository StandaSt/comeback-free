<?php
/* =========================
   0) Nastaveni pobocky do session (POST)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_SET_BRANCH'])
) {
    header('Content-Type: application/json; charset=utf-8');

    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Neplatny JSON'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idPob = (int)($data['id_pob'] ?? 0);
    if ($idPob <= 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'err' => 'Neplatna pobocka'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $_SESSION['cb_pobocka_id'] = $idPob;
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

/* =========================
   0c) Touch aktivity (POST)
   ========================= */
if (
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && isset($_SERVER['HTTP_X_COMEBACK_TOUCH'])
) {
    $nowTs = time();
    if (!isset($_SESSION['cb_session_start_ts']) || (int)$_SESSION['cb_session_start_ts'] <= 0) {
        $_SESSION['cb_session_start_ts'] = $nowTs;
    }
    $_SESSION['cb_last_activity_ts'] = $nowTs;
    http_response_code(204);
    exit;
}
