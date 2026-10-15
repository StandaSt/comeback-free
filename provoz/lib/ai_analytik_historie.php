<?php
declare(strict_types=1);
/* Pripravuje trvale snimky vysledku a autorizovane cteni historie bez novych AI/SQL analyz. */
require_once __DIR__ . '/../db/db_ai_analytik_historie_cteni.php';
require_once __DIR__ . '/../db/db_ai_analytik_historie_zapis.php';

/** Uklada jen data odpovedi; podpis exportu a docasne tokeny nejsou soucasti archivu. */
function cb_ai_historie_snimek(array $data, array $prubeh): array
{
    return array_intersect_key($data, array_flip([
        'ok', 'response_type', 'clarification', 'text', 'chart', 'columns', 'rows', 'meta',
    ])) + ['processing' => $prubeh];
}

/** Znovu podepise export ulozeneho vysledku pro aktualne opravnenou osobu. */
function cb_ai_historie_export(array $data, array $audit, int $idUser): array
{
    if (($data['response_type'] ?? '') === 'clarification') { return $data; }
    if (empty($_SESSION['ai_analytik_export_secret'])) {
        $_SESSION['ai_analytik_export_secret'] = bin2hex(random_bytes(32));
    }
    $meta = $data['meta'] ?? [];
    $data['export'] = cb_ai_analytik_export_podepsat([
        'version' => 1, 'id_user' => $idUser, 'audit_id' => (int)$audit['id_ai_analytik_audit'],
        'expires_at' => time() + CB_AI_ANALYTIK_EXPORT_PLATNOST_SEKUND,
        'prompt' => (string)$audit['prompt'], 'years' => $meta['years'] ?? [],
        'text' => $data['text'] ?? '', 'chart' => $data['chart'] ?? null,
        'columns' => $data['columns'] ?? [], 'rows' => $data['rows'] ?? [],
        'model' => $meta['model'] ?? $audit['model'], 'usage' => $meta['usage'] ?? [],
        'duration_ms' => $meta['duration_ms'] ?? 0,
    ], (string)$_SESSION['ai_analytik_export_secret']);
    return $data;
}

/** Detail obsahuje vsechna kola; token navazani vraci pouze jeho vlastnikovi pri stale platne vazbe. */
function cb_ai_historie_detail(int $idAudit, int $idUser, string $token): array
{
    $audit = cb_ai_historie_audit($idAudit, $idUser);
    $rows = cb_ai_historie_kola($idAudit);
    $turns = [];
    foreach ($rows as $row) {
        $data = $row['odpoved_json'] === null ? null : json_decode($row['odpoved_json'], true, 512, JSON_THROW_ON_ERROR);
        if ((int)$row['verze_formatu'] !== 1) {
            throw new CbAiAnalytikUzivatelskaChyba('Tato verze historického výsledku zatím není podporována.');
        }
        if (is_array($data)) {
            $data['meta']['can_save_prompt'] = (int)$audit['id_user'] === $idUser;
            $data = cb_ai_historie_export($data, $audit, $idUser);
        }
        $turns[] = [
            'id' => (int)$row['id'], 'order' => (int)$row['poradi'], 'created_at' => $row['vytvoreno'],
            'prompt' => $row['zadani'], 'kind' => $row['typ_zadani'], 'status' => $row['stav'],
            'error' => $row['chyba'], 'data' => $data,
        ];
    }
    // Prohlizeni historie nesmi znovu aktivovat skoncene navazani ani zneplatnit jine okno.
    if ($turns !== [] && (int)$audit['id_user'] === $idUser && $token !== ''
        && (int)$audit['zbyva'] > 0 && in_array($audit['status'], ['awaiting_followup', 'awaiting_clarification'], true)
        && hash_equals((string)$audit['continuation_token_hash'], hash('sha256', $token))) {
        $last = count($turns) - 1;
        if (is_array($turns[$last]['data'])) {
            $stored = json_decode((string)($audit['continuation_state_json'] ?? ''), true);
            $count = (int)($stored['agent_state']['followup_count'] ?? 0);
            $turns[$last]['data']['continuation'] = [
                'audit_id' => $idAudit, 'token' => $token,
                'kind' => $audit['status'] === 'awaiting_followup' ? 'followup' : 'clarification',
                'expires_in_seconds' => (int)$audit['zbyva'],
                'remaining_followups' => max(0, CB_AI_ANALYTIK_MAX_NAVAZANI - $count),
            ];
        }
    }
    return ['audit_id' => $idAudit, 'prompt' => $audit['prompt'], 'turns' => $turns];
}

/** Vyridi jen dva cteci endpointy historie; spolecne prihlaseni a CSRF overuje gateway. */
function cb_ai_historie_http(string $action, array $input, int $idUser): void
{
    if (!in_array($action, ['history_uses', 'history_detail'], true)) { return; }
    try {
        $id = (int)($input['audit_id'] ?? 0);
        $data = $action === 'history_uses'
            ? cb_ai_historie_pouziti($id, $idUser, max(0, (int)($input['before_id'] ?? 0)))
            : cb_ai_historie_detail($id, $idUser, (string)($input['continuation_token'] ?? ''));
        cb_ai_analytik_json(200, ['ok' => true] + $data);
    } catch (CbAiAnalytikUzivatelskaChyba $error) {
        cb_ai_analytik_json(404, ['ok' => false, 'error' => $error->getMessage()]);
    } catch (Throwable $error) {
        error_log('Franta historie: ' . $error->getMessage());
        cb_ai_analytik_json(503, ['ok' => false, 'error' => 'Historii se nepodařilo načíst. Zkuste ji otevřít znovu.']);
    }
}
