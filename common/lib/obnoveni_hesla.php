<?php
declare(strict_types=1);

/*
 * Obnoveni zapomenuteho hesla.
 * Resi pouze odeslani odkazu, jeho overeni a ulozeni noveho hesla.
 */

require_once __DIR__ . '/prvni_vstup.php';
require_once __DIR__ . '/../db/db_obnoveni_hesla_log.php';

/* Ukonci rozpracovany login a pripravi kratkou session vyhradne pro obnoveni hesla. */
function cb_obnoveni_hesla_priprav(array $user): void
{
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

    $stmt = $db->prepare('SELECT id_user, jmeno, prijmeni, email FROM user WHERE email=? AND aktivni=1 AND heslo_hash IS NOT NULL LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($user)) {
        return false;
    }

    $token = cb_prvni_vstup_vytvor_token($db, (int)$user['id_user']);
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
    if (!is_array($user) || (int)$user['aktivni'] !== 1 || trim((string)$user['heslo_hash']) === '') {
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
    $user = cb_prvni_vstup_user($db, $idUser);
    if (!is_array($user) || trim((string)$user['heslo_hash']) === '') {
        throw new RuntimeException('Odkaz pro nastavení nového hesla už není platný.');
    }

    $heslo = (string)($post['heslo'] ?? '');
    $hesloZnovu = (string)($post['heslo_znovu'] ?? '');
    cb_obnoveni_hesla_over_hodnoty($heslo, $hesloZnovu);
    $hash = password_hash($heslo, PASSWORD_DEFAULT);

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('UPDATE user SET heslo_hash=? WHERE id_user=? AND heslo_hash IS NOT NULL');
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
