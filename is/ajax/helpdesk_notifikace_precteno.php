<?php
// ajax/helpdesk_notifikace_precteno.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../../www/lib/session_boot.php';
    require_once __DIR__ . '/../../www/lib/app.php';
}
require_once __DIR__ . '/../lib/helpdesk_prava.php';

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
    if ($idUser <= 0) {
        throw new RuntimeException('Neznámý uživatel.');
    }

    $idNotifikace = (int)($data['id_helpdesk_notifikace'] ?? 0);
    $conn = db();

    if ($idNotifikace > 0) {
        $stmt = $conn->prepare('
            UPDATE helpdesk_notifikace
            SET precteno = COALESCE(precteno, NOW())
            WHERE id_helpdesk_notifikace = ? AND id_user = ?
            LIMIT 1
        ');
        if ($stmt instanceof mysqli_stmt) {
            $stmt->bind_param('ii', $idNotifikace, $idUser);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        $stmtAll = $conn->prepare('
            UPDATE helpdesk_notifikace
            SET precteno = COALESCE(precteno, NOW())
            WHERE id_user = ? AND precteno IS NULL
        ');
        if ($stmtAll instanceof mysqli_stmt) {
            $stmtAll->bind_param('i', $idUser);
            $stmtAll->execute();
            $stmtAll->close();
        }
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_notifikace_precteno.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
