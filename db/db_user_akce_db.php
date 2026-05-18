<?php
declare(strict_types=1);

if (!function_exists('db_user_akce_db_insert')) {
    /**
     * @param array<string, mixed> $row
     */
    function db_user_akce_db_insert(mysqli $conn, array $row): bool
    {
        $idUser = (int)($row['id_user'] ?? 0);
        if ($idUser <= 0) {
            return false;
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
            return false;
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
            return false;
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

        return $ok;
    }
}
