<?php
// lib/push_2fa_api.php * Verze: V1 * Aktualizace: 26.2.2026
declare(strict_types=1);

/*
 * 2FA API (polling z PC + zrušení požadavku)
 *
 * Použití:
 * - GET ?check=1   -> vrátí stav aktuální 2FA výzvy ze session (cb_2fa_token)
 * - GET ?cancel=1  -> zruší aktuální 2FA výzvu (stav=ne) + vyčistí session
 *
 * Vrací JSON:
 * - ok: true/false
 * - stav: ceka|ok|ne|exp
 * - zbyva_sec: int (jen pro stav=ceka)
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

function cb_2fa_cleanup_session(): void
{
    unset($_SESSION['login_ok']);
    unset($_SESSION['cb_2fa_token']);

    unset($_SESSION['cb_user']);
    unset($_SESSION['cb_token']);
    unset($_SESSION['cb_user_profile']);
    unset($_SESSION['cb_user_branches']);

    unset($_SESSION['cb_timeout_min']);
    unset($_SESSION['cb_session_start_ts']);
    unset($_SESSION['cb_last_activity_ts']);
}

try {
    $token = (string)($_SESSION['cb_2fa_token'] ?? '');
    if ($token === '') {
        echo json_encode(['ok' => true, 'stav' => 'exp'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cancel = (isset($_GET['cancel']) && (string)$_GET['cancel'] === '1');
    $check = (isset($_GET['check']) && (string)$_GET['check'] === '1');

    if (!$cancel && !$check) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'err' => 'Neplatný požadavek.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $conn = db();

    if ($cancel) {
        $stmt = $conn->prepare('
            UPDATE push_login_2fa
            SET stav=\'ne\', rozhodnuto=NOW()
            WHERE token=? AND stav=\'ceka\'
        ');
        if ($stmt) {
            $stmt->bind_param('s', $token);
            $stmt->execute();
            $stmt->close();
        }

        cb_2fa_cleanup_session();
        echo json_encode(['ok' => true, 'stav' => 'ne'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // check
    $stmt = $conn->prepare('
        SELECT stav, TIMESTAMPDIFF(SECOND, NOW(), vyprsi) AS zbyva_sec
        FROM push_login_2fa
        WHERE token=?
        LIMIT 1
    ');
    if (!$stmt) {
        throw new RuntimeException('DB: prepare selhal.');
    }

    $stmt->bind_param('s', $token);
    $stmt->execute();
    $stmt->bind_result($stav, $zbyvaSec);
    $rowOk = $stmt->fetch();
    $stmt->close();

    if (!$rowOk) {
        cb_2fa_cleanup_session();
        echo json_encode(['ok' => true, 'stav' => 'exp'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!is_string($stav) || $stav === '') {
        $stav = 'exp';
    }

    if ($stav === 'ceka') {
        $z = (int)$zbyvaSec;
        if ($z <= 0) {
            // expirace
            $stmt = $conn->prepare('
                UPDATE push_login_2fa
                SET stav=\'exp\'
                WHERE token=? AND stav=\'ceka\'
            ');
            if ($stmt) {
                $stmt->bind_param('s', $token);
                $stmt->execute();
                $stmt->close();
            }

            cb_2fa_cleanup_session();
            echo json_encode(['ok' => true, 'stav' => 'exp'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        echo json_encode(['ok' => true, 'stav' => 'ceka', 'zbyva_sec' => $z], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($stav === 'ok') {
        // login_ok vzniká až tady
        $_SESSION['login_ok'] = 1;
        unset($_SESSION['cb_2fa_token']);

        $_SESSION['cb_flash'] = 'Přihlášení OK';

        echo json_encode(['ok' => true, 'stav' => 'ok'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($stav === 'ne') {
        cb_2fa_cleanup_session();
        echo json_encode(['ok' => true, 'stav' => 'ne'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // exp nebo cokoliv jiného
    cb_2fa_cleanup_session();
    echo json_encode(['ok' => true, 'stav' => 'exp'], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'err' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// lib/push_2fa_api.php * Verze: V1 * Aktualizace: 26.2.2026 * Počet řádků: 152
// Konec souboru