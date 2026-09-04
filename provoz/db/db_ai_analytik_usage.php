<?php
declare(strict_types=1);

function cb_ai_analytik_usage_start(int $idAudit, string $typVolani, string $model): int
{
    $prices = cb_ai_analytik_ceny_modelu($model);

    $conn = db();
    $stmt = $conn->prepare(
        'INSERT INTO ai_analytik_openai_usage
            (id_ai_analytik_audit, created_at, typ_volani, response_id, model, service_tier,
             input_price_per_million, cached_input_price_per_million, output_price_per_million,
             usage_json, status)
         VALUES (?, NOW(3), ?, \'\', ?, \'default\', ?, ?, ?, \'{}\', \'started\')'
    );
    $inputPrice = (float)$prices['input'];
    $cachedInputPrice = (float)$prices['cached_input'];
    $outputPrice = (float)$prices['output'];
    $stmt->bind_param(
        'issddd',
        $idAudit,
        $typVolani,
        $model,
        $inputPrice,
        $cachedInputPrice,
        $outputPrice
    );
    $stmt->execute();
    $id = (int)$conn->insert_id;
    $stmt->close();
    return $id;
}

function cb_ai_analytik_usage_finish(int $idUsage, array $response): void
{
    $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
    $usageJson = json_encode(
        $response['usage_raw'] ?? [],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_openai_usage
         SET completed_at = NOW(3), response_id = ?, model = ?, service_tier = ?, duration_ms = ?,
             input_tokens = ?, cached_input_tokens = ?, output_tokens = ?, reasoning_tokens = ?, total_tokens = ?,
             cost_usd = ?, usage_json = ?, status = \'completed\', error_type = NULL, error_code = NULL,
             error_message = NULL
         WHERE id_ai_analytik_openai_usage = ?'
    );
    $responseId = (string)($response['id'] ?? '');
    $model = (string)($response['model'] ?? '');
    $serviceTier = (string)($response['service_tier'] ?? 'default');
    $durationMs = (int)($response['duration_ms'] ?? 0);
    $inputTokens = (int)($usage['input_tokens'] ?? 0);
    $cachedInputTokens = (int)($usage['cached_input_tokens'] ?? 0);
    $outputTokens = (int)($usage['output_tokens'] ?? 0);
    $reasoningTokens = (int)($usage['reasoning_tokens'] ?? 0);
    $totalTokens = (int)($usage['total_tokens'] ?? 0);
    $costUsd = (float)($response['cost_usd'] ?? 0);
    $stmt->bind_param(
        'sssiiiiiidsi',
        $responseId,
        $model,
        $serviceTier,
        $durationMs,
        $inputTokens,
        $cachedInputTokens,
        $outputTokens,
        $reasoningTokens,
        $totalTokens,
        $costUsd,
        $usageJson,
        $idUsage
    );
    $stmt->execute();
    $stmt->close();
}

function cb_ai_analytik_usage_error(int $idUsage, Throwable $error, int $durationMs): void
{
    $conn = db();
    $stmt = $conn->prepare(
        'UPDATE ai_analytik_openai_usage
         SET completed_at = NOW(3), duration_ms = ?, status = \'error\', error_type = ?, error_code = NULLIF(?, \'\'),
             error_message = ?
         WHERE id_ai_analytik_openai_usage = ?'
    );
    $type = get_class($error);
    $code = (string)$error->getCode();
    $message = mb_substr($error->getMessage(), 0, 1000);
    $stmt->bind_param('isssi', $durationMs, $type, $code, $message, $idUsage);
    $stmt->execute();
    $stmt->close();
}
