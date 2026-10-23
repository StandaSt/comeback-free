<?php
// Rozsah reportu a kontrol podle HR; Admin/Top management sdili dynamicky firemni rozsah pobocek.
declare(strict_types=1);

// Ucel rozsahu: MAIN je mnozina a viditelne pobocky vychazeji z mist vykonu prace.
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';
require_once __DIR__ . '/../../common/lib/prebrani_prav.php';
require_once __DIR__ . '/../../common/lib/firemni_pristup.php';

// Načte osobu přes účet; historická ani zrušená pracoviště nerozhodují o dnešním přístupu.
function cb_reporty_pristup_nacti(mysqli $conn, int $idUser): array
{
    if (($profile = cb_prebrani_prav_profil($idUser)) !== null) {
        if ($profile['reporty'] === null) {
            throw new CbUserVisibleException($profile['reporty_chyba']);
        }
        return array_replace($profile['reporty'], ['user_id'=>1, 'person_id'=>1]);
    }
    // Porovnavani vice radku v jednom pozadavku znovu nenacita stejnou HR osobu a pobocky.
    static $cache = [];
    $cacheKey = spl_object_id($conn) . ':' . $idUser;
    if (isset($cache[$cacheKey])) { return $cache[$cacheKey]; }
    $stmt = $conn->prepare('SELECT hp.id_person, hp.id_user, hp.id_firma, hp.pristup_vsechny_pobocky,
        (SELECT MIN(rp.id_role) FROM hr_pristupovy_profil rp WHERE rp.id_person=hp.id_person) AS role_id
        FROM hr_person hp WHERE hp.id_user=? AND hp.aktivni=1');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $person = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($person) || !in_array((int)$person['role_id'], [1, 2, 3, 4, 5, 7, 9], true)) {
        throw new CbUserVisibleException('Pro zobrazení reportů chybí aktivní HR osoba nebo platná role.');
    }
    $personId = (int)$person['id_person'];
    $role = (int)$person['role_id'];
    // Administratorsky firemni rozsah zahrne novou pobocku bez rucniho prirazeni do HR.
    $companyIds = in_array($role, [1, 2], true) ? cb_firemni_pristup_firmy($conn, $idUser) : [];
    $stmt = $conn->prepare('SELECT p.id_pob, p.nazev, p.id_firma,
        EXISTS (SELECT 1 FROM ' . cb_hr_pracoviste_sql($conn) . ' pr WHERE pr.id_person=? AND pr.id_pob=p.id_pob
            AND pr.platny=1 AND pr.zruseno IS NULL
            AND (pr.platnost_od IS NULL OR pr.platnost_od<=CURDATE())
            AND (pr.platnost_do IS NULL OR pr.platnost_do>=CURDATE())) AS assigned,
        EXISTS (SELECT 1 FROM ' . cb_hr_main_sql($conn) . ' pr WHERE pr.id_person=? AND pr.id_pob=p.id_pob
            AND pr.hlavni=1 AND pr.platny=1 AND pr.zruseno IS NULL
            AND (pr.platnost_od IS NULL OR pr.platnost_od<=CURDATE())
            AND (pr.platnost_do IS NULL OR pr.platnost_do>=CURDATE())) AS main
        FROM pobocka p WHERE p.aktivni=1 ORDER BY p.id_pob');
    $stmt->bind_param('ii', $personId, $personId);
    $stmt->execute();
    $result = $stmt->get_result();
    $branches = [];
    $mainIds = [];
    while ($branch = $result->fetch_assoc()) {
        $id = (int)$branch['id_pob'];
        if ((int)$branch['main'] === 1) { $mainIds[] = $id; }
        $allowed = in_array($role, [1, 2], true) ? in_array((int)$branch['id_firma'], $companyIds, true) : ($role === 3
            ? ((int)$branch['id_firma']===(int)$person['id_firma'] && (int)$branch['assigned']===1)
            : ($role === 4 ? (int)$branch['id_firma'] === (int)$person['id_firma']
                : (in_array($role, [5, 7], true) && (int)$branch['id_firma']===(int)$person['id_firma'] && (int)$branch['assigned']===1)));
        if ($allowed) {
            $branches[$id] = ['id' => $id, 'name' => (string)$branch['nazev'], 'main' => (int)$branch['main'] === 1];
        }
    }
    $stmt->close();
    return $cache[$cacheKey] = ['user_id' => $idUser, 'person_id' => $personId, 'role' => $role,
        'company_id' => (int)$person['id_firma'], 'branches' => $branches,
        'own_reports' => in_array($role, [5, 7, 9], true)];
}

// Podmínka chrání jednotlivé reporty; vlastní výpomoc neotevírá celou cizí pobočku.
function cb_reporty_archiv_sql(array $scope, bool $google = false): string
{
    $ids = implode(',', array_map('intval', array_keys($scope['branches'])));
    $branchSql = $ids === '' ? '0=1' : 'r.id_pob IN (' . $ids . ')';
    if (!$scope['own_reports']) { return '(' . $branchSql . ')'; }
    $userId = (int)$scope['user_id'];
    $ownSql = $google
        ? 'EXISTS (SELECT 1 FROM reporty_is own_report WHERE own_report.platny=1
            AND own_report.id_pob=r.id_pob AND own_report.datum_reportu=r.datum_reportu
            AND own_report.zadal=' . $userId . ')'
        : 'r.zadal=' . $userId;
    return '(' . $branchSql . ' OR ' . $ownSql . ')';
}

// Stejný rozsah hodin používá tabulka i exporty ještě před sčítáním a filtrováním.
function cb_reporty_hodiny_sql(array $scope): string
{
    if (in_array($scope['role'], [1, 2], true)) { return '1=1'; }
    if ($scope['role'] === 4) {
        return 'EXISTS (SELECT 1 FROM pobocka scope_branch WHERE scope_branch.id_pob=r.id_pob
            AND scope_branch.id_firma=' . (int)$scope['company_id'] . ')';
    }
    if ($scope['role'] === 9) { return 'ro.id_user=' . (int)$scope['user_id']; }
    $ids = implode(',', array_map('intval', array_keys($scope['branches'])));
    return $ids === '' ? '0=1' : 'r.id_pob IN (' . $ids . ')';
}

// Detail a porovnání ověří konkrétní pobočku a datum, nikoli jen položku ve filtru poboček.
function cb_reporty_archiv_muze_cist(mysqli $conn, array $scope, int $branchId, string $date): bool
{
    if (isset($scope['branches'][$branchId])) { return true; }
    if (!$scope['own_reports']) { return false; }
    $userId = (int)$scope['user_id'];
    $stmt = $conn->prepare('SELECT 1 FROM reporty_is WHERE platny=1 AND id_pob=?
        AND datum_reportu=? AND zadal=? LIMIT 1');
    $stmt->bind_param('isi', $branchId, $date, $userId);
    $stmt->execute();
    $allowed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $allowed;
}

// Výroba v neděli nevytváří report; případný skutečně uložený report zůstává viditelný.
function cb_reporty_archiv_radek_viditelny(array $scope, int $branchId, DateTimeImmutable $day, bool $saved): bool
{
    if ($saved) { return true; }
    return isset($scope['branches'][$branchId]) && !($branchId === 7 && $day->format('N') === '7');
}
