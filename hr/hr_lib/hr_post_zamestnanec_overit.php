<?php
declare(strict_types=1);

// Ucel: Overeni karty vyzaduje doplnene udaje a bezchybne mista, MAIN, sloty a pozici.
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

/* Zpracuje overeni karty v povolenem rozsahu HR; podminkou je alespon jeden slot nebo funkce. */
require_once __DIR__ . '/../hr_db/hr_zamestnanci_pristup.php';

function hr_post_zamestnanec_overit(mysqli $db): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $idUser = hr_current_user_id();
        if (!cb_pravo_ma(307)) {
            throw new CbUserVisibleException('Nemáte právo upravit zaměstnance.');
        }
        hr_zamestnanci_vyzaduj_osobu($db, $idUser, $idPerson);
        $errors=cb_hr_zarazeni_chyby($db,$idPerson);
        if ($errors!==[]) throw new CbUserVisibleException('Chyba zařazení: '.implode(' ',$errors));
        hr_update_employee_completeness($db, $idPerson);
        $stmt = $db->prepare('UPDATE hr_person SET overen = 1 WHERE id_person = ? AND overen = 0');
        $stmt->bind_param('i', $idPerson);
        $stmt->execute();
        $updated = $stmt->affected_rows;
        $stmt->close();
        if ($updated !== 1) {
            throw new CbUserVisibleException('Kartu nelze ověřit nebo již byla ověřena.');
        }
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)),
            true,
            'Zaměstnanec byl ověřen.'
        );
    } catch (Throwable $e) {
        cb_form_finish(
            cb_root_url('index.php?m=hr&page=zamestnanec&id=' . rawurlencode((string)$idPerson)),
            false,
            cb_hr_chyba_text($e, 'Ověření zaměstnance', ['table' => 'hr_person']),
            $_POST
        );
    }
}
