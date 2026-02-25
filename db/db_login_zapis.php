<?php
// db/db_login_zapis.php * Verze: V1 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * ZÁPIS PŘIHLÁŠENÍ DO DB
 *
 * Účel:
 * - vloží 1 řádek do user_login (akce=1, duvod=0)
 * - vloží 1 řádek do user_spy (navázaný přes id_login)
 *
 * Důležité:
 * - tenhle soubor NEVOLÁ Směny (API)
 * - bere jen data z PHP runtime (session_id, IP, User-Agent)
 *
 * Návratová hodnota:
 * - vrací id_login (AUTO_INCREMENT) z tabulky user_login
 *
 * Vedlejší efekt:
 * - uloží $_SESSION['cb_id_login'] = id_login
 *
 * Poznámka:
 * - screen_w/screen_h/is_touch zatím neplníme (zůstává NULL),
 *   ale sloupce jsou připravené pro budoucí doplnění.
 */

if (!function_exists('cb_db_insert_login_and_spy')) {

    /**
     * Vloží záznam o přihlášení do user_login a navazující user_spy.
     *
     * Co ukládáme:
     * - user_login: id_user, session_id, kdy (auto), akce=1, duvod=0, ip
     * - user_spy:  id_login (FK), id_user, user_agent (+ do budoucna screen/touch)
     *
     * Vrací:
     * - id_login z tabulky user_login (AUTO_INCREMENT)
     *
     * Vedlejší efekt:
     * - uloží $_SESSION['cb_id_login'] = id_login (hodí se pro další části IS)
     */
    function cb_db_insert_login_and_spy(mysqli $conn, int $idUser): int
    {
        // Session ID je vždy k dispozici, pokud běží session.
        $sessionId = (string)session_id();

        // IP adresa – může být prázdná nebo chybět (např. CLI).
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if (is_string($ip)) {
            $ip = trim($ip);
            if ($ip === '') {
                $ip = null;
            }
        } else {
            $ip = null;
        }

        // User-Agent – může být prázdný nebo chybět.
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (is_string($ua)) {
            $ua = trim($ua);
            if ($ua === '') {
                $ua = null;
            }
        } else {
            $ua = null;
        }

        // 1) user_login (akce=1, duvod=0)
        $stmt = $conn->prepare(
            'INSERT INTO user_login (id_user, session_id, akce, duvod, ip) VALUES (?,?,1,0,?)'
        );
        $stmt->bind_param('iss', $idUser, $sessionId, $ip);
        $stmt->execute();
        $stmt->close();

        // AUTO_INCREMENT z user_login
        $idLogin = (int)$conn->insert_id;

        // 2) user_spy (zatím jen UA; screen_w/screen_h/is_touch zůstanou NULL)
        $stmt = $conn->prepare(
            'INSERT INTO user_spy (id_login, id_user, user_agent, screen_w, screen_h, is_touch)
             VALUES (?,?,?,NULL,NULL,NULL)'
        );
        $stmt->bind_param('iis', $idLogin, $idUser, $ua);
        $stmt->execute();
        $stmt->close();

        // Pro další vrstvy (např. logout, info bloky) si držíme id_login v session.
        $_SESSION['cb_id_login'] = $idLogin;

        return $idLogin;
    }
}

// db/db_login_zapis.php * Verze: V1 * Aktualizace: 21.2.2026 *  Počet řádků: 97
// Konec souboru