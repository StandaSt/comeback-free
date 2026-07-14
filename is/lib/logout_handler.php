<?php
/* =========================
   0) Logout (GET)
   ========================= */
if (isset($_GET['action']) && (string)$_GET['action'] === 'logout') {
    require_once __DIR__ . '/../../www/db/db_user.php';

    $cbLogoutReasonRaw = trim((string)($_GET['duvod'] ?? '1'));
    $cbLogoutReason = ($cbLogoutReasonRaw === '0') ? 0 : 1;
    $cbUser = $_SESSION['cb_user'] ?? null;
    $idUser = 0;
    if (is_array($cbUser) && !empty($cbUser['id_user'])) {
        $idUser = (int)$cbUser['id_user'];
        $conn = db();
        cb_db_clear_online_login_flags($conn, $idUser);
        cb_db_insert_login_event($conn, $idUser, 0, $cbLogoutReason);
    }

    if (function_exists('cb_session_forget_auth')) {
        cb_session_forget_auth();
    }
    $_SESSION = [];
    session_destroy();

    header('Location: ' . cb_url('/'));
    exit;
}
