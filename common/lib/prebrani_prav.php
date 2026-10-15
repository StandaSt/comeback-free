<?php
declare(strict_types=1);

/* Prebrani prav pro vyvoj: pouze USER 1, skutecna identita a autorstvi zustavaji beze zmeny.
 * DB obsahuje jen jednorazovy pokyn. Profil se zachyti pri loginu a zije vyhradne v session.
 * Test vzdy zacina v Provozu; trvaly vychozi modul admina se nemeni.
 * Knihovna sama pri nacteni nic nemeni; DB zapisy jsou pouze vyslovne volane prihlasovacim tokem.
 */

/** Vrati profil pouze skutecne prihlasenemu USER 1; cizi ucty jej nikdy nezdedi. */
function cb_prebrani_prav_profil(int $idUser = 1): ?array
{
    $profile = $_SESSION['cb_prebrani_prav'] ?? null;
    return $idUser === 1 && !empty($_SESSION['login_ok'])
        && (int)($_SESSION['cb_user']['id_user'] ?? 0) === 1
        && is_array($profile) && (int)($profile['id_user_zdroj'] ?? 0) > 0 ? $profile : null;
}

/** Chybejici tabulka znamena nenainstalovanou funkci, nikoli chybu bezneho loginu. */
function cb_prebrani_prav_pripraveno(mysqli $db): bool
{
    $result = $db->query("SHOW TABLES LIKE 'admin\\_new\\_identita'");
    $ready = $result->num_rows === 1;
    $result->free();
    return $ready;
}

/** Platnost pocita DB hodinami, aby rozdil hodin PHP a MySQL nemohl prodlouzit pokyn. */
function cb_prebrani_prav_pokyn_platny(int $ageSeconds): bool
{
    return $ageSeconds >= 0 && $ageSeconds <= 3600;
}

/** Zachyti aktualni pristupovy profil bez prepisu prihlaseneho ID nebo zmen zdrojoveho uctu. */
function cb_prebrani_prav_nacti(mysqli $db, int $source): array
{
    require_once __DIR__ . '/../db/db_prava.php';
    require_once __DIR__ . '/../db/db_user_pobocka.php';
    require_once __DIR__ . '/hr_schema.php';
    require_once __DIR__ . '/../../hr/hr_db/hr_zamestnanci_pristup.php';
    require_once __DIR__ . '/../../provoz/lib/reporty_pristup.php';
    require_once __DIR__ . '/../../smeny/sm_lib/sm_pozadavky_osoba.php';
    if ($source <= 0 || cb_prebrani_prav_profil() !== null) {
        throw new CbUserVisibleException('Nejprve opusťte aktuální přebrání práv.');
    }
    $stmt = $db->prepare('SELECT p.id_person,p.id_firma,p.pristup_vsechny_pobocky,
        CONCAT_WS(" ",o.prijmeni,o.jmeno) AS jmeno FROM hr_person p JOIN user u ON u.id_user=p.id_user
        LEFT JOIN hr_osobni_udaje o ON o.id_person=p.id_person AND o.platny=1 WHERE p.id_user=? AND p.aktivni=1');
    $stmt->bind_param('i', $source);
    $stmt->execute();
    $result = $stmt->get_result();
    $person = $result->num_rows === 1 ? $result->fetch_assoc() : null;
    $stmt->close();
    if (!is_array($person)) {
        throw new CbUserVisibleException('Vyberte existujícího aktivního uživatele.');
    }
    // Stavajici loader plni jen role/prava; jeho docasny vystup zachytime a vzdy obnovime session.
    $saved = $_SESSION;
    try {
        cb_db_prava_nacti_do_session($db, $source);
        $roles = $_SESSION['cb_user']['roles'];
        $rights = $_SESSION['prava'];
        $states = $_SESSION['prava_stav'];
        $roleLabel = $_SESSION['cb_user']['role'];
    } finally {
        $_SESSION = $saved;
    }
    $roleIds = array_map(static fn(array $r): int => (int)$r['id_role'], $roles);
    if ($roleIds === []) {
        throw new CbUserVisibleException('Zvolený uživatel nemá přístupovou roli.');
    }
    $personId = (int)$person['id_person'];
    $mainStmt = $db->prepare('SELECT m.id_pob,b.nazev FROM ' . cb_hr_main_sql($db) . ' m
        LEFT JOIN pobocka b ON b.id_pob=m.id_pob WHERE m.id_person=? AND m.platny=1 AND m.zruseno IS NULL
        AND (m.platnost_od IS NULL OR m.platnost_od<=CURDATE())
        AND (m.platnost_do IS NULL OR m.platnost_do>=CURDATE())');
    $mainStmt->bind_param('i', $personId);
    $mainStmt->execute();
    $mainRows = $mainStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mainStmt->close();
    $branchStmt = $db->prepare('SELECT DISTINCT id_pob FROM hr_pracoviste WHERE id_person=? AND platny=1
        AND zruseno IS NULL AND (platnost_od IS NULL OR platnost_od<=CURDATE())
        AND (platnost_do IS NULL OR platnost_do>=CURDATE())');
    $branchStmt->bind_param('i', $personId);
    $branchStmt->execute();
    $hrBranches = array_map('intval', array_column($branchStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id_pob'));
    $branchStmt->close();
    $assignments = [];
    foreach (['slot', 'funkce'] as $kind) {
        $table = cb_hr_schema_table($db, $kind);
        $stmt = $db->prepare("SELECT * FROM {$table} WHERE id_person=? AND platny=1
            AND (platnost_od IS NULL OR platnost_od<=CURDATE())
            AND (platnost_do IS NULL OR platnost_do>=CURDATE())");
        $stmt->bind_param('i', $personId);
        $stmt->execute();
        $assignments[$kind] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    // Reportovy rozsah muze mit pro nektere role chybejici MAIN; uchovame odmitnuti, nikoli adminsky fallback.
    try {
        $reports = cb_reporty_pristup_nacti($db, $source);
        $reportError = '';
    } catch (CbUserVisibleException $error) {
        $reports = null;
        $reportError = $error->getMessage();
    }
    return ['id_user_zdroj'=>$source, 'jmeno'=>(string)$person['jmeno'], 'id_firma'=>(int)$person['id_firma'],
        'role_id'=>min($roleIds), 'roles'=>$roles, 'role'=>$roleLabel, 'prava'=>$rights, 'prava_stav'=>$states,
        'main'=>count($mainRows) === 1 ? $mainRows[0] : null,
        'pobocky'=>cb_db_user_pobocky($db, $source), 'hr_pobocky'=>$hrBranches,
        'hr_scope_pobocky'=>hr_zamestnanci_pobocky($db, $source),
        'firmy'=>cb_firemni_pristup_firmy($db, $source), 'hr_firmy'=>hr_zamestnanci_firmy($db, $source),
        'sloty'=>$assignments['slot'], 'funkce'=>$assignments['funkce'],
        'reporty'=>$reports, 'reporty_chyba'=>$reportError,
        'smeny_osoba'=>cb_smeny_pozadavky_osoba($db, $source), 'vytvoreno'=>time()];
}

/** Dokonci jednorazove prevzeti az po uspesnem loginu admina; bez pokynu nema vliv. */
function cb_prebrani_prav_po_loginu(mysqli $db, int $idUser): void
{
    unset($_SESSION['cb_prebrani_prav']);
    if ($idUser !== 1 || !cb_user_ma_roli(1) || !cb_prebrani_prav_pripraveno($db)) {
        return;
    }
    $db->begin_transaction();
    try {
        $rows = $db->query('SELECT id_user_zdroj,TIMESTAMPDIFF(SECOND,vytvoreno,NOW()) AS stari
            FROM admin_new_identita FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
        $profile = null;
        $notice = '';
        if (count($rows) === 1 && cb_prebrani_prav_pokyn_platny((int)$rows[0]['stari'])) {
            try {
                $profile = cb_prebrani_prav_nacti($db, (int)$rows[0]['id_user_zdroj']);
            } catch (CbUserVisibleException $error) {
                // Neaktivni nebo odebrany zdroj nesmi zablokovat dalsi normalni login admina.
                $notice = 'Přebrání práv nebylo aktivováno: ' . $error->getMessage();
            }
        }
        $db->query('DELETE FROM admin_new_identita');
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
    if ($notice !== '') {
        $_SESSION['cb_flash'] = $notice;
    }
    if ($profile !== null) {
        cb_prebrani_prav_aktivuj($profile);
    }
}

/** Naplni pouze pristupovy kontext; skutecne ID, login a osobni profil nikdy neprepisuje. */
function cb_prebrani_prav_aktivuj(array $profile): void
{
    if (empty($_SESSION['login_ok']) || (int)($_SESSION['cb_user']['id_user'] ?? 0) !== 1 || !cb_user_ma_roli(1)) {
        throw new CbUserVisibleException('Přebrání práv může aktivovat pouze normálně přihlášený admin USER 1.');
    }
    $_SESSION['cb_prebrani_prav'] = $profile;
    $_SESSION['prava'] = $profile['prava'];
    $_SESSION['prava_stav'] = $profile['prava_stav'];
    $_SESSION['cb_user']['roles'] = $profile['roles'];
    $_SESSION['cb_user']['role'] = $profile['role'];
    $_SESSION['cb_user']['sloty'] = $profile['sloty'];
    // Test nesmi zacit v adminove ulozene Administraci, na kterou prevzaty profil nemusi mit pravo.
    $_SESSION['cb_user_settings']['aktivni_modul'] = 'provoz';
    // Nesmi zustat adminsky predvyber pobocek; ulozena nastaveni uctu v DB nemenime.
    $_SESSION['selected_pobocky'] = array_map('intval', array_column($profile['pobocky'], 'id_pob'));
    $_SESSION['cb_pobocka_id'] = $_SESSION['selected_pobocky'][0] ?? 0;
    $_SESSION['selected_pobocky_mode'] = 'custom';
    unset($_SESSION['selected_oblast'], $_SESSION['selected_oblasti'], $_SESSION['cb_user_branches']);
}
