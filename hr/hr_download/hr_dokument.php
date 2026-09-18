<?php
declare(strict_types=1);

/*
 * Samostatny vystup souboru HR dokumentu bez vykresleni spolecneho HTML obalu.
 * Pred odeslanim vzdy overi prihlaseni, prava a firemni pristup k zamestnanci.
 */

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/db/db_prava.php';
require_once __DIR__ . '/../../common/lib/firemni_pristup.php';
require_once __DIR__ . '/../hr_db/dokumenty_uchazecu.php';

cb_session_guard_entry();

if (!empty($_SESSION['login_ok']) && !cb_session_validate_after_login()) {
    cb_session_forget_auth();
}
if (empty($_SESSION['login_ok'])) {
    http_response_code(401);
    exit;
}

$user = $_SESSION['cb_user'] ?? [];
$idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
cb_db_prava_nacti_do_session(db(), $idUser);
if (!cb_pravo_ma(300) || !cb_pravo_ma(301)) {
    http_response_code(403);
    exit;
}

hr_stream_employee_document(
    db(),
    $idUser,
    max(0, (int)($_GET['id_dokument'] ?? 0)),
    max(1, (int)($_GET['verze'] ?? 1))
);
