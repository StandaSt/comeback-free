<?php
// db/db_login_blok_info.php * Verze: V1 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * LOGIN BLOK – DATA DO SESSION (pro hlavičku)
 *
 * Účel:
 * - po úspěšném přihlášení načteme z DB pár údajů o přihlášení
 *   a uložíme je do $_SESSION['cb_login_info'].
 *
 * Proč:
 * - hlavička pak nemusí dělat další DB dotazy při každém requestu.
 *
 * Co ukládáme:
 * - aktuální login (čas+ip) podle id_login (tedy "teď")
 * - předchozí úspěšný login (čas+ip) – mimo právě vložený řádek
 * - statistiky loginů (jen úspěšné: akce=1)
 *   - total: celkem
 *   - today: dnes (DATE(kdy)=CURDATE())
 *
 * Důležité:
 * - soubor NEVOLÁ Směny (API)
 * - pracuje jen s DB tabulkou user_login
 */

if (!function_exists('cb_db_fill_login_info_session')) {

    /**
     * Načte login informace pro hlavičku a uloží je do session.
     *
     * Vstup:
     * - $idUser  ... uživatel
     * - $idLogin ... aktuální login (řádek, který jsme právě vložili)
     */
    function cb_db_fill_login_info_session(mysqli $conn, int $idUser, int $idLogin): void
    {
        $currentKdy = null;
        $currentIp = null;

        // A) aktuální login (podle id_login)
        $stmt = $conn->prepare('SELECT kdy, ip FROM user_login WHERE id_login=? LIMIT 1');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (current login).');
        }
        $stmt->bind_param('i', $idLogin);
        $stmt->execute();
        $stmt->bind_result($kdyDb, $ipDb);
        if ($stmt->fetch()) {
            $currentKdy = is_string($kdyDb) ? $kdyDb : null;
            $currentIp = is_string($ipDb) ? $ipDb : null;
        }
        $stmt->close();

        // B) předchozí úspěšný login (akce=1), mimo právě vložený řádek
        $prevKdy = null;
        $prevIp = null;

        $stmt = $conn->prepare(
            'SELECT kdy, ip
             FROM user_login
             WHERE id_user=? AND akce=1 AND id_login<>?
             ORDER BY kdy DESC, id_login DESC
             LIMIT 1'
        );
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (prev login).');
        }
        $stmt->bind_param('ii', $idUser, $idLogin);
        $stmt->execute();
        $stmt->bind_result($pkdyDb, $pipDb);
        if ($stmt->fetch()) {
            $prevKdy = is_string($pkdyDb) ? $pkdyDb : null;
            $prevIp = is_string($pipDb) ? $pipDb : null;
        }
        $stmt->close();

        // C) statistika loginů (jen úspěšné: akce=1)
        $total = 0;
        $today = 0;

        $stmt = $conn->prepare('SELECT COUNT(*) FROM user_login WHERE id_user=? AND akce=1');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (login total).');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($cnt);
        if ($stmt->fetch()) {
            $total = (int)$cnt;
        }
        $stmt->close();

        $stmt = $conn->prepare('SELECT COUNT(*) FROM user_login WHERE id_user=? AND akce=1 AND DATE(kdy)=CURDATE()');
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (login today).');
        }
        $stmt->bind_param('i', $idUser);
        $stmt->execute();
        $stmt->bind_result($cnt2);
        if ($stmt->fetch()) {
            $today = (int)$cnt2;
        }
        $stmt->close();

        // Výsledná struktura v session:
        // - držíme to stabilní, aby se na to UI mohlo spolehnout.
        $_SESSION['cb_login_info'] = [
            'current' => [
                'kdy' => $currentKdy,
                'ip' => $currentIp,
                'id_login' => $idLogin,
            ],
            'prev' => ($prevKdy !== null || $prevIp !== null) ? [
                'kdy' => $prevKdy,
                'ip' => $prevIp,
            ] : null,
            'stats' => [
                'total' => $total,
                'today' => $today,
            ],
        ];
    }
}

// db/db_login_blok_info.php * Verze: V1 * Aktualizace: 21.2.2026 * Počet řádků: 127
// Konec souboru