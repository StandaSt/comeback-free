<?php
// db/db_api_restia.php * Verze: V2 * Aktualizace: 22.2.2026
declare(strict_types=1);

/*
 * DB: api_restia (log volání do Restie) – HROMADNÝ ZÁPIS Z BUFFERU
 *
 * Tabulka:
 * - id_api_restia (AI)
 * - id_user (int unsigned, NULL)
 * - id_login (bigint unsigned, NULL)
 * - kdy_start (datetime(6))
 * - ms (int unsigned)
 * - metoda (varchar)
 * - endpoint (varchar)
 * - url (text, NULL)
 * - active_pos_id (char(36), NULL)
 * - http_status (smallint unsigned, NULL)
 * - bytes_out (int unsigned, NULL)
 * - bytes_in (int unsigned, NULL)
 * - pocet_zaznamu (int unsigned, NULL)
 * - total_count (int unsigned, NULL)
 * - chyba (text, NULL)
 * - poznamka (varchar(255), NULL)
 *
 * Logika:
 * - volání Restie sbírá do $_SESSION['api_restia_buffer']
 * - flush se volá až když máme id_user + id_login (ideálně po úspěšném loginu)
 * - při problému s logováním se nesmí shodit hlavní běh (import/login)
 */

require_once __DIR__ . '/../lib/bootstrap.php';

if (!function_exists('db_api_restia_flush')) {

    /**
     * Zapíše session buffer do DB a buffer smaže.
     *
     * @param mysqli $conn DB spojení
     * @param ?int $idUser NULL když neznáme uživatele
     * @param ?int $idLogin NULL když neznáme login
     */
    function db_api_restia_flush(mysqli $conn, ?int $idUser, ?int $idLogin): void
    {
        $buf = $_SESSION['api_restia_buffer'] ?? null;
        if (!is_array($buf) || count($buf) === 0) {
            return;
        }

        $stmt = $conn->prepare(
            'INSERT INTO api_restia
                (id_user, id_login, kdy_start, ms, metoda, endpoint, url, active_pos_id, http_status,
                 bytes_out, bytes_in, pocet_zaznamu, total_count, chyba, poznamka)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (api_restia flush).');
        }

        foreach ($buf as $row) {
            if (!is_array($row)) {
                continue;
            }

            $kdyStart = (string)($row['kdy_start'] ?? '');
            $ms = (int)($row['ms'] ?? 0);

            $metoda = (string)($row['metoda'] ?? '');
            $endpoint = (string)($row['endpoint'] ?? '');

            $url = $row['url'] ?? null;
            $url = ($url === null || $url === '') ? null : (string)$url;

            $activePosId = $row['active_pos_id'] ?? null;
            $activePosId = ($activePosId === null || $activePosId === '') ? null : (string)$activePosId;

            $httpStatus = $row['http_status'] ?? null;
            $httpStatus = ($httpStatus === null || $httpStatus === '') ? null : (int)$httpStatus;

            $bytesOut = $row['bytes_out'] ?? null;
            $bytesOut = ($bytesOut === null || $bytesOut === '') ? null : (int)$bytesOut;

            $bytesIn = $row['bytes_in'] ?? null;
            $bytesIn = ($bytesIn === null || $bytesIn === '') ? null : (int)$bytesIn;

            $pocetZaznamu = $row['pocet_zaznamu'] ?? null;
            $pocetZaznamu = ($pocetZaznamu === null || $pocetZaznamu === '') ? null : (int)$pocetZaznamu;

            $totalCount = $row['total_count'] ?? null;
            $totalCount = ($totalCount === null || $totalCount === '') ? null : (int)$totalCount;

            $chyba = $row['chyba'] ?? null;
            $chyba = is_string($chyba) ? trim($chyba) : null;
            if ($chyba === '') {
                $chyba = null;
            }

            $poznamka = $row['poznamka'] ?? null;
            $poznamka = is_string($poznamka) ? trim($poznamka) : null;
            if ($poznamka === '') {
                $poznamka = null;
            }

            $ok = (int)($row['ok'] ?? 0);
            if ($ok === 0 && $httpStatus !== null && $httpStatus >= 200 && $httpStatus <= 299) {
                $ok = 1;
            }

            $idUserInt = ($idUser === null) ? null : (int)$idUser;
            $idLoginInt = ($idLogin === null) ? null : (int)$idLogin;

            // i i s i s s s i i i i i s s
            $stmt->bind_param(
                'iisissssiiiiiss',
                $idUserInt,
                $idLoginInt,
                $kdyStart,
                $ms,
                $metoda,
                $endpoint,
                $url,
                $activePosId,
                $httpStatus,
                $bytesOut,
                $bytesIn,
                $pocetZaznamu,
                $totalCount,
                $chyba,
                $poznamka
            );

            $stmt->execute();
        }

        $stmt->close();

        $_SESSION['api_restia_buffer'] = [];
    }
}

// db/db_api_restia.php * Verze: V2 * Aktualizace: 22.2.2026
// Počet řádků: 143
// Konec souboru