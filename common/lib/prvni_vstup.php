<?php
declare(strict_types=1);

/*
 * Prvni vstup do IS.
 * Resi pouze overeni pozvanky, ulozeni lokalniho hesla a pripravu identity.
 */

function cb_prvni_vstup_user(mysqli $db, int $idUser): ?array
{
    $stmt = $db->prepare('SELECT id_user, jmeno, prijmeni, email, telefon, aktivni, schvalen, heslo_hash FROM user WHERE id_user=? LIMIT 1');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($row) ? $row : null;
}

function cb_prvni_vstup_priprav(array $user, bool $obnoveniHesla = false): void
{
    $_SESSION['cb_prvni_vstup_user_id'] = (int)$user['id_user'];
    $_SESSION['cb_prvni_vstup_platnost_do'] = time() + 180;
    $_SESSION['cb_prvni_vstup_obnoveni_hesla'] = $obnoveniHesla ? 1 : 0;
}

function cb_prvni_vstup_zbyva(): int
{
    $idUser = (int)($_SESSION['cb_prvni_vstup_user_id'] ?? 0);
    $platnostDo = (int)($_SESSION['cb_prvni_vstup_platnost_do'] ?? 0);
    $zbyva = $platnostDo - time();
    if ($idUser > 0 && $zbyva > 0) {
        return $zbyva;
    }
    unset($_SESSION['cb_prvni_vstup_user_id'], $_SESSION['cb_prvni_vstup_platnost_do'], $_SESSION['cb_prvni_vstup_obnoveni_hesla']);
    return 0;
}

function cb_prvni_vstup_vytvor_token(mysqli $db, int $idUser): string
{
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $stmt = $db->prepare('UPDATE user_prvni_vstup_token SET zruseno=NOW() WHERE id_user=? AND pouzito IS NULL AND zruseno IS NULL');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $stmt->close();
    $stmt = $db->prepare('INSERT INTO user_prvni_vstup_token (id_user, token_hash, platnost_do) VALUES (?, UNHEX(SHA2(?,256)), NOW() + INTERVAL 3 DAY)');
    $stmt->bind_param('is', $idUser, $token);
    $stmt->execute();
    $stmt->close();
    return $token;
}

function cb_prvni_vstup_over_token(mysqli $db, string $token): bool
{
    if (strlen($token) < 40) {
        return false;
    }
    $stmt = $db->prepare('SELECT id_user FROM user_prvni_vstup_token WHERE token_hash=UNHEX(SHA2(?,256)) AND pouzito IS NULL AND zruseno IS NULL AND platnost_do>NOW() LIMIT 1');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
        return false;
    }
    $user = cb_prvni_vstup_user($db, (int)$row['id_user']);
    if (!is_array($user) || (int)$user['aktivni'] !== 1) {
        return false;
    }
    cb_prvni_vstup_priprav($user, trim((string)$user['heslo_hash']) !== '');
    return true;
}

function cb_prvni_vstup_obnoveni_hesla_odeslat(mysqli $db, string $email): bool
{
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Zadejte platný e-mail.');
    }

    $stmt = $db->prepare('SELECT id_user, jmeno, prijmeni, email FROM user WHERE email=? AND aktivni=1 LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($user)) {
        return false;
    }

    $token = cb_prvni_vstup_vytvor_token($db, (int)$user['id_user']);
    $link = cb_url_abs('?prvni_vstup=' . rawurlencode($token));
    $name = trim((string)$user['jmeno'] . ' ' . (string)$user['prijmeni']);
    require_once __DIR__ . '/email_reset_hesla.php';
    cb_email_reset_hesla_odeslat((string)$user['email'], $name, $link);
    return true;
}

function cb_prvni_vstup_dokonci_login(mysqli $db, array $user): void
{
    $idUser = (int)$user['id_user'];
    cb_session_regenerate_after_login();
    $_SESSION['cb_user'] = [
        'id_user' => $idUser,
        'name' => (string)$user['jmeno'],
        'surname' => (string)$user['prijmeni'],
        'email' => (string)$user['email'],
        'telefon' => (string)($user['telefon'] ?? ''),
        'active' => (bool)$user['aktivni'],
        'approved' => (bool)$user['schvalen'],
        'roles' => [],
        'sloty' => [],
    ];
    cb_session_bind_after_login();
    require_once __DIR__ . '/../db/db_user.php';
    require_once __DIR__ . '/../db/db_prava.php';
    require_once __DIR__ . '/../db/db_login_zapis.php';
    cb_db_ensure_user_set($db, $idUser);
    cb_db_prava_nacti_do_session($db, $idUser);
    $idLogin = cb_db_insert_login_and_spy($db, $idUser);
    require_once __DIR__ . '/../db/db_login_blok_info.php';
    cb_db_fill_login_info_session($db, $idUser, $idLogin);
    require_once __DIR__ . '/smeny_graphql.php';
    cb_login_load_settings_to_session($idUser);
    $_SESSION['login_ok'] = 1;
    unset($_SESSION['cb_auth_ok'], $_SESSION['cb_2fa_token'], $_SESSION['cb_token'], $_SESSION['cb_prvni_vstup_user_id'], $_SESSION['cb_prvni_vstup_platnost_do'], $_SESSION['cb_prvni_vstup_obnoveni_hesla'], $_SESSION['cb_local_login_user_id']);
    $_SESSION['cb_timeout_min'] = 720;
    $_SESSION['cb_session_start_ts'] = time();
}

function cb_lokalni_login_zahaj(mysqli $db, array $user): void
{
    $idUser = (int)$user['id_user'];
    $_SESSION['cb_user'] = ['id_user' => $idUser, 'name' => (string)$user['jmeno'], 'surname' => (string)$user['prijmeni'], 'email' => (string)$user['email'], 'telefon' => (string)($user['telefon'] ?? ''), 'active' => true, 'approved' => (bool)$user['schvalen'], 'roles' => [], 'sloty' => []];
    $_SESSION['cb_auth_ok'] = 1;
    $_SESSION['cb_local_login_user_id'] = $idUser;
    require_once __DIR__ . '/../db/db_user.php';
    cb_db_ensure_user_set($db, $idUser);
    $on2fa = 1;
    $row = $db->query('SELECT on_2fa FROM set_system WHERE id_set=1 LIMIT 1')->fetch_assoc();
    if (is_array($row)) { $on2fa = (int)$row['on_2fa']; }
    if ((string)($GLOBALS['PROSTREDI'] ?? '') === 'LOCAL' || $on2fa !== 1) {
        cb_prvni_vstup_dokonci_login($db, $user);
        return;
    }
    $stmt = $db->prepare('SELECT id FROM push_zarizeni WHERE id_user=? AND aktivni=1 LIMIT 1');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $stmt->store_result();
    $hasDevice = $stmt->num_rows > 0;
    $stmt->close();
    if (!$hasDevice) { return; }
    $token = bin2hex(random_bytes(32));
    $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? '')) ?: 'UNKNOWN';
    $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? '')) ?: null;
    $limit = defined('CB_2FA_LIMIT_SEC') ? max(1, (int)CB_2FA_LIMIT_SEC) : 300;
    $stmt = $db->prepare("INSERT INTO push_login_2fa (id_user, token, stav, ip, prohlizec, vytvoreno, vyprsi, rozhodnuto, id_zarizeni) VALUES (?, ?, 'ceka', ?, ?, NOW(), (NOW() + INTERVAL ? SECOND), NULL, NULL)");
    $stmt->bind_param('isssi', $idUser, $token, $ip, $ua, $limit);
    $stmt->execute();
    $stmt->close();
    $_SESSION['cb_2fa_token'] = $token;
    unset($_SESSION['cb_auth_ok']);
    require_once __DIR__ . '/../notifikace/notifikace_2fa.php';
    cb_push_send_2fa($idUser, $token);
}

function cb_prvni_vstup_uloz(mysqli $db, array $post): void
{
    if (cb_prvni_vstup_zbyva() <= 0) {
        throw new RuntimeException('Čas pro nastavení hesla vypršel. Přihlaste se znovu.');
    }
    $idUser = (int)($_SESSION['cb_prvni_vstup_user_id'] ?? 0);
    $user = cb_prvni_vstup_user($db, $idUser);
    $obnoveniHesla = !empty($_SESSION['cb_prvni_vstup_obnoveni_hesla']);
    $jmeno = trim((string)($post['jmeno'] ?? ''));
    $prijmeni = trim((string)($post['prijmeni'] ?? ''));
    $email = trim((string)($post['email'] ?? ''));
    $heslo = (string)($post['heslo'] ?? '');
    $hesloZnovu = (string)($post['heslo_znovu'] ?? '');
    if ($obnoveniHesla && is_array($user)) {
        $jmeno = (string)$user['jmeno'];
        $prijmeni = (string)$user['prijmeni'];
        $email = (string)$user['email'];
    }
    if (!is_array($user) || $jmeno === '' || $prijmeni === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Vyplňte celé jméno a platný e-mail.');
    }
    if (
        strlen($heslo) < 8
        || preg_match('/[a-z]/', $heslo) !== 1
        || preg_match('/[A-Z]/', $heslo) !== 1
        || preg_match('/[0-9]/', $heslo) !== 1
    ) {
        throw new RuntimeException('Heslo musí mít alespoň 8 znaků, malé a velké písmeno a číslici.');
    }
    if ($heslo !== $hesloZnovu) {
        throw new RuntimeException('Hesla se neshodují.');
    }
    $hash = password_hash($heslo, PASSWORD_DEFAULT);
    $db->begin_transaction();
    try {
        $whereHeslo = $obnoveniHesla ? 'heslo_hash IS NOT NULL' : 'heslo_hash IS NULL';
        $stmt = $db->prepare('UPDATE user SET jmeno=?, prijmeni=?, email=?, heslo_hash=? WHERE id_user=? AND ' . $whereHeslo);
        $stmt->bind_param('ssssi', $jmeno, $prijmeni, $email, $hash, $idUser);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException($obnoveniHesla ? 'Odkaz pro nastavení nového hesla už není platný.' : 'První vstup už byl dokončen.');
        }
        $stmt->close();
        $stmt = $db->prepare('UPDATE user_prvni_vstup_token SET pouzito=NOW() WHERE id_user=? AND pouzito IS NULL AND zruseno IS NULL');
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    $user = cb_prvni_vstup_user($db, $idUser);
    if (!is_array($user)) {
        throw new RuntimeException('Uživatel po uložení neexistuje.');
    }
    cb_lokalni_login_zahaj($db, $user);
}
