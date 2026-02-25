<?php
// db/db_restia_token.php * Verze: V1 * Aktualizace: 22.2.2026
declare(strict_types=1);

/*
 * DB: restia_token – uložení a čtení 1 aktuálního access tokenu
 *
 * Tabulka restia_token je navržena jako 1 řádek:
 * - id_restia_token = 1 (PK)
 * - access_token (text)
 * - expires_at (datetime(3))
 * - vytvoreno (datetime(3))
 */

require_once __DIR__ . '/../lib/bootstrap.php';

if (!function_exists('db_restia_token_get')) {

    /**
     * Vrátí uložený token, nebo null když není.
     *
     * @return array{access_token:string, expires_at:string}|null
     */
    function db_restia_token_get(mysqli $conn): ?array
    {
        $sql = 'SELECT access_token, expires_at FROM restia_token WHERE id_restia_token = 1 LIMIT 1';
        $res = $conn->query($sql);
        if ($res === false) {
            throw new RuntimeException('DB: select selhal (restia_token).');
        }

        $row = $res->fetch_assoc();
        $res->free();

        if (!is_array($row) || empty($row['access_token']) || empty($row['expires_at'])) {
            return null;
        }

        return [
            'access_token' => (string)$row['access_token'],
            'expires_at' => (string)$row['expires_at'],
        ];
    }
}

if (!function_exists('db_restia_token_upsert')) {

    /**
     * Uloží token (id=1) – přepíše vždy.
     */
    function db_restia_token_upsert(mysqli $conn, string $accessToken, string $expiresAt): void
    {
        $stmt = $conn->prepare(
            'INSERT INTO restia_token (id_restia_token, access_token, expires_at)
             VALUES (1, ?, ?)
             ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                expires_at = VALUES(expires_at),
                vytvoreno = CURRENT_TIMESTAMP(3)'
        );
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (restia_token upsert).');
        }

        $stmt->bind_param('ss', $accessToken, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }
}

// db/db_restia_token.php * Verze: V1 * Aktualizace: 22.2.2026
// Počet řádků: 73
// Konec souboru