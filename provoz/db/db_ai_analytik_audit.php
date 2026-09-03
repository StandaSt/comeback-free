<?php
declare(strict_types=1);

function cb_ai_analytik_prehled_pristupu(mysqli $conn): array
{
    $stmt = $conn->prepare(
        'SELECT
            u.id_user,
            u.jmeno,
            u.prijmeni,
            COALESCE(stat.prompty, 0) AS prompty,
            COALESCE(stat.duration_ms, 0) AS duration_ms,
            COALESCE(stat.total_tokens, 0) AS total_tokens,
            COALESCE(stat.cost_usd, 0) AS cost_usd
         FROM `user` AS u
         INNER JOIN cis_prava AS pravo
            ON pravo.id_pravo = ?
           AND pravo.aktivni = 1
         LEFT JOIN prava_global AS globalni
            ON globalni.id_role = u.id_role
           AND globalni.id_pravo = pravo.id_pravo
         LEFT JOIN prava_vyjimky AS vyjimka
            ON vyjimka.id_user = u.id_user
           AND vyjimka.id_pravo = pravo.id_pravo
         LEFT JOIN (
            SELECT
                audit.id_user,
                COUNT(*) AS prompty,
                SUM(audit.duration_ms) AS duration_ms,
                SUM(COALESCE(souhrn_usage.total_tokens, 0)) AS total_tokens,
                SUM(COALESCE(souhrn_usage.cost_usd, 0)) AS cost_usd
            FROM ai_analytik_audit AS audit
            LEFT JOIN (
                SELECT
                    id_ai_analytik_audit,
                    SUM(total_tokens) AS total_tokens,
                    SUM(cost_usd) AS cost_usd
                FROM ai_analytik_openai_usage
                GROUP BY id_ai_analytik_audit
            ) AS souhrn_usage
                ON souhrn_usage.id_ai_analytik_audit = audit.id_ai_analytik_audit
            GROUP BY audit.id_user
         ) AS stat
            ON stat.id_user = u.id_user
         WHERE u.aktivni = 1
           AND u.in_system = 1
           AND (
                (globalni.id_pravo IS NOT NULL AND (vyjimka.povoleno IS NULL OR vyjimka.povoleno = 1))
                OR vyjimka.povoleno = 1
           )
         ORDER BY u.prijmeni ASC, u.jmeno ASC, u.id_user ASC'
    );
    $pravo = CB_AI_ANALYTIK_PRAVO;
    $stmt->bind_param('i', $pravo);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'jmeno' => trim((string)$row['jmeno'] . ' ' . (string)$row['prijmeni']),
            'prompty' => (int)$row['prompty'],
            'duration_ms' => (int)$row['duration_ms'],
            'total_tokens' => (int)$row['total_tokens'],
            'cost_usd' => (float)$row['cost_usd'],
        ];
    }
    $stmt->close();

    return $rows;
}

function cb_ai_analytik_audit_start(int $idUser, int $idLogin, string $model, string $prompt, string $scope): int
{
    $conn = db();
    $stmt = $conn->prepare(
        'INSERT INTO ai_analytik_audit
            (created_at, id_user, id_login, model, prompt, scope, status)
         VALUES (NOW(3), ?, NULLIF(?, 0), ?, ?, ?, \'received\')'
    );
    $stmt->bind_param('iisss', $idUser, $idLogin, $model, $prompt, $scope);
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
