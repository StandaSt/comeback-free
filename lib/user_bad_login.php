<?php
declare(strict_types=1);
// lib/user_bad_login.php * Verze: V4 * Aktualizace: 20.2.2026 * Počet řádků: 69

/*
 * NEÚSPĚŠNÝ LOGIN – WRAPPER (bez DB)
 *
 * Cíl:
 * - lib/ soubory do DB NESAHÁ
 * - tenhle soubor jen sesbírá informace a předá je DB vrstvě
 *
 * Co dělá:
 * 1) sesbírá data o pokusu o přihlášení:
 *    - email
 *    - heslo (ukládá se do DB jako nekódovaný řetězec – jen pro interní diagnostiku)
 *    - IP (REMOTE_ADDR)
 *    - user_agent (HTTP_USER_AGENT)
 *    - screen_w, screen_h, is_touch (pokud jsou ve „wait spy info“ v session)
 * 2) zavolá db/db_bad_login.php → db_bad_login_log()
 *
 * Pozn.:
 * - záznam se dělá pokaždé, když Směny nevrátí token
 * - pokud DB zápis selže, login se tím neblokuje (jen se zapíše diagnostika)
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../db/db_bad_login.php';

/**
 * Zapíše neúspěšný login do DB (user_bad_login).
 * - DB zápis dělá db_bad_login_log() v /db
 */
function cb_user_bad_login_log(string $email, string $heslo): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // „wait spy info“ = data z JS, uložená před pokusem o login (pokud existují)
        $wait = $_SESSION['wait_spy_info'] ?? null;

        $screenW = null;
        $screenH = null;
        $isTouch = null;

        if (is_array($wait)) {
            if (isset($wait['screen_w'])) {
                $screenW = (int)$wait['screen_w'];
            }
            if (isset($wait['screen_h'])) {
                $screenH = (int)$wait['screen_h'];
            }
            if (isset($wait['is_touch'])) {
                $isTouch = (int)$wait['is_touch'];
            }
        }

        // DB vrstva si řeší normalizace vstupů a vložení řádku
        db_bad_login_log($email, $heslo, $ip, $ua, $screenW, $screenH, $isTouch);

    } catch (Throwable $e) {
        // Diagnostika, ale neblokujeme běh jen kvůli logování.
        require_once __DIR__ . '/login_diagnostika.php';
        cb_login_log_line('db_bad_login_failed', ['email' => $email], $e);
    }
}

// lib/user_bad_login.php * Verze: V4 * Aktualizace: 20.2.2026 * Počet řádků: 69
// Konec souboru