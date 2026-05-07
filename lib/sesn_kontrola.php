<?php
// lib/sesn_kontrola.php * Verze: V1 * Aktualizace: 07.05.2026
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
     * Overi, ze prihlasena session porad patri stejnemu klientovi.
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

        return hash_equals($stored, cb_session_client_fingerprint());
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
        unset($_SESSION['cb_id_login']);
        unset($_SESSION['cb_login_info']);
        unset($_SESSION['cb_timeout_min']);
        unset($_SESSION['cb_session_start_ts']);
        unset($_SESSION['cb_last_activity_ts']);
        unset($_SESSION['cb_session_bound']);
        unset($_SESSION['cb_session_bound_at']);
        unset($_SESSION['cb_session_bound_id']);
        unset($_SESSION['cb_session_fingerprint']);
    }
}

