<?php
/*
 * Ucel souboru: Zpracuje vytvoreni jednoho nebo vice HR pozadavku.
 * Overuje pravo, zapisuje data a provede jednotny 303 redirect.
 */
declare(strict_types=1);

function hr_post_pozadavek_vytvorit(mysqli $db, array $user): void
{
    try {
        if (!cb_pravo_ma(312)) {
            throw new RuntimeException('Na zadani pozadavku nemate pravo.');
        }

        $idUser = (int)($user['id_user'] ?? 0);
        $mainPobocka = hr_nacti_hlavni_pobocku_uzivatele($db, $idUser);
        try {
            $zadalPerson = hr_current_person_id($db);
        } catch (RuntimeException $e) {
            if (!cb_pravo_ma(311)) {
                throw $e;
            }
            $zadalPerson = 1;
        }

        $pocet = (int)($_POST['pocet'] ?? 1);
        $idSlot = (int)($_POST['id_slot'] ?? 0);
        $upresneni = mb_substr(trim((string)($_POST['upresneni'] ?? '')), 0, 500);
        hr_uloz_pozadavek($db, (int)$mainPobocka['id_pob'], $idSlot, $pocet, $upresneni, $zadalPerson);
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=pozadavky'),
            true,
            'Požadavek byl uložen.'
        );
    } catch (Throwable $e) {
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=pozadavky'),
            false,
            $e->getMessage(),
            $_POST
        );
    }
}
