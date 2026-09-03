<?php
// helpdesk/hl_ajax/hl_notifikace_nacist.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../../common/lib/session_boot.php';
    require_once __DIR__ . '/../../common/lib/app.php';
}
require_once __DIR__ . '/../hl_lib/hl_prava.php';

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
    $idFirma = cb_helpdesk_current_company_id();
    $pocet = 0;
    $stmtC = $conn->prepare('
        SELECT COUNT(*)
        FROM helpdesk_notifikace n
        INNER JOIN helpdesk h ON h.id_helpdesk = n.id_helpdesk
        WHERE n.id_user = ? AND n.precteno IS NULL AND COALESCE(h.id_firma, 1) = ?
    ');
    if ($stmtC instanceof mysqli_stmt) {
        $stmtC->bind_param('ii', $idUser, $idFirma);
        $stmtC->execute();
        $stmtC->bind_result($pocetDb);
        if ($stmtC->fetch()) {
            $pocet = (int)$pocetDb;
        }
        $stmtC->close();
    }

    $notifikace = [];
    $stmt = $conn->prepare('
        SELECT n.id_helpdesk_notifikace, n.id_helpdesk, n.id_helpdesk_zprava, n.typ, n.text, n.vytvoreno, n.precteno
        FROM helpdesk_notifikace n
        INNER JOIN helpdesk h ON h.id_helpdesk = n.id_helpdesk
        WHERE n.id_user = ? AND COALESCE(h.id_firma, 1) = ?
        ORDER BY n.vytvoreno DESC, n.id_helpdesk_notifikace DESC
        LIMIT 20
    ');
    if ($stmt instanceof mysqli_stmt) {
        $stmt->bind_param('ii', $idUser, $idFirma);
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

// helpdesk/hl_ajax/hl_notifikace_nacist.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
