<?php
declare(strict_types=1);

if (!function_exists('db_user_akce_db_insert')) {
    /**
     * @param array<string, mixed> $row
     */
    function db_user_akce_db_insert(mysqli $conn, array $row): int
    {
        $idUser = (int)($row['id_user'] ?? 0);
        if ($idUser <= 0) {
            return 0;
        }
        $idAkce = (int)($row['id_akce'] ?? 0);
        if ($idAkce < 0) {
            $idAkce = 0;
        }

        $requestUri = trim((string)($row['request_uri'] ?? ''));
        $metoda = trim((string)($row['metoda'] ?? ''));
        $status = trim((string)($row['status'] ?? 'ok'));
        $errMsg = trim((string)($row['err_msg'] ?? ''));

        if ($requestUri === '') {
            $requestUri = null;
        }
        if ($metoda === '') {
            $metoda = null;
        }
        if ($status === '') {
            $status = 'ok';
        }
        if ($errMsg === '') {
            $errMsg = null;
        }

        $casStart = (string)($row['cas_start'] ?? '');
        if ($casStart === '') {
            return 0;
        }

        $requestMs = (float)($row['request_ms'] ?? 0);
        $sqlCount = (int)($row['sql_count'] ?? 0);
        $sqlTotalMs = (float)($row['sql_total_ms'] ?? 0);
        $sqlMaxMs = (float)($row['sql_max_ms'] ?? 0);
        $rowsReturned = (int)($row['rows_returned'] ?? 0);
        $rowsAffected = (int)($row['rows_affected'] ?? 0);
        $bytesReceived = (int)($row['bytes_received'] ?? 0);
        $bytesSent = (int)($row['bytes_sent'] ?? 0);

        $sql = '
            INSERT INTO user_akce_db
                (
                    cas_start, id_user, id_akce, request_uri, metoda,
                    request_ms, sql_count, sql_total_ms, sql_max_ms,
                    rows_returned, rows_affected, bytes_received, bytes_sent,
                    status, err_msg
                )
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $conn->prepare($sql);
        if (!$stmt instanceof mysqli_stmt) {
            return 0;
        }

        $stmt->bind_param(
            'siissdiddiiiiss',
            $casStart,
            $idUser,
            $idAkce,
            $requestUri,
            $metoda,
            $requestMs,
            $sqlCount,
            $sqlTotalMs,
            $sqlMaxMs,
            $rowsReturned,
            $rowsAffected,
            $bytesReceived,
            $bytesSent,
            $status,
            $errMsg
        );
        $ok = $stmt->execute();
        $stmt->close();

        return $ok ? (int)$conn->insert_id : 0;
    }
}

if (!function_exists('db_user_akce_db_detail_insert')) {
    /**
     * @param array<string, mixed> $row
     */
    function db_user_akce_db_detail_insert(mysqli $conn, int $idUserAkceDb, array $row): bool
    {
        if ($idUserAkceDb <= 0) {
            return false;
        }

        $typ = trim((string)($row['typ'] ?? ''));
        if (!in_array($typ, ['card', 'dashboard', 'ajax', 'db'], true)) {
            return false;
        }

        $nazev = trim((string)($row['nazev'] ?? ''));
        $soubor = trim((string)($row['soubor'] ?? ''));
        $mode = trim((string)($row['mode'] ?? ''));
        $usek = trim((string)($row['usek'] ?? ''));
        $idKarta = (int)($row['id_karta'] ?? 0);
        $ms = isset($row['ms']) ? (float)$row['ms'] : null;
        $totalMs = isset($row['total_ms']) ? (float)$row['total_ms'] : null;
        $stepMs = isset($row['step_ms']) ? (float)$row['step_ms'] : null;
        $detailJson = null;

        if (isset($row['detail_json']) && is_string($row['detail_json']) && trim($row['detail_json']) !== '') {
            $detailJson = trim($row['detail_json']);
        } elseif (isset($row['detail']) && is_array($row['detail'])) {
            $encoded = json_encode($row['detail'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $detailJson = is_string($encoded) ? $encoded : null;
        }

        $nazev = $nazev !== '' ? $nazev : null;
        $soubor = $soubor !== '' ? $soubor : null;
        $mode = $mode !== '' ? $mode : null;
        $usek = $usek !== '' ? $usek : null;
        $idKartaDb = $idKarta > 0 ? $idKarta : null;

        $sql = '
            INSERT INTO user_akce_db_detail
                (
                    id_user_akce_db, typ, nazev, id_karta, soubor, mode, usek,
                    ms, total_ms, step_ms, detail_json
                )
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ';
        $stmt = $conn->prepare($sql);
        if (!$stmt instanceof mysqli_stmt) {
            return false;
        }

        $stmt->bind_param(
            'ississsddds',
            $idUserAkceDb,
            $typ,
            $nazev,
            $idKartaDb,
            $soubor,
            $mode,
            $usek,
            $ms,
            $totalMs,
            $stepMs,
            $detailJson
        );
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }
}

if (!function_exists('db_user_akce_db_detail_insert_many')) {
    /**
     * @param array<int, array<string, mixed>> $rows
     */
    function db_user_akce_db_detail_insert_many(mysqli $conn, int $idUserAkceDb, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (is_array($row) && db_user_akce_db_detail_insert($conn, $idUserAkceDb, $row)) {
                $count++;
            }
        }

        return $count;
    }
}
