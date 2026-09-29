<?php
declare(strict_types=1);

/* Ucel souboru: Prida zamestnanci dalsi pracovni slot bez ruseni jeho ostatnich slotu. */

function hr_post_zamestnanec_pozice_zmenit(mysqli $db, int $idUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        if (!cb_pravo_ma(307)) {
            throw new CbUserVisibleException('Nemáte právo měnit slot zaměstnance.');
        }
        $slot = trim((string)($_POST['id_slot'] ?? ''));
        if ($idPerson <= 0 || preg_match('/^\d+$/', $slot) !== 1) {
            throw new CbUserVisibleException('Vyberte zaměstnance a slot.');
        }
        $platiOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['platnost_od'] ?? ''), 'Platí od');
        hr_zarazeni_zmenit($db, $idPerson, (int)$slot, $platiOd, $idUser);
        cb_form_finish(hr_pracovni_pomer_url($idPerson), true, 'Slot byl přidán s platností od zadaného data.');
    } catch (Throwable $e) {
        cb_form_finish(hr_pracovni_pomer_url($idPerson), false, cb_hr_chyba_text($e, 'Přidání slotu zaměstnance', ['table' => cb_hr_schema_table($db, 'slot')]));
    }
}
