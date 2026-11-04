<?php
// helpdesk/hl_ajax/hl_stav_zmenit.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);
/* Účel: Mění stav autorizovaného tiketu a společnou historií zachovává původní i novou číselníkovou hodnotu. */

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

    if (!cb_pravo_ma(602)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'Nemáte právo měnit stav tiketu.'], JSON_UNESCAPED_UNICODE);
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
    $stav = trim((string)($data['stav'] ?? ''));
    if (!in_array($stav, ['nový', 'řeší se', 'vyřešeno'], true)) {
        throw new CbUserVisibleException('Vyberte platný stav tiketu.');
    }

    if ($idHelpdesk <= 0) {
        throw new CbUserVisibleException('Chybí číslo požadavku.');
    }

    $uzavrenoSql = 'NULL';
    if ($stav === 'vyřešeno') {
        $uzavrenoSql = 'NOW()';
    }

    $conn = db();
    if (!cb_helpdesk_can_view($conn, $idHelpdesk, $idUser)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'err' => 'Nemáte přístup k požadavku.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Změna a její historie se potvrdí společně; souběžný zápis nesmí změnit původní hodnotu.
    $conn->begin_transaction();
    $before=cb_cinnost_db_jeden($conn,'SELECT stav FROM helpdesk WHERE id_helpdesk=? FOR UPDATE',[$idHelpdesk]);
    $sql = '
        UPDATE helpdesk
        SET stav = ?, upraveno = NOW(), posledni_zprava = NOW(), uzavreno = ' . $uzavrenoSql . '
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

    if (($before['stav'] ?? null)!==$stav) {
        cb_cinnost_zapsat(CbCinnostAkce::POLE_ULOZENO,cb_helpdesk_cinnost_cil($conn,$idHelpdesk)+[
            'id_pole'=>CbHelpdeskCinnost::STAV,'data'=>['puvodni'=>cb_helpdesk_cinnost_stav((string)$before['stav']),'nova'=>cb_helpdesk_cinnost_stav($stav)]]);
    }
    if ($stav === 'vyřešeno') {
        cb_helpdesk_notifikace_ucastnikum(
            $conn,
            $idHelpdesk,
            null,
            $idUser,
            'zmena_stavu',
            'Tiket č. ' . (string)$idHelpdesk . ' byl uzavřen a označen jako vyřešený'
        );
    }

    $conn->commit();
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($conn)) { try { $conn->rollback(); } catch (Throwable $rollbackError) { error_log('Helpdesk: rollback stavu selhal.'); } }
    cb_helpdesk_json_chyba($e, 'Změna stavu tiketu', ['table' => 'helpdesk']);
}

// helpdesk/hl_ajax/hl_stav_zmenit.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
