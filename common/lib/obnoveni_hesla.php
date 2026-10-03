<?php
declare(strict_types=1);

/*
 * Obnoveni zapomenuteho hesla.
 * Resi odeslani odkazu pro kazdy e-mail evidovany v tabulce user,
 * jeho overeni a ulozeni noveho lokalniho hesla i pri puvodnim NULL hashi.
 */

require_once __DIR__ . '/prvni_vstup.php';
require_once __DIR__ . '/pc_session.php';
require_once __DIR__ . '/../db/db_obnoveni_hesla_log.php';

/* Nacte ucet pro obnovu podle prihlasovaciho e-mailu; HR stav reset neblokuje. */
function cb_obnoveni_hesla_user_podle_email(mysqli $db, string $email): ?array
{
    $stmt = $db->prepare(
        'SELECT u.id_user, u.email,
                COALESCE(ou.jmeno, \'\') AS jmeno,
                COALESCE(ou.prijmeni, \'\') AS prijmeni
         FROM user u
         LEFT JOIN hr_osobni_udaje ou ON ou.id_person=u.id_user AND ou.platny=1
         WHERE u.email=?
         LIMIT 1'
    );
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($user) ? $user : null;
}

/* Nacte ucet podle ID bez zavislosti na HR osobe nebo dosavadnim hashi hesla. */
function cb_obnoveni_hesla_user_podle_id(mysqli $db, int $idUser): ?array
{
    if ($idUser <= 0) {
        return null;
    }
    $stmt = $db->prepare(
        'SELECT id_user, email,
                heslo_hash IS NOT NULL AND TRIM(heslo_hash) <> \'\' AS ma_hash
         FROM user WHERE id_user=? LIMIT 1'
    );
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return is_array($user) ? $user : null;
}

/* Vytvori resetovaci token odlisitelny od pozvanky prvniho vstupu. */
function cb_obnoveni_hesla_vytvor_token(mysqli $db, int $idUser): string
{
    $token = 'reset.' . bin2hex(random_bytes(32));
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

/* Ukonci rozpracovany login a pripravi kratkou session vyhradne pro obnoveni hesla. */
function cb_obnoveni_hesla_priprav(array $user): void
{
    cb_pc_session_revoke_current(db(), 'reset_hesla');
    cb_session_invalidate_auth();
    unset($_SESSION['cb_prvni_vstup_user_id'], $_SESSION['cb_prvni_vstup_platnost_do']);
    $_SESSION['cb_obnoveni_hesla_user_id'] = (int)$user['id_user'];
    $_SESSION['cb_obnoveni_hesla_platnost_do'] = time() + 180;
}

/* Vrati zbyvajici platnost formulare obnoveni hesla v sekundach. */
function cb_obnoveni_hesla_zbyva(): int
{
    $idUser = (int)($_SESSION['cb_obnoveni_hesla_user_id'] ?? 0);
    $platnostDo = (int)($_SESSION['cb_obnoveni_hesla_platnost_do'] ?? 0);
    $zbyva = $platnostDo - time();
    if ($idUser > 0 && $zbyva > 0) {
        return $zbyva;
    }
    unset($_SESSION['cb_obnoveni_hesla_user_id'], $_SESSION['cb_obnoveni_hesla_platnost_do']);
    return 0;
}

/* Vytvori a odesle odkaz pro obnoveni lokalniho hesla. */
function cb_obnoveni_hesla_odeslat(mysqli $db, string $email): bool
{
    if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Zadejte platný e-mail.');
    }

    $user = cb_obnoveni_hesla_user_podle_email($db, $email);
    if (!is_array($user)) {
        return false;
    }

    $token = cb_obnoveni_hesla_vytvor_token($db, (int)$user['id_user']);
    $link = cb_url_abs('?obnoveni_hesla=' . rawurlencode($token));
    $name = trim((string)$user['jmeno'] . ' ' . (string)$user['prijmeni']);
    require_once __DIR__ . '/email_reset_hesla.php';
    $idAkceLog = db_obnoveni_hesla_log_zahaj($db, (int)$user['id_user']);
    try {
        cb_email_reset_hesla_odeslat((string)$user['email'], $name, $link);
    } catch (Throwable $e) {
        try {
            db_obnoveni_hesla_log_dokonci($db, $idAkceLog, false, $e->getMessage());
        } catch (Throwable $logError) {
            error_log('Obnova hesla: nepodarilo se uzavrit log akce ' . $idAkceLog . ': ' . $logError->getMessage());
        }
        throw $e;
    }
    db_obnoveni_hesla_log_dokonci($db, $idAkceLog, true);
    return true;
}

/* Overi jednorazovy odkaz a pripravi session obnoveni hesla. */
function cb_obnoveni_hesla_over_token(mysqli $db, string $token): bool
{
    $novyResetToken = preg_match('/\Areset\.[a-f0-9]{64}\z/', $token) === 1;
    $staryResetToken = preg_match('/\A[A-Za-z0-9_-]{43}\z/', $token) === 1;
    if (!$novyResetToken && !$staryResetToken) {
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

    $user = cb_obnoveni_hesla_user_podle_id($db, (int)$row['id_user']);
    // Stare odkazy zachovame jen u uctu, ktery uz hash mel; pozvanka bez hashe reset neotevre.
    if (!is_array($user) || (!$novyResetToken && empty($user['ma_hash']))) {
        return false;
    }
    cb_obnoveni_hesla_priprav($user);
    return true;
}

/* Overi pravidla noveho hesla a shodu obou zadani. */
function cb_obnoveni_hesla_over_hodnoty(string $heslo, string $hesloZnovu): void
{
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
}

/* Ulozi nove heslo, spotrebuje odkaz a ukonci vsechny rozpracovane login stavy. */
function cb_obnoveni_hesla_uloz(mysqli $db, array $post): void
{
    if (cb_obnoveni_hesla_zbyva() <= 0) {
        throw new RuntimeException('Čas pro nastavení hesla vypršel. Použijte nový odkaz.');
    }
    $idUser = (int)($_SESSION['cb_obnoveni_hesla_user_id'] ?? 0);
    $user = cb_obnoveni_hesla_user_podle_id($db, $idUser);
    if (!is_array($user)) {
        throw new RuntimeException('Odkaz pro nastavení nového hesla už není platný.');
    }

    $heslo = (string)($post['heslo'] ?? '');
    $hesloZnovu = (string)($post['heslo_znovu'] ?? '');
    cb_obnoveni_hesla_over_hodnoty($heslo, $hesloZnovu);
    $hash = password_hash($heslo, PASSWORD_DEFAULT);

    $db->begin_transaction();
    try {
        // Reset nastavuje lokalni heslo stejne pro puvodni NULL hash i pro zapomenute heslo.
        $stmt = $db->prepare('UPDATE user SET heslo_hash=? WHERE id_user=?');
        $stmt->bind_param('si', $hash, $idUser);
        $stmt->execute();
        $ulozeno = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$ulozeno) {
            throw new RuntimeException('Odkaz pro nastavení nového hesla už není platný.');
        }

        $stmt = $db->prepare('UPDATE user_prvni_vstup_token SET pouzito=NOW() WHERE id_user=? AND pouzito IS NULL AND zruseno IS NULL');
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    cb_session_invalidate_auth();
    unset(
        $_SESSION['cb_obnoveni_hesla_user_id'],
        $_SESSION['cb_obnoveni_hesla_platnost_do'],
        $_SESSION['cb_prvni_vstup_user_id'],
        $_SESSION['cb_prvni_vstup_platnost_do'],
        $_SESSION['cb_local_login_user_id']
    );
}
