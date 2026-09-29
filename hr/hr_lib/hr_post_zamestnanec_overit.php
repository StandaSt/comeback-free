<?php
declare(strict_types=1);

/* Zpracuje overeni karty; podminkou je alespon jeden slot nebo jedna funkce. */

function hr_post_zamestnanec_overit(mysqli $db): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $idUser = hr_current_user_id();
        if (!cb_pravo_ma(307)) {
            throw new CbUserVisibleException('Nemáte právo upravit zaměstnance.');
        }
        cb_firemni_pristup_vyzaduj_osobu($db, $idUser, $idPerson);
        $slotTable = cb_hr_schema_table($db, 'slot');
        $functionTable = cb_hr_schema_table($db, 'funkce');
        $functionFk = cb_hr_funkce_fk($db);
        $stmt = $db->prepare("SELECT (SELECT COUNT(*) FROM {$slotTable} WHERE id_person = ? AND platny = 1 AND (platnost_od IS NULL OR platnost_od <= CURDATE()) AND (platnost_do IS NULL OR platnost_do >= CURDATE())) AS slotu, (SELECT COUNT(*) FROM {$functionTable} WHERE id_person = ? AND {$functionFk} <> 1 AND platny = 1 AND (platnost_od IS NULL OR platnost_od <= CURDATE()) AND (platnost_do IS NULL OR platnost_do >= CURDATE())) AS funkci, (SELECT COUNT(*) FROM hr_pracoviste WHERE id_person = ? AND platny = 1 AND (platnost_od IS NULL OR platnost_od <= CURDATE()) AND (platnost_do IS NULL OR platnost_do >= CURDATE())) AS pobocek, (SELECT COUNT(*) FROM hr_pracoviste WHERE id_person = ? AND platny = 1 AND hlavni = 1 AND (platnost_od IS NULL OR platnost_od <= CURDATE()) AND (platnost_do IS NULL OR platnost_do >= CURDATE())) AS hlavnich");
        $stmt->bind_param('iiii', $idPerson, $idPerson, $idPerson, $idPerson);
        $stmt->execute();
        $zarazeni = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        // Osoba muze mit vice slotu, jednu funkci nebo kombinaci slotu s restauracni funkci.
        if (((int)($zarazeni['slotu'] ?? 0) < 1 && (int)($zarazeni['funkci'] ?? 0) < 1) || (int)($zarazeni['pobocek'] ?? 0) < 1 || (int)($zarazeni['hlavnich'] ?? 0) !== 1) {
            throw new CbUserVisibleException('Před ověřením nastavte alespoň jeden slot nebo funkci, alespoň jednu pobočku a právě jednu hlavní pobočku.');
        }
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
