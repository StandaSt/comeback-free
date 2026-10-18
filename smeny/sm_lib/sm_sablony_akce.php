<?php
declare(strict_types=1);

/* Účel souboru: Směruje zápisy šablon; automatickému ukládání vrací JSON se stavem, otiskem a ID slotů. */

function cb_smeny_sablony_akce(mysqli $db, array $branches): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return;
    }
    $action = trim((string)($_POST['action'] ?? ''));
    if (!str_starts_with($action, 'smeny_sablona_')) {
        return;
    }
    $automatic = $action === 'smeny_sablona_tyden_ulozit' && ($_POST['automaticky'] ?? '') === '1';
    try {
        if (!cb_smeny_sablony_ma_pravo()) {
            throw new CbUserVisibleException('Nemáte právo upravovat šablony směn.');
        }
        if (!cb_crf_platny()) {
            throw new CbUserVisibleException('Platnost stránky vypršela. Obnovte ji a změnu proveďte znovu.');
        }
        if ($automatic && trim((string)($_POST['otisk'] ?? '')) === '') {
            throw new CbUserVisibleException('Chybí ověření verze šablony. Obnovte stránku.');
        }
        if ($action === 'smeny_sablona_ulozit') {
            $idTemplate = cb_smeny_sablona_ulozit($db, $branches);
            cb_smeny_sablony_flash_ulozit('success', 'Šablona byla uložena.');
        } elseif ($action === 'smeny_sablona_tyden_ulozit') {
            $savedBlocks = [];
            $savedFingerprint = '';
            $idTemplate = cb_smeny_sablona_tyden_ulozit($db, $branches, $savedBlocks, $savedFingerprint);
            if ($automatic) {
                header('Content-Type: application/json; charset=UTF-8');
                header('Cache-Control: no-store');
                echo json_encode(['ok'=>true, 'blocks'=>$savedBlocks, 'otisk'=>$savedFingerprint], JSON_THROW_ON_ERROR);
                exit;
            }
            cb_smeny_sablony_flash_ulozit('success', 'Celá šablona byla uložena.');
        } else {
            throw new CbUserVisibleException('Požadovaná akce šablony neexistuje.');
        }
    } catch (Throwable $e) {
        cb_smeny_aktivita_chyba($e);
        $message = cb_chyba_uzivatel($e, [
            'module' => 'smeny',
            'action' => 'Úprava šablony směn',
            'table' => 'smeny_sablona',
        ]);
        if ($automatic) {
            http_response_code(422);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store');
            echo json_encode(['ok'=>false, 'message'=>$message], JSON_THROW_ON_ERROR);
            exit;
        }
        cb_smeny_sablony_flash_ulozit('error', $message);
        $idTemplate = max(0, (int)($_POST['id_smeny_sablona'] ?? 0));
    }

    $url = 'index.php?m=smeny&page=sablony';
    if ($idTemplate > 0) {
        $url .= '&id=' . $idTemplate;
    }
    header('Location: ' . cb_root_url($url));
    exit;
}
