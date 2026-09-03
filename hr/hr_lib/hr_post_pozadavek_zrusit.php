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

        hr_zrus_pozadavek($db, (int)($_POST['id_pozadavek'] ?? 0), hr_current_user_id());
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=pozadavky'),
            true,
            'Požadavek byl zrušen.'
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
