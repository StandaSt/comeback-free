<?php
// ajax/helpdesk_stav_zmenit.php * Verze: V1 * Aktualizace: 20.06.2026
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

    if (!cb_helpdesk_is_admin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'Jen admin může měnit stav.'], JSON_UNESCAPED_UNICODE);
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
    $stav = trim((string)($data['stav'] ?? ''));
    if (!in_array($stav, ['nový', 'řeší se', 'vyřešeno', 'zamítnuto'], true)) {
        throw new RuntimeException('Neplatný stav.');
    }

    if ($idHelpdesk <= 0) {
        throw new RuntimeException('Chybí id_helpdesk.');
    }

    $uzavrenoSql = 'NULL';
    if (in_array($stav, ['vyřešeno', 'zamítnuto'], true)) {
        $uzavrenoSql = 'NOW()';
    }

    $conn = db();
    $sql = '
        UPDATE helpdesk
        SET stav = ?, upraveno = NOW(), uzavreno = ' . $uzavrenoSql . '
        WHERE id_helpdesk = ?
        LIMIT 1
    ';
    $stmt = $conn->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se připravit změnu stavu.');
    }

    $stmt->bind_param('si', $stav, $idHelpdesk);
    $stmt->execute();
    $stmt->close();

    if ($stav === 'zamítnuto') {
        cb_helpdesk_notifikace_ucastnikum(
            $conn,
            $idHelpdesk,
            null,
            $idUser,
            'zmena_stavu',
            'Tiket č. ' . (string)$idHelpdesk . ' byl zamítnut a nebude se řešit'
        );
    } elseif ($stav === 'vyřešeno') {
        cb_helpdesk_notifikace_ucastnikum(
            $conn,
            $idHelpdesk,
            null,
            $idUser,
            'zmena_stavu',
            'Tiket č. ' . (string)$idHelpdesk . ' byl uzavřen a označen jako vyřešený'
        );
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_stav_zmenit.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
