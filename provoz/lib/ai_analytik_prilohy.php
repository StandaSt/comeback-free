<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_analytik_openai.php';

const CB_AI_ANALYTIK_PRILOHA_MAX_POCET = 5;
const CB_AI_ANALYTIK_PRILOHA_MAX_BAJTU = 20 * 1024 * 1024;
// Dočasná pojistka pro konverzaci s pěti půlhodinovými navázáními; po ukončení se soubor maže hned.
const CB_AI_ANALYTIK_PRILOHA_PLATNOST_SEKUND = 21600;
const CB_AI_ANALYTIK_KURZ_USD_CZK = 20.8;

function cb_ai_analytik_prilohy_smazat_openai(array $attachments): void
{
    foreach ($attachments as $attachment) {
        $fileId = is_array($attachment)
            ? trim((string)($attachment['file_id'] ?? ''))
            : trim((string)$attachment);
        if ($fileId !== '' && !cb_ai_analytik_openai_soubor_smazat($fileId)) {
            error_log('AI analytik: dočasnou přílohu OpenAI se nepodařilo smazat: ' . $fileId);
        }
    }
}

function cb_ai_analytik_prilohy_session_cleanup(): void
{
    $batches = is_array($_SESSION['ai_analytik_prilohy'] ?? null)
        ? $_SESSION['ai_analytik_prilohy']
        : [];
    $cutoff = time() - CB_AI_ANALYTIK_PRILOHA_PLATNOST_SEKUND;
    foreach ($batches as $token => $batch) {
        if (!is_array($batch) || (int)($batch['created_at'] ?? 0) < $cutoff) {
            if (is_array($batch)) {
                cb_ai_analytik_prilohy_smazat_openai(
                    is_array($batch['attachments'] ?? null) ? $batch['attachments'] : []
                );
            }
            unset($batches[$token]);
        }
    }
    $_SESSION['ai_analytik_prilohy'] = $batches;
}

function cb_ai_analytik_priloha_normalizovat_upload(array $upload): array
{
    $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Příloha překračuje povolenou velikost.',
            UPLOAD_ERR_PARTIAL => 'Příloha se nenahrála celá. Zkuste to znovu.',
            default => 'Přílohu se nepodařilo nahrát.',
        };
        throw new CbAiAnalytikUzivatelskaChyba($message);
    }

    $tmpPath = (string)($upload['tmp_name'] ?? '');
    $size = (int)($upload['size'] ?? 0);
    if ($tmpPath === '' || !is_uploaded_file($tmpPath) || $size <= 0) {
        throw new CbAiAnalytikUzivatelskaChyba('Příloha není platný nahraný soubor.');
    }
    if ($size > CB_AI_ANALYTIK_PRILOHA_MAX_BAJTU) {
        throw new CbAiAnalytikUzivatelskaChyba('Jedna příloha může mít nejvýše 20 MB.');
    }

    $filename = basename(str_replace('\\', '/', trim((string)($upload['name'] ?? 'priloha'))));
    $filename = preg_replace('/[\x00-\x1F\x7F]+/u', '', $filename) ?? '';
    $filename = mb_substr($filename !== '' ? $filename : 'priloha', 0, 180);
    $extension = mb_strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = [
        'pdf' => ['canonical' => 'application/pdf', 'mimes' => ['application/pdf']],
        'docx' => [
            'canonical' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'mimes' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/octet-stream',
            ],
        ],
        'xlsx' => [
            'canonical' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'mimes' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
        ],
        'csv' => [
            'canonical' => 'text/csv',
            'mimes' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
        ],
        'jpg' => ['canonical' => 'image/jpeg', 'mimes' => ['image/jpeg']],
        'jpeg' => ['canonical' => 'image/jpeg', 'mimes' => ['image/jpeg']],
        'png' => ['canonical' => 'image/png', 'mimes' => ['image/png']],
        'webp' => ['canonical' => 'image/webp', 'mimes' => ['image/webp']],
        'gif' => ['canonical' => 'image/gif', 'mimes' => ['image/gif']],
    ];
    if (!isset($allowed[$extension])) {
        throw new CbAiAnalytikUzivatelskaChyba('Povolené přílohy jsou PDF, DOCX, XLSX, CSV a obrázky JPG, PNG, WEBP nebo GIF.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo !== false ? (string)finfo_file($finfo, $tmpPath) : '';
    if ($finfo !== false) {
        finfo_close($finfo);
    }
    if ($detectedMime === '' || !in_array($detectedMime, $allowed[$extension]['mimes'], true)) {
        throw new CbAiAnalytikUzivatelskaChyba('Typ přílohy neodpovídá její příponě.');
    }

    return [
        'tmp_path' => $tmpPath,
        'name' => $filename,
        'size' => $size,
        'mime' => (string)$allowed[$extension]['canonical'],
        'input_type' => str_starts_with((string)$allowed[$extension]['canonical'], 'image/')
            ? 'input_image'
            : 'input_file',
    ];
}

function cb_ai_analytik_prilohy_nahrat(array $upload, int $idUser, string $batchToken = ''): array
{
    cb_ai_analytik_prilohy_session_cleanup();
    $batches = $_SESSION['ai_analytik_prilohy'];
    $batch = $batchToken !== '' && is_array($batches[$batchToken] ?? null)
        ? $batches[$batchToken]
        : null;
    if ($batch === null) {
        $batchToken = bin2hex(random_bytes(24));
        $batch = ['created_at' => time(), 'id_user' => $idUser, 'attachments' => [], 'estimate' => null];
    }
    if ((int)($batch['id_user'] ?? 0) !== $idUser) {
        throw new CbAiAnalytikUzivatelskaChyba('Dočasná sada příloh není platná.');
    }
    $attachments = is_array($batch['attachments'] ?? null) ? $batch['attachments'] : [];
    if (count($attachments) >= CB_AI_ANALYTIK_PRILOHA_MAX_POCET) {
        throw new CbAiAnalytikUzivatelskaChyba('K jednomu dotazu lze přiložit nejvýše 5 souborů.');
    }

    $normalized = cb_ai_analytik_priloha_normalizovat_upload($upload);
    $openAiFile = cb_ai_analytik_openai_soubor_nahrat(
        $normalized['tmp_path'],
        $normalized['name'],
        $normalized['mime'],
        CB_AI_ANALYTIK_PRILOHA_PLATNOST_SEKUND
    );
    $attachments[] = [
        'file_id' => (string)$openAiFile['id'],
        'name' => $normalized['name'],
        'size' => $normalized['size'],
        'mime' => $normalized['mime'],
        'input_type' => $normalized['input_type'],
    ];
    $batch['attachments'] = $attachments;
    $batch['estimate'] = null;
    $batches[$batchToken] = $batch;
    $_SESSION['ai_analytik_prilohy'] = $batches;

    return [
        'token' => $batchToken,
        'count' => count($attachments),
        'attachment' => [
            'name' => $normalized['name'],
            'size' => $normalized['size'],
        ],
    ];
}

function cb_ai_analytik_prilohy_user_content(string $prompt, array $attachments): array
{
    $content = [['type' => 'input_text', 'text' => $prompt !== '' ? $prompt : ' ']];
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            continue;
        }
        $fileId = trim((string)($attachment['file_id'] ?? ''));
        if ($fileId === '') {
            continue;
        }
        if ((string)($attachment['input_type'] ?? '') === 'input_image') {
            $content[] = ['type' => 'input_image', 'file_id' => $fileId, 'detail' => 'auto'];
        } else {
            $content[] = ['type' => 'input_file', 'file_id' => $fileId];
        }
    }
    return $content;
}

function cb_ai_analytik_prilohy_odhadnout(
    string $batchToken,
    int $idUser,
    string $model,
    string $prompt
): array {
    cb_ai_analytik_prilohy_session_cleanup();
    $batches = $_SESSION['ai_analytik_prilohy'];
    $batch = $batches[$batchToken] ?? null;
    if (!is_array($batch) || (int)($batch['id_user'] ?? 0) !== $idUser) {
        throw new CbAiAnalytikUzivatelskaChyba('Přílohy již nejsou dostupné. Vyberte je znovu.');
    }
    $attachments = is_array($batch['attachments'] ?? null) ? $batch['attachments'] : [];
    if ($attachments === []) {
        throw new CbAiAnalytikUzivatelskaChyba('Nejprve vyberte alespoň jednu přílohu.');
    }

    $baseInput = [[
        'role' => 'user',
        'content' => [['type' => 'input_text', 'text' => $prompt !== '' ? $prompt : ' ']],
    ]];
    $withFilesInput = [[
        'role' => 'user',
        'content' => cb_ai_analytik_prilohy_user_content($prompt, $attachments),
    ]];
    $baseTokens = cb_ai_analytik_openai_spocitat_vstup(['model' => $model, 'input' => $baseInput]);
    $withFilesTokens = cb_ai_analytik_openai_spocitat_vstup(['model' => $model, 'input' => $withFilesInput]);
    $attachmentTokens = max(0, $withFilesTokens - $baseTokens);
    $prices = cb_ai_analytik_ceny_modelu($model);
    $costUsd = ($attachmentTokens * (float)$prices['input']) / 1_000_000;
    $estimate = [
        'model' => $model,
        'attachment_tokens' => $attachmentTokens,
        'cost_usd' => $costUsd,
        'cost_czk' => $costUsd * CB_AI_ANALYTIK_KURZ_USD_CZK,
        'created_at' => time(),
    ];
    $batch['estimate'] = $estimate;
    $batches[$batchToken] = $batch;
    $_SESSION['ai_analytik_prilohy'] = $batches;

    return $estimate + ['token' => $batchToken, 'count' => count($attachments)];
}

function cb_ai_analytik_prilohy_pouzit(string $batchToken, int $idUser, string $model): array
{
    if ($batchToken === '') {
        return [];
    }
    cb_ai_analytik_prilohy_session_cleanup();
    $batches = $_SESSION['ai_analytik_prilohy'];
    $batch = $batches[$batchToken] ?? null;
    if (!is_array($batch) || (int)($batch['id_user'] ?? 0) !== $idUser) {
        throw new CbAiAnalytikUzivatelskaChyba('Odhad příloh vypršel. Proveďte jej znovu.');
    }
    $estimate = $batch['estimate'] ?? null;
    if (!is_array($estimate) || (string)($estimate['model'] ?? '') !== $model) {
        throw new CbAiAnalytikUzivatelskaChyba('Po změně modelu je nutné znovu odhadnout cenu příloh.');
    }
    $attachments = is_array($batch['attachments'] ?? null) ? array_values($batch['attachments']) : [];
    if ($attachments === []) {
        throw new CbAiAnalytikUzivatelskaChyba('Přílohy již nejsou dostupné. Vyberte je znovu.');
    }
    unset($batches[$batchToken]);
    $_SESSION['ai_analytik_prilohy'] = $batches;
    return $attachments;
}

function cb_ai_analytik_prilohy_zahodit(string $batchToken, int $idUser): bool
{
    if ($batchToken === '') {
        return true;
    }
    $batches = is_array($_SESSION['ai_analytik_prilohy'] ?? null)
        ? $_SESSION['ai_analytik_prilohy']
        : [];
    $batch = $batches[$batchToken] ?? null;
    if (!is_array($batch) || (int)($batch['id_user'] ?? 0) !== $idUser) {
        return false;
    }
    cb_ai_analytik_prilohy_smazat_openai(
        is_array($batch['attachments'] ?? null) ? $batch['attachments'] : []
    );
    unset($batches[$batchToken]);
    $_SESSION['ai_analytik_prilohy'] = $batches;
    return true;
}
