<?php
declare(strict_types=1);

/* Účel: Změní aktivitu pobočky v povolené firmě a potvrdí změnu společně s auditem HR. */
require_once __DIR__ . '/hr_audit.php';

function hr_post_pobocka_zmenit_stav(mysqli $db, int $idUser): void
{
    try {
        if (!cb_hr_nastaveni_ma_pravo()) {
            throw new CbUserVisibleException('Nemáte právo spravovat nastavení HR.');
        }
        hr_nastaveni_pobocka_zmenit_stav($db, (int)($_POST['id_pob'] ?? -1), (int)($_POST['aktivni'] ?? 0) === 1, $idUser);
        hr_audit_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), true, 'Stav pobočky byl změněn.');
    } catch (Throwable $e) {
        hr_audit_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), false, cb_hr_chyba_text($e, 'Změna stavu pobočky', ['table' => 'pobocky']));
    }
}
