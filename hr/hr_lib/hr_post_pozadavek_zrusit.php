<?php
/*
 * Ucel souboru: Zpracuje zruseni vlastniho otevreneho HR pozadavku.
 * Overuje pravo, meni stav pozadavku a provede jednotny 303 redirect.
 */
declare(strict_types=1);

/* Audit HR se potvrzuje spolecne se zmenou; neuspesny pokus zustava odlisnou udalosti. */
require_once __DIR__ . '/hr_audit.php';

function hr_post_pozadavek_zrusit(mysqli $db): void
{
    try {
        if (!cb_pravo_ma(314)) {
            throw new CbUserVisibleException('Nemáte právo zrušit HR požadavek.');
        }

        hr_zrus_pozadavek($db, (int)($_POST['id_pozadavek'] ?? 0), hr_current_user_id());
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=pozadavky'),
            true,
            'Požadavek byl zrušen.'
        );
    } catch (Throwable $e) {
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=pozadavky'),
            false,
            cb_hr_chyba_text($e, 'Zrušení HR požadavku', ['table' => 'hr_pozadavky']),
            $_POST
        );
    }
}
