<?php
declare(strict_types=1);

function hr_post_pobocka_pridat(mysqli $db, int $idUser): void
{
    try {
        if (!cb_hr_nastaveni_ma_pravo()) {
            throw new CbUserVisibleException('Nemáte právo spravovat nastavení HR.');
        }
        hr_nastaveni_pobocka_pridat($db, $_POST, $idUser);
        cb_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), true, 'Pobočka byla přidána.');
    } catch (Throwable $e) {
        cb_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), false, cb_hr_chyba_text($e, 'Přidání pobočky', ['table' => 'pobocky']), $_POST);
    }
}
