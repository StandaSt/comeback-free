<?php
declare(strict_types=1);

/*
 * HTTP a streamovaci brana AI analytika.
 * Overuje vstup, ridi audit, spousti agenta a vraci NDJSON udalosti klientovi.
 */

require_once __DIR__ . '/ai_analytik_pravidla.php';
require_once __DIR__ . '/ai_analytik_agent.php';
require_once __DIR__ . '/ai_analytik_prilohy.php';
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
    header('Content-Encoding: none');
    @ini_set('zlib.output_compression', '0');
    @ini_set('implicit_flush', '1');
    ob_implicit_flush(true);
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

function cb_ai_analytik_chyba_pro_uzivatele(Throwable $error, bool $maPravoTechnickeDiagnostiky, int $idAudit): string
{
    $reference = 'Audit #' . $idAudit;
    if ($error instanceof CbAiAnalytikZrusenoUzivatelem) {
        return 'Analýza byla na váš pokyn zastavena. · ' . $reference;
    }
    if ($error instanceof CbAiAnalytikSpojeniPreruseno) {
        return 'Spojení s prohlížečem bylo ukončeno; analýza byla zastavena. · ' . $reference;
    }
    if ($maPravoTechnickeDiagnostiky) {
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

/** Zapise neocekavanou technickou chybu AI do centralniho logu a push toku. */
function cb_ai_analytik_oznam_chybu_adminovi(Throwable $error, int $idAudit): void
{
    // Technicka chyba AI patri do centralniho logu; detailni prubeh zustava v auditni tabulce.
    cb_chyba_oznam($error, [
        'module' => 'AI_ANALYTIK',
        'action' => $idAudit > 0 ? 'AI audit ID ' . $idAudit : 'Zahájení AI auditu',
        'table' => 'ai_analytik_audit',
    ]);
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

    $contentType = mb_strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
    if (str_starts_with($contentType, 'multipart/form-data')) {
        $input = $_POST;
    } else {
        try {
            $input = json_decode((string)file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            cb_ai_analytik_json(400, ['ok' => false, 'error' => 'Požadavek nemá platný formát.']);
        }
    }
    if (!is_array($input)) {
        cb_ai_analytik_json(400, ['ok' => false, 'error' => 'Požadavek nemá platný formát.']);
    }

    $csrf = (string)($input['csrf'] ?? '');
    $sessionCsrf = (string)($_SESSION['ai_analytik_csrf'] ?? '');
    if ($sessionCsrf === '' || $csrf === '' || !hash_equals($sessionCsrf, $csrf)) {
        cb_ai_analytik_json(403, ['ok' => false, 'error' => 'Neplatné zabezpečení formuláře. Obnovte stránku.']);
    }

    $action = (string)($input['action'] ?? '');
    $idUser = (int)($_SESSION['cb_user']['id_user'] ?? 0);
    if ($idUser <= 0) {
        cb_ai_analytik_json(401, ['ok' => false, 'error' => 'Platnost přihlášení vypršela.']);
    }

    if ($action === 'attachment_upload') {
        try {
            $upload = $_FILES['attachment'] ?? null;
            if (!is_array($upload)) {
                throw new CbAiAnalytikUzivatelskaChyba('Vyberte přílohu.');
            }
            cb_ai_analytik_json(200, ['ok' => true] + cb_ai_analytik_prilohy_nahrat(
                $upload,
                $idUser,
                trim((string)($input['attachment_token'] ?? ''))
            ));
        } catch (CbAiAnalytikUzivatelskaChyba $error) {
            cb_ai_analytik_json(422, ['ok' => false, 'error' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log('AI analytik: upload přílohy selhal: ' . $error->getMessage());
            cb_ai_analytik_oznam_chybu_adminovi($error, 0);
            cb_ai_analytik_json(503, ['ok' => false, 'error' => 'Přílohu se nepodařilo bezpečně připravit.']);
        }
    }

    if ($action === 'attachment_estimate') {
        $model = trim((string)($input['model'] ?? ''));
        if (!cb_ai_analytik_model_je_povoleny($model) || !cb_ai_analytik_model_ma_pravo($model)) {
            cb_ai_analytik_json(403, ['ok' => false, 'error' => 'K vybranému modelu nemáte oprávnění.']);
        }
        try {
            $estimate = cb_ai_analytik_prilohy_odhadnout(
                trim((string)($input['attachment_token'] ?? '')),
                $idUser,
                $model,
                trim((string)($input['prompt'] ?? ''))
            );
            cb_ai_analytik_json(200, ['ok' => true, 'estimate' => $estimate]);
        } catch (CbAiAnalytikUzivatelskaChyba $error) {
            cb_ai_analytik_json(422, ['ok' => false, 'error' => $error->getMessage()]);
        } catch (Throwable $error) {
            error_log('AI analytik: odhad příloh selhal: ' . $error->getMessage());
            cb_ai_analytik_oznam_chybu_adminovi($error, 0);
            cb_ai_analytik_json(503, ['ok' => false, 'error' => 'Odhad nákladů na přílohy se nepodařilo získat.']);
        }
    }

    if ($action === 'attachment_discard') {
        cb_ai_analytik_json(200, [
            'ok' => cb_ai_analytik_prilohy_zahodit(
                trim((string)($input['attachment_token'] ?? '')),
                $idUser
            ),
        ]);
    }

    if ($action === 'continuation_finish') {
        $idAudit = (int)($input['audit_id'] ?? 0);
        $stored = null;
        try {
            $stored = cb_ai_analytik_audit_nacist_pokracovani(
                $idAudit,
                $idUser,
                (string)($input['continuation_token'] ?? '')
            );
            $auditData = is_array($stored['audit'] ?? null) ? $stored['audit'] : [];
            $auditData['status'] = 'completed';
            cb_ai_analytik_audit_finish($idAudit, $auditData);
            $agentState = is_array($stored['agent_state'] ?? null) ? $stored['agent_state'] : [];
            cb_ai_analytik_prilohy_smazat_openai(
                is_array($agentState['attachment_file_ids'] ?? null)
                    ? $agentState['attachment_file_ids']
                    : []
            );
            cb_ai_analytik_json(200, ['ok' => true]);
        } catch (CbAiAnalytikUzivatelskaChyba $error) {
            cb_ai_analytik_json(422, ['ok' => false, 'error' => $error->getMessage()]);
        } catch (Throwable $error) {
            if (is_array($stored)) {
                cb_ai_analytik_audit_obnovit_cekani(
                    $idAudit,
                    $idUser,
                    (string)($stored['continuation_kind'] ?? 'followup')
                );
            }
            error_log('AI analytik: ukončení pokračování auditu #' . $idAudit . ' selhalo: ' . $error->getMessage());
            cb_ai_analytik_oznam_chybu_adminovi($error, $idAudit);
            cb_ai_analytik_json(503, ['ok' => false, 'error' => 'Konverzaci se nepodařilo ukončit. Zkuste to znovu.']);
        }
    }

    if ($action === 'prompt_list') {
        $pouzeUlozene = ($input['filter'] ?? '') !== 'all';
        $vsechnyUzivatele = !$pouzeUlozene && cb_ai_analytik_uzivatel_je_admin(db(), $idUser);
        cb_ai_analytik_json(200, [
            'ok' => true,
            'prompts' => cb_ai_analytik_audit_prompty_uzivatele($idUser, $pouzeUlozene, $vsechnyUzivatele),
        ]);
    }

    if ($action === 'save_prompt') {
        $idAudit = (int)($input['audit_id'] ?? 0);
        if (!cb_ai_analytik_audit_ulozit_prompt($idAudit, $idUser)) {
            cb_ai_analytik_json(404, ['ok' => false, 'error' => 'Prompt nelze uložit.']);
        }
        cb_ai_analytik_json(200, ['ok' => true]);
    }

    if ($action === 'cancel') {
        $idAudit = (int)($input['audit_id'] ?? 0);
        if ($idAudit <= 0) {
            cb_ai_analytik_json(422, ['ok' => false, 'error' => 'Nelze určit běžící analýzu.']);
        }
        try {
            $connectionId = cb_ai_analytik_audit_pozadat_o_zruseni($idAudit, $idUser);
            $sqlInterrupted = $connectionId > 0 && cb_ai_analytik_sql_prerusit_dotaz($connectionId);
            cb_ai_analytik_json(202, [
                'ok' => true,
                'status' => 'cancel_requested',
                'sql_interrupted' => $sqlInterrupted,
            ]);
        } catch (Throwable $error) {
            error_log('AI analytik: zrušení auditu #' . $idAudit . ' selhalo: ' . $error->getMessage());
            cb_ai_analytik_json(503, ['ok' => false, 'error' => 'Analýzu se nepodařilo zastavit. Zkuste to znovu.']);
        }
    }

    $idLogin = (int)($_SESSION['cb_id_login'] ?? 0);
    $maPravoTechnickeDiagnostiky = cb_pravo_ma(102);
    $isContinuation = $action === 'continue';
    $resumeState = null;
    $clarificationAnswer = '';
    $previousDurationMs = 0;
    $attachments = [];
    $continuationKind = 'clarification';

    if ($isContinuation) {
        $idAudit = (int)($input['audit_id'] ?? 0);
        $clarificationAnswer = trim((string)($input['answer'] ?? ''));
        if ($clarificationAnswer === '') {
            cb_ai_analytik_json(422, ['ok' => false, 'error' => 'Napište odpověď pro AI analytika.']);
        }
        $stored = null;
        try {
            $stored = cb_ai_analytik_audit_nacist_pokracovani(
                $idAudit,
                $idUser,
                (string)($input['continuation_token'] ?? '')
            );
            $prompt = trim((string)($stored['prompt'] ?? ''));
            $model = trim((string)($stored['model'] ?? ''));
            $requestedOutput = cb_ai_analytik_normalizovat_vystup(
                is_array($stored['requested_output'] ?? null) ? $stored['requested_output'] : []
            );
            $years = cb_ai_analytik_normalizovat_roky(
                $stored['context']['years'] ?? null,
                cb_ai_analytik_dostupne_roky(db())
            );
            $ambiguityMode = cb_ai_analytik_normalizovat_nejasnost($stored['context']['ambiguity_mode'] ?? null);
            $analysisContext = ['years' => $years, 'ambiguity_mode' => $ambiguityMode];
            $resumeState = is_array($stored['agent_state'] ?? null) ? $stored['agent_state'] : null;
            $continuationKind = (string)($stored['continuation_kind'] ?? 'clarification');
            $previousDurationMs = max(0, (int)($stored['duration_ms'] ?? 0));
            if ($prompt === '' || $resumeState === null) {
                throw new RuntimeException('Uložený stav analýzy není úplný.');
            }
            if ((int)($resumeState['followup_count'] ?? 0) >= CB_AI_ANALYTIK_MAX_NAVAZANI) {
                $finishedAudit = is_array($stored['audit'] ?? null) ? $stored['audit'] : [];
                $finishedAudit['status'] = 'completed';
                cb_ai_analytik_audit_finish($idAudit, $finishedAudit);
                cb_ai_analytik_prilohy_smazat_openai(
                    is_array($resumeState['attachment_file_ids'] ?? null)
                        ? $resumeState['attachment_file_ids']
                        : []
                );
                cb_ai_analytik_json(422, [
                    'ok' => false,
                    'error' => 'Byl vyčerpán limit navazujících upřesnění. Spusťte nový prompt.',
                ]);
            }
        } catch (Throwable $error) {
            if (is_array($stored)) {
                cb_ai_analytik_audit_obnovit_cekani(
                    $idAudit,
                    $idUser,
                    (string)($stored['continuation_kind'] ?? 'clarification')
                );
            }
            cb_ai_analytik_json(422, ['ok' => false, 'error' => $error->getMessage()]);
        }
    } else {
        $prompt = trim((string)($input['prompt'] ?? ''));
        if ($prompt === '') {
            cb_ai_analytik_json(422, ['ok' => false, 'error' => 'Napište dotaz.']);
        }
        $modelRaw = array_key_exists('model', $input) ? $input['model'] : CB_AI_ANALYTIK_VYCHOZI_MODEL;
        $model = is_string($modelRaw) ? trim($modelRaw) : '';
        $modelProAudit = mb_substr($model !== '' ? $model : '[neplatný formát]', 0, 64);
        try {
            $idAudit = cb_ai_analytik_audit_start($idUser, $idLogin, $modelProAudit, $prompt, 'global');
        } catch (Throwable $error) {
            error_log('AI analytik: audit nelze zahájit: ' . $error->getMessage());
            cb_ai_analytik_oznam_chybu_adminovi($error, 0);
            cb_ai_analytik_json(503, [
                'ok' => false,
            'error' => $maPravoTechnickeDiagnostiky
                ? get_class($error) . ': ' . $error->getMessage() . ' v ' . $error->getFile() . ':' . $error->getLine()
                : 'Audit AI analytika není dostupný. Dotaz nebyl spuštěn.',
            ]);
        }
    }

    $startedAt = hrtime(true);
    $auditFinished = false;
    register_shutdown_function(static function () use ($idAudit, $startedAt, $previousDurationMs, &$auditFinished): void {
        if ($auditFinished) {
            return;
        }
        $lastError = error_get_last();
        if (!is_array($lastError) || !in_array((int)$lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        cb_ai_analytik_audit_finish($idAudit, [
            'status' => 'fatal_error',
            'duration_ms' => $previousDurationMs + (int)((hrtime(true) - $startedAt) / 1_000_000),
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
            cb_ai_analytik_oznam_chybu_adminovi($error, $idAudit);
        }
        $audit['duration_ms'] = $previousDurationMs + (int)((hrtime(true) - $startedAt) / 1_000_000);
        cb_ai_analytik_audit_finish($idAudit, $audit);
        $auditFinished = true;
        cb_ai_analytik_json(403, [
            'ok' => false,
            'error' => 'Přístup k AI analytikovi byl zablokován kvůli pokusu použít nepovolený model.',
        ]);
    }
    if (!cb_ai_analytik_model_ma_pravo($model)) {
        $audit['duration_ms'] = $previousDurationMs + (int)((hrtime(true) - $startedAt) / 1_000_000);
        $audit['status'] = 'model_permission_denied';
        $audit['error_type'] = 'model_permission_denied';
        $audit['error_message'] = 'Uživatel nemá právo použít model: ' . $model;
        cb_ai_analytik_audit_finish($idAudit, $audit);
        $auditFinished = true;
        cb_ai_analytik_json(403, ['ok' => false, 'error' => 'K vybranému modelu nemáte oprávnění.']);
    }

    if (!$isContinuation) {
        try {
            $requestedOutput = cb_ai_analytik_normalizovat_vystup(
                is_array($input['vystup'] ?? null) ? $input['vystup'] : []
            );
            $availableYears = cb_ai_analytik_dostupne_roky(db());
            $years = cb_ai_analytik_normalizovat_roky($input['roky'] ?? null, $availableYears);
            $ambiguityMode = cb_ai_analytik_normalizovat_nejasnost($input['nejistota'] ?? null);
            $analysisContext = ['years' => $years, 'ambiguity_mode' => $ambiguityMode];
            $attachments = cb_ai_analytik_prilohy_pouzit(
                trim((string)($input['attachment_token'] ?? '')),
                $idUser,
                $model
            );
        } catch (Throwable $error) {
            $audit['duration_ms'] = (int)((hrtime(true) - $startedAt) / 1_000_000);
            $audit['status'] = 'rejected_request';
            $audit['error_type'] = get_class($error);
            $audit['error_code'] = (string)$error->getCode();
            $audit['error_message'] = $error->getMessage();
            cb_ai_analytik_audit_finish($idAudit, $audit);
            $auditFinished = true;
            if (!($error instanceof CbAiAnalytikUzivatelskaChyba)) {
                cb_ai_analytik_oznam_chybu_adminovi($error, $idAudit);
            }
            cb_ai_analytik_json(422, [
                'ok' => false,
                'error' => cb_ai_analytik_chyba_pro_uzivatele($error, $maPravoTechnickeDiagnostiky, $idAudit),
            ]);
        }
        cb_ai_analytik_audit_request($idAudit, $requestedOutput, $analysisContext);
    }

    if (empty($_SESSION['ai_analytik_export_secret'])) {
        $_SESSION['ai_analytik_export_secret'] = bin2hex(random_bytes(32));
    }
    $exportSecret = (string)$_SESSION['ai_analytik_export_secret'];
    $activeAttachmentFileIds = $isContinuation
        ? array_values(array_filter(array_map(
            'strval',
            is_array($resumeState['attachment_file_ids'] ?? null)
                ? $resumeState['attachment_file_ids']
                : []
        )))
        : array_values(array_filter(array_map(
            static fn(array $attachment): string => trim((string)($attachment['file_id'] ?? '')),
            array_filter($attachments, 'is_array')
        )));
    session_write_close();
    ignore_user_abort(true);

    cb_ai_analytik_stream_start();
    $initialApiCalls = $resumeState === null ? 0 : max(0, (int)($resumeState['api_calls'] ?? 0));
    $initialSqlCalls = $resumeState === null ? 0 : max(0, (int)($resumeState['sql_count'] ?? 0));
    cb_ai_analytik_stream('progress', [
        'stage' => 'accepted',
        'message' => $isContinuation
            ? 'Upřesnění přijato. Pokračuji ve stejné analýze bez opakování hotových kroků.'
            : 'Dotaz přijat. Vybrané roky: ' . implode(', ', $years) . '. Zahajuji analýzu.',
        'meta' => [
            'api_calls' => $initialApiCalls,
            'sql_count' => $initialSqlCalls,
            'audit_id' => $idAudit,
            'years' => $years,
            'ambiguity_mode' => $ambiguityMode,
        ],
    ]);

    try {
        $progress = static function (string $stage, string $message, array $meta) use ($idAudit): void {
            cb_ai_analytik_audit_status($idAudit, $stage);
            cb_ai_analytik_stream('progress', ['stage' => $stage, 'message' => $message, 'meta' => $meta]);
        };
        $result = cb_ai_analytik_agent_spustit(
            $idAudit,
            $model,
            $prompt,
            $requestedOutput,
            $analysisContext,
            $progress,
            $resumeState,
            $clarificationAnswer,
            $attachments
        );
        $durationMs = $previousDurationMs + (int)((hrtime(true) - $startedAt) / 1_000_000);
        $audit['openai_summary_response_id'] = (string)$result['last_response_id'];
        $audit['row_count'] = count($result['rows']);
        $audit['duration_ms'] = $durationMs;

        $export = null;
        $continuation = null;
        if ($result['response_type'] !== 'clarification') {
            $export = cb_ai_analytik_export_podepsat([
                'version' => 1,
                'id_user' => $idUser,
                'audit_id' => $idAudit,
                'expires_at' => time() + CB_AI_ANALYTIK_EXPORT_PLATNOST_SEKUND,
                'prompt' => $prompt,
                'years' => $years,
                'text' => $result['text'],
                'chart' => $result['chart'],
                'columns' => $result['columns'],
                'rows' => $result['rows'],
                'model' => $model,
                'usage' => $result['usage'],
                'duration_ms' => $durationMs,
            ], $exportSecret);
        }

        $continuationState = is_array($result['continuation_state'] ?? null)
            ? $result['continuation_state']
            : [];
        $followupCount = max(0, (int)($continuationState['followup_count'] ?? 0));
        $canContinue = $followupCount < CB_AI_ANALYTIK_MAX_NAVAZANI;
        if ($result['response_type'] === 'clarification' && !$canContinue) {
            throw new CbAiAnalytikAgentLimitChyba(
                'AI požádala o další upřesnění po vyčerpání povoleného limitu.'
            );
        }
        if ($canContinue) {
            $nextContinuationKind = $result['response_type'] === 'clarification' ? 'clarification' : 'followup';
            $continuationToken = cb_ai_analytik_audit_ulozit_pokracovani($idAudit, $idUser, [
                'version' => 1,
                'continuation_kind' => $nextContinuationKind,
                'prompt' => $prompt,
                'model' => $model,
                'requested_output' => $requestedOutput,
                'context' => $analysisContext,
                'agent_state' => $continuationState,
                'duration_ms' => $durationMs,
                'audit' => $audit,
            ]);
            $continuation = [
                'audit_id' => $idAudit,
                'token' => $continuationToken,
                'kind' => $nextContinuationKind,
                'expires_in_seconds' => CB_AI_ANALYTIK_POKRACOVANI_PLATNOST_SEKUND,
                'followup_count' => $followupCount,
                'remaining_followups' => CB_AI_ANALYTIK_MAX_NAVAZANI - $followupCount,
            ];
            $auditFinished = true;
        } else {
            $audit['status'] = 'completed';
            cb_ai_analytik_audit_finish($idAudit, $audit);
            $auditFinished = true;
            cb_ai_analytik_prilohy_smazat_openai(
                is_array($result['attachment_file_ids'] ?? null)
                    ? $result['attachment_file_ids']
                    : $activeAttachmentFileIds
            );
        }

        cb_ai_analytik_stream('result', ['data' => [
            'ok' => true,
            'response_type' => $result['response_type'],
            'clarification' => $result['clarification'],
            'text' => $result['text'],
            'chart' => $result['chart'],
            'columns' => $result['columns'],
            'rows' => $result['rows'],
            'export' => $export,
            'continuation' => $continuation,
            'meta' => [
                'model' => $model,
                'vystup' => $requestedOutput,
                'years' => $years,
                'ambiguity_mode' => $ambiguityMode,
                'catalogs' => $result['catalogs'],
                'usage' => $result['usage'],
                'duration_ms' => $durationMs,
                'api_calls' => (int)$result['api_calls'],
                'sql_count' => (int)$result['sql_count'],
                'audit_id' => $idAudit,
            ],
        ]]);
    } catch (Throwable $error) {
        $audit['duration_ms'] = $previousDurationMs + (int)((hrtime(true) - $startedAt) / 1_000_000);
        $audit['status'] = match (true) {
            $error instanceof CbAiAnalytikZrusenoUzivatelem => 'cancelled',
            $error instanceof CbAiAnalytikSpojeniPreruseno => 'connection_lost',
            $error instanceof CbAiAnalytikUzivatelskaChyba => 'rejected_request',
            $error instanceof CbAiAnalytikAgentLimitChyba => 'limit_exceeded',
            default => 'error',
        };
        $audit['error_type'] = get_class($error);
        $audit['error_code'] = (string)$error->getCode();
        $audit['error_message'] = mb_substr($error->getMessage(), 0, 1000);
        $canRetryContinuation = $isContinuation
            && !($error instanceof CbAiAnalytikZrusenoUzivatelem)
            && !($error instanceof CbAiAnalytikUzivatelskaChyba)
            && !($error instanceof CbAiAnalytikAgentLimitChyba);
        if ($canRetryContinuation) {
            cb_ai_analytik_audit_obnovit_cekani($idAudit, $idUser, $continuationKind);
        } else {
            cb_ai_analytik_audit_finish($idAudit, $audit);
            cb_ai_analytik_prilohy_smazat_openai($activeAttachmentFileIds);
        }
        $auditFinished = true;
        error_log('AI analytik audit #' . $idAudit . ': ' . get_class($error) . ': ' . $error->getMessage());
        if (
            !($error instanceof CbAiAnalytikZrusenoUzivatelem)
            && !($error instanceof CbAiAnalytikSpojeniPreruseno)
            && !($error instanceof CbAiAnalytikUzivatelskaChyba)
        ) {
            cb_ai_analytik_oznam_chybu_adminovi($error, $idAudit);
        }
        cb_ai_analytik_stream('error', [
            'message' => cb_ai_analytik_chyba_pro_uzivatele($error, $maPravoTechnickeDiagnostiky, $idAudit),
            'audit_id' => $idAudit,
        ]);
    }
    exit;
}

cb_ai_analytik_gateway();
