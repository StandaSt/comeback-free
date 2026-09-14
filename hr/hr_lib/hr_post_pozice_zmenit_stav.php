<?php
declare(strict_types=1);

function hr_post_pozice_zmenit_stav(mysqli $db): void
{
    try {
        if (!cb_hr_nastaveni_ma_pravo()) {
            throw new RuntimeException('Nemáte právo spravovat nastavení HR.');
        }
        hr_nastaveni_pozice_zmenit_stav($db, (int)($_POST['id_slot'] ?? -1), (int)($_POST['aktivni'] ?? 0) === 1);
        cb_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), true, 'Stav pozice byl změněn.');
    } catch (Throwable $e) {
        cb_form_finish(cb_root_url('index.php?m=hr&page=nastaveni'), false, $e->getMessage());
    }
}
