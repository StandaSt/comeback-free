<?php
// ajax/helpdesk_zprava_pridat.php * Verze: V1 * Aktualizace: 24.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../lib/session_boot.php';
    require_once __DIR__ . '/../lib/app.php';
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
    $idHelpdesk = (int)($data['id_helpdesk'] ?? 0);
    $zprava = trim((string)($data['zprava'] ?? ''));

    if ($idUser <= 0) {
        throw new RuntimeException('Neznámý uživatel.');
    }
    if ($idHelpdesk <= 0) {
        throw new RuntimeException('Chybí id_helpdesk.');
    }
    if ($zprava === '') {
        throw new RuntimeException('Zpráva je prázdná.');
    }

    $conn = db();
    if (!cb_helpdesk_can_write($conn, $idHelpdesk, $idUser)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'Nemáte právo odpovědět.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $typAutora = cb_helpdesk_is_admin() ? 'admin' : 'user';

    $conn->begin_transaction();

    $stavPred = '';
    $stmtBefore = $conn->prepare('SELECT stav FROM helpdesk WHERE id_helpdesk = ? LIMIT 1');
    if ($stmtBefore instanceof mysqli_stmt) {
        $stmtBefore->bind_param('i', $idHelpdesk);
        $stmtBefore->execute();
        $stmtBefore->bind_result($stavPredDb);
        if ($stmtBefore->fetch()) {
            $stavPred = trim((string)$stavPredDb);
        }
        $stmtBefore->close();
    }

    $stmt = $conn->prepare('
        INSERT INTO helpdesk_zprava
        (id_helpdesk, id_user, typ_autora, zprava, systemova, vytvoreno)
        VALUES (?, ?, ?, ?, 0, NOW())
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se připravit zápis zprávy.');
    }

    $stmt->bind_param('iiss', $idHelpdesk, $idUser, $typAutora, $zprava);
    $stmt->execute();
    $idZprava = (int)$stmt->insert_id;
    $stmt->close();

    $novyStav = $stavPred;
    if (cb_helpdesk_is_admin() && $stavPred === 'nový') {
        $novyStav = 'řeší se';
    }

    if ($novyStav !== '' && $novyStav !== $stavPred) {
        $stmtU = $conn->prepare('UPDATE helpdesk SET stav = ?, upraveno = NOW() WHERE id_helpdesk = ? LIMIT 1');
        if ($stmtU instanceof mysqli_stmt) {
            $stmtU->bind_param('si', $novyStav, $idHelpdesk);
            $stmtU->execute();
            $stmtU->close();
        }
    } else {
        $stmtU = $conn->prepare('UPDATE helpdesk SET upraveno = NOW() WHERE id_helpdesk = ? LIMIT 1');
        if ($stmtU instanceof mysqli_stmt) {
            $stmtU->bind_param('i', $idHelpdesk);
            $stmtU->execute();
            $stmtU->close();
        }
    }

    $stmtS = $conn->prepare('
        INSERT INTO helpdesk_sledujici
        (id_helpdesk, id_user, duvod, vytvoreno)
        VALUES (?, ?, \'reagoval\', NOW())
        ON DUPLICATE KEY UPDATE duvod = duvod
    ');
    if ($stmtS instanceof mysqli_stmt) {
        $stmtS->bind_param('ii', $idHelpdesk, $idUser);
        $stmtS->execute();
        $stmtS->close();
    }

    cb_helpdesk_snapshot_zapis($conn, $idHelpdesk, $idZprava, $idUser);

    if (cb_helpdesk_is_admin()) {
        cb_helpdesk_notifikace_sledujicim_o_admin_odpovedi($conn, $idHelpdesk, $idZprava, $idUser, $zprava);
    } else {
        $textNotifikaceZprava = preg_replace('/\s+/u', ' ', $zprava);
        $textNotifikaceZprava = trim((string)$textNotifikaceZprava);
        if (mb_strlen($textNotifikaceZprava, 'UTF-8') > 180) {
            $textNotifikaceZprava = mb_substr($textNotifikaceZprava, 0, 177, 'UTF-8') . '...';
        }

        cb_helpdesk_notifikace_adminum(
            $conn,
            $idHelpdesk,
            $idZprava,
            $idUser,
            'nova_odpoved',
            'Uživatel reaguje na tiket č.' . (string)$idHelpdesk . ': ' . $textNotifikaceZprava
        );
    }

    $conn->commit();

    echo json_encode([
        'ok' => true,
        'id_helpdesk' => $idHelpdesk,
        'id_helpdesk_zprava' => $idZprava,
        'stav' => $novyStav,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    http_response_code(400);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// ajax/helpdesk_zprava_pridat.php * Verze: V1 * Aktualizace: 24.06.2026
// Konec souboru
