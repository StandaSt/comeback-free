<?php
declare(strict_types=1);
// db/db_bad_login.php * Verze: V3 * Aktualizace: 20.2.2026 * Počet řádků: 110

/*
 * DB: user_bad_login (neúspěšný login)
 *
 * Cíl:
 * - DB zápisy jsou jen ve složce /db
 * - lib/ soubory jen volají DB vrstvu
 *
 * Co dělá:
 * 1) Normalizuje vstupy (email/IP/UA/screen)
 * 2) Vloží řádek do user_bad_login:
 *    - email
 *    - heslo (nekódovaný řetězec, jak ho zadal uživatel)
 *    - ip
 *    - user_agent
 *    - screen_w, screen_h, is_touch
 * 3) Vrátí id_bad_login (AUTO_INCREMENT)
 * 4) Načte zbývající pokusy a přesný konec blokace v klouzavém časovém okně.
 *
 * Poznámky:
 * - tabulka user_bad_login má ip NOT NULL → když IP není k dispozici, uloží se prázdný string
 * - screen_* a is_touch mohou být NULL
 */
if (!function_exists('db_bad_login_log')) {

    /**
     * Vloží záznam neúspěšného přihlášení.
     *
     * @return int id_bad_login (AUTO_INCREMENT)
     */
    function db_bad_login_log(
        string $email,
        string $hesloPlain,
        ?string $ip,
        ?string $userAgent,
        ?int $screenW,
        ?int $screenH,
        ?int $isTouch
    ): int {
        $email = trim($email);

        // heslo ukládáme jako nekódovaný řetězec (požadavek projektu)
        $heslo = (string)$hesloPlain;

        // IP (NOT NULL v DB) → prázdný string, pokud nic není
        if (is_string($ip)) {
            $ip = trim($ip);
        }
        if (!is_string($ip) || $ip === '') {
            $ip = '';
        }

        // user agent (může být NULL)
        if (is_string($userAgent)) {
            $userAgent = trim($userAgent);
            if ($userAgent === '') {
                $userAgent = null;
            }
        } else {
            $userAgent = null;
        }

        // screen_w/screen_h: buď NULL, nebo kladné číslo v rozsahu SMALLINT
        if ($screenW !== null) {
            $screenW = (int)$screenW;
            if ($screenW < 1 || $screenW > 65535) {
                $screenW = null;
            }
        }
        if ($screenH !== null) {
            $screenH = (int)$screenH;
            if ($screenH < 1 || $screenH > 65535) {
                $screenH = null;
            }
        }

        // is_touch: NULL nebo 0/1
        if ($isTouch !== null) {
            if ((int)$isTouch === 1) {
                $isTouch = 1;
            } else {
                $isTouch = 0;
            }
        }

        $conn = db();

        $stmt = $conn->prepare(
            'INSERT INTO user_bad_login (email, heslo, ip, user_agent, screen_w, screen_h, is_touch)
             VALUES (?,?,?,?,?,?,?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (user_bad_login).');
        }

        $stmt->bind_param('ssssiii', $email, $heslo, $ip, $userAgent, $screenW, $screenH, $isTouch);
        $stmt->execute();
        $stmt->close();

        return (int)$conn->insert_id;
    }
}

// db/db_bad_login.php * Verze: V3 * Aktualizace: 20.2.2026 * Počet řádků: 110
if (!function_exists('db_bad_login_count_recent_by_email')) {

    /**
     * Vrati pocet neuspesnych pokusu pro stejny email za poslednich X minut.
     */
    function db_bad_login_count_recent_by_email(string $email, int $minutes): int
    {
        $email = trim($email);
        $minutes = (int)$minutes;
        if ($minutes <= 0) {
            $minutes = 1;
        }

        $conn = db();
        $stmt = $conn->prepare(
            'SELECT COUNT(*) AS cnt
             FROM user_bad_login
             WHERE email = ?
               AND kdy >= (NOW() - INTERVAL ? MINUTE)'
        );
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (user_bad_login count).');
        }

        $stmt->bind_param('si', $email, $minutes);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->close();

        return (int)$cnt;
    }
}

/** Načte nejnovější chyby; blokace skončí vypršením páté nejnovější, i při souběžných pokusech. */
function db_bad_login_stav(string $email, int $maxAttempts = 5, int $minutes = 15): array
{
    $email = trim($email);
    $maxAttempts = max(1, $maxAttempts);
    $minutes = max(1, $minutes);
    $stmt = db()->prepare(
        'SELECT TIMESTAMPDIFF(SECOND, NOW(), kdy + INTERVAL ? MINUTE) + 1 AS sekund,
                DATE_FORMAT(kdy + INTERVAL ? MINUTE + INTERVAL 1 SECOND, \'%H:%i:%s\') AS do_casu
         FROM user_bad_login
         WHERE email=? AND kdy >= NOW() - INTERVAL ? MINUTE
         ORDER BY kdy DESC, id_bad_login DESC LIMIT ' . $maxAttempts
    );
    if (!$stmt) {
        throw new RuntimeException('Nelze načíst stav omezení přihlášení.');
    }
    try {
        $stmt->bind_param('iisi', $minutes, $minutes, $email, $minutes);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $blocked = count($rows) >= $maxAttempts;
        // Hranice původního filtru je včetně poslední sekundy, proto přidáváme jednu sekundu.
        $last = $blocked ? $rows[$maxAttempts - 1] : null;
        return [
            'zbyva' => max(0, $maxAttempts - count($rows)),
            'blokovano' => $blocked,
            'sekund' => $last !== null ? max(1, (int)$last['sekund']) : 0,
            'do_casu' => $last !== null ? (string)$last['do_casu'] : '',
            'limit' => $maxAttempts,
            'minut' => $minutes,
        ];
    } finally {
        $stmt->close();
    }
}

// Konec souboru
