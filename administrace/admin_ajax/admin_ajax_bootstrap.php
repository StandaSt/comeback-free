<?php
declare(strict_types=1);

/*
 * Účel souboru: Společně připraví zabezpečené JSON odpovědi administračních AJAX endpointů.
 * Kontroluje metodu, projektovou hlavičku, přihlášení a roli Admin.
 */

require_once __DIR__ . '/../../common/lib/session_boot.php';
require_once __DIR__ . '/../../common/lib/ochrana_crf.php';
// DB konfigurace musí být načtena v globálním rozsahu stejně jako v hlavním index.php.
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';
require_once __DIR__ . '/../../common/db/db_prava.php';
require_once __DIR__ . '/../admin_lib/admin_chyby.php';

cb_session_guard_entry();

/** Spustí konkrétní AJAX akci a převede její výsledek nebo chybu na JSON. */
function cb_admin_ajax_spustit(callable $action): never
{
    header('Content-Type: application/json; charset=utf-8');

    try {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new CbUserVisibleException('Tuto akci nelze otevřít přímo. Obnovte stránku a zkuste ji znovu.');
        }
        if ((string)($_SERVER['HTTP_X_COMEBACK_ADMIN_EDITACE_PRAV'] ?? '') !== '1') {
            throw new CbUserVisibleException('Požadavek není platný. Obnovte stránku a zkuste akci znovu.');
        }
        if (empty($_SESSION['login_ok'])) {
            http_response_code(401);
            throw new CbUserVisibleException('Přihlášení vypršelo. Přihlaste se prosím znovu.');
        }

        cb_crf_vyzaduj();

        $user = $_SESSION['cb_user'] ?? [];
        $idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
        $roles = cb_db_user_role_data(db(), $idUser);
        if (!array_key_exists(1, $roles)) {
            http_response_code(403);
            throw new CbUserVisibleException('K editaci práv nemáte oprávnění.');
        }

        $result = $action();
        echo json_encode(['ok' => true] + (is_array($result) ? $result : []), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        cb_admin_json_chyba($e, 'Editace práv');
    }
    exit;
}
