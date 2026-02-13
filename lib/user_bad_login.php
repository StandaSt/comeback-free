<?php
// lib/user_bad_login.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 34
declare(strict_types=1);

/*
 * Zápis neúspěšného přihlášení do DB (user_bad_login)
 *
 * Bezpečnost:
 * - heslo se neukládá čitelně, ukládá se jen SHA-256 hash (jednosměrný otisk)
 * - pokud zápis selže, neblokuje to uživatele (je to jen log)
 */

require_once __DIR__ . '/bootstrap.php';

function cb_user_bad_login_log(string $email, string $heslo): void
{
    try {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $hash = hash('sha256', $heslo);

        $sql = 'INSERT INTO user_bad_login (email, heslo, ip) VALUES (?,?,?)';
        $stmt = db()->prepare($sql);
        $stmt->bind_param('sss', $email, $hash, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Diagnostika, ale nezastavujeme běh jen kvůli logování.
        require_once __DIR__ . '/login_diagnostika.php';
        cb_login_log_line('db_bad_login_failed', ['email' => $email], $e);
    }
}

// lib/user_bad_login.php * Verze: V1 * Aktualizace: 12.2.2026 * Počet řádků: 34
// Konec souboru