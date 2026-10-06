<?php
declare(strict_types=1);
/* Uklada novou polozku nebo davku zmen; JSON odpoved zachovava rozepsany druhy blok v prohlizeci. */

// Po ulozeni vraci uzivatele do centralni spravy ciselniku HR.
function hr_post_ciselnik_ulozit(mysqli $db): void
{
    // Zachova kontext karty, ze ktere uzivatel otevrel spravu ciselniku.
    $returnPerson = max(0, (int)($_POST['navrat_person'] ?? 0));
    $url = cb_root_url('index.php?m=hr&page=nastaveni' . ($returnPerson > 0 ? '&navrat_person=' . $returnPerson : ''));
    $async = isset($_SERVER['HTTP_X_COMEBACK_FORM']) && (string)($_POST['catalog_async'] ?? '') === '1';
    try {
        if (isset($_POST['rows'])) {
            $rows = json_decode((string)$_POST['rows'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($rows)) throw new CbUserVisibleException('Neplatná sada změn.');
            $saved = hr_ciselnik_davka_ulozit($db, (string)($_POST['typ'] ?? ''), $rows);
        } else {
            $saved = [hr_ciselnik_ulozit($db, (string)($_POST['typ'] ?? ''), (int)($_POST['id'] ?? 0), (string)($_POST['nazev'] ?? ''), isset($_POST['aktivni']), (int)($_POST['id_role'] ?? 0))];
        }
        if ($async) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success'=>true,'message'=>'Změny byly uloženy.','items'=>$saved], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            exit;
        }
        cb_form_finish($url, true, 'Položka číselníku byla uložena.');
    } catch (Throwable $e) {
        if ($async) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success'=>false,'message'=>cb_hr_chyba_text($e, 'Správa číselníků HR')], JSON_UNESCAPED_UNICODE);
            exit;
        }
        cb_form_finish($url, false, cb_hr_chyba_text($e, 'Správa číselníků HR'), $_POST);
    }
}
