<?php
declare(strict_types=1);

/*
 * Ucel souboru: Vydava a jednorazove spotrebovava petiminutove prihlasovaci
 * e-mailove tokeny. Volajici smi o vydani pozadat pouze po overeni hesla.
 * Tokeny jsou oddelene od obnovy hesla; v DB je jen jejich SHA-256 otisk.
 */

/* Svaze odkaz s aktualnim heslem a e-mailem, aby jejich zmena odkaz zneplatnila. */
function cb_login_email_identita(array $user): string
{
    return hash('sha256', (string)$user['email'] . "\0" . (string)$user['heslo_hash']);
}

/* Rozpozna pouze nahodny 256bitovy token v presne ocekavanem formatu. */
function cb_login_email_format(string $token): bool
{
    return preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
}

/* Vytvori odkaz; zamek uzivatele serializuje soubezne zadosti i limit odesilani. */
function cb_login_email_odeslat(mysqli $db, array $user, string $module): void
{
    $idUser = (int)$user['id_user'];
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $identita = cb_login_email_identita($user);
    $module = in_array($module, ['provoz', 'hr', 'smeny'], true) ? $module : 'provoz';
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT email, heslo_hash FROM user WHERE id_user=? FOR UPDATE');
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $aktualni = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($aktualni) || !hash_equals($identita, cb_login_email_identita($aktualni))) {
            throw new CbUserVisibleException('Přihlašovací údaje se změnily. Přihlaste se znovu.');
        }
        $stmt = $db->prepare('SELECT COUNT(*) AS pocet, COALESCE(MAX(vytvoreno > NOW() - INTERVAL 60 SECOND), 0) AS nedavno FROM user_login_email_token WHERE id_user=? AND vytvoreno > NOW() - INTERVAL 15 MINUTE');
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $limit = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ((int)$limit['nedavno'] > 0 || (int)$limit['pocet'] >= 5) {
            throw new CbUserVisibleException('O přihlašovací e-mail lze požádat jednou za minutu, nejvýše 5krát za 15 minut. Vyčkejte prosím.');
        }
        $stmt = $db->prepare('UPDATE user_login_email_token SET zruseno=NOW() WHERE id_user=? AND pouzito IS NULL AND zruseno IS NULL');
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->close();
        $stmt = $db->prepare('INSERT INTO user_login_email_token (id_user, token_hash, identita_hash, cil_modul, vytvoreno, platnost_do) VALUES (?, ?, ?, ?, NOW(), NOW() + INTERVAL 5 MINUTE)');
        $stmt->bind_param('isss', $idUser, $hash, $identita, $module);
        $stmt->execute();
        $stmt->close();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }

    // SMTP probiha mimo transakci; pri chybe zustane odkaz zneplatneny a limit zachovany.
    try {
        require_once __DIR__ . '/email_login.php';
        // Produkcni odkaz nesmi prevzit podvrzenou Host hlavicku prihlasovaciho pozadavku.
        $path = 'common/lib/login_email_vstup.php?t=' . $token;
        $url = ($GLOBALS['PROSTREDI'] ?? '') === 'SERVER'
            ? 'https://comebacks.cz/' . $path : cb_url_abs($path);
        cb_email_login_odeslat((string)$user['email'], $url);
    } catch (Throwable $e) {
        $stmt = $db->prepare('UPDATE user_login_email_token SET zruseno=NOW() WHERE token_hash=? AND pouzito IS NULL');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $stmt->close();
        throw $e;
    }
}

/* Atomicky spotrebuje platny odkaz; plnou session smi volajici vytvorit az po uspechu. */
function cb_login_email_spotrebuj(mysqli $db, string $token): ?array
{
    if (!cb_login_email_format($token)) {
        return null;
    }
    $hash = hash('sha256', $token);
    $db->begin_transaction();
    try {
        $stmt = $db->prepare('SELECT id_user, identita_hash, cil_modul FROM user_login_email_token WHERE token_hash=? AND pouzito IS NULL AND zruseno IS NULL AND platnost_do>NOW() FOR UPDATE');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($row)) {
            $db->rollback();
            return null;
        }
        require_once __DIR__ . '/prvni_vstup.php';
        $user = cb_prvni_vstup_user($db, (int)$row['id_user']);
        if (!is_array($user) || (int)$user['aktivni'] !== 1 || !hash_equals((string)$row['identita_hash'], cb_login_email_identita($user))) {
            $db->rollback();
            return null;
        }
        $stmt = $db->prepare('UPDATE user_login_email_token SET pouzito=NOW() WHERE token_hash=? AND pouzito IS NULL AND zruseno IS NULL AND platnost_do>NOW()');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $ok = $stmt->affected_rows === 1;
        $stmt->close();
        $db->commit();
        return $ok ? ['user' => $user, 'module' => (string)$row['cil_modul']] : null;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
