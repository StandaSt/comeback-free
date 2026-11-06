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
 * 3) Připraví informaci o zbývajících pokusech, době blokace a nastavení nového hesla.
 *
 * Pozn.:
 * - zaznam se dela pri neznamem e-mailu nebo nespravnem hesle do IS
 * - ucet bez nastaveneho hesla se neloguje jako chybny pokus
 * - pokud DB zápis selže, login se tím neblokuje (jen se zapíše diagnostika)
 */
require_once __DIR__ . '/../db/db_bad_login.php';
require_once __DIR__ . '/../notifikace/notifikace_2fa.php';

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
        cb_push_send_bad_login_admin($email, is_string($ip) ? $ip : null, 1);

    } catch (Throwable $e) {
        // Diagnostika, ale neblokujeme běh jen kvůli logování.
    }
}

// lib/user_bad_login.php * Verze: V4 * Aktualizace: 20.2.2026 * Počet řádků: 69
/**
 * Vrati true, kdyz je email docasne blokovany kvuli opakovanym neuspesnym pokusum.
 */
function cb_user_bad_login_is_blocked(string $email, int $maxAttempts = 5, int $minutes = 15): bool
{
    try {
        return db_bad_login_count_recent_by_email($email, $minutes) >= $maxAttempts;
    } catch (Throwable $e) {
        return false;
    }
}

/** Doplní chybu o aktuální limit; při nedostupnosti diagnostiky zachová původní hlášení. */
function cb_user_bad_login_zprava(string $email, string $message = '', bool $jenBlokace = false): string
{
    // Nabidka obnovy zustane citelna i pri nedostupnem poctu pokusu; samotna kontrola nic nenabizi.
    $hint = ' Pokud heslo neznáte, klikněte na „Nastavit nové heslo“.';
    try {
        $stav = db_bad_login_stav($email);
    } catch (Throwable $e) {
        return $jenBlokace ? $message : $message . $hint;
    }
    if ($stav['blokovano']) {
        $seconds = (int)$stav['sekund'];
        $wait = $seconds >= 60 ? intdiv($seconds, 60) . ' min ' . ($seconds % 60) . ' s' : $seconds . ' s';
        return 'Přihlášení je dočasně zablokované: ' . $stav['limit'] . ' neúspěšných pokusů během '
            . $stav['minut'] . ' minut. Další pokus je možný v ' . $stav['do_casu'] . ' (za ' . $wait
            . '). Do té doby nelze přihlášení dokončit ani se správným heslem.' . $hint;
    }
    if ($jenBlokace) {
        return $message;
    }
    $remaining = (int)$stav['zbyva'];
    $suffix = $remaining === 1 ? 'Zbývá 1 pokus' : 'Zbývají ' . $remaining . ' pokusy';
    if ($remaining >= 5) {
        $suffix = 'Zbývá ' . $remaining . ' pokusů';
    }
    return $message . ' ' . $suffix . ' do dočasné blokace (limit ' . $stav['limit'] . ' chyb za '
        . $stav['minut'] . ' minut).' . $hint;
}

// Konec souboru
