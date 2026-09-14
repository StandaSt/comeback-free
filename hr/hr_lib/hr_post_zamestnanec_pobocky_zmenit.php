<?php
declare(strict_types=1);

function hr_post_zamestnanec_pobocky_zmenit(mysqli $db, int $idUser): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        if (!cb_pravo_ma(307)) {
            throw new RuntimeException('Nemáte právo měnit pobočky zaměstnance.');
        }
        $pobocky = hr_employee_normalize_branch_ids($_POST['id_pob'] ?? []);
        $hlavniText = trim((string)($_POST['id_pob_hlavni'] ?? ''));
        if ($idPerson <= 0 || preg_match('/^\d+$/', $hlavniText) !== 1) {
            throw new RuntimeException('Vyberte zaměstnance, pobočky a hlavní pobočku.');
        }
        $platiOd = hr_pracovni_pomer_datum_z_postu((string)($_POST['platnost_od'] ?? ''), 'Platí od');
        hr_pracoviste_zmenit($db, $idPerson, $pobocky, (int)$hlavniText, $platiOd, $idUser);
        cb_form_finish(hr_pracovni_pomer_url($idPerson), true, 'Pobočky byly změněny s platností od zadaného data.');
    } catch (Throwable $e) {
        cb_form_finish(hr_pracovni_pomer_url($idPerson), false, $e->getMessage());
    }
}
