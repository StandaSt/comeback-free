<?php
// lib/sesn_kontrola.php * Verze: V2 * Aktualizace: 18.07.2026
declare(strict_types=1);

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

if (!function_exists('cb_session_validate_after_login')) {
    /**
     * Overi, ze prihlasena session porad patri stejnemu klientovi a neni prosla.
     */
    function cb_session_validate_after_login(): bool
    {
        if (empty($_SESSION['login_ok'])) {
            return true;
        }

        $stored = trim((string)($_SESSION['cb_session_fingerprint'] ?? ''));
        if ($stored === '') {
            return false;
        }

        if (!hash_equals($stored, cb_session_client_fingerprint())) {
            return false;
        }

        $nowTs = time();
        $sessionStartRaw = $_SESSION['cb_session_start_ts'] ?? null;
        if (!is_int($sessionStartRaw) && !is_string($sessionStartRaw)) {
            return false;
        }

        $sessionStartTs = filter_var($sessionStartRaw, FILTER_VALIDATE_INT);
        if ($sessionStartTs === false || $sessionStartTs <= 0 || $sessionStartTs > $nowTs) {
            return false;
        }

        $maxSessionSeconds = 12 * 60 * 60;
        if (($nowTs - $sessionStartTs) >= $maxSessionSeconds) {
            return false;
        }

        $todayStart = (new DateTimeImmutable('today 08:00'))->getTimestamp();
        if ($nowTs >= $todayStart && $sessionStartTs < $todayStart) {
            return false;
        }

        return true;
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
        unset($_SESSION['login_ok']);
        unset($_SESSION['cb_auth_ok']);
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
            return;
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
