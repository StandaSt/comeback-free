<?php
// helpdesk/hl_ajax/hl_vytvorit.php * Verze: V1 * Aktualizace: 20.06.2026
declare(strict_types=1);

if (!defined('CB_HELPDESK_DISPATCH_INTERNAL')) {
    require_once __DIR__ . '/../../common/lib/session_boot.php';
    require_once __DIR__ . '/../../common/lib/app.php';
}
require_once __DIR__ . '/../hl_lib/hl_prava.php';
require_once __DIR__ . '/../hl_lib/hl_snapshot.php';
require_once __DIR__ . '/../hl_lib/hl_notifikace.php';
require_once __DIR__ . '/../hl_lib/hl_upload.php';

$redirectBase = cb_root_url('index.php?m=helpdesk&src=' . rawurlencode((string)($_SESSION['cb_helpdesk_source_module'] ?? 'provoz')));

try {
    if (empty($_SESSION['login_ok'])) {
        header('Location: ' . cb_root_url('index.php'));
        exit;
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Location: ' . $redirectBase . '&hd=new-ticket&err=method');
        exit;
    }

    cb_crf_vyzaduj();

    $data = $_POST;

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
    $modulKey = strtolower(trim((string)($data['modul'] ?? $_SESSION['cb_helpdesk_source_module'] ?? '')));
    $allowedAreas = cb_helpdesk_allowed_areas();
    if (!isset($allowedAreas[$modulKey])) {
        throw new RuntimeException('Zvolená oblast není dostupná.');
    }
    $modul = cb_helpdesk_area_id($modulKey);

    if ($predmet === '') {
        throw new RuntimeException('Chybí předmět.');
    }
    if ($popis === '') {
        throw new RuntimeException('Chybí popis.');
    }
    if (mb_strlen($popis, 'UTF-8') < 25) {
        throw new RuntimeException('Popis je příliš krátký.');
    }

    $conn = db();
    $uploadedAttachments = [];
    $conn->begin_transaction();

    $stmt = $conn->prepare('
        INSERT INTO helpdesk
        (id_firma, id_user_zalozil, modul, typ, stav, verejny, predmet, popis, pocet_zprav, vytvoreno, upraveno, posledni_zprava)
        VALUES (?, ?, ?, ?, \'nový\', ?, ?, ?, 1, NOW(), NOW(), NOW())
    ');
    if (!($stmt instanceof mysqli_stmt)) {
        throw new RuntimeException('Nepodařilo se připravit založení požadavku.');
    }

    $idFirma = cb_helpdesk_current_company_id();
    $stmt->bind_param('iiisiss', $idFirma, $idUser, $modul, $typ, $verejny, $predmet, $popis);
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

    $files = $_FILES['prilohy'] ?? null;
    if (is_array($files) && isset($files['error']) && is_array($files['error'])) {
        foreach ($files['error'] as $index => $error) {
            if ((int)$error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name' => (string)($files['name'][$index] ?? ''),
                'type' => (string)($files['type'][$index] ?? ''),
                'tmp_name' => (string)($files['tmp_name'][$index] ?? ''),
                'error' => (int)$error,
                'size' => (int)($files['size'][$index] ?? 0),
            ];
            $uploadedAttachments[] = cb_helpdesk_upload_priloha($conn, $idHelpdesk, $idZprava, $idUser, $file);
        }
    }

    cb_helpdesk_snapshot_zapis($conn, $idHelpdesk, $idZprava, $idUser);
    cb_helpdesk_notifikace_adminum($conn, $idHelpdesk, $idZprava, $idUser, 'novy_pozadavek', 'Nový HelpDesk požadavek #' . (string)$idHelpdesk . ': ' . $predmet);

    $conn->commit();

    header('Location: ' . $redirectBase . '&hd=mine&created=' . (string)$idHelpdesk);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->rollback();
    }
    foreach (($uploadedAttachments ?? []) as $uploadedAttachment) {
        cb_helpdesk_upload_smazat($uploadedAttachment);
    }
    header('Location: ' . $redirectBase . '&hd=new-ticket&err=save');
}

// helpdesk/hl_ajax/hl_vytvorit.php * Verze: V1 * Aktualizace: 20.06.2026
// Konec souboru
