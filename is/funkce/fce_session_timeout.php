<?php
// funkce/fce_session_timeout.php * Verze: V1 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * SESSION – TIMEOUT NEAKTIVITY
 *
 * Účel:
 * - po úspěšném loginu nastavit hodnoty do session,
 *   aby šlo odpočítávat neaktivitu a případně uživatele odhlásit.
 *
 * Důležité:
 * - tenhle soubor NESAHÁ do DB
 * - neřeší žádné kontroly typu isset/fallback navíc – nastavuje hodnoty natvrdo,
 *   přesně podle toho, jak to bylo ve db_user_login.php.
 */

if (!function_exists('cb_session_init_timeout')) {

    /**
     * Inicializuje session údaje pro timeout neaktivity.
     */
    function cb_session_init_timeout(): void
    {
        // zatím pevně, později půjde z administrace (DB)
        $_SESSION['cb_timeout_min'] = 20;

        // kdy naposledy proběhla uživatelská akce (na startu = teď)
        $_SESSION['cb_last_activity_ts'] = time();

        // kdy začala tahle přihlášená session (na startu = teď)
        $_SESSION['cb_session_start_ts'] = time();
    }
}

// funkce/fce_session_timeout.php * Verze: V1 * Aktualizace: 21.2.2026 * Počet řádků: 37
// Konec souboru