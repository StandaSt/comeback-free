<?php
declare(strict_types=1);

/* Účel: Ověří oprávnění a přidá pobočku společně s auditem; vrátí výsledek formuláře HR. */
require_once __DIR__ . '/hr_audit.php';

function hr_post_pobocka_pridat(mysqli $db, int $idUser): void
{
    try {
        if (!cb_hr_nastaveni_ma_pravo()) {
            throw new CbUserVisibleException('Nemáte právo spravovat nastavení HR.');
        }
        hr_nastaveni_pobocka_pridat($db, $_POST, $idUser);
        hr_audit_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), true, 'Pobočka byla přidána.');
    } catch (Throwable $e) {
        hr_audit_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), false, cb_hr_chyba_text($e, 'Přidání pobočky', ['table' => 'pobocky']), $_POST);
    }
}
