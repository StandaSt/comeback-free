<?php
// helpdesk/hl_ajax/hl_sledovat.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);
/* Účel: Ukládá sledování tiketu a skutečnou změnu důvodu zaznamenává společným zapisovačem ve stejné transakci. */

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../../common/lib/session_boot.php';
    require_once __DIR__ . '/../../common/lib/app.php';
}
require_once __DIR__ . '/../hl_lib/hl_prava.php';
require_once __DIR__ . '/../hl_lib/hl_chyby.php';
require_once __DIR__ . '/../hl_lib/hl_notifikace.php';
require_once __DIR__ . '/../hl_lib/hl_cinnost.php';

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

    cb_crf_vyzaduj();

    $raw = (string)file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new CbUserVisibleException('Požadavek nemá platná data. Obnovte stránku a zkuste to znovu.');
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
        throw new CbUserVisibleException('Chybí číslo požadavku.');
    }

    $conn = db();
    if (!cb_helpdesk_can_view($conn, $idHelpdesk, $idUser)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'Nemáte přístup k požadavku.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Původní důvod čteme pod zámkem, aby historie nepopisovala souběžně přepsanou hodnotu.
    $conn->begin_transaction();
    $before=cb_cinnost_db_jeden($conn,'SELECT duvod FROM helpdesk_sledujici WHERE id_helpdesk=? AND id_user=? FOR UPDATE',[$idHelpdesk,$idUser]);
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

    if (($before['duvod'] ?? null)!==$duvod) {
        cb_cinnost_zapsat(CbCinnostAkce::POLE_ULOZENO,cb_helpdesk_cinnost_cil($conn,$idHelpdesk)+[
            'id_pole'=>CbHelpdeskCinnost::SLEDOVANI,'data'=>['puvodni'=>cb_helpdesk_cinnost_sledovani($before['duvod'] ?? null),'nova'=>cb_helpdesk_cinnost_sledovani($duvod)]]);
    }
    if ($duvod === 'stejny_problem') {
        cb_helpdesk_notifikace_adminum($conn, $idHelpdesk, null, $idUser, 'stejny_problem', 'Další uživatel má stejný problém v HelpDesku #' . (string)$idHelpdesk);
    }

    $conn->commit();
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($conn)) { try { $conn->rollback(); } catch (Throwable $rollbackError) { error_log('Helpdesk: rollback sledování selhal.'); } }
    cb_helpdesk_json_chyba($e, 'Přidání sledujícího k tiketu', ['table' => 'helpdesk_sledujici']);
}

// helpdesk/hl_ajax/hl_sledovat.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
