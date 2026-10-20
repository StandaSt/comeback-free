<?php
declare(strict_types=1);

/* Účel: Načítá vlastní profil a mění výhradně přihlašovací údaje účtu IS.
 * Kontakty, osobní údaje ani pracovní zařazení v HR se zde nikdy nezapisují.
 */
require_once __DIR__ . '/obnoveni_hesla.php';
require_once __DIR__ . '/email_zmena.php';
require_once __DIR__ . '/hr_pracoviste_main.php';

/** Identita pochází pouze z přihlášení, nikdy z formuláře nebo převzatých práv. */
function cb_profil_id(): int
{
    $id = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    if (empty($_SESSION['login_ok']) || $id <= 0) {
        throw new RuntimeException('Platnost přihlášení vypršela. Přihlaste se znovu.');
    }
    return $id;
}

/** Chybějící ruční migrace nesmí znemožnit zobrazení profilu ani změnu hesla. */
function cb_profil_email_pripraven(mysqli $db): bool
{
    $result = $db->query("SHOW TABLES LIKE 'user\\_email\\_zmena'");
    $ready = $result->num_rows === 1;
    $result->free();
    return $ready;
}

/** Načte přihlašovací účet bez uložení hashe do session nebo výstupu. */
function cb_profil_ucet(mysqli $db, bool $zamknout = false): array
{
    $id = cb_profil_id();
    $stmt = $db->prepare('SELECT id_user, email, heslo_hash FROM user WHERE id_user=? LIMIT 1' . ($zamknout ? ' FOR UPDATE' : ''));
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
        throw new RuntimeException('Uživatelský účet nebyl nalezen.');
    }
    return $row;
}

/** Čte dnešní vlastní zařazení; profil neposkytuje editaci personální evidence. */
function cb_profil_data(mysqli $db): array
{
    $account = cb_profil_ucet($db);
    $id = (int)$account['id_user'];
    $data = ['email' => (string)$account['email'], 'ma_heslo' => trim((string)$account['heslo_hash']) !== '',
        'email_pripraven' => cb_profil_email_pripraven($db), 'ceka_email' => null];
    unset($account);
    $queries = [
        'pobocky' => 'SELECT DISTINCT b.id_pob, b.nazev FROM hr_pracoviste w JOIN pobocka b ON b.id_pob=w.id_pob
            WHERE w.id_person=? AND w.platny=1 AND w.zruseno IS NULL
            AND (w.platnost_od IS NULL OR w.platnost_od<=CURDATE()) AND (w.platnost_do IS NULL OR w.platnost_do>=CURDATE())
            ORDER BY (b.kod=\'Vyroba\'),b.id_pob',
        'sloty' => 'SELECT DISTINCT c.id_slot, c.slot AS nazev FROM hr_slot s JOIN cis_slot c ON c.id_slot=s.id_slot
            WHERE s.id_person=? AND s.platny=1 AND s.zruseno IS NULL
            AND (s.platnost_od IS NULL OR s.platnost_od<=CURDATE()) AND (s.platnost_do IS NULL OR s.platnost_do>=CURDATE()) ORDER BY c.id_slot',
        'role' => 'SELECT DISTINCT c.id_role, c.role AS nazev FROM hr_pristupovy_profil p JOIN cis_role c ON c.id_role=p.id_role
            WHERE p.id_person=? AND c.aktivni=1 ORDER BY c.id_role',
    ];
    foreach ($queries as $key => $sql) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $data[$key] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    $data['main'] = cb_hr_main_id($db, $id);
    if ($data['email_pripraven']) {
        $stmt = $db->prepare('SELECT novy_email, platnost_do FROM user_email_zmena WHERE id_user=? AND potvrzeno IS NULL AND zruseno IS NULL AND platnost_do>NOW() ORDER BY id_user_email_zmena DESC LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $data['ceka_email'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    return $data;
}

/** Omezí pokusy i u již přihlášeného člověka, heslo nikde neloguje. */
function cb_profil_over_heslo(array $account, string $password): void
{
    $attempts = $_SESSION['cb_profil_pokusy'] ?? [];
    $attempts = array_values(array_filter(is_array($attempts) ? $attempts : [], static fn($ts): bool => is_int($ts) && $ts > time() - 900));
    $_SESSION['cb_profil_pokusy'] = $attempts;
    if (count($attempts) >= 5) {
        throw new RuntimeException('Příliš mnoho pokusů. Zkuste to znovu za 15 minut.');
    }
    if (trim((string)$account['heslo_hash']) === '') {
        throw new RuntimeException('Nejprve si nastavte vlastní heslo pro IS pomocí odkazu na e-mailu.');
    }
    if ($password === '' || !password_verify($password, (string)$account['heslo_hash'])) {
        $_SESSION['cb_profil_pokusy'][] = time();
        throw new RuntimeException('Současné heslo není správné.');
    }
    unset($_SESSION['cb_profil_pokusy']);
}

/** Po změně hesla ukončí přihlášení na všech zařízeních a zneplatní staré resetovací odkazy. */
function cb_profil_zmen_heslo(mysqli $db, array $post): void
{
    $password = (string)($post['heslo'] ?? '');
    cb_obnoveni_hesla_over_hodnoty($password, (string)($post['heslo_znovu'] ?? ''));
    $db->begin_transaction();
    try {
        $account = cb_profil_ucet($db, true);
        cb_profil_over_heslo($account, (string)($post['soucasne_heslo'] ?? ''));
        if (password_verify($password, (string)$account['heslo_hash'])) {
            throw new RuntimeException('Nové heslo musí být jiné než současné.');
        }
        $id = (int)$account['id_user'];
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE user SET heslo_hash=? WHERE id_user=?');
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $stmt->close();
        foreach ([
            'UPDATE user_prvni_vstup_token SET zruseno=NOW() WHERE id_user=? AND pouzito IS NULL AND zruseno IS NULL',
            'UPDATE user_login l JOIN user_pc_session s ON s.id_login=l.id_login SET l.duvod=0 WHERE s.id_user=? AND s.zruseno IS NULL AND l.akce=1 AND l.duvod=2',
            "UPDATE user_pc_session SET zruseno=NOW(),duvod_zruseni='zmena_hesla' WHERE id_user=? AND zruseno IS NULL",
        ] as $sql) {
            $stmt = $db->prepare($sql);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
    cb_session_invalidate_auth();
}

/** Připraví změnu pouze v účtu USER; kontaktní e-maily HR se vůbec neotevírají. */
function cb_profil_zmen_email(mysqli $db, array $post): void
{
    if (!cb_profil_email_pripraven($db)) {
        throw new RuntimeException('Změna e-mailu zatím není dostupná. Správce musí dokončit její přípravu.');
    }
    $email = trim((string)($post['email'] ?? ''));
    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Zadejte platný e-mail.');
    }
    $db->begin_transaction();
    try {
        $account = cb_profil_ucet($db, true);
        cb_profil_over_heslo($account, (string)($post['soucasne_heslo'] ?? ''));
        $id = (int)$account['id_user'];
        $change = cb_email_zmena_priprav($db, $id, $id, $email, $id);
        if ($change === null) {
            throw new RuntimeException('Tento e-mail již používáte pro přihlášení.');
        }
        // Dokud není potvrzení skutečně odeslané, nezůstane v DB nový platný požadavek.
        require_once __DIR__ . '/email_zmena_oznameni.php';
        cb_email_zmena_oznameni_odeslat($change['stary_email'], $change['novy_email'], cb_url_abs('?potvrdit_email=' . rawurlencode($change['token'])));
        $db->commit();
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}

/** Běžný POST/redirect drží hesla mimo URL, session i AJAX historii modulu HR. */
function cb_profil_vyrid_post(): void
{
    $action = (string)($_POST['cb_action'] ?? '');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !in_array($action, ['profil_heslo', 'profil_email', 'profil_nastavit_heslo'], true)) {
        return;
    }
    $module = (string)($_POST['profil_modul'] ?? 'provoz');
    if (!in_array($module, ['provoz', 'hr', 'smeny', 'ukoly', 'helpdesk', 'administrace'], true)) {
        $module = 'provoz';
    }
    $url = cb_root_url('index.php?m=' . $module . '&' . ($module === 'helpdesk' ? 'hd' : 'page') . '=uprava_profilu');
    try {
        cb_profil_id();
        if (!cb_crf_platny()) {
            throw new RuntimeException('Platnost formuláře vypršela. Obnovte stránku.');
        }
        if ($action === 'profil_heslo') {
            cb_profil_zmen_heslo(db(), $_POST);
            $_SESSION['cb_flash'] = 'Heslo pro IS bylo změněno. Přihlaste se novým heslem.';
            $url = cb_root_url('');
        } elseif ($action === 'profil_email') {
            cb_profil_zmen_email(db(), $_POST);
            $_SESSION['cb_profil_zprava'] = ['ok' => true, 'text' => 'Na nový e-mail jsme odeslali potvrzovací odkaz. Do potvrzení používejte původní e-mail.'];
        } else {
            $account = cb_profil_ucet(db());
            if (trim((string)$account['heslo_hash']) !== '') {
                throw new RuntimeException('Vlastní heslo již máte nastavené. Použijte změnu hesla.');
            }
            if ((int)($_SESSION['cb_profil_reset_odeslan'] ?? 0) > time() - 900) {
                throw new RuntimeException('Odkaz již byl odeslán. Další můžete vyžádat za 15 minut.');
            }
            cb_obnoveni_hesla_odeslat(db(), (string)$account['email']);
            $_SESSION['cb_profil_reset_odeslan'] = time();
            $_SESSION['cb_profil_zprava'] = ['ok' => true, 'text' => 'Na přihlašovací e-mail jsme odeslali odkaz pro nastavení vlastního hesla IS.'];
        }
    } catch (mysqli_sql_exception $error) {
        $_SESSION['cb_profil_zprava'] = ['ok' => false, 'text' => cb_chyba_uzivatel($error, ['module' => 'SYSTEM', 'action' => 'Úprava vlastního profilu'])];
    } catch (RuntimeException $error) {
        $_SESSION['cb_profil_zprava'] = ['ok' => false, 'text' => $error->getMessage()];
    } catch (Throwable $error) {
        $_SESSION['cb_profil_zprava'] = ['ok' => false, 'text' => cb_chyba_uzivatel($error, ['module' => 'SYSTEM', 'action' => 'Úprava vlastního profilu'])];
    }
    header('Location: ' . $url, true, 303);
    exit;
}
