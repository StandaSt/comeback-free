<?php
// db/db_api_smeny.php * Verze: V4 * Aktualizace: 21.2.2026
declare(strict_types=1);

/*
 * DB: api_smeny (log volání do Směn) – HROMADNÝ ZÁPIS Z BUFFERU
 *
 * Tabulka (po úpravě):
 * - id_api_smeny (AI)
 * - kdy_start (datetime(6))
 * - ms (int unsigned)
 * - id_user (int unsigned, NULL)
 * - id_login (int unsigned, NULL)
 * - bytes_out (int unsigned, NULL)
 * - bytes_in (int unsigned, NULL)
 * - chyba (TEXT, NULL)
 * - ok (tinyint 0/1)
 *
 * Logika:
 * - volání Směn sbírá do $_SESSION['api_smeny_buffer']
 * - flush se volá:
 *   - po úspěšném loginu: s id_user + id_login
 *   - po neúspěšném loginu: bez id_user + id_login (NULL)
 */

require_once __DIR__ . '/../lib/bootstrap.php';

if (!function_exists('db_api_smeny_flush')) {

    /**
     * Zapíše session buffer do DB a buffer smaže.
     *
     * @param mysqli   $conn    DB spojení (může být db() nebo transakční $conn)
     * @param ?int     $idUser  NULL při neúspěšném loginu
     * @param ?int     $idLogin NULL při neúspěšném loginu
     */
    function db_api_smeny_flush(mysqli $conn, ?int $idUser, ?int $idLogin): void
    {
        $buf = $_SESSION['api_smeny_buffer'] ?? null;
        if (!is_array($buf) || count($buf) === 0) {
            return;
        }

        $mode = 'both';
        if ($idUser === null && $idLogin === null) {
            $mode = 'none';
        } elseif ($idUser !== null && $idLogin === null) {
            $mode = 'user_only';
        } elseif ($idUser === null && $idLogin !== null) {
            $mode = 'login_only';
        }

        if ($mode === 'both') {
            $stmt = $conn->prepare(
                'INSERT INTO api_smeny
                    (kdy_start, ms, id_user, id_login, bytes_out, bytes_in, chyba, ok)
                 VALUES (?,?,?,?,?,?,?,?)'
            );
        } elseif ($mode === 'user_only') {
            $stmt = $conn->prepare(
                'INSERT INTO api_smeny
                    (kdy_start, ms, id_user, id_login, bytes_out, bytes_in, chyba, ok)
                 VALUES (?,?,?,NULL,?,?,?,?)'
            );
        } elseif ($mode === 'login_only') {
            $stmt = $conn->prepare(
                'INSERT INTO api_smeny
                    (kdy_start, ms, id_user, id_login, bytes_out, bytes_in, chyba, ok)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?)'
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO api_smeny
                    (kdy_start, ms, id_user, id_login, bytes_out, bytes_in, chyba, ok)
                 VALUES (?, ?, NULL, NULL, ?, ?, ?, ?)'
            );
        }

        if ($stmt === false) {
            throw new RuntimeException('DB: prepare selhal (api_smeny flush).');
        }

        foreach ($buf as $row) {
            if (!is_array($row)) {
                continue;
            }

            $kdyStart = (string)($row['kdy_start'] ?? '');
            $ms = (int)($row['ms'] ?? 0);

            $bytesOut = $row['bytes_out'] ?? null;
            $bytesIn = $row['bytes_in'] ?? null;

            $bytesOut = ($bytesOut === null || $bytesOut === '') ? null : (int)$bytesOut;
            $bytesIn = ($bytesIn === null || $bytesIn === '') ? null : (int)$bytesIn;

            $chyba = $row['chyba'] ?? null;
            $chyba = is_string($chyba) ? trim($chyba) : null;
            if ($chyba === '') {
                $chyba = null;
            }

            $ok = (int)($row['ok'] ?? 0);

            if ($mode === 'both') {
                $idUserInt = (int)$idUser;
                $idLoginInt = (int)$idLogin;

                // s i i i i i s i
                $stmt->bind_param(
                    'siiiiisi',
                    $kdyStart,
                    $ms,
                    $idUserInt,
                    $idLoginInt,
                    $bytesOut,
                    $bytesIn,
                    $chyba,
                    $ok
                );

            } elseif ($mode === 'user_only') {
                $idUserInt = (int)$idUser;

                // s i i i i s i  (id_login je NULL v SQL)
                $stmt->bind_param(
                    'siiiisi',
                    $kdyStart,
                    $ms,
                    $idUserInt,
                    $bytesOut,
                    $bytesIn,
                    $chyba,
                    $ok
                );

            } elseif ($mode === 'login_only') {
                $idLoginInt = (int)$idLogin;

                // s i i i i s i  (id_user je NULL v SQL)
                $stmt->bind_param(
                    'siiiisi',
                    $kdyStart,
                    $ms,
                    $idLoginInt,
                    $bytesOut,
                    $bytesIn,
                    $chyba,
                    $ok
                );

            } else {
                // s i i i s i (id_user i id_login jsou NULL v SQL)
                $stmt->bind_param(
                    'siiisi',
                    $kdyStart,
                    $ms,
                    $bytesOut,
                    $bytesIn,
                    $chyba,
                    $ok
                );
            }

            $stmt->execute();
        }

        $stmt->close();

        // buffer vyčistit až po úspěšném zápisu
        $_SESSION['api_smeny_buffer'] = [];
    }
}

// db/db_api_smeny.php * Verze: V4 * Aktualizace: 21.2.2026
// Počet řádků: 0
// Konec souboru