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
                (EXISTS (
                    SELECT 1
                    FROM user_role AS ur
                    INNER JOIN prava_global AS globalni
                        ON globalni.id_role = ur.id_role
                       AND globalni.id_pravo = pravo.id_pravo
                    WHERE ur.id_user = u.id_user
                ) AND (vyjimka.povoleno IS NULL OR vyjimka.povoleno = 1))
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
    $conn->query(
        "UPDATE ai_analytik_audit
         SET completed_at = NOW(3), status = 'clarification_expired', continuation_state_json = NULL,
             continuation_token_hash = NULL, continuation_expires_at = NULL
         WHERE completed_at IS NULL
           AND status = 'awaiting_clarification'
           AND continuation_expires_at < NOW(3)"
    );
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

function cb_ai_analytik_audit_ulozit_pokracovani(int $idAudit, int $idUser, array $state): string
{
    if ($idAudit <= 0 || $idUser <= 0) {
        throw new RuntimeException('Rozpracovanou analýzu nelze uložit.');
    }
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $stateJson = json_encode(
        $state,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $conn = db();
    $stmt = $conn->prepare(
        "UPDATE ai_analytik_audit
         SET status = 'awaiting_clarification', continuation_token_hash = ?,
             continuation_state_json = ?, continuation_expires_at = DATE_ADD(NOW(3), INTERVAL 3 MINUTE)
         WHERE id_ai_analytik_audit = ? AND id_user = ? AND completed_at IS NULL"
    );
    $stmt->bind_param('ssii', $tokenHash, $stateJson, $idAudit, $idUser);
    $stmt->execute();
    $updated = $stmt->affected_rows;
    $stmt->close();
    if ($updated !== 1) {
        throw new RuntimeException('Rozpracovanou analýzu nelze uložit.');
    }
    return $token;
}

function cb_ai_analytik_audit_nacist_pokracovani(int $idAudit, int $idUser, string $token): array
{
    if ($idAudit <= 0 || $idUser <= 0 || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new CbAiAnalytikUzivatelskaChyba('Odkaz na rozpracovanou analýzu není platný.');
    }
    $conn = db();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            "SELECT continuation_token_hash, continuation_state_json,
                    continuation_expires_at > NOW(3) AS is_valid
             FROM ai_analytik_audit
             WHERE id_ai_analytik_audit = ? AND id_user = ? AND completed_at IS NULL
               AND status = 'awaiting_clarification'
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->bind_param('ii', $idAudit, $idUser);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($row)
            || (int)($row['is_valid'] ?? 0) !== 1
            || !hash_equals((string)($row['continuation_token_hash'] ?? ''), hash('sha256', $token))) {
            $conn->rollback();
            throw new CbAiAnalytikUzivatelskaChyba('Upřesnění už nelze použít. Spusťte zadání znovu.');
        }
        $state = json_decode((string)$row['continuation_state_json'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state) || (int)($state['version'] ?? 0) !== 1) {
            $conn->rollback();
            throw new RuntimeException('Uložený stav analýzy není platný.');
        }
        $stmt = $conn->prepare(
            "UPDATE ai_analytik_audit
             SET status = 'resuming'
             WHERE id_ai_analytik_audit = ?"
        );
        $stmt->bind_param('i', $idAudit);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return $state;
    } catch (Throwable $error) {
        try {
            $conn->rollback();
        } catch (Throwable) {
        }
        throw $error;
    }
}

function cb_ai_analytik_audit_obnovit_cekani(int $idAudit, int $idUser): void
{
    $conn = db();
    $stmt = $conn->prepare(
        "UPDATE ai_analytik_audit
         SET status = 'awaiting_clarification'
         WHERE id_ai_analytik_audit = ? AND id_user = ? AND completed_at IS NULL
           AND status = 'resuming' AND continuation_expires_at > NOW(3)"
    );
    $stmt->bind_param('ii', $idAudit, $idUser);
    $stmt->execute();
    $stmt->close();
}

function cb_ai_analytik_audit_request(int $idAudit, array $requestedOutput, array $context): void
{
    $scopeNormalized = 'global';
    $outputJson = json_encode(
        [
            'output' => $requestedOutput,
            'years' => array_values($context['years']),
            'ambiguity_mode' => (string)$context['ambiguity_mode'],
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_audit
         SET scope_normalized = ?, requested_output_json = ?
         WHERE id_ai_analytik_audit = ?'
    );
    $stmt->bind_param('ssi', $scopeNormalized, $outputJson, $idAudit);
    $stmt->execute();
    $stmt->close();
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

function cb_ai_analytik_audit_je_zruseni_pozadovano(int $idAudit): bool
{
    if ($idAudit <= 0) {
        return false;
    }
    $conn = db();
    $stmt = $conn->prepare(
        'SELECT cancel_requested_at IS NOT NULL AS cancelled
         FROM ai_analytik_audit
         WHERE id_ai_analytik_audit = ? AND completed_at IS NULL'
    );
    $stmt->bind_param('i', $idAudit);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['cancelled'] ?? 0) === 1;
}

/**
 * Požádá o ukončení pouze vlastního nedokončeného auditu a vrátí ID právě běžícího SQL spojení.
 * ID spojení nikdy nepřichází od klienta, ale jen z interního SQL auditu.
 */
function cb_ai_analytik_audit_pozadat_o_zruseni(int $idAudit, int $idUser): int
{
    if ($idAudit <= 0 || $idUser <= 0) {
        return 0;
    }
    $conn = db();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'SELECT audit.id_ai_analytik_audit, sql_audit.connection_id
             FROM ai_analytik_audit AS audit
             LEFT JOIN ai_analytik_sql_audit AS sql_audit
               ON sql_audit.id_ai_analytik_audit = audit.id_ai_analytik_audit
              AND sql_audit.completed_at IS NULL
             WHERE audit.id_ai_analytik_audit = ?
               AND audit.id_user = ?
               AND audit.completed_at IS NULL
             ORDER BY sql_audit.id_ai_analytik_sql_audit DESC
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->bind_param('ii', $idAudit, $idUser);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!is_array($row)) {
            $conn->rollback();
            return 0;
        }

        $stmt = $conn->prepare(
            'UPDATE ai_analytik_audit
             SET cancel_requested_at = COALESCE(cancel_requested_at, NOW(3))
             WHERE id_ai_analytik_audit = ?'
        );
        $stmt->bind_param('i', $idAudit);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        return max(0, (int)($row['connection_id'] ?? 0));
    } catch (Throwable $error) {
        $conn->rollback();
        throw $error;
    }
}

/** Přeruší výhradně aktuální SQL dotaz známého auditního spojení. */
function cb_ai_analytik_sql_prerusit_dotaz(int $connectionId): bool
{
    if ($connectionId <= 0) {
        return false;
    }
    try {
        $conn = db();
        return $conn->query('KILL QUERY ' . $connectionId) === true;
    } catch (Throwable $error) {
        error_log('AI analytik: SQL dotaz nelze přerušit: ' . $error->getMessage());
        return false;
    }
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
             SET completed_at = NOW(3), cancelled_at = IF(? = \'cancelled\', NOW(3), cancelled_at),
                 continuation_token_hash = NULL, continuation_state_json = NULL, continuation_expires_at = NULL,
                 datum_od = NULLIF(?, \'\'), datum_do = NULLIF(?, \'\'),
                 sql_text = NULLIF(?, \'\'), sql_params_json = NULLIF(?, \'\'), row_count = ?,
                 openai_plan_response_id = NULLIF(?, \'\'), openai_summary_response_id = NULLIF(?, \'\'),
                  duration_ms = ?, status = ?, error_type = NULLIF(?, \'\'), error_code = NULLIF(?, \'\'),
                  error_message = NULLIF(?, \'\')
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
        $errorType = substr((string)($data['error_type'] ?? ''), 0, 100);
        $errorCode = substr((string)($data['error_code'] ?? ''), 0, 50);
        $error = mb_substr((string)($data['error_message'] ?? ''), 0, 1000);

        $stmt->bind_param(
            'sssssississssi',
            $status,
            $datumOd,
            $datumDo,
            $sqlText,
            $sqlParams,
            $rowCount,
            $planId,
            $summaryId,
            $durationMs,
            $status,
            $errorType,
            $errorCode,
            $error,
            $idAudit
        );
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('AI analytik: audit dokončení selhalo.');
    }
}

function cb_ai_analytik_tool_audit_start(int $idAudit, int $poradi, string $tool, array $arguments): int
{
    $argumentsJson = json_encode(
        $arguments,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $conn = db();
    $stmt = $conn->prepare(
        'INSERT INTO ai_analytik_tool_audit
            (id_ai_analytik_audit, poradi, tool_name, started_at, arguments_json, status)
         VALUES (?, ?, ?, NOW(3), ?, \'started\')'
    );
    $stmt->bind_param('iiss', $idAudit, $poradi, $tool, $argumentsJson);
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function cb_ai_analytik_tool_audit_finish(int $idToolAudit, array $data): void
{
    if ($idToolAudit <= 0) {
        return;
    }
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_tool_audit
         SET completed_at = NOW(3), duration_ms = ?, result_count = ?, status = ?,
             error_type = NULLIF(?, \'\'), error_message = NULLIF(?, \'\')
         WHERE id_ai_analytik_tool_audit = ?'
    );
    $durationMs = (int)($data['duration_ms'] ?? 0);
    $resultCount = (int)($data['result_count'] ?? 0);
    $status = substr((string)($data['status'] ?? 'error'), 0, 30);
    $errorType = substr((string)($data['error_type'] ?? ''), 0, 100);
    $errorMessage = mb_substr((string)($data['error_message'] ?? ''), 0, 1000);
    $stmt->bind_param('iisssi', $durationMs, $resultCount, $status, $errorType, $errorMessage, $idToolAudit);
    $stmt->execute();
    $stmt->close();
}
