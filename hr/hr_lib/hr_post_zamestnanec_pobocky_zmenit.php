<?php
declare(strict_types=1);

// Ucel: Overi pravo a samostatna data ucinnosti pobocek a MAIN; nula znamena vedomou volbu bez MAIN.

// Odeslany formular ulozi atomicky; pouhe zaskrtnuti nikdy nic nezapisuje.
function hr_post_zamestnanec_pobocky_zmenit(mysqli $db, int $idUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        if (!cb_pravo_ma(307)) {
            throw new CbUserVisibleException('Nemáte právo měnit pobočky zaměstnance.');
        }
        $pobocky = hr_employee_normalize_branch_ids($_POST['id_pob'] ?? []);
        $hlavniText = trim((string)($_POST['id_pob_hlavni'] ?? ''));
        if ($idPerson <= 0 || preg_match('/^(0|[1-9]\d*)$/', $hlavniText) !== 1) {
            throw new CbUserVisibleException('Vyberte zaměstnance, pobočky a hlavní pobočku.');
        }
        $platiOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['platnost_od'] ?? ''), 'Platí od');
        $mainOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['main_platnost_od'] ?? ''), 'MAIN platí od');
        hr_pracoviste_zmenit($db, $idPerson, $pobocky, (int)$hlavniText, $platiOd, $idUser, $mainOd);
        cb_form_finish(hr_pracovni_pomer_url($idPerson), true, 'Povolené pobočky a MAIN byly uloženy. Nezměněné záznamy zůstaly zachované.');
    } catch (Throwable $e) {
        cb_form_finish(hr_pracovni_pomer_url($idPerson), false, cb_hr_chyba_text($e, 'Změna poboček zaměstnance', ['table' => 'hr_pracoviste']));
    }
}
