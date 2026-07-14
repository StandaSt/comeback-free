<?php
// ajax/helpdesk_detail.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../lib/session_boot.php';
    require_once __DIR__ . '/../lib/app.php';
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
    $idHelpdesk = (int)($_GET['id_helpdesk'] ?? 0);
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
        SELECT h.id_helpdesk, h.id_user_zalozil, h.typ, h.stav, h.verejny, h.predmet, h.popis,
               h.vytvoreno, h.upraveno, h.uzavreno, u.jmeno, u.prijmeni
        FROM helpdesk h
        LEFT JOIN `user` u ON u.id_user = h.id_user_zalozil
        WHERE h.id_helpdesk = ?
        LIMIT 1
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se načíst požadavek.');
    }
    $stmt->bind_param('i', $idHelpdesk);
    $stmt->execute();
    $res = $stmt->get_result();
    $ticket = null;
    if ($res instanceof mysqli_result) {
        $ticket = $res->fetch_assoc();
        $res->free();
    }
    $stmt->close();

    if (!is_array($ticket)) {
        throw new RuntimeException('Požadavek nenalezen.');
    }

    cb_helpdesk_mark_read($conn, $idHelpdesk, $idUser);

    $zpravy = [];
    $stmtZ = $conn->prepare('
        SELECT z.id_helpdesk_zprava, z.id_helpdesk, z.id_user, z.typ_autora, z.zprava, z.systemova, z.vytvoreno,
               u.jmeno, u.prijmeni
        FROM helpdesk_zprava z
        LEFT JOIN `user` u ON u.id_user = z.id_user
        WHERE z.id_helpdesk = ?
        ORDER BY z.vytvoreno ASC, z.id_helpdesk_zprava ASC
    ');
    if ($stmtZ instanceof mysqli_stmt) {
        $stmtZ->bind_param('i', $idHelpdesk);
        $stmtZ->execute();
        $resZ = $stmtZ->get_result();
        if ($resZ instanceof mysqli_result) {
            while ($row = $resZ->fetch_assoc()) {
                $zpravy[] = $row;
            }
            $resZ->free();
        }
        $stmtZ->close();
    }

    $prilohy = [];
    $stmtP = $conn->prepare('
        SELECT id_helpdesk_priloha, id_helpdesk, id_helpdesk_zprava, id_user, puvodni_nazev, cesta, mime_typ, velikost_b, vytvoreno
        FROM helpdesk_priloha
        WHERE id_helpdesk = ?
        ORDER BY vytvoreno ASC, id_helpdesk_priloha ASC
    ');
    if ($stmtP instanceof mysqli_stmt) {
        $stmtP->bind_param('i', $idHelpdesk);
        $stmtP->execute();
        $resP = $stmtP->get_result();
        if ($resP instanceof mysqli_result) {
            while ($row = $resP->fetch_assoc()) {
                $prilohy[] = $row;
            }
            $resP->free();
        }
        $stmtP->close();
    }

    echo json_encode([
        'ok' => true,
        'ticket' => $ticket,
        'zpravy' => $zpravy,
        'prilohy' => $prilohy,
        'admin' => cb_helpdesk_is_admin() ? 1 : 0,
        'current_user_id' => $idUser,
        'can_write' => cb_helpdesk_can_write($conn, $idHelpdesk, $idUser) ? 1 : 0,
        'has_new_reply' => 0,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_detail.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
