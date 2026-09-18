<?php
declare(strict_types=1);

/* Zpracuje jednorazove overeni jedne karty zamestnance. */

function hr_post_zamestnanec_overit(mysqli $db): void
{
    $idPerson = (int)($_POST['id_person'] ?? 0);
    try {
        $idUser = hr_current_user_id();
        if (!cb_pravo_ma(307)) {
            throw new CbUserVisibleException('Nemáte právo upravit zaměstnance.');
        }
        cb_firemni_pristup_vyzaduj_osobu($db, $idUser, $idPerson);
        $stmt = $db->prepare('SELECT (SELECT COUNT(*) FROM hr_zarazeni WHERE id_person = ? AND platny = 1 AND (platnost_od IS NULL OR platnost_od <= CURDATE()) AND (platnost_do IS NULL OR platnost_do >= CURDATE())) AS pozic, (SELECT COUNT(*) FROM hr_pracoviste WHERE id_person = ? AND platny = 1 AND (platnost_od IS NULL OR platnost_od <= CURDATE()) AND (platnost_do IS NULL OR platnost_do >= CURDATE())) AS pobocek, (SELECT COUNT(*) FROM hr_pracoviste WHERE id_person = ? AND platny = 1 AND hlavni = 1 AND (platnost_od IS NULL OR platnost_od <= CURDATE()) AND (platnost_do IS NULL OR platnost_do >= CURDATE())) AS hlavnich');
        $stmt->bind_param('iii', $idPerson, $idPerson, $idPerson);
        $stmt->execute();
        $zarazeni = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ((int)($zarazeni['pozic'] ?? 0) !== 1 || (int)($zarazeni['pobocek'] ?? 0) < 1 || (int)($zarazeni['hlavnich'] ?? 0) !== 1) {
            throw new CbUserVisibleException('Před ověřením nastavte právě jednu aktuální pozici, alespoň jednu pobočku a právě jednu hlavní pobočku.');
        }
        $stmt = $db->prepare('UPDATE hr_person SET overen = 1 WHERE id_person = ? AND aktivni = 1 AND overen = 0');
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
