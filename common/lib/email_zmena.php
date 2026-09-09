<?php
declare(strict_types=1);

/*
 * Účel souboru: Vytvoří a potvrdí jednorázovou změnu přihlašovacího e-mailu.
 * Odesílání zpráv ani HTML výstup sem nepatří.
 */

/**
 * @return null|array{token:string,id_user:int,stary_email:string,novy_email:string}
 */
function cb_email_zmena_priprav(
    mysqli $db,
    int $idUser,
    int $idPerson,
    string $novyEmail,
    int $zadalUser
): ?array {
    $novyEmail = trim($novyEmail);
    if ($idUser <= 0 || $idPerson <= 0 || $zadalUser <= 0 || filter_var($novyEmail, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Nelze připravit změnu přihlašovacího e-mailu.');
    }

    $stmt = $db->prepare('SELECT email FROM user WHERE id_user = ? LIMIT 1 FOR UPDATE');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($user)) {
        throw new RuntimeException('Navázaný uživatelský účet nebyl nalezen.');
    }

    $staryEmail = trim((string)$user['email']);
    if (strcasecmp($staryEmail, $novyEmail) === 0) {
        return null;
    }

    $stmt = $db->prepare('SELECT 1 FROM user WHERE email = ? AND id_user <> ? LIMIT 1');
    $stmt->bind_param('si', $novyEmail, $idUser);
    $stmt->execute();
    $emailPouzit = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    if ($emailPouzit) {
        throw new RuntimeException('Nový e-mail již používá jiný uživatelský účet.');
    }

    $stmt = $db->prepare('UPDATE user_email_zmena SET zruseno = NOW() WHERE id_user = ? AND potvrzeno IS NULL AND zruseno IS NULL');
    $stmt->bind_param('i', $idUser);
    $stmt->execute();
    $stmt->close();

    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare('INSERT INTO user_email_zmena (id_user, id_person, stary_email, novy_email, token_hash, platnost_do, id_user_zadal) VALUES (?, ?, ?, ?, UNHEX(SHA2(?, 256)), NOW() + INTERVAL 3 DAY, ?)');
    $stmt->bind_param('iisssi', $idUser, $idPerson, $staryEmail, $novyEmail, $token, $zadalUser);
    $stmt->execute();
    $stmt->close();

    return [
        'token' => $token,
        'id_user' => $idUser,
        'stary_email' => $staryEmail,
        'novy_email' => $novyEmail,
    ];
}

/**
 * @return array{id_user:int,novy_email:string}
 */
function cb_email_zmena_potvrd(mysqli $db, string $token): array
{
    $token = trim($token);
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new RuntimeException('Odkaz pro potvrzení změny e-mailu není platný.');
    }

    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id_user_email_zmena, id_user, stary_email, novy_email FROM user_email_zmena WHERE token_hash = UNHEX(SHA2(?, 256)) AND potvrzeno IS NULL AND zruseno IS NULL AND platnost_do > NOW() LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $zmena = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($zmena)) {
            throw new RuntimeException('Odkaz pro potvrzení změny e-mailu není platný nebo již vypršel.');
        }

        $idZmena = (int)$zmena['id_user_email_zmena'];
        $idUser = (int)$zmena['id_user'];
        $staryEmail = trim((string)$zmena['stary_email']);
        $novyEmail = trim((string)$zmena['novy_email']);

        $stmt = $db->prepare('SELECT email FROM user WHERE id_user = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($user) || strcasecmp(trim((string)$user['email']), $staryEmail) !== 0) {
            throw new RuntimeException('Přihlašovací e-mail byl mezitím změněn. Tento odkaz již nelze použít.');
        }

        $stmt = $db->prepare('SELECT 1 FROM user WHERE email = ? AND id_user <> ? LIMIT 1');
        $stmt->bind_param('si', $novyEmail, $idUser);
        $stmt->execute();
        $emailPouzit = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if ($emailPouzit) {
            throw new RuntimeException('Nový e-mail již používá jiný uživatelský účet.');
        }

        $stmt = $db->prepare('UPDATE user SET email = ? WHERE id_user = ?');
        $stmt->bind_param('si', $novyEmail, $idUser);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('UPDATE user_email_zmena SET potvrzeno = NOW() WHERE id_user_email_zmena = ?');
        $stmt->bind_param('i', $idZmena);
        $stmt->execute();
        $stmt->close();

        $stmt = $db->prepare('UPDATE user_email_zmena SET zruseno = NOW() WHERE id_user = ? AND id_user_email_zmena <> ? AND potvrzeno IS NULL AND zruseno IS NULL');
        $stmt->bind_param('ii', $idUser, $idZmena);
        $stmt->execute();
        $stmt->close();

        $db->commit();
        return ['id_user' => $idUser, 'novy_email' => $novyEmail];
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
