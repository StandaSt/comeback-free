<?php
// ajax/helpdesk_sledovat.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../lib/session_boot.php';
    require_once __DIR__ . '/../lib/app.php';
}
require_once __DIR__ . '/../lib/helpdesk_prava.php';
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

    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('Neplatná data.');
    }

    $idUser = cb_helpdesk_current_user_id();
    $idHelpdesk = (int)($data['id_helpdesk'] ?? 0);
    $duvod = trim((string)($data['duvod'] ?? 'stejny_problem'));
    if (!in_array($duvod, ['sleduje', 'stejny_problem', 'reagoval'], true)) {
        $duvod = 'stejny_problem';
    }

    if ($idUser <= 0) {
        throw new RuntimeException('Neznámý uživatel.');
    }
    if ($idHelpdesk <= 0) {
        throw new RuntimeException('Chybí id_helpdesk.');
    }

    $conn = db();
    if (!cb_helpdesk_can_view($conn, $idHelpdesk, $idUser)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'Nemáte přístup k požadavku.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $conn->prepare('
        INSERT INTO helpdesk_sledujici
        (id_helpdesk, id_user, duvod, vytvoreno)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE duvod = VALUES(duvod)
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se zapsat sledování.');
    }

    $stmt->bind_param('iis', $idHelpdesk, $idUser, $duvod);
    $stmt->execute();
    $stmt->close();

    if ($duvod === 'stejny_problem') {
        cb_helpdesk_notifikace_adminum($conn, $idHelpdesk, null, $idUser, 'stejny_problem', 'Další uživatel má stejný problém v HelpDesku #' . (string)$idHelpdesk);
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_sledovat.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
