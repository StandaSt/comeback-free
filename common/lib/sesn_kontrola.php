<?php
// lib/sesn_kontrola.php * Verze: V2 * Aktualizace: 18.07.2026
/* Overuje vazbu a platnost prihlaseni; pri jeho zruseni odstrani i prebrani prav a odemceni e-mailovych schranek.
 * Kazde prihlaseni overuje samostatne; pocet soubeznych prihlaseni uzivatele neni omezen.
 * Zruseni identity odstrani i rozpracovanou vyzvu PIN; novy otevreny PIN zablokuje stare prihlaseni do jeho zmeny.
 * Duvody zamitnuti zaznamenava bez prihlasovacich tajemstvi do neverejneho diagnostickeho logu. */
declare(strict_types=1);

require_once __DIR__ . '/pc_session.php';
require_once __DIR__ . '/login_pin.php';

if (!function_exists('cb_session_client_fingerprint')) {
    /**
     * Vrati jednoduchy otisk klienta pro navazani session.
     */
    function cb_session_client_fingerprint(): string
    {
        $ua = trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $lang = trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));

        return hash('sha256', $ua . '|' . $lang);
    }
}

if (!function_exists('cb_session_reject_replaced_request')) {
    /**
     * Ukonci pouze pozadavek stareho okna. Sdilenou PHP session noveho usera nemeni.
     */
    function cb_session_reject_replaced_request(): void
    {
        cb_session_log_rejection('request_session_replaced');
        http_response_code(409);
        header('X-Comeback-Session-Replaced: 1');
        if (cb_session_is_internal_request()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok' => false,
                'err' => 'Na tomto zařízení se přihlásil jiný uživatel.',
            ], JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!doctype html><html lang="cs"><meta charset="utf-8"><title>Přihlášení skončilo</title>'
                . '<body style="margin:0;min-height:100vh;display:grid;place-items:center;background:#111827;color:#fff;font-family:Arial,sans-serif;padding:24px;box-sizing:border-box">'
                . '<main style="max-width:520px;text-align:center"><h1>Přihlášení v tomto okně skončilo</h1>'
                . '<p style="font-size:18px;line-height:1.5">Na tomto zařízení se přihlásil jiný uživatel. Toto staré okno můžete zavřít.</p></main></body></html>';
        }
        exit;
    }
}

if (!function_exists('cb_session_login_is_current')) {
    /**
     * Overi konkretni aktivni login a aktivitu osoby bez omezeni soubeznych prihlaseni.
     */
    function cb_session_login_is_current(): bool
    {
        $user = $_SESSION['cb_user'] ?? null;
        $idUser = is_array($user) ? (int)($user['id_user'] ?? 0) : 0;
        $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);
        if ($idUser <= 0 || $idLogin <= 0 || !function_exists('db')) {
            cb_session_log_rejection('login_identity_missing');
            return false;
        }

        try {
            $conn = db();
            // Spravcem zadany pocatecni PIN nesmi jit obejit drive otevrenou relaci.
            $pinSettings = cb_pin_nastaveni($conn, $idUser);
            if ($pinSettings !== null && cb_pin_pocatecni($pinSettings)) {
                cb_session_log_rejection('pin_initial_change_required');
                return false;
            }
            if (!cb_pc_session_is_current($conn, $idUser, $idLogin)) {
                return false;
            }
            // Platnost patri tomuto loginu, nikoli poslednimu prihlaseni celeho uzivatele.
            $stmt = $conn->prepare(
                'SELECT ul.id_login
                 FROM user_login ul
                 INNER JOIN hr_person p ON p.id_user = ul.id_user AND p.aktivni = 1
                 WHERE ul.id_login = ? AND ul.id_user = ? AND ul.akce = 1 AND ul.duvod = 2
                 LIMIT 1'
            );
            $stmt->bind_param('ii', $idLogin, $idUser);

            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $current = is_array($row) && (int)($row['id_login'] ?? 0) === $idLogin;
            if (!$current) cb_session_log_rejection('login_not_current_or_person_inactive');
            return $current;
        } catch (Throwable $e) {
            cb_session_log_rejection('login_database_error');
            return false;
        }
    }
}

if (!function_exists('cb_session_validate_after_login')) {
    /**
     * Overi, ze prihlasena session porad patri stejnemu klientovi a neni prosla.
     */
    function cb_session_validate_after_login(): bool
    {
        if (empty($_SESSION['login_ok'])) {
            return true;
        }

        if (!cb_pc_request_matches_session()) {
            cb_session_reject_replaced_request();
        }

        $stored = trim((string)($_SESSION['cb_session_fingerprint'] ?? ''));
        if ($stored === '') {
            cb_session_log_rejection('client_fingerprint_missing');
            return false;
        }

        if (!hash_equals($stored, cb_session_client_fingerprint())) {
            // Porovnani slozek je dostupne az po prvni uspesne kontrole teto relace.
            $previous = $_SESSION['cb_session_diagnostic_client'] ?? [];
            $checks = [];
            foreach (['ua' => 'HTTP_USER_AGENT', 'language' => 'HTTP_ACCEPT_LANGUAGE'] as $part => $header) {
                if (is_array($previous) && isset($previous[$part]) && is_string($previous[$part])) {
                    $checks[$part === 'ua' ? 'ua_changed' : 'language_changed'] =
                        !hash_equals($previous[$part], hash('sha256', trim((string)($_SERVER[$header] ?? ''))));
                }
            }
            cb_session_log_rejection('client_fingerprint_changed', $checks);
            return false;
        }

        $nowTs = time();
        $sessionStartRaw = $_SESSION['cb_session_start_ts'] ?? null;
        if (!is_int($sessionStartRaw) && !is_string($sessionStartRaw)) {
            cb_session_log_rejection('session_start_missing');
            return false;
        }

        $sessionStartTs = filter_var($sessionStartRaw, FILTER_VALIDATE_INT);
        if ($sessionStartTs === false || $sessionStartTs <= 0 || $sessionStartTs > $nowTs) {
            cb_session_log_rejection('session_start_invalid');
            return false;
        }

        $maxSessionSeconds = 12 * 60 * 60;
        if (($nowTs - $sessionStartTs) >= $maxSessionSeconds) {
            cb_session_log_rejection('session_max_age');
            return false;
        }

        $todayStart = (new DateTimeImmutable('today 08:00'))->getTimestamp();
        if ($nowTs >= $todayStart && $sessionStartTs < $todayStart) {
            cb_session_log_rejection('session_daily_boundary');
            return false;
        }

        $current = cb_session_login_is_current();
        if ($current) {
            // Ukladame jen otisky hlavicek, aby dalsi zamitnuti rozlisilo zmenu UA od zmeny jazyka.
            $_SESSION['cb_session_diagnostic_client'] = [
                'ua' => hash('sha256', trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))),
                'language' => hash('sha256', trim((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))),
            ];
        }
        return $current;
    }
}

if (!function_exists('cb_session_is_internal_request')) {
    /**
     * Pozna interni AJAX pozadavek podle projektovych hlavicek X-Comeback-*.
     */
    function cb_session_is_internal_request(): bool
    {
        foreach (array_keys($_SERVER) as $serverKey) {
            if (strncmp((string)$serverKey, 'HTTP_X_COMEBACK_', 16) === 0) {
                return true;
            }
        }

        $requestedWith = strtolower(trim((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')));
        return $requestedWith === 'xmlhttprequest';
    }
}

if (!function_exists('cb_session_forget_auth')) {
    /**
     * Vymaze autentizacni data session bez uplneho zniceni cele session.
     */
    function cb_session_forget_auth(): void
    {
        // Jednorazove prebrani prav neprezije logout, expiraci ani zneplatneni session.
        unset($_SESSION['cb_prebrani_prav']);
        unset($_SESSION['login_ok']);
        unset($_SESSION['cb_auth_ok']);
        unset($_SESSION['cb_pin_ceka'], $_SESSION['cb_pin_zprava']);
        unset($_SESSION['cb_2fa_token']);
        unset($_SESSION['cb_user']);
        unset($_SESSION['cb_token']);
        unset($_SESSION['cb_user_profile']);
        unset($_SESSION['cb_user_branches']);
        unset($_SESSION['prava']);
        unset($_SESSION['cb_id_login']);
        unset($_SESSION['cb_login_info']);
        unset($_SESSION['cb_timeout_min']);
        unset($_SESSION['cb_session_start_ts']);
        unset($_SESSION['cb_last_activity_ts']);
        unset($_SESSION['cb_system']);
        unset($_SESSION['cb_user_settings']);
        unset($_SESSION['cb_session_bound']);
        unset($_SESSION['cb_session_bound_at']);
        unset($_SESSION['cb_session_bound_id']);
        unset($_SESSION['cb_session_fingerprint']);
        unset($_SESSION['cb_session_diagnostic_client']);
        unset($_SESSION['cb_pc_token']);
        unset($_SESSION['cb_pc_session_token']);
        // Rucne odemcena posta nesmi prezit zadny zpusob ukonceni prihlaseni do IS.
        unset($_SESSION['email_pristup'], $_SESSION['email_pokusy'], $_SESSION['email_posledni_pokus']);
    }
}

if (!function_exists('cb_session_invalidate_auth')) {
    /**
     * Serverove zneplatni prihlasenou cast session a zabrani pouziti stareho ID.
     */
    function cb_session_invalidate_auth(): void
    {
        cb_session_forget_auth();

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }
}

if (!function_exists('cb_session_guard_entry')) {
    /**
     * Centralni kontrola prihlasene session pro vstupni soubory modulu.
     */
    function cb_session_guard_entry(bool $touchValidPageRequest = true): void
    {
        if (empty($_SESSION['login_ok'])) {
            if (cb_session_is_internal_request()) {
                cb_session_log_rejection('authentication_missing');
                http_response_code(401);
                exit;
            }

            return;
        }

        if (!cb_pc_request_matches_session()) {
            cb_session_reject_replaced_request();
        }

        if (!cb_session_validate_after_login()) {
            cb_session_invalidate_auth();

            if (cb_session_is_internal_request()) {
                http_response_code(401);
                exit;
            }

            header('Location: ' . cb_login_url());
            exit;
        }

    }
}
