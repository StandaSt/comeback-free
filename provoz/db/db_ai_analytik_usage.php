<?php
declare(strict_types=1);

function cb_ai_analytik_usage_zapsat(int $idAudit, string $typVolani, array $response): void
{
    $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
    $prices = is_array($response['prices'] ?? null) ? $response['prices'] : [];
    $usageJson = json_encode(
        $response['usage_raw'] ?? [],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
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
    $inputPrice = (float)($prices['input'] ?? 0);
    $cachedInputPrice = (float)($prices['cached_input'] ?? 0);
    $outputPrice = (float)($prices['output'] ?? 0);
    $costUsd = (float)($response['cost_usd'] ?? 0);

    $conn = db();
    $stmt = $conn->prepare(
        'INSERT INTO ai_analytik_openai_usage
            (id_ai_analytik_audit, created_at, typ_volani, response_id, model, service_tier,
             duration_ms, input_tokens, cached_input_tokens, output_tokens, reasoning_tokens, total_tokens,
             input_price_per_million, cached_input_price_per_million, output_price_per_million,
             cost_usd, usage_json)
         VALUES (?, NOW(3), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->bind_param(
        'issssiiiiiidddds',
        $idAudit,
        $typVolani,
        $responseId,
        $model,
        $serviceTier,
        $durationMs,
        $inputTokens,
        $cachedInputTokens,
        $outputTokens,
        $reasoningTokens,
        $totalTokens,
        $inputPrice,
        $cachedInputPrice,
        $outputPrice,
        $costUsd,
        $usageJson
    );
    $stmt->execute();
    $stmt->close();
}
