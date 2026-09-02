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

function cb_ai_analytik_sql_audit_finish(int $idSqlAudit, string $status, int $durationMs, int $rowCount, string $error = ''): void
{
    if ($idSqlAudit <= 0) {
        return;
    }
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_sql_audit
         SET completed_at = NOW(3), duration_ms = ?, row_count = ?, status = ?, error_message = NULLIF(?, \'\')
         WHERE id_ai_analytik_sql_audit = ?'
    );
    $error = mb_substr($error, 0, 2000);
    $stmt->bind_param('iissi', $durationMs, $rowCount, $status, $error, $idSqlAudit);
    $stmt->execute();
    $stmt->close();
}
