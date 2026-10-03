<?php
declare(strict_types=1);

/**
 * Omezuje cteni zamestnancu vedoucimu pobocky podle jeho aktualni hlavni pobocky.
 * Firemni rozsah zustava dalsi samostatnou podminkou pristupu.
 */
require_once __DIR__ . '/pozadavky.php';
require_once __DIR__ . '/../../common/lib/firemni_pristup.php';

/** Vrati SQL podminku pro osobu s aliasem p; vyssi role zachovaji svuj dosavadni rozsah. */
function hr_zamestnanci_pobocka_sql(mysqli $db, int $idUser): string
{
    if ($idUser <= 0) {
        return '1 = 0';
    }
    $stmt = $db->prepare('SELECT MIN(id_role) AS role_id FROM hr_pristupovy_profil WHERE id_person = ?');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $role = (int)($stmt->get_result()->fetch_assoc()['role_id'] ?? 0);
    $stmt->close();
    if ($role !== 5) {
        return '1 = 1';
    }

    // Bez jednoznacne platne hlavni pobocky nesmi vedouci ziskat sirsi pristup.
    try {
        $pobocka = hr_nacti_hlavni_pobocku_uzivatele($db, $idUser);
    } catch (CbUserVisibleException $error) {
        return '1 = 0';
    }
    $idPob = (int)$pobocka['id_pob'];
    // Zamestnanci staci vedlejsi pobocka; rozhoduje jeji platnost k dnesnimu dni.
    return "EXISTS (SELECT 1 FROM hr_pracoviste hr_scope
        WHERE hr_scope.id_person = p.id_person AND hr_scope.id_pob = {$idPob}
          AND hr_scope.platny = 1 AND hr_scope.zruseno IS NULL
          AND (hr_scope.platnost_od IS NULL OR hr_scope.platnost_od <= CURDATE())
          AND (hr_scope.platnost_do IS NULL OR hr_scope.platnost_do >= CURDATE()))";
}

/** Kontroluje stejny rozsah i pri otevreni detailu primym odkazem. */
function hr_zamestnanci_muze_osobu(mysqli $db, int $idUser, int $idPerson): bool
{
    if (!cb_firemni_pristup_muze_osobu($db, $idUser, $idPerson)) {
        return false;
    }
    $scope = hr_zamestnanci_pobocka_sql($db, $idUser);
    $stmt = $db->prepare("SELECT p.id_person FROM hr_person p WHERE p.id_person = ? AND {$scope}");
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $allowed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $allowed;
}
