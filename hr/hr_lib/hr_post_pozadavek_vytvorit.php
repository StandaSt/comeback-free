<?php
/*
 * Ucel souboru: Zpracuje vytvoreni jednoho nebo vice HR pozadavku.
 * Overuje pravo, zapisuje data a provede jednotny 303 redirect.
 */
declare(strict_types=1);

/* Audit HR se potvrzuje spolecne se zmenou; neuspesny pokus zustava odlisnou udalosti. */
require_once __DIR__ . '/hr_audit.php';

function hr_post_pozadavek_vytvorit(mysqli $db, array $user): void
{
    try {
        if (!cb_pravo_ma(312)) {
            throw new CbUserVisibleException('Nemáte právo zadat HR požadavek.');
        }

        $idUser = (int)($user['id_user'] ?? 0);
        $mainPobocka = hr_nacti_hlavni_pobocku_uzivatele($db, $idUser);
        $pocet = (int)($_POST['pocet'] ?? 1);
        $idSlot = (int)($_POST['id_slot'] ?? 0);
        $upresneni = mb_substr(trim((string)($_POST['upresneni'] ?? '')), 0, 500);
        hr_uloz_pozadavek($db, (int)$mainPobocka['id_pob'], $idSlot, $pocet, $upresneni, $idUser);
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=pozadavky'),
            true,
            'Požadavek byl uložen.'
        );
    } catch (Throwable $e) {
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=pozadavky'),
            false,
            cb_hr_chyba_text($e, 'Založení HR požadavku', ['table' => 'hr_pozadavky']),
            $_POST
        );
    }
}
