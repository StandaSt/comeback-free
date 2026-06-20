<?php
// ajax/helpdesk_priloha_nahrat.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../lib/session_boot.php';
    require_once __DIR__ . '/../lib/app.php';
}
require_once __DIR__ . '/../lib/helpdesk_prava.php';
require_once __DIR__ . '/../lib/helpdesk_upload.php';
require_once __DIR__ . '/../lib/helpdesk_notifikace.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    if (empty($_SESSION['login_ok'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'err' => 'Nutné přihlášení.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'err' => 'Neplatná metoda.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idUser = cb_helpdesk_current_user_id();
    $idHelpdesk = (int)($_POST['id_helpdesk'] ?? 0);
    $idZprava = (int)($_POST['id_helpdesk_zprava'] ?? 0);
    $idZpravaDb = null;
    if ($idZprava > 0) {
        $idZpravaDb = $idZprava;
    }

    if ($idUser <= 0) {
        throw new RuntimeException('Neznámý uživatel.');
    }
    if ($idHelpdesk <= 0) {
        throw new RuntimeException('Chybí id_helpdesk.');
    }

    $conn = db();
    if (!cb_helpdesk_can_write($conn, $idHelpdesk, $idUser)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'Nemáte právo nahrát přílohu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!array_key_exists('soubor', $_FILES) || !is_array($_FILES['soubor'])) {
        throw new RuntimeException('Chybí soubor.');
    }

    $priloha = cb_helpdesk_upload_priloha($conn, $idHelpdesk, $idZpravaDb, $idUser, $_FILES['soubor']);

    if (cb_helpdesk_is_admin()) {
        cb_helpdesk_notifikace_ucastnikum($conn, $idHelpdesk, $idZpravaDb, $idUser, 'nova_priloha', 'Nová příloha v HelpDesku #' . (string)$idHelpdesk);
    } else {
        cb_helpdesk_notifikace_adminum($conn, $idHelpdesk, $idZpravaDb, $idUser, 'nova_priloha', 'Nová příloha v HelpDesku #' . (string)$idHelpdesk);
    }

    echo json_encode([
        'ok' => true,
        'priloha' => $priloha,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_priloha_nahrat.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
