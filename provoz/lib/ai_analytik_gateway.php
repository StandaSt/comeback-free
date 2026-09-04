<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_analytik_pravidla.php';
require_once __DIR__ . '/ai_analytik_agent.php';
require_once __DIR__ . '/ai_analytik_export_common.php';
require_once __DIR__ . '/../db/db_ai_analytik_audit.php';
require_once __DIR__ . '/../db/db_ai_analytik_bezpecnost.php';

set_time_limit(0);

function cb_ai_analytik_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cb_ai_analytik_stream_start(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(200);
    header('Content-Type: application/x-ndjson; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Accel-Buffering: no');
}

function cb_ai_analytik_stream(string $event, array $payload): void
{
    echo json_encode(
        ['event' => $event] + $payload,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    ) . "\n";
    flush();
}

function cb_ai_analytik_normalizovat_vystup(array $vystup): array
{
    if (!array_key_exists('text', $vystup) || !is_bool($vystup['text'])
        || !array_key_exists('tabulka', $vystup) || !is_bool($vystup['tabulka'])
        || !array_key_exists('graf', $vystup) || !is_bool($vystup['graf'])) {
        throw new CbAiAnalytikUzivatelskaChyba('Požadovaný výstup nemá platný formát.');
    }
    if (!$vystup['text'] && !$vystup['tabulka'] && !$vystup['graf']) {
        throw new CbAiAnalytikUzivatelskaChyba('Vyberte alespoň jeden požadovaný výstup.');
    }
    return ['text' => $vystup['text'], 'tabulka' => $vystup['tabulka'], 'graf' => $vystup['graf']];
}

function cb_ai_analytik_chyba_pro_uzivatele(Throwable $error, bool $isAdmin, int $idAudit): string
{
    $reference = 'Audit #' . $idAudit;
    if ($isAdmin) {
        return get_class($error) . ': ' . $error->getMessage()
            . ' v ' . $error->getFile() . ':' . $error->getLine() . ' · ' . $reference;
    }
    if ($error instanceof CbAiAnalytikUzivatelskaChyba) {
        return $error->getMessage() . ' · ' . $reference;
    }
    $message = $error->getMessage();
    if (str_starts_with($message, 'OpenAI API')) {
        return 'Služba OpenAI je momentálně nedostupná. · ' . $reference;
    }
    if (str_contains(mb_strtolower($message), 'databáz') || str_contains($message, 'SQL')) {
        return 'Datový zdroj je momentálně nedostupný. · ' . $reference;
    }
    return 'Dotaz se nepodařilo zpracovat. · ' . $reference;
}

function cb_ai_analytik_gateway(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        cb_ai_analytik_json(405, ['ok' => false, 'error' => 'Povolen je pouze POST požadavek.']);
    }
    if (empty($_SESSION['login_ok'])) {
        cb_ai_analytik_json(401, ['ok' => false, 'error' => 'Platnost přihlášení vypršela.']);
    }
    if (!cb_ai_analytik_ma_pravo()) {
        cb_ai_analytik_json(403, ['ok' => false, 'error' => 'K AI analytikovi nemáte oprávnění.']);
    }

    try {
        $input = json_decode((string)file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        cb_ai_analytik_json(400, ['ok' => false, 'error' => 'Požadavek nemá platný formát.']);
    }
    if (!is_array($input)) {
        cb_ai_analytik_json(400, ['ok' => false, 'error' => 'Požadavek nemá platný formát.']);
    }

    $csrf = (string)($input['csrf'] ?? '');
    $sessionCsrf = (string)($_SESSION['ai_analytik_csrf'] ?? '');
    if ($sessionCsrf === '' || $csrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        cb_ai_analytik_json(403, ['ok' => false, 'error' => 'Neplatné zabezpečení formuláře. Obnovte stránku.']);
    }

    $prompt = trim((string)($input['prompt'] ?? ''));
    if ($prompt === '') {
        cb_ai_analytik_json(422, ['ok' => false, 'error' => 'Napište dotaz.']);
    }

    $modelRaw = array_key_exists('model', $input) ? $input['model'] : CB_AI_ANALYTIK_VYCHOZI_MODEL;
    $model = is_string($modelRaw) ? trim($modelRaw) : '';
    $modelProAudit = mb_substr($model !== '' ? $model : '[neplatný formát]', 0, 64);
    $areasRaw = $input['oblasti'] ?? [CB_AI_ANALYTIK_VYCHOZI_OBLAST];
    $areasForAudit = is_array($areasRaw)
        ? implode(',', array_map(static fn(mixed $area): string => is_string($area) ? $area : '?', $areasRaw))
        : '[neplatné]';
    $areasForAudit = mb_substr($areasForAudit !== '' ? $areasForAudit : '[prázdné]', 0, 255);
    $idUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);
    $isAdmin = cb_user_ma_roli(1);

    try {
        $idAudit = cb_ai_analytik_audit_start($idUser, $idLogin, $modelProAudit, $prompt, $areasForAudit);
    } catch (Throwable $error) {
        error_log('AI analytik: audit nelze zahájit: ' . $error->getMessage());
        cb_ai_analytik_json(503, [
            'ok' => false,
            'error' => $isAdmin
                ? get_class($error) . ': ' . $error->getMessage() . ' v ' . $error->getFile() . ':' . $error->getLine()
                : 'Audit AI analytika není dostupný. Dotaz nebyl spuštěn.',
        ]);
    }

    $startedAt = hrtime(true);
    $auditFinished = false;
    register_shutdown_function(static function () use ($idAudit, $startedAt, &$auditFinished): void {
        if ($auditFinished) {
            return;
        }
        $lastError = error_get_last();
        if (!is_array($lastError) || !in_array((int)$lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        cb_ai_analytik_audit_finish($idAudit, [
            'status' => 'fatal_error',
            'duration_ms' => (int)((hrtime(true) - $startedAt) / 1_000_000),
            'error_type' => 'PHP_FATAL_' . (int)$lastError['type'],
            'error_code' => (string)$lastError['type'],
            'error_message' => mb_substr((string)$lastError['message'], 0, 1000),
        ]);
        $auditFinished = true;
    });
    $audit = ['status' => 'error', 'error_message' => '', 'error_type' => '', 'error_code' => '', 'row_count' => 0];
    if (!cb_ai_analytik_model_je_povoleny($model)) {
        try {
            cb_ai_analytik_zablokovat_pravo($idUser);
            $audit['status'] = 'security_block';
            $audit['error_message'] = 'Podvržený model mimo serverový whitelist: ' . $model;
            $audit['error_type'] = 'model_whitelist_violation';
        } catch (Throwable $error) {
            unset($_SESSION['prava'][CB_AI_ANALYTIK_PRAVO]);
            $audit['status'] = 'security_block_error';
            $audit['error_message'] = 'Blokace podvrženého modelu selhala: ' . $error->getMessage();
            $audit['error_type'] = get_class($error);
            $audit['error_code'] = (string)$error->getCode();
        }
        $audit['duration_ms'] = (int)((hrtime(true) - $startedAt) / 1_000_000);
        cb_ai_analytik_audit_finish($idAudit, $audit);
        $auditFinished = true;
        cb_ai_analytik_json(403, [
            'ok' => false,
            'error' => 'Přístup k AI analytikovi byl zablokován kvůli pokusu použít nepovolený model.',
        ]);
    }

    try {
        $areas = cb_ai_analytik_normalizovat_oblasti($areasRaw);
    } catch (Throwable $error) {
        $audit['duration_ms'] = (int)((hrtime(true) - $startedAt) / 1_000_000);
        $audit['status'] = 'rejected_request';
        $audit['error_type'] = get_class($error);
        $audit['error_code'] = (string)$error->getCode();
        $audit['error_message'] = $error->getMessage();
        cb_ai_analytik_audit_finish($idAudit, $audit);
        $auditFinished = true;
        cb_ai_analytik_json(422, ['ok' => false, 'error' => $error->getMessage() . ' · Audit #' . $idAudit]);
    }

    try {
        $requestedOutput = cb_ai_analytik_normalizovat_vystup(
            is_array($input['vystup'] ?? null) ? $input['vystup'] : []
        );
    } catch (Throwable $error) {
        $audit['duration_ms'] = (int)((hrtime(true) - $startedAt) / 1_000_000);
        $audit['status'] = 'rejected_request';
        $audit['error_type'] = get_class($error);
        $audit['error_code'] = (string)$error->getCode();
        $audit['error_message'] = $error->getMessage();
        cb_ai_analytik_audit_finish($idAudit, $audit);
        $auditFinished = true;
        cb_ai_analytik_json(422, ['ok' => false, 'error' => $error->getMessage() . ' · Audit #' . $idAudit]);
    }

    cb_ai_analytik_audit_request($idAudit, $areas, $requestedOutput);

    if (empty($_SESSION['ai_analytik_export_secret'])) {
        $_SESSION['ai_analytik_export_secret'] = bin2hex(random_bytes(32));
    }
    $exportSecret = (string)$_SESSION['ai_analytik_export_secret'];
    session_write_close();

    cb_ai_analytik_stream_start();
    cb_ai_analytik_stream('progress', [
        'stage' => 'accepted',
        'message' => 'Dotaz přijat, zahajuji analýzu.',
        'meta' => ['api_calls' => 0, 'sql_count' => 0],
    ]);

    try {
        $progress = static function (string $stage, string $message, array $meta) use ($idAudit): void {
            cb_ai_analytik_audit_status($idAudit, $stage);
            cb_ai_analytik_stream('progress', ['stage' => $stage, 'message' => $message, 'meta' => $meta]);
        };
        $result = cb_ai_analytik_agent_spustit($idAudit, $model, $prompt, $areas, $requestedOutput, $progress);
        $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
        $audit['openai_summary_response_id'] = (string)$result['last_response_id'];
        $audit['row_count'] = count($result['rows']);
        $audit['duration_ms'] = $durationMs;
        $audit['status'] = 'completed';
        cb_ai_analytik_audit_finish($idAudit, $audit);
        $auditFinished = true;

        $export = cb_ai_analytik_export_podepsat([
            'version' => 1,
            'id_user' => $idUser,
            'audit_id' => $idAudit,
            'expires_at' => time() + CB_AI_ANALYTIK_EXPORT_PLATNOST_SEKUND,
            'prompt' => $prompt,
            'text' => $result['text'],
            'chart' => $result['chart'],
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'model' => $model,
            'usage' => $result['usage'],
            'duration_ms' => $durationMs,
        ], $exportSecret);

        cb_ai_analytik_stream('result', ['data' => [
            'ok' => true,
            'text' => $result['text'],
            'chart' => $result['chart'],
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'export' => $export,
            'meta' => [
                'model' => $model,
                'vystup' => $requestedOutput,
                'scope' => implode(',', $areas),
                'usage' => $result['usage'],
                'duration_ms' => $durationMs,
                'api_calls' => (int)$result['api_calls'],
                'sql_count' => (int)$result['sql_count'],
                'audit_id' => $idAudit,
            ],
        ]]);
    } catch (Throwable $error) {
        $audit['duration_ms'] = (int)((hrtime(true) - $startedAt) / 1_000_000);
        $audit['status'] = match (true) {
            $error instanceof CbAiAnalytikUzivatelskaChyba => 'rejected_request',
            $error instanceof CbAiAnalytikAgentLimitChyba => 'limit_exceeded',
            $error instanceof CbAiAnalytikSqlBezpecnostniChyba => 'security_rejected',
            default => 'error',
        };
        $audit['error_type'] = get_class($error);
        $audit['error_code'] = (string)$error->getCode();
        $audit['error_message'] = mb_substr($error->getMessage(), 0, 1000);
        cb_ai_analytik_audit_finish($idAudit, $audit);
        $auditFinished = true;
        error_log('AI analytik audit #' . $idAudit . ': ' . get_class($error) . ': ' . $error->getMessage());
        cb_ai_analytik_stream('error', [
            'message' => cb_ai_analytik_chyba_pro_uzivatele($error, $isAdmin, $idAudit),
            'audit_id' => $idAudit,
        ]);
    }
    exit;
}

cb_ai_analytik_gateway();
