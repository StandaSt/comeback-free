<?php
/*
 * Ucel souboru: Zpracuje zruseni vlastniho otevreneho HR pozadavku.
 * Overuje pravo, meni stav pozadavku a provede jednotny 303 redirect.
 */
declare(strict_types=1);

function hr_post_pozadavek_zrusit(mysqli $db): void
{
    try {
        if (!cb_pravo_ma(314)) {
            throw new RuntimeException('Na zruseni pozadavku nemate pravo.');
        }

        try {
            $zrusilPerson = hr_current_person_id($db);
        } catch (RuntimeException $e) {
            if (!cb_pravo_ma(311)) {
                throw $e;
            }
            $zrusilPerson = 1;
        }

        hr_zrus_pozadavek($db, (int)($_POST['id_pozadavek'] ?? 0), $zrusilPerson);
        $_SESSION['hr_flash'] = [
            'type' => 'hr_success',
            'text' => 'Požadavek byl zrušen.',
        ];
    } catch (Throwable $e) {
        $_SESSION['hr_flash'] = [
            'type' => 'hr_error',
            'text' => $e->getMessage(),
        ];
    }

    header('Location: ' . cb_root_url('index.php?m=hr&page=pozadavky'), true, 303);
    exit;
}
