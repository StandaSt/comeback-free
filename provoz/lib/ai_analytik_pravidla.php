<?php
declare(strict_types=1);

const CB_AI_ANALYTIK_PRAVO = 210;
const CB_AI_ANALYTIK_VYCHOZI_MODEL = 'gpt-5.6-terra';

function cb_ai_analytik_povolene_modely(): array
{
    return [
        'gpt-5.6-luna',
        'gpt-5.6-terra',
        'gpt-5.6-sol',
    ];
}

function cb_ai_analytik_model_je_povoleny(string $model): bool
{
    return in_array($model, cb_ai_analytik_povolene_modely(), true);
}

function cb_ai_analytik_ceny_modelu(string $model): array
{
    $ceny = [
        'gpt-5.6-luna' => ['input' => 0.20, 'cached_input' => 0.02, 'output' => 1.20],
        'gpt-5.6-terra' => ['input' => 2.00, 'cached_input' => 0.20, 'output' => 12.00],
        'gpt-5.6-sol' => ['input' => 4.00, 'cached_input' => 0.40, 'output' => 20.00],
    ];

    if (!isset($ceny[$model])) {
        throw new RuntimeException('Pro model není nastavena cena.');
    }

    return $ceny[$model];
}

final class CbAiAnalytikUzivatelskaChyba extends RuntimeException
{
}

function cb_ai_analytik_ma_pravo(): bool
{
    $stavy = $_SESSION['prava_stav'] ?? null;
    if (!is_array($stavy) || !array_key_exists(CB_AI_ANALYTIK_PRAVO, $stavy)) {
        return false;
    }

    return function_exists('cb_pravo_ma') && cb_pravo_ma(CB_AI_ANALYTIK_PRAVO);
}

function cb_ai_analytik_csrf_token(): string
{
    if (empty($_SESSION['ai_analytik_csrf'])) {
        $_SESSION['ai_analytik_csrf'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['ai_analytik_csrf'];
}
