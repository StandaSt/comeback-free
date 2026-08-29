<?php
declare(strict_types=1);

/*
 * Účel souboru: Společně připraví zabezpečené JSON odpovědi administračních AJAX endpointů.
 * Kontroluje metodu, projektovou hlavičku, přihlášení a roli Admin.
 */

require_once __DIR__ . '/../../common/lib/session_boot.php';
// DB konfigurace musí být načtena v globálním rozsahu stejně jako v hlavním index.php.
require_once __DIR__ . '/../../common/config/secrets.php';
require_once __DIR__ . '/../../common/lib/app.php';
require_once __DIR__ . '/../../common/lib/uloz_akci.php';

cb_session_guard_entry();

/** Spustí konkrétní AJAX akci a převede její výsledek nebo chybu na JSON. */
function cb_admin_ajax_spustit(callable $action): never
{
    header('Content-Type: application/json; charset=utf-8');

    try {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            throw new RuntimeException('AJAX endpoint přijímá pouze POST.');
        }
        if ((string)($_SERVER['HTTP_X_COMEBACK_ADMIN_EDITACE_PRAV'] ?? '') !== '1') {
            throw new RuntimeException('Chybí hlavička požadavku pro editaci práv.');
        }
        if (empty($_SESSION['login_ok'])) {
            http_response_code(401);
            throw new RuntimeException('Přihlášení vypršelo.');
        }

        $user = $_SESSION['cb_user'] ?? [];
        if (!is_array($user) || (int)($user['id_role'] ?? 0) !== 1) {
            http_response_code(403);
            throw new RuntimeException('Editace práv je dostupná pouze roli Admin.');
        }

        $result = $action();
        echo json_encode(['ok' => true] + (is_array($result) ? $result : []), JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        // Chyba se neskrývá: klient dostane konkrétní text a server jej současně zapíše do error logu.
        error_log('[admin_editace_prav] ' . $e->getMessage());
        if (http_response_code() < 400) {
            http_response_code(400);
        }
        echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
