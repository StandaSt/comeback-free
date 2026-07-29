<?php
declare(strict_types=1);

/**
 * Zpracuje POST formulare HR pozadavku.
 */
function hr_post_pozadavky(mysqli $db, array $cbUser, int $roleId): void
{
    try {
        $currentPersonId = hr_current_person_id($db);
    } catch (RuntimeException $e) {
        if ($roleId !== 1) {
            throw $e;
        }
        $currentPersonId = 1;
    }
    $akce = (string)($_POST['akce'] ?? 'vytvorit');
    $mainPobocka = $roleId === 5 ? hr_nacti_hlavni_pobocku_uzivatele($db, (int)($cbUser['id_user'] ?? 0)) : [];

    if ($akce === 'zrusit') {
        $idPob = $roleId === 5 ? (int)$mainPobocka['id_pob'] : 0;
        hr_zrus_pozadavek($db, (int)($_POST['id_pozadavek'] ?? 0), $idPob, $currentPersonId, $roleId);
        $_SESSION['hr_pozadavek_zrusen'] = 1;
    } else {
        $pocet = (int)($_POST['pocet'] ?? 1);
        $idPob = $roleId === 1 ? (int)($_POST['id_pob'] ?? 0) : (int)$mainPobocka['id_pob'];
        $idSlot = (int)($_POST['id_slot'] ?? 0);
        $upresneni = mb_substr(trim((string)($_POST['upresneni'] ?? '')), 0, 500);

        hr_uloz_pozadavek($db, $idPob, $idSlot, $pocet, $upresneni, $currentPersonId);
        $_SESSION['hr_pozadavek_ulozeno'] = 1;
    }

    header('Location: ?page=pozadavky');
    exit;
}
