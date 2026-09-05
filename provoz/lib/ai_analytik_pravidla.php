<?php
declare(strict_types=1);

const CB_AI_ANALYTIK_PRAVO = 210;
const CB_AI_ANALYTIK_VYCHOZI_MODEL = 'gpt-5.6-terra';
const CB_AI_ANALYTIK_VYCHOZI_NEJASNOST = 'varianty';

function cb_ai_analytik_model_prava(): array
{
    return [
        'gpt-5.6-luna' => 213,
        'gpt-5.6-terra' => 214,
        'gpt-5.6-sol' => 215,
        'gpt-6-astra' => 216,
    ];
}

function cb_ai_analytik_povolene_modely(): array
{
    return array_keys(cb_ai_analytik_model_prava());
}

function cb_ai_analytik_model_je_povoleny(string $model): bool
{
    return in_array($model, cb_ai_analytik_povolene_modely(), true);
}

function cb_ai_analytik_model_ma_pravo(string $model): bool
{
    $prava = cb_ai_analytik_model_prava();
    return isset($prava[$model]) && function_exists('cb_pravo_ma') && cb_pravo_ma($prava[$model]);
}

function cb_ai_analytik_modely_uzivatele(): array
{
    return array_values(array_filter(
        cb_ai_analytik_povolene_modely(),
        static fn(string $model): bool => cb_ai_analytik_model_ma_pravo($model)
    ));
}

function cb_ai_analytik_nazvy_modelu(mysqli $conn): array
{
    $modelPrava = cb_ai_analytik_model_prava();
    $pravoModelu = array_flip($modelPrava);
    $result = $conn->query(
        'SELECT id_pravo, nazev
         FROM cis_prava
         WHERE id_pravo IN (213, 214, 215, 216)'
    );
    if (!($result instanceof mysqli_result)) {
        throw new RuntimeException('Nelze načíst názvy modelů AI analytika.');
    }

    $names = [];
    while ($row = $result->fetch_assoc()) {
        $rightId = (int)$row['id_pravo'];
        if (isset($pravoModelu[$rightId])) {
            $names[$pravoModelu[$rightId]] = (string)$row['nazev'];
        }
    }
    $result->free();

    return $names;
}

function cb_ai_analytik_rezimy_nejasnosti(): array
{
    return ['varianty', 'upresnit'];
}

function cb_ai_analytik_dostupne_roky(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT DISTINCT YEAR(report) AS rok
         FROM objednavky_restia
         WHERE report IS NOT NULL
         ORDER BY rok DESC'
    );
    $years = [];
    while ($row = $result->fetch_assoc()) {
        $year = (int)($row['rok'] ?? 0);
        if ($year > 0) {
            $years[] = $year;
        }
    }
    $result->free();
    return $years;
}

function cb_ai_analytik_normalizovat_roky(mixed $rawYears, array $availableYears): array
{
    if (!is_array($rawYears) || $rawYears === []) {
        throw new CbAiAnalytikUzivatelskaChyba('Vyberte alespoň jeden rok.');
    }
    $requested = [];
    foreach ($rawYears as $rawYear) {
        if (!is_int($rawYear) && !(is_string($rawYear) && preg_match('/^\d{4}$/', $rawYear))) {
            throw new CbAiAnalytikUzivatelskaChyba('Výběr roků nemá platný formát.');
        }
        $requested[(int)$rawYear] = true;
    }
    $years = [];
    foreach ($availableYears as $availableYear) {
        $year = (int)$availableYear;
        if (isset($requested[$year])) {
            $years[] = $year;
            unset($requested[$year]);
        }
    }
    if ($requested !== [] || $years === []) {
        throw new CbAiAnalytikUzivatelskaChyba('Byl vybrán rok, pro který nejsou dostupné objednávky.');
    }
    return $years;
}

function cb_ai_analytik_normalizovat_nejasnost(mixed $rawMode): string
{
    if (!is_string($rawMode) || !in_array($rawMode, cb_ai_analytik_rezimy_nejasnosti(), true)) {
        throw new CbAiAnalytikUzivatelskaChyba('Režim nejasného zadání není platný.');
    }
    return $rawMode;
}

function cb_ai_analytik_ceny_modelu(string $model): array
{
    $ceny = [
        'gpt-5.6-luna' => ['input' => 0.20, 'cached_input' => 0.02, 'output' => 1.20],
        'gpt-5.6-terra' => ['input' => 2.00, 'cached_input' => 0.20, 'output' => 12.00],
        'gpt-5.6-sol' => ['input' => 4.00, 'cached_input' => 0.40, 'output' => 20.00],
        'gpt-6-astra' => ['input' => 10.00, 'cached_input' => 1.00, 'output' => 50.00],
    ];

    if (!isset($ceny[$model])) {
        throw new RuntimeException('Pro model není nastavena cena.');
    }

    return $ceny[$model];
}

final class CbAiAnalytikUzivatelskaChyba extends RuntimeException
{
}

final class CbAiAnalytikAgentLimitChyba extends RuntimeException
{
}

/** Požadavek byl zastaven uživatelem přes ovládání AI analytika. */
final class CbAiAnalytikZrusenoUzivatelem extends RuntimeException
{
}

/** Prohlížeč klienta přerušil stream; běžící práci už nemá smysl dokončovat. */
final class CbAiAnalytikSpojeniPreruseno extends RuntimeException
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
