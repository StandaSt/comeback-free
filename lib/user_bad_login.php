<?php
declare(strict_types=1);
// lib/user_bad_login.php * Verze: V4 * Aktualizace: 20.2.2026 * PoÄŤet Ĺ™ĂˇdkĹŻ: 69

/*
 * NEĂšSPÄšĹ NĂť LOGIN â€“ WRAPPER (bez DB)
 *
 * CĂ­l:
 * - lib/ soubory do DB NESAHĂ
 * - tenhle soubor jen sesbĂ­rĂˇ informace a pĹ™edĂˇ je DB vrstvÄ›
 *
 * Co dÄ›lĂˇ:
 * 1) sesbĂ­rĂˇ data o pokusu o pĹ™ihlĂˇĹˇenĂ­:
 *    - email
 *    - heslo (uklĂˇdĂˇ se do DB jako nekĂłdovanĂ˝ Ĺ™etÄ›zec â€“ jen pro internĂ­ diagnostiku)
 *    - IP (REMOTE_ADDR)
 *    - user_agent (HTTP_USER_AGENT)
 *    - screen_w, screen_h, is_touch (pokud jsou ve â€žwait spy infoâ€ś v session)
 * 2) zavolĂˇ db/db_bad_login.php â†’ db_bad_login_log()
 *
 * Pozn.:
 * - zĂˇznam se dÄ›lĂˇ pokaĹľdĂ©, kdyĹľ SmÄ›ny nevrĂˇtĂ­ token
 * - pokud DB zĂˇpis selĹľe, login se tĂ­m neblokuje (jen se zapĂ­Ĺˇe diagnostika)
 */
require_once __DIR__ . '/../db/db_bad_login.php';

/**
 * ZapĂ­Ĺˇe neĂşspÄ›ĹˇnĂ˝ login do DB (user_bad_login).
 * - DB zĂˇpis dÄ›lĂˇ db_bad_login_log() v /db
 */
function cb_user_bad_login_log(string $email, string $heslo): void
{
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // â€žwait spy infoâ€ś = data z JS, uloĹľenĂˇ pĹ™ed pokusem o login (pokud existujĂ­)
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

        // DB vrstva si Ĺ™eĹˇĂ­ normalizace vstupĹŻ a vloĹľenĂ­ Ĺ™Ăˇdku
        db_bad_login_log($email, $heslo, $ip, $ua, $screenW, $screenH, $isTouch);

    } catch (Throwable $e) {
        // Diagnostika, ale neblokujeme bÄ›h jen kvĹŻli logovĂˇnĂ­.
    }
}

// lib/user_bad_login.php * Verze: V4 * Aktualizace: 20.2.2026 * PoÄŤet Ĺ™ĂˇdkĹŻ: 69
// Konec souboru
