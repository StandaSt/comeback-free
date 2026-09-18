<?php
declare(strict_types=1);

/*
 * Ucel souboru: Zpracuje akci ulozeni noveho zamestnance do HR evidence.
 * Provadi validaci a zapis pres HR DB logiku; neresi vyber HTTP akce ani layout.
 */
function hr_post_zamestnanec(mysqli $db, int $idUser): void
{
    try {
        if (!cb_pravo_ma(305)) {
            throw new CbUserVisibleException('Nemáte právo založit zaměstnance.');
        }
        $employee = hr_insert_employee($db, $_POST, $_FILES, $idUser);
        $idPerson = (int)$employee['id_person'];
        cb_user_spojeni_odeslat($db, (int)$employee['id_user']);
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)),
            true,
            'Zaměstnanec byl uložen.'
        );
    } catch (Throwable $e) {
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=novy_zamestnanec'),
            false,
            cb_hr_chyba_text($e, 'Založení zaměstnance', ['table' => 'hr_person']),
            $_POST
        );
    }
}
