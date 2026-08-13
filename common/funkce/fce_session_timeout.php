<?php
// funkce/fce_session_timeout.php * Verze: V2 * Aktualizace: 12.08.2026
declare(strict_types=1);

/*
 * SESSION – PEVNA PLATNOST
 *
 * Účel:
 * - po úspěšném loginu nastavit hodnoty do session,
 *   aby šlo ukončit přihlášení po pevné platnosti session.
 *
 * Důležité:
 * - tenhle soubor NESAHÁ do DB
 * - neřeší žádné kontroly typu isset/fallback navíc – nastavuje hodnoty natvrdo,
 *   přesně podle toho, jak to bylo ve db_user_login.php.
 */

if (!function_exists('cb_session_init_timeout')) {

    /**
     * Inicializuje session údaje pro pevnou platnost.
     */
    function cb_session_init_timeout(): void
    {
        $_SESSION['cb_timeout_min'] = 720;

        // kdy začala tahle přihlášená session (na startu = teď)
        $_SESSION['cb_session_start_ts'] = time();
    }
}

// funkce/fce_session_timeout.php * Verze: V2 * Aktualizace: 12.08.2026
// Konec souboru
