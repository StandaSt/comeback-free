<?php
declare(strict_types=1);

function cb_ai_analytik_sql_audit_start(int $idAudit, int $poradi, string $ucel, string $sql): int
{
    $conn = db();
    $stmt = $conn->prepare(
        'INSERT INTO ai_analytik_sql_audit
            (id_ai_analytik_audit, poradi, started_at, ucel, sql_text, status)
         VALUES (?, ?, NOW(3), ?, ?, \'started\')'
    );
    $stmt->bind_param('iiss', $idAudit, $poradi, $ucel, $sql);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function cb_ai_analytik_sql_audit_finish(int $idSqlAudit, array $data): void
{
    if ($idSqlAudit <= 0) {
        return;
    }
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_sql_audit
         SET completed_at = NOW(3), duration_ms = ?, row_count = ?, result_bytes = ?, status = ?,
             error_type = NULLIF(?, \'\'), error_code = NULLIF(?, \'\'), `sqlstate` = NULLIF(?, \'\'),
             error_message = NULLIF(?, \'\')
         WHERE id_ai_analytik_sql_audit = ?'
    );
    $durationMs = (int)($data['duration_ms'] ?? 0);
    $rowCount = (int)($data['row_count'] ?? 0);
    $resultBytes = (int)($data['result_bytes'] ?? 0);
    $status = substr((string)($data['status'] ?? 'error'), 0, 30);
    $errorType = substr((string)($data['error_type'] ?? ''), 0, 100);
    $errorCode = substr((string)($data['error_code'] ?? ''), 0, 50);
    $sqlState = substr((string)($data['sqlstate'] ?? ''), 0, 10);
    $error = mb_substr((string)($data['error_message'] ?? ''), 0, 2000);
    $stmt->bind_param(
        'iiisssssi',
        $durationMs,
        $rowCount,
        $resultBytes,
        $status,
        $errorType,
        $errorCode,
        $sqlState,
        $error,
        $idSqlAudit
    );
    $stmt->execute();
    $stmt->close();
}

/** Uloží serverové ID spojení běžícího analytického SELECTu pro bezpečné KILL QUERY. */
function cb_ai_analytik_sql_audit_connection(int $idSqlAudit, int $connectionId): void
{
    if ($idSqlAudit <= 0 || $connectionId <= 0) {
        return;
    }
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_sql_audit
         SET connection_id = ?
         WHERE id_ai_analytik_sql_audit = ? AND completed_at IS NULL'
    );
    $stmt->bind_param('ii', $connectionId, $idSqlAudit);
    $stmt->execute();
    $stmt->close();
}
