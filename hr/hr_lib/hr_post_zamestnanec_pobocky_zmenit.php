<?php
declare(strict_types=1);

// Ucel: Overi pravo, kombinovany vyber oblasti a pobocek a data ucinnosti mist a mnoziny MAIN.
require_once __DIR__ . '/../hr_db/hr_mista_zapis.php';

// Odeslany formular ulozi atomicky; pouhe zaskrtnuti nikdy nic nezapisuje.
function hr_post_zamestnanec_pobocky_zmenit(mysqli $db, int $idUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        if (!cb_pravo_ma(307)) {
            throw new CbUserVisibleException('Nemáte právo měnit pobočky zaměstnance.');
        }
        if ($idPerson<=0) throw new CbUserVisibleException('Vyberte zaměstnance.');
        $oblasti=hr_mista_id((array)($_POST['id_oblast_prace']??[]));
        $pobocky=hr_mista_id((array)($_POST['id_pob']??[]));
        $main=hr_mista_id((array)($_POST['id_main']??[]));
        $platiOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['platnost_od'] ?? ''), 'Platí od');
        $mainOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['main_platnost_od'] ?? ''), 'MAIN platí od');
        hr_mista_zmenit($db,$idPerson,$oblasti,$pobocky,$main,$platiOd,$mainOd,$idUser);
        cb_form_finish(hr_pracovni_pomer_url($idPerson), true, 'Místo výkonu práce a MAIN pobočky byly uloženy.');
    } catch (Throwable $e) {
        cb_form_finish(hr_pracovni_pomer_url($idPerson), false, cb_hr_chyba_text($e, 'Změna poboček zaměstnance', ['table' => 'hr_misto_vykonu_prace']));
    }
}
