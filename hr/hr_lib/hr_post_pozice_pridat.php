<?php
declare(strict_types=1);

function hr_post_pozice_pridat(mysqli $db): void
{
    try {
        if (!cb_hr_nastaveni_ma_pravo()) {
            throw new CbUserVisibleException('Nemáte právo spravovat nastavení HR.');
        }
        hr_nastaveni_pozice_pridat($db, (string)($_POST['slot'] ?? ''));
        cb_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), true, 'Pozice byla přidána.');
    } catch (Throwable $e) {
        cb_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), false, cb_hr_chyba_text($e, 'Přidání pozice', ['table' => 'cis_slot']), $_POST);
    }
}
