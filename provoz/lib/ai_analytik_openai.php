<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_analytik_pravidla.php';

/**
 * Jedno nízkoúrovňové volání Responses API.
 * Funkce záměrně nevyžaduje text: mezikrok agenta může obsahovat pouze function_call.
 */
function cb_ai_analytik_openai_request(array $payload): array
{
    $apiKey = trim((string)getenv('AI_ANALYTIK_OPENAI_API_KEY'));
    if ($apiKey === '') {
        throw new RuntimeException('OpenAI API není na serveru nakonfigurováno.');
    }

    $payload['service_tier'] = 'default';
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $curl = curl_init('https://api.openai.com/v1/responses');
    if ($curl === false) {
        throw new RuntimeException('OpenAI API nelze inicializovat.');
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $json,
    ]);

    $startedAt = hrtime(true);
    $body = curl_exec($curl);
    $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
    $curlError = curl_error($curl);
    $httpStatus = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if (!is_string($body)) {
        throw new RuntimeException('OpenAI API není dostupné: ' . ($curlError !== '' ? $curlError : 'síťová chyba.'));
    }
    if ($httpStatus < 200 || $httpStatus >= 300) {
        $errorBody = json_decode($body, true);
        $errorMessage = is_array($errorBody) ? trim((string)($errorBody['error']['message'] ?? '')) : '';
        throw new RuntimeException(
            'OpenAI API HTTP ' . $httpStatus . ($errorMessage !== '' ? ': ' . mb_substr($errorMessage, 0, 1000) : '.')
        );
    }

    $response = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($response)) {
        throw new RuntimeException('OpenAI API vrátilo neplatnou odpověď.');
    }
    $status = trim((string)($response['status'] ?? ''));
    if (in_array($status, ['failed', 'cancelled', 'incomplete'], true)) {
        $message = trim((string)($response['error']['message'] ?? ''));
        if ($message === '') {
            $message = trim((string)($response['incomplete_details']['reason'] ?? $status));
        }
        throw new RuntimeException('OpenAI API nedokončilo odpověď: ' . $message);
    }

    $outputText = trim((string)($response['output_text'] ?? ''));
    if ($outputText === '' && isset($response['output']) && is_array($response['output'])) {
        foreach ($response['output'] as $item) {
            if (!is_array($item) || !isset($item['content']) || !is_array($item['content'])) {
                continue;
            }
            foreach ($item['content'] as $content) {
                if (is_array($content) && (string)($content['type'] ?? '') === 'output_text') {
                    $outputText .= (string)($content['text'] ?? '');
                }
            }
        }
        $outputText = trim($outputText);
    }

    $model = trim((string)($response['model'] ?? $payload['model'] ?? ''));
    $serviceTier = trim((string)($response['service_tier'] ?? 'default'));
    $usageRaw = isset($response['usage']) && is_array($response['usage']) ? $response['usage'] : [];
    $inputTokens = (int)($usageRaw['input_tokens'] ?? 0);
    $cachedInputTokens = (int)($usageRaw['input_tokens_details']['cached_tokens'] ?? 0);
    $outputTokens = (int)($usageRaw['output_tokens'] ?? 0);
    $reasoningTokens = (int)($usageRaw['output_tokens_details']['reasoning_tokens'] ?? 0);
    $totalTokens = (int)($usageRaw['total_tokens'] ?? ($inputTokens + $outputTokens));
    $prices = cb_ai_analytik_ceny_modelu((string)($payload['model'] ?? $model));
    $uncachedInputTokens = max(0, $inputTokens - $cachedInputTokens);
    $costUsd = (
        ($uncachedInputTokens * (float)$prices['input'])
        + ($cachedInputTokens * (float)$prices['cached_input'])
        + ($outputTokens * (float)$prices['output'])
    ) / 1_000_000;

    return [
        'id' => (string)($response['id'] ?? ''),
        'status' => $status,
        'text' => $outputText,
        'output' => is_array($response['output'] ?? null) ? $response['output'] : [],
        'model' => $model,
        'service_tier' => $serviceTier,
        'duration_ms' => $durationMs,
        'usage' => [
            'input_tokens' => $inputTokens,
            'cached_input_tokens' => $cachedInputTokens,
            'output_tokens' => $outputTokens,
            'reasoning_tokens' => $reasoningTokens,
            'total_tokens' => $totalTokens,
        ],
        'usage_raw' => $usageRaw,
        'prices' => $prices,
        'cost_usd' => $costUsd,
    ];
}
