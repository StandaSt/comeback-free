<?php
declare(strict_types=1);
// Rozsah mist vykonu prace sdileji vsechny cteci dotazy tohoto souboru.
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';


/**
 * Jednotny rozsah osob v HR podle role, firmy a mist vykonu prace; MAIN nerozsiruje viditelnost.
 * Stejnou podminku pouziva cteni, dokumenty i kontrola cile zapisove akce.
 * Pri prebrani prav zachova vlastni osobu USER 1 a prevezme jen rozsah zdrojoveho profilu.
 * Testovaci identity ponechava pro prihlaseni, ale nezpristupnuje je jako zamestnance.
 */
require_once __DIR__ . '/pozadavky.php';
require_once __DIR__ . '/../../common/lib/firemni_pristup.php';

/** Pozna testovaci cil HR akce bez zmeny prihlaseni nebo prav tohoto uctu. */
function hr_zamestnanci_je_testovaci(mysqli $db, int $idPerson): bool
{
    $stmt = $db->prepare('SELECT 1 FROM hr_person WHERE id_person = ? AND testovaci = 1 LIMIT 1');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $testovaci = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $testovaci;
}

/** Nacte skutecnou person prihlaseneho USER; nezamenuje obe identifikace. */
function hr_zamestnanci_divak(mysqli $db, int $idUser): array
{
    if (($profile = cb_prebrani_prav_profil($idUser)) !== null) {
        return ['id_person'=>1, 'id_firma'=>$profile['id_firma'], 'role_id'=>$profile['role_id']];
    }
    $stmt = $db->prepare('SELECT p.id_person, p.id_firma, MIN(pr.id_role) AS role_id
        FROM hr_person p LEFT JOIN hr_pristupovy_profil pr ON pr.id_person = p.id_person
        WHERE p.id_user = ? GROUP BY p.id_person, p.id_firma');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->num_rows === 1 ? $result->fetch_assoc() : [];
    $stmt->close();
    return $row ?? [];
}

/** HR firmy: admin a top manager maji vsechny, ostatni pouze vlastni firmu. */
function hr_zamestnanci_firmy(mysqli $db, int $idUser): array
{
    if (($profile = cb_prebrani_prav_profil($idUser)) !== null) {
        // Vlastni karta zustava dostupna i pri testu profilu jine firmy; SQL scope stale chrani ostatni.
        $own = $db->query('SELECT id_firma FROM hr_person WHERE id_user=1')->fetch_assoc();
        return array_values(array_unique(array_merge($profile['hr_firmy'], [(int)($own['id_firma'] ?? 0)])));
    }
    $viewer = hr_zamestnanci_divak($db, $idUser);
    if (in_array((int)($viewer['role_id'] ?? 0), [1, 2], true)) {
        $rows = $db->query('SELECT id_firma FROM firma')->fetch_all(MYSQLI_ASSOC);
        return array_map(static fn(array $row): int => (int)$row['id_firma'], $rows);
    }
    $firm = (int)($viewer['id_firma'] ?? 0);
    return $firm > 0 ? [$firm] : [];
}

/** Vrati kompletni SQL rozsah osob; alias je pouze interni SQL identifikator. */
function hr_zamestnanci_pobocka_sql(mysqli $db, int $idUser, string $alias = 'p'): string
{
    if (preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', $alias) !== 1) {
        throw new InvalidArgumentException('Neplatny alias rozsahu HR.');
    }
    $viewer = hr_zamestnanci_divak($db, $idUser);
    $person = (int)($viewer['id_person'] ?? 0);
    $firm = (int)($viewer['id_firma'] ?? 0);
    $role = (int)($viewer['role_id'] ?? 0);
    if ($idUser <= 0 || $person <= 0 || !in_array($role, [1, 2, 3, 4, 5, 7, 9], true)) {
        return '1 = 0';
    }
    $base = "{$alias}.testovaci = 0";
    // Self vzdy znamena skutecneho aktéra, nikoli zdrojovy ucet profilu.
    if (($profile = cb_prebrani_prav_profil($idUser)) !== null) {
        $own = "{$alias}.id_person = 1";
        if (in_array($role, [7, 9], true)) {
            return "{$base} AND {$own}";
        }
        if ($role===3 || $role===5) {
            $idsSql=implode(',',array_map('intval',$profile['hr_pobocky']))?:'-1';
            $target=cb_hr_pracoviste_sql($db);
            return "{$base} AND ({$own} OR ({$alias}.id_firma={$firm} AND EXISTS(SELECT 1 FROM {$target} hr_scope
                WHERE hr_scope.id_person={$alias}.id_person AND hr_scope.id_pob IN ({$idsSql}) AND ".cb_hr_ucinne_sql('hr_scope').")))";
        }
    }
    if (in_array($role, [1, 2], true)) {
        return $base;
    }
    $base .= " AND {$alias}.id_firma = {$firm}";
    if ($role === 4) {
        return $base;
    }
    $own = "{$alias}.id_person = {$person}";
    if (in_array($role, [7, 9], true)) {
        return "{$base} AND {$own}";
    }
    $places=cb_hr_pracoviste_sql($db);
    $branches="EXISTS(SELECT 1 FROM {$places} hr_scope JOIN {$places} hr_view ON hr_view.id_pob=hr_scope.id_pob
        WHERE hr_scope.id_person={$alias}.id_person AND hr_view.id_person={$person}
        AND ".cb_hr_ucinne_sql('hr_scope')." AND ".cb_hr_ucinne_sql('hr_view').")";
    return "{$base} AND ({$own} OR {$branches})";
}

/** Rozsah pobocek pro soucty pozadavku a volby; nizsi role nemaji personalni rozsah. */
function hr_zamestnanci_pobocky(mysqli $db, int $idUser): array
{
    if (($profile = cb_prebrani_prav_profil($idUser)) !== null) {
        return $profile['hr_scope_pobocky'];
    }
    $viewer = hr_zamestnanci_divak($db, $idUser);
    $role = (int)($viewer['role_id'] ?? 0);
    $person = (int)($viewer['id_person'] ?? 0);
    if (!in_array($role, [1, 2, 3, 4, 5], true)) {
        return [];
    }
    $firms = implode(',', hr_zamestnanci_firmy($db, $idUser)) ?: '0';
    $sql = "SELECT b.id_pob FROM pobocka b WHERE b.id_firma IN ({$firms})";
    if ($role === 3 || $role === 5) {
        $sql .= " AND EXISTS (SELECT 1 FROM " . cb_hr_pracoviste_sql($db) . " v WHERE v.id_person = {$person}
            AND v.id_pob = b.id_pob AND v.platny = 1 AND v.zruseno IS NULL
            AND (v.platnost_od IS NULL OR v.platnost_od <= CURDATE())
            AND (v.platnost_do IS NULL OR v.platnost_do >= CURDATE()))";
    }
    return array_map('intval', array_column($db->query($sql)->fetch_all(MYSQLI_ASSOC), 'id_pob'));
}

/** Kontroluje stejny rozsah i pri otevreni detailu primym odkazem. */
function hr_zamestnanci_muze_osobu(mysqli $db, int $idUser, int $idPerson): bool
{
    $scope = hr_zamestnanci_pobocka_sql($db, $idUser);
    $stmt = $db->prepare("SELECT p.id_person FROM hr_person p WHERE p.id_person = ? AND {$scope}");
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $allowed = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $allowed;
}

/** Zapisovy handler musi kontrolovat stejnou osobu jako seznam a prime odkazy. */
function hr_zamestnanci_vyzaduj_osobu(mysqli $db, int $idUser, int $idPerson): void
{
    if (!hr_zamestnanci_muze_osobu($db, $idUser, $idPerson)) {
        throw new CbUserVisibleException('K tomuto zaměstnanci nemáte přístup v HR.');
    }
}
