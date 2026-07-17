<?php
// ajax/helpdesk_notifikace_nacist.php * Verze: V1 * Aktualizace: 20.06.2026
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

    $idUser = cb_helpdesk_current_user_id();
    if ($idUser <= 0) {
        throw new RuntimeException('Neznámý uživatel.');
    }

    $conn = db();
    $pocet = 0;
    $stmtC = $conn->prepare('SELECT COUNT(*) FROM helpdesk_notifikace WHERE id_user = ? AND precteno IS NULL');
    if ($stmtC instanceof mysqli_stmt) {
        $stmtC->bind_param('i', $idUser);
        $stmtC->execute();
        $stmtC->bind_result($pocetDb);
        if ($stmtC->fetch()) {
            $pocet = (int)$pocetDb;
        }
        $stmtC->close();
    }

    $notifikace = [];
    $stmt = $conn->prepare('
        SELECT id_helpdesk_notifikace, id_helpdesk, id_helpdesk_zprava, typ, text, vytvoreno, precteno
        FROM helpdesk_notifikace
        WHERE id_user = ?
        ORDER BY vytvoreno DESC, id_helpdesk_notifikace DESC
        LIMIT 20
    ');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $notifikace[] = $row;
            }
            $res->free();
        }
        $stmt->close();
    }

    echo json_encode([
        'ok' => true,
        'neprecteno' => $pocet,
        'notifikace' => $notifikace,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_notifikace_nacist.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
