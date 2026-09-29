<?php
declare(strict_types=1);

/* Ucel souboru: Zpracuje zmenu nebo odebrani jedine firemni funkce zamestnance. */

function hr_post_zamestnanec_funkce_zmenit(mysqli $db, int $idUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        if (!cb_pravo_ma(307)) {
            throw new CbUserVisibleException('Nemáte právo měnit funkci zaměstnance.');
        }
        $rawFunction = trim((string)($_POST['id_funkce'] ?? ''));
        $idFunkce = $rawFunction === '' ? null : (ctype_digit($rawFunction) ? (int)$rawFunction : -1);
        if ($idPerson <= 0 || $idFunkce === -1) {
            throw new CbUserVisibleException('Vyberte zaměstnance a platnou funkci.');
        }
        $platiOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['platnost_od'] ?? ''), 'Platí od');
        hr_funkce_zmenit($db, $idPerson, $idFunkce, isset($_POST['v_treninku']), $platiOd, $idUser);
        cb_form_finish(hr_pracovni_pomer_url($idPerson), true, $idFunkce === null ? 'Funkce byla odebrána.' : 'Funkce byla uložena.');
    } catch (Throwable $e) {
        cb_form_finish(hr_pracovni_pomer_url($idPerson), false, cb_hr_chyba_text($e, 'Změna funkce zaměstnance', ['table' => cb_hr_schema_table($db, 'funkce')]));
    }
}

