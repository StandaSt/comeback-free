<?php
declare(strict_types=1);

/* Účel: Zpracuje založení zaměstnance s auditem; při nepotvrzení uklidí i novou fotografii podle ID. */
require_once __DIR__ . '/hr_audit.php';

/*
 * Ucel souboru: Zpracuje akci ulozeni noveho zamestnance do HR evidence.
 * Provadi validaci a zapis pres HR DB logiku; neresi vyber HTTP akce ani layout.
 */
function hr_post_zamestnanec(mysqli $db, int $idUser): void
{
    $employee = null;
    $confirmed = false;
    try {
        if (!cb_pravo_ma(305)) {
            throw new CbUserVisibleException('Nemáte právo založit zaměstnance.');
        }
        $employee = hr_insert_employee($db, $_POST, $_FILES, $idUser);
        $idPerson = (int)$employee['id_person'];
        // Pozvánka smí odcházet až po potvrzení zaměstnance i jeho auditu.
        hr_audit_nova_osoba($db, $idPerson);
        hr_audit_dokoncit($db, true, 'Zaměstnanec byl uložen.');
        $confirmed = true;
        cb_user_spojeni_odeslat($db, (int)$employee['id_user']);
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)),
            true,
            'Zaměstnanec byl uložen.'
        );
    } catch (Throwable $e) {
        // Při vrácení nového zaměstnance v DB nesmí zůstat portrét pro pozdější znovupoužití ID.
        if (!$confirmed && !empty($employee['foto'])) {
            $photoFile = dirname(__DIR__, 2) . '/' . $employee['foto'];
            if (is_file($photoFile)) unlink($photoFile);
        }
        hr_audit_form_finish(
            cb_root_url('index.php?m=hr&page=novy_zamestnanec'),
            false,
            cb_hr_chyba_text($e, 'Založení zaměstnance', ['table' => 'hr_person']),
            $_POST
        );
    }
}
