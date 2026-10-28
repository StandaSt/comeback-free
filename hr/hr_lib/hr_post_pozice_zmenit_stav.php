<?php
declare(strict_types=1);

/* Účel: Zpracuje změnu aktivity pracovního slotu v číselníku včetně oprávnění a auditu HR. */
require_once __DIR__ . '/hr_audit.php';

function hr_post_pozice_zmenit_stav(mysqli $db): void
{
    try {
        if (!cb_hr_nastaveni_ma_pravo()) {
            throw new CbUserVisibleException('Nemáte právo spravovat nastavení HR.');
        }
        hr_nastaveni_pozice_zmenit_stav($db, (int)($_POST['id_slot'] ?? -1), (int)($_POST['aktivni'] ?? 0) === 1);
        hr_audit_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), true, 'Stav slotu byl změněn.');
    } catch (Throwable $e) {
        hr_audit_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), false, cb_hr_chyba_text($e, 'Změna stavu slotu', ['table' => 'cis_slot']));
    }
}
