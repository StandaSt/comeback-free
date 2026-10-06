<?php
declare(strict_types=1);
/* Kompatibilni obsluha starsich akci slotu pouziva centralni spravu a pravo 319. */

// Vrati pouze skutecne pracovni sloty; historicke IT a Management se nespravuji.
function hr_nastaveni_pozice(mysqli $db): array
{
    return $db->query('SELECT id_slot,slot,aktivni FROM cis_slot WHERE id_slot NOT IN (0,9) ORDER BY aktivni DESC,slot')->fetch_all(MYSQLI_ASSOC);
}

// Starsi formular pridani je chranen stejne jako nova centralni sprava.
function hr_nastaveni_pozice_pridat(mysqli $db, string $nazev): void
{
    hr_ciselnik_ulozit($db, 'slot', 0, $nazev, true, 0);
}

// Historicke sloty nelze aktivovat ani pres drivejsi POST akci.
function hr_nastaveni_pozice_zmenit_stav(mysqli $db, int $idSlot, bool $aktivni): void
{
    if (!cb_hr_nastaveni_ma_pravo() || in_array($idSlot, [0,9], true) || $idSlot < 1) {
        throw new CbUserVisibleException('Nemáte právo upravit tento pracovní slot.');
    }
    $stmt=$db->prepare('SELECT slot FROM cis_slot WHERE id_slot=?');
    $stmt->bind_param('i',$idSlot); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) throw new CbUserVisibleException('Pracovní slot nebyl nalezen.');
    hr_ciselnik_ulozit($db, 'slot', $idSlot, (string)$row['slot'], $aktivni, 0);
}
