<?php
declare(strict_types=1);

/*
 * Ucel souboru: Udrzet prave jednu aktivni identitu IS pro jeden browser
 * profil. Cookie obsahuje pouze nahodny identifikator; DB uklada jen jeho hash.
 * Vazbu profilu uchovava take u prihlaseni, aby ji dalsi session neprepsala.
 * Posledni kontakt urcuje stav online, nikoli platnost prihlaseni po uspani nebo vypadku site.
 * Zamitnute kontroly zaznamenava bez tokenu do neverejneho denniho diagnostickeho logu.
 */

/** Zaznam duvodu nesmi zmenit vysledek overeni ani vypsat citliva data do odpovedi. */
function cb_session_log_rejection(string $reason, array $checks = []): void
{
    static $written = [];
    $idUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);
    $key = $idUser . ':' . $idLogin . ':' . $reason;
    if (isset($written[$key])) return;
    $written[$key] = true;

    // Pevny seznam dovoluje jen vysledky kontrol, nikdy obsah cookies, tokenu nebo SQL.
    $safeChecks = [];
    foreach (['ua_changed', 'language_changed', 'cookie_valid', 'pc_token_valid', 'session_token_valid',
        'cookie_matches', 'device_exists', 'device_user_matches', 'device_login_matches',
        'device_token_matches', 'device_revoked', 'device_fresh'] as $name) {
        if (isset($checks[$name]) && is_bool($checks[$name])) $safeChecks[$name] = $checks[$name];
    }
    // URL muze obsahovat tajny udaj i v ceste; ukladame jen overenou cestu PHP vstupu.
    $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $row = ['cas' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format(DATE_ATOM),
        'id_user' => $idUser, 'id_login' => $idLogin, 'duvod' => $reason,
        'skript' => preg_match('~^/[a-zA-Z0-9_./-]*\.php$~D', $script) === 1 ? $script : '',
        'metoda' => in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], true)
            ? $_SERVER['REQUEST_METHOD'] : '', 'kontroly' => $safeChecks];
    try {
        $directory = dirname(__DIR__, 3) . '/data/tmp/session_diagnostika';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) return;
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        @file_put_contents($directory . '/' . substr($row['cas'], 0, 10) . '.jsonl', $line, FILE_APPEND | LOCK_EX);
    } catch (Throwable $error) {
        // Diagnostika je vedlejsi; neplatna relace zustane zamitnuta i pri chybe uloziste.
    }
}

/** Hranice stavu online v prehledu zarizeni; nejde o dobu platnosti prihlaseni. */
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

// Aktivuje relaci a ve stejne transakci zachova identitu profilu v historii prihlaseni.
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

        // Historie drzi stejny hash cookie i po odhlaseni nebo novem prihlaseni na tomto profilu.
        $stmt = $db->prepare(
            'UPDATE user_login SET pc_hash=UNHEX(SHA2(?,256))
             WHERE id_login=? AND id_user=? AND akce=1'
        );
        if (!($stmt instanceof mysqli_stmt)) {
            throw new RuntimeException('Nelze ulozit vazbu prihlaseni na zarizeni.');
        }
        $stmt->bind_param('sii', $pcToken, $idLogin, $idUser);
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

/** Overi nezrusenou vazbu zarizeni a obnovi kontakt i po delsi odmlce prohlizece. */
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
        cb_session_log_rejection('device_credentials_invalid', [
            'cookie_valid' => preg_match('/^[a-f0-9]{64}$/', $cookieToken) === 1,
            'pc_token_valid' => preg_match('/^[a-f0-9]{64}$/', $pcToken) === 1,
            'session_token_valid' => preg_match('/^[a-f0-9]{64}$/', $sessionToken) === 1,
            'cookie_matches' => $pcToken !== '' && hash_equals($pcToken, $cookieToken),
        ]);
        return false;
    }

    // Odmlka prohlizece nerusi prihlaseni; jeho casovou platnost overuje sesn_kontrola.php.
    $sql = '
        SELECT id_login
        FROM user_pc_session
        WHERE pc_hash=UNHEX(SHA2(?,256))
          AND session_hash=UNHEX(SHA2(?,256))
          AND id_user=?
          AND id_login=?
          AND zruseno IS NULL
        LIMIT 1
    ';
    $stmt = $db->prepare($sql);
    if (!($stmt instanceof mysqli_stmt)) {
        cb_session_log_rejection('device_select_prepare_failed');
        return false;
    }
    $stmt->bind_param('ssii', $pcToken, $sessionToken, $idUser, $idLogin);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!is_array($row)) {
        // Doplneni priciny je pouze SELECT po zamitnuti vazby; stari kontaktu neni duvod zamitnuti.
        $checks = [];
        try {
            $diagnostic = $db->prepare('SELECT id_user=?, id_login=?, session_hash=UNHEX(SHA2(?,256)),
                zruseno IS NOT NULL
                FROM user_pc_session WHERE pc_hash=UNHEX(SHA2(?,256)) LIMIT 1');
            if ($diagnostic instanceof mysqli_stmt) {
                $diagnostic->bind_param('iiss', $idUser, $idLogin, $sessionToken, $pcToken);
                $diagnostic->execute();
                $flags = $diagnostic->get_result()->fetch_row();
                $diagnostic->close();
                $checks['device_exists'] = is_array($flags);
                if (is_array($flags)) {
                    foreach (['device_user_matches', 'device_login_matches', 'device_token_matches', 'device_revoked'] as $index => $name) {
                        $checks[$name] = (bool)$flags[$index];
                    }
                }
            }
        } catch (Throwable $error) {
            // Chyba doplnujiciho SELECT nesmi potlacit zaznam puvodniho zamitnuti.
        }
        cb_session_log_rejection('device_session_not_current', $checks);
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
        cb_session_log_rejection('device_touch_prepare_failed');
        return false;
    }
    $stmt->bind_param('ssii', $pcToken, $sessionToken, $idUser, $idLogin);
    $ok = $stmt->execute();
    $stmt->close();
    if (!$ok) cb_session_log_rejection('device_touch_failed');
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
