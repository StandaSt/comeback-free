<?php
declare(strict_types=1);
/* Osetruje zanikly odber 2FA notifikaci a navrat ke sparovani az po schvaleni cekajiciho prihlaseni. */

/** Jen potvrzeny zanik 404/410 vyradi konkretni nezmeneny odber; ostatni chyby registraci zachovaji. */
function cb_push_odber_vyrad_zanikly(mysqli $db, int $user, int $device, string $endpoint, ?int $httpStatus): bool
{
    if (!in_array($httpStatus, [404, 410], true)) return false;
    $stmt = $db->prepare('UPDATE push_zarizeni SET aktivni=0 WHERE id=? AND id_user=? AND endpoint_hash=UNHEX(SHA2(?,256)) AND aktivni=1');
    if (!$stmt) throw new RuntimeException('Nelze vyřadit zaniklý odběr notifikací.');
    // Hash brani vyrazeni noveho odberu, pokud byl mezitim ve stejnem zaznamu obnoven.
    $stmt->bind_param('iis', $device, $user, $endpoint);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();
    return $changed;
}

/** Bez aktivniho zarizeni rozlisime navrat drive registrovaneho telefonu od uplne prvni registrace. */
function cb_push_obnova_drivejsi_registrace(mysqli $db, int $user): bool
{
    $stmt = $db->prepare('SELECT id FROM push_zarizeni WHERE id_user=? AND aktivni=0 LIMIT 1');
    if (!$stmt) throw new RuntimeException('Nelze ověřit předchozí registraci telefonu.');
    $stmt->bind_param('i', $user);
    $stmt->execute();
    $stmt->store_result();
    $found = $stmt->num_rows > 0;
    $stmt->close();
    return $found;
}

/** Navrat do registrace je povolen az po potvrzenem 2FA; neotevira pritom prihlaseny IS. */
function cb_push_obnova_po_schvaleni(mysqli $db, int $user): bool
{
    if (cb_duveryhodne_zarizeni_aktivni_id($db, $user) > 0) return false;
    $_SESSION['cb_auth_ok'] = 1;
    $_SESSION['cb_2fa_obnova_registrace'] = 1;
    unset($_SESSION['cb_2fa_token']);
    return true;
}
