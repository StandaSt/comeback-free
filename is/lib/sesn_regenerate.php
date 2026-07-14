<?php
// lib/sesn_regenerate.php * Verze: V1 * Aktualizace: 07.05.2026
declare(strict_types=1);

if (!function_exists('cb_session_regenerate_after_login')) {
    /**
     * Po uspesnem loginu vygeneruje nove session ID.
     */
    function cb_session_regenerate_after_login(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        return session_regenerate_id(true);
    }
}

if (!function_exists('cb_session_bind_after_login')) {
    /**
     * Svaze aktualni prihlasenou session s klientem.
     */
    function cb_session_bind_after_login(): void
    {
        $_SESSION['cb_session_bound'] = 1;
        $_SESSION['cb_session_bound_at'] = time();
        $_SESSION['cb_session_bound_id'] = (string)session_id();
        $_SESSION['cb_session_fingerprint'] = cb_session_client_fingerprint();
    }
}

