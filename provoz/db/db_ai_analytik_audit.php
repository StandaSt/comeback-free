<?php
declare(strict_types=1);

function cb_ai_analytik_audit_start(int $idUser, int $idLogin, string $model, string $prompt): int
{
    $conn = db();
    $stmt = $conn->prepare(
        'INSERT INTO ai_analytik_audit
            (created_at, id_user, id_login, model, prompt, scope, status)
         VALUES (NOW(3), ?, NULLIF(?, 0), ?, ?, \'global\', \'received\')'
    );
    $stmt->bind_param('iiss', $idUser, $idLogin, $model, $prompt);
    $stmt->execute();
    $idAudit = (int)$conn->insert_id;
    $stmt->close();
    return $idAudit;
}

function cb_ai_analytik_audit_status(int $idAudit, string $status): void
{
    if ($idAudit <= 0) {
        return;
    }
    $status = substr($status, 0, 30);
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_audit SET status = ? WHERE id_ai_analytik_audit = ?'
    );
    $stmt->bind_param('si', $status, $idAudit);
    $stmt->execute();
    $stmt->close();
}

function cb_ai_analytik_audit_sql(
    int $idAudit,
    string $datumOd,
    string $datumDo,
    string $sqlText,
    string $sqlParamsJson
): void {
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_audit
         SET datum_od = ?, datum_do = ?, sql_text = ?, sql_params_json = ?, status = \'query_ready\'
         WHERE id_ai_analytik_audit = ?'
    );
    $stmt->bind_param('ssssi', $datumOd, $datumDo, $sqlText, $sqlParamsJson, $idAudit);
    $stmt->execute();
    $stmt->close();
}

function cb_ai_analytik_audit_finish(int $idAudit, array $data): void
{
    if ($idAudit <= 0) {
        return;
    }

    try {
        $conn = db();
        $stmt = $conn->prepare(
            'UPDATE ai_analytik_audit
             SET completed_at = NOW(3), datum_od = NULLIF(?, \'\'), datum_do = NULLIF(?, \'\'),
                 sql_text = NULLIF(?, \'\'), sql_params_json = NULLIF(?, \'\'), row_count = ?,
                 openai_plan_response_id = NULLIF(?, \'\'), openai_summary_response_id = NULLIF(?, \'\'),
                 duration_ms = ?, status = ?, error_message = NULLIF(?, \'\')
             WHERE id_ai_analytik_audit = ?'
        );

        $datumOd = (string)($data['datum_od'] ?? '');
        $datumDo = (string)($data['datum_do'] ?? '');
        $sqlText = (string)($data['sql_text'] ?? '');
        $sqlParams = (string)($data['sql_params_json'] ?? '');
        $rowCount = (int)($data['row_count'] ?? 0);
        $planId = (string)($data['openai_plan_response_id'] ?? '');
        $summaryId = (string)($data['openai_summary_response_id'] ?? '');
        $durationMs = (int)($data['duration_ms'] ?? 0);
        $status = substr((string)($data['status'] ?? 'error'), 0, 30);
        $error = mb_substr((string)($data['error_message'] ?? ''), 0, 1000);

        $stmt->bind_param(
            'ssssississi',
            $datumOd,
            $datumDo,
            $sqlText,
            $sqlParams,
            $rowCount,
            $planId,
            $summaryId,
            $durationMs,
            $status,
            $error,
            $idAudit
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('AI analytik: audit dokončení selhalo.');
    }
}
