<?php
declare(strict_types=1);

/*
 * Ucel souboru: Udrzet prave jednu aktivni identitu IS pro jeden browser
 * profil. Cookie obsahuje pouze nahodny identifikator; DB uklada jen jeho hash.
 */

function cb_pc_session_timeout_sec(): int
{
    return 150;
}

function cb_pc_request_matches_session(): bool
{
    $requestToken = strtolower(trim((string)($_SERVER['HTTP_X_COMEBACK_PC_SESSION'] ?? '')));
    if ($requestToken === '' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $requestToken = strtolower(trim((string)($_POST['cb_pc_session'] ?? '')));
    }
    if ($requestToken === '') {
        return true;
    }
    $sessionToken = strtolower(trim((string)($_SESSION['cb_pc_session_token'] ?? '')));
    return preg_match('/^[a-f0-9]{64}$/', $requestToken) === 1
        && preg_match('/^[a-f0-9]{64}$/', $sessionToken) === 1
        && hash_equals($sessionToken, $requestToken);
}

function cb_pc_cookie_name(): string
{
    $params = session_get_cookie_params();
    return !empty($params['secure']) ? '__Host-cb_pc' : 'cb_pc';
}

function cb_pc_cookie_token(bool $create = false): string
{
    $name = cb_pc_cookie_name();
    $token = strtolower(trim((string)($_COOKIE[$name] ?? '')));
    if (preg_match('/^[a-f0-9]{64}$/', $token) === 1) {
        return $token;
    }
    if (!$create || headers_sent()) {
        return '';
    }

    $token = bin2hex(random_bytes(32));
    $params = session_get_cookie_params();
    $ok = setcookie($name, $token, [
        'expires' => time() + (365 * 24 * 60 * 60),
        'path' => '/',
        'domain' => '',
        'secure' => !empty($params['secure']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!$ok) {
        return '';
    }

    $_COOKIE[$name] = $token;
    return $token;
}

function cb_pc_session_activate(mysqli $db, int $idUser, int $idLogin): void
{
    if ($idUser <= 0 || $idLogin <= 0) {
        throw new RuntimeException('Nelze navázat přihlášení na zařízení.');
    }

    $pcToken = cb_pc_cookie_token(true);
    if ($pcToken === '') {
        throw new RuntimeException('Nelze uložit identifikaci zařízení.');
    }
    $sessionToken = bin2hex(random_bytes(32));

    $db->begin_transaction();
    try {
        $stmt = $db->prepare(
            'SELECT id_user, id_login
             FROM user_pc_session
             WHERE pc_hash=UNHEX(SHA2(?,256)) AND zruseno IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        if (!($stmt instanceof mysqli_stmt)) {
            throw new RuntimeException('Nelze načíst předchozí session zařízení.');
        }
        $stmt->bind_param('s', $pcToken);
        $stmt->execute();
        $previous = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($previous)) {
            $previousLogin = (int)($previous['id_login'] ?? 0);
            if ($previousLogin > 0 && $previousLogin !== $idLogin) {
                $stmt = $db->prepare(
                    'UPDATE user_login SET duvod=0
                     WHERE id_login=? AND akce=1 AND duvod=2'
                );
                if (!($stmt instanceof mysqli_stmt)) {
                    throw new RuntimeException('Nelze ukončit předchozí přihlášení zařízení.');
                }
                $stmt->bind_param('i', $previousLogin);
                $stmt->execute();
                $stmt->close();
            }
        }

        $stmt = $db->prepare(
            'INSERT INTO user_pc_session
                (pc_hash, id_user, id_login, session_hash, vytvoreno, last_seen, zruseno, duvod_zruseni)
             VALUES
                (UNHEX(SHA2(?,256)), ?, ?, UNHEX(SHA2(?,256)), NOW(), NOW(), NULL, NULL)
             ON DUPLICATE KEY UPDATE
                id_user=VALUES(id_user),
                id_login=VALUES(id_login),
                session_hash=VALUES(session_hash),
                vytvoreno=NOW(),
                last_seen=NOW(),
                zruseno=NULL,
                duvod_zruseni=NULL'
        );
        if (!($stmt instanceof mysqli_stmt)) {
            throw new RuntimeException('Nelze uložit session zařízení.');
        }
        $stmt->bind_param('siis', $pcToken, $idUser, $idLogin, $sessionToken);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    $_SESSION['cb_pc_token'] = $pcToken;
    $_SESSION['cb_pc_session_token'] = $sessionToken;
}

function cb_pc_session_is_current(mysqli $db, int $idUser, int $idLogin): bool
{
    $cookieToken = cb_pc_cookie_token(false);
    $pcToken = strtolower(trim((string)($_SESSION['cb_pc_token'] ?? '')));
    $sessionToken = strtolower(trim((string)($_SESSION['cb_pc_session_token'] ?? '')));
    if (
        $idUser <= 0
        || $idLogin <= 0
        || preg_match('/^[a-f0-9]{64}$/', $cookieToken) !== 1
        || preg_match('/^[a-f0-9]{64}$/', $pcToken) !== 1
        || preg_match('/^[a-f0-9]{64}$/', $sessionToken) !== 1
        || !hash_equals($pcToken, $cookieToken)
    ) {
        return false;
    }

    $timeout = cb_pc_session_timeout_sec();
    $sql = '
        SELECT id_login
        FROM user_pc_session
        WHERE pc_hash=UNHEX(SHA2(?,256))
          AND session_hash=UNHEX(SHA2(?,256))
          AND id_user=?
          AND id_login=?
          AND zruseno IS NULL
          AND last_seen >= (NOW() - INTERVAL ' . $timeout . ' SECOND)
        LIMIT 1
    ';
    $stmt = $db->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        return false;
    }
    $stmt->bind_param('ssii', $pcToken, $sessionToken, $idUser, $idLogin);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
        return false;
    }

    $stmt = $db->prepare(
        'UPDATE user_pc_session
         SET last_seen=NOW()
         WHERE pc_hash=UNHEX(SHA2(?,256))
           AND session_hash=UNHEX(SHA2(?,256))
           AND id_user=?
           AND id_login=?
           AND zruseno IS NULL'
    );
    if (!($stmt instanceof mysqli_stmt)) {
        return false;
    }
    $stmt->bind_param('ssii', $pcToken, $sessionToken, $idUser, $idLogin);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function cb_pc_session_revoke_current(mysqli $db, string $reason): void
{
    $pcToken = cb_pc_cookie_token(false);
    if (preg_match('/^[a-f0-9]{64}$/', $pcToken) !== 1) {
        return;
    }
    $reason = preg_replace('/[^a-z0-9_-]/i', '', $reason) ?? '';
    $reason = substr($reason !== '' ? $reason : 'ukonceno', 0, 40);

    $db->begin_transaction();
    try {
        $stmt = $db->prepare(
            'SELECT id_login
             FROM user_pc_session
             WHERE pc_hash=UNHEX(SHA2(?,256)) AND zruseno IS NULL
             LIMIT 1
             FOR UPDATE'
        );
        if (!($stmt instanceof mysqli_stmt)) {
            throw new RuntimeException('Nelze načíst session zařízení.');
        }
        $stmt->bind_param('s', $pcToken);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $idLogin = is_array($row) ? (int)($row['id_login'] ?? 0) : 0;
        if ($idLogin > 0) {
            $stmt = $db->prepare(
                'UPDATE user_login SET duvod=0
                 WHERE id_login=? AND akce=1 AND duvod=2'
            );
            if (!($stmt instanceof mysqli_stmt)) {
                throw new RuntimeException('Nelze ukončit přihlášení zařízení.');
            }
            $stmt->bind_param('i', $idLogin);
            $stmt->execute();
            $stmt->close();
        }

        $stmt = $db->prepare(
            'UPDATE user_pc_session
             SET zruseno=NOW(), duvod_zruseni=?
             WHERE pc_hash=UNHEX(SHA2(?,256)) AND zruseno IS NULL'
        );
        if (!($stmt instanceof mysqli_stmt)) {
            throw new RuntimeException('Nelze zneplatnit session zařízení.');
        }
        $stmt->bind_param('ss', $reason, $pcToken);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
