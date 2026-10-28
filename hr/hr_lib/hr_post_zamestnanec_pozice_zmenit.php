<?php
declare(strict_types=1);

/* Audit HR se potvrzuje spolecne se zmenou; neuspesny pokus zustava odlisnou udalosti. */
require_once __DIR__ . '/hr_audit.php';

/* Ucel: Ulozi slot a provozni funkci osoby ze spolecneho bloku v jedine transakci. Funkce nepatri konkretnimu slotu. */

function hr_post_zamestnanec_pozice_zmenit(mysqli $db, int $idUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        if (!cb_pravo_ma(307)) {
            throw new CbUserVisibleException('Nemáte právo měnit slot zaměstnance.');
        }
        $slot = trim((string)($_POST['id_slot'] ?? ''));
        if ($idPerson <= 0 || ($slot!=='' && preg_match('/^[1-9]\d*$/', $slot) !== 1)) {
            throw new CbUserVisibleException('Vyberte zaměstnance a slot.');
        }
        $platiOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['platnost_od'] ?? ''), 'Platí od');
        hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
        hr_audit_transaction_begin($db);
        try {
            cb_hr_osobu_zamkni($db,$idPerson);
            if ($slot!=='') hr_zarazeni_zmenit($db,$idPerson,(int)$slot,$platiOd,$idUser,false);
            $raw=(string)($_POST['id_funkce']??'');
            if ($raw!=='' && !ctype_digit($raw)) throw new CbUserVisibleException('Vyberte platnou provozní funkci.');
            $function=cb_hr_funkce_osoby($db,$idPerson,$platiOd);
            $selected=$raw===''?null:(int)$raw;
            $training=isset($_POST['v_treninku']);
            // Ulozeni slotu nesmi nenapadne rusit manazerskou pozici ani prepisovat nezmenenou historii funkce.
            if (($function['zarazeni']??'')==='manazerske') throw new CbUserVisibleException('Manažerská pozice nesmí mít pracovní slot ani provozní funkci.');
            if ($selected!==($function? (int)$function['id_funkce']:null) || $training!==(bool)($function['v_treninku']??false)) {
                hr_funkce_zmenit($db,$idPerson,$selected,$training,$platiOd,$idUser,'provozni',false);
            }
            $end=(string)($_POST['ukoncit_slot']??'');
            if ($end!=='' && (!ctype_digit($end) || (int)$end<=0)) throw new CbUserVisibleException('Vyberte platný slot k ukončení.');
            if ($end!=='') hr_zarazeni_ukoncit($db,$idPerson,(int)$end,$platiOd,$idUser);
            hr_mista_over_casovou_osu($db,$idPerson,$platiOd);
            hr_update_employee_completeness($db,$idPerson);
            hr_audit_transaction_commit($db);
        } catch (Throwable $e) { hr_audit_transaction_rollback($db); throw $e; }
        hr_audit_form_finish(hr_pracovni_pomer_url($idPerson), true, 'Pracovní sloty a funkce osoby byly uloženy.');
    } catch (Throwable $e) {
        hr_audit_form_finish(hr_pracovni_pomer_url($idPerson), false, cb_hr_chyba_text($e, 'Přidání slotu zaměstnance', ['table' => cb_hr_schema_table($db, 'slot')]));
    }
}
