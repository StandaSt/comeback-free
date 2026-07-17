<?php
// ajax/helpdesk_vytvorit.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../../www/lib/session_boot.php';
    require_once __DIR__ . '/../../www/lib/app.php';
}
require_once __DIR__ . '/../lib/helpdesk_prava.php';
require_once __DIR__ . '/../lib/helpdesk_snapshot.php';
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
    if ($idUser <= 0) {
        throw new RuntimeException('Neznámý uživatel.');
    }

    $typ = trim((string)($data['typ'] ?? 'chyba'));
    if (!in_array($typ, ['chyba', 'dotaz', 'navrh'], true)) {
        $typ = 'chyba';
    }

    $predmet = trim((string)($data['predmet'] ?? ''));
    $popis = trim((string)($data['popis'] ?? ''));
    $urceni = trim((string)($data['urceni'] ?? 'reagovat'));
    $verejny = match ($urceni) {
        'admin' => 0,
        'cist' => 2,
        default => 1,
    };

    if ($predmet === '') {
        throw new RuntimeException('Chybí předmět.');
    }
    if ($popis === '') {
        throw new RuntimeException('Chybí popis.');
    }

    $conn = db();
    $conn->begin_transaction();

    $stmt = $conn->prepare('
        INSERT INTO helpdesk
        (id_user_zalozil, typ, stav, verejny, predmet, popis, vytvoreno, upraveno, posledni_zprava)
        VALUES (?, ?, \'nový\', ?, ?, ?, NOW(), NOW(), NOW())
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se připravit založení požadavku.');
    }

    $stmt->bind_param('isiss', $idUser, $typ, $verejny, $predmet, $popis);
    $stmt->execute();
    $idHelpdesk = (int)$stmt->insert_id;
    $stmt->close();

    if ($idHelpdesk <= 0) {
        throw new RuntimeException('Požadavek se nepodařilo založit.');
    }

    $typAutora = 'user';
    if (cb_helpdesk_is_admin()) {
        $typAutora = 'admin';
    }

    $stmtZ = $conn->prepare('
        INSERT INTO helpdesk_zprava
        (id_helpdesk, id_user, typ_autora, zprava, systemova, vytvoreno)
        VALUES (?, ?, ?, ?, 0, NOW())
    ');
    if (!($stmtZ instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se připravit první zprávu.');
    }

    $stmtZ->bind_param('iiss', $idHelpdesk, $idUser, $typAutora, $popis);
    $stmtZ->execute();
    $idZprava = (int)$stmtZ->insert_id;
    $stmtZ->close();

    $stmtS = $conn->prepare('
        INSERT INTO helpdesk_sledujici
        (id_helpdesk, id_user, duvod, vytvoreno)
        VALUES (?, ?, \'autor\', NOW())
        ON DUPLICATE KEY UPDATE duvod = duvod
    ');
    if ($stmtS instanceof mysqli_stmt) {
        $stmtS->bind_param('ii', $idHelpdesk, $idUser);
        $stmtS->execute();
        $stmtS->close();
    }

    cb_helpdesk_mark_read($conn, $idHelpdesk, $idUser);

    cb_helpdesk_snapshot_zapis($conn, $idHelpdesk, $idZprava, $idUser);
    cb_helpdesk_notifikace_adminum($conn, $idHelpdesk, $idZprava, $idUser, 'novy_pozadavek', 'Nový HelpDesk požadavek #' . (string)$idHelpdesk . ': ' . $predmet);

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'id_helpdesk' => $idHelpdesk,
        'id_helpdesk_zprava' => $idZprava,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_vytvorit.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
