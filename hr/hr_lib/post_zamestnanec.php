<?php
declare(strict_types=1);

/* Audit HR se potvrzuje spolecne se zmenou; neuspesny pokus zustava odlisnou udalosti. */
require_once __DIR__ . '/hr_audit.php';

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
        // Pozvánka smí odcházet až po potvrzení zaměstnance i jeho auditu.
        hr_audit_nova_osoba($db, $idPerson);
        hr_audit_dokoncit($db, true, 'Zaměstnanec byl uložen.');
        cb_user_spojeni_odeslat($db, (int)$employee['id_user']);
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)),
            true,
            'Zaměstnanec byl uložen.'
        );
    } catch (Throwable $e) {
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=novy_zamestnanec'),
            false,
            cb_hr_chyba_text($e, 'Založení zaměstnance', ['table' => 'hr_person']),
            $_POST
        );
    }
}
