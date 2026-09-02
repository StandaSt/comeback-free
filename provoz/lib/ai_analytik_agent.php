<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_analytik_openai.php';
require_once __DIR__ . '/../db/db_ai_analytik.php';
require_once __DIR__ . '/../db/db_ai_analytik_usage.php';

function cb_ai_analytik_agent_tools(): array
{
    return [
        [
            'type' => 'function',
            'name' => 'find_schema',
            'description' => 'Vyhledá tabulky a view podle názvu tabulky, sloupce nebo komentáře. Prázdné hledání vrátí seznam všech dostupných objektů.',
            'strict' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => ['search' => ['type' => 'string']],
                'required' => ['search'],
                'additionalProperties' => false,
            ],
        ],
        [
            'type' => 'function',
            'name' => 'describe_schema',
            'description' => 'Vrátí povolené sloupce, datové typy a cizí klíče vybraných tabulek nebo view.',
            'strict' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'tables' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['tables'],
                'additionalProperties' => false,
            ],
        ],
        [
            'type' => 'function',
            'name' => 'run_readonly_sql',
            'description' => 'Po serverové bezpečnostní kontrole spustí jeden read-only SELECT nebo WITH...SELECT a vrátí jeho výsledek.',
            'strict' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'purpose' => ['type' => 'string'],
                    'sql' => ['type' => 'string'],
                ],
                'required' => ['purpose', 'sql'],
                'additionalProperties' => false,
            ],
        ],
    ];
}

function cb_ai_analytik_agent_output_format(): array
{
    $cell = [
        'anyOf' => [
            ['type' => 'string'],
            ['type' => 'number'],
            ['type' => 'boolean'],
            ['type' => 'null'],
        ],
    ];
    return [
        'type' => 'json_schema',
        'name' => 'ai_analytik_vysledek',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
                'table' => [
                    'anyOf' => [
                        [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'columns' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'key' => ['type' => 'string'],
                                            'label' => ['type' => 'string'],
                                            'type' => ['type' => 'string', 'enum' => ['text', 'number', 'currency', 'date', 'datetime']],
                                        ],
                                        'required' => ['key', 'label', 'type'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                                'rows' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'array', 'items' => $cell],
                                ],
                            ],
                            'required' => ['title', 'columns', 'rows'],
                            'additionalProperties' => false,
                        ],
                        ['type' => 'null'],
                    ],
                ],
                'chart' => [
                    'anyOf' => [
                        [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'type' => ['type' => 'string', 'enum' => ['bar', 'line', 'pie']],
                                'labels' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'series' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'unit' => ['type' => 'string'],
                                            'data' => [
                                                'type' => 'array',
                                                'items' => ['anyOf' => [['type' => 'number'], ['type' => 'null']]],
                                            ],
                                        ],
                                        'required' => ['name', 'unit', 'data'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['title', 'type', 'labels', 'series'],
                            'additionalProperties' => false,
                        ],
                        ['type' => 'null'],
                    ],
                ],
            ],
            'required' => ['text', 'table', 'chart'],
            'additionalProperties' => false,
        ],
    ];
}

function cb_ai_analytik_agent_usage_add(array &$total, array $response): void
{
    $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];
    foreach (['input_tokens', 'cached_input_tokens', 'output_tokens', 'reasoning_tokens', 'total_tokens'] as $key) {
        $total[$key] += (int)($usage[$key] ?? 0);
    }
    $total['openai_duration_ms'] += (int)($response['duration_ms'] ?? 0);
    $total['cost_usd'] += (float)($response['cost_usd'] ?? 0);
}

function cb_ai_analytik_agent_normalizovat_vysledek(array $raw, array $requestedOutput): array
{
    $text = $requestedOutput['text'] ? trim((string)($raw['text'] ?? '')) : '';
    $columns = [];
    $rows = [];
    $table = is_array($raw['table'] ?? null) ? $raw['table'] : null;
    if ($requestedOutput['tabulka'] && is_array($table)) {
        foreach (is_array($table['columns'] ?? null) ? $table['columns'] : [] as $index => $column) {
            if (!is_array($column)) {
                continue;
            }
            $key = trim((string)($column['key'] ?? ''));
            if ($key === '' || isset($columns[$key])) {
                $key = 'column_' . ((int)$index + 1);
            }
            $type = (string)($column['type'] ?? 'text');
            if (!in_array($type, ['text', 'number', 'currency', 'date', 'datetime'], true)) {
                $type = 'text';
            }
            $columns[$key] = ['key' => $key, 'label' => (string)($column['label'] ?? $key), 'type' => $type];
        }
        foreach (is_array($table['rows'] ?? null) ? $table['rows'] : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalizedRow = [];
            foreach (array_values($columns) as $index => $column) {
                $normalizedRow[$column['key']] = $row[$index] ?? null;
            }
            $rows[] = $normalizedRow;
        }
        $columns = array_values($columns);
    }

    $chart = null;
    $rawChart = is_array($raw['chart'] ?? null) ? $raw['chart'] : null;
    if ($requestedOutput['graf'] && is_array($rawChart)) {
        $labels = array_map('strval', is_array($rawChart['labels'] ?? null) ? array_values($rawChart['labels']) : []);
        $series = [];
        foreach (is_array($rawChart['series'] ?? null) ? $rawChart['series'] : [] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $data = [];
            foreach (is_array($item['data'] ?? null) ? $item['data'] : [] as $value) {
                $data[] = $value === null ? null : (float)$value;
            }
            $series[] = [
                'id' => 'series_' . ((int)$index + 1),
                'name' => (string)($item['name'] ?? ''),
                'unit' => (string)($item['unit'] ?? ''),
                'data' => $data,
            ];
        }
        if ($labels !== [] && $series !== []) {
            $chart = [
                'kind' => 'ai_analytik',
                'title' => (string)($rawChart['title'] ?? ''),
                'type' => in_array((string)($rawChart['type'] ?? ''), ['bar', 'line', 'pie'], true)
                    ? (string)$rawChart['type'] : 'bar',
                'labels' => $labels,
                'series' => $series,
            ];
        }
    }

    if ($text === '' && $columns === [] && $chart === null) {
        throw new RuntimeException('AI nevytvořila žádný z požadovaných výstupů.');
    }
    return ['text' => $text, 'columns' => $columns, 'rows' => $rows, 'chart' => $chart];
}

function cb_ai_analytik_agent_spustit(
    int $idAudit,
    string $model,
    string $prompt,
    array $requestedOutput,
    callable $progress
): array {
    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    $outputText = $requestedOutput['text'] ? 'ano' : 'ne';
    $outputTable = $requestedOutput['tabulka'] ? 'ano' : 'ne';
    $outputChart = $requestedOutput['graf'] ? 'ano' : 'ne';
    $instructions = <<<TEXT
Jsi interní AI analytik vedení společnosti Comeback. Dnes je {$today} (Europe/Prague).
Máš globální read-only přístup k povoleným datům všech firem a všech poboček v informačním systému.
Nejsi omezen na žádnou obchodní oblast. Můžeš analyzovat provoz, objednávky, tržby, HR, mzdy, směny, uživatele i technická provozní data.
Nástroje find_schema a describe_schema použij jen tehdy, když skutečně neznáš potřebnou tabulku, view, sloupec nebo vazbu. Nedělej povinné průzkumné ani kontrolní dotazy před každou analýzou.
Připraveným view v_ai_* důvěřuj a nespojuj je znovu s jejich zdrojovými tabulkami jen kvůli ověření. Pokud je dotaz jasný, použij co nejméně SQL dotazů, ideálně jeden výsledný agregační SELECT. Neopakuj samostatný součtový dotaz, pokud lze součet získat z již vráceného výsledku.
SQL nikdy nesmí měnit data. Dotazy formuluj úsporně, filtruj období přímo ve čteném objektu a agreguj v databázi; neposílej si zbytečně každý zdrojový řádek, pokud uživatel požaduje souhrn.
Odpověz česky pouze z ověřených výsledků nástrojů. Pokud data nestačí nebo je dotaz nejednoznačný, popiš přesně proč.
Požadované části výstupu: text={$outputText}, tabulka={$outputTable}, graf={$outputChart}.
Nevyžádanou část vrať jako prázdný text nebo null. Graf vytvoř jen tehdy, když je pro výsledek věcně vhodný. Řádky tabulky vrať jako pole hodnot přesně ve stejném pořadí jako columns.
TEXT;

    $conversation = [['role' => 'user', 'content' => $prompt]];
    $usageTotal = [
        'input_tokens' => 0,
        'cached_input_tokens' => 0,
        'output_tokens' => 0,
        'reasoning_tokens' => 0,
        'total_tokens' => 0,
        'openai_duration_ms' => 0,
        'cost_usd' => 0.0,
    ];
    $apiCalls = 0;
    $sqlCalls = 0;
    $lastResponseId = '';
    $nextOpenAiMessage = 'AI analyzuje zadání a určuje, která data potřebuje.';

    while (true) {
        if (connection_aborted()) {
            throw new RuntimeException('Uživatel ukončil spojení během zpracování dotazu.');
        }
        $apiCalls++;
        $progress('openai', $nextOpenAiMessage, ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]);
        $response = cb_ai_analytik_openai_request([
            'model' => $model,
            'store' => false,
            'reasoning' => ['effort' => 'low'],
            'include' => ['reasoning.encrypted_content'],
            'instructions' => $instructions,
            'input' => $conversation,
            'tools' => cb_ai_analytik_agent_tools(),
            'tool_choice' => 'auto',
            'parallel_tool_calls' => false,
            'text' => ['format' => cb_ai_analytik_agent_output_format()],
        ]);
        cb_ai_analytik_usage_zapsat($idAudit, 'agent_' . $apiCalls, $response);
        cb_ai_analytik_agent_usage_add($usageTotal, $response);
        $lastResponseId = (string)$response['id'];

        foreach ($response['output'] as $item) {
            if (is_array($item)) {
                $conversation[] = $item;
            }
        }

        $functionCalls = [];
        foreach ($response['output'] as $item) {
            if (is_array($item) && (string)($item['type'] ?? '') === 'function_call') {
                $functionCalls[] = $item;
            }
        }

        if ($functionCalls === []) {
            $text = trim((string)$response['text']);
            if ($text === '') {
                throw new RuntimeException('OpenAI API nevrátilo konečný výsledek ani požadavek na nástroj.');
            }
            $raw = json_decode($text, true, 128, JSON_THROW_ON_ERROR);
            if (!is_array($raw)) {
                throw new RuntimeException('AI vrátila neplatný formát výsledku.');
            }
            $progress('formatting', 'Připravuji výsledek pro zobrazení.', ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]);
            return cb_ai_analytik_agent_normalizovat_vysledek($raw, $requestedOutput) + [
                'usage' => $usageTotal,
                'api_calls' => $apiCalls,
                'sql_count' => $sqlCalls,
                'last_response_id' => $lastResponseId,
            ];
        }

        foreach ($functionCalls as $call) {
            $name = (string)($call['name'] ?? '');
            $callId = (string)($call['call_id'] ?? '');
            $arguments = json_decode((string)($call['arguments'] ?? '{}'), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($arguments) || $callId === '') {
                throw new RuntimeException('AI předala neplatné volání nástroje.');
            }

            if ($name === 'find_schema') {
                $search = trim((string)($arguments['search'] ?? ''));
                $message = $search === ''
                    ? 'Načítám seznam dostupných tabulek a view.'
                    : 'Hledám v DB schématu: „' . $search . '“.';
                $progress('schema', $message, ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]);
                $toolResult = cb_ai_analytik_schema_hledat($search);
                $nextOpenAiMessage = 'AI vyhodnocuje nalezené tabulky a vybírá potřebné sloupce.';
            } elseif ($name === 'describe_schema') {
                $tables = is_array($arguments['tables'] ?? null) ? $arguments['tables'] : [];
                $tableNames = array_values(array_filter(array_map('strval', $tables), static fn(string $table): bool => $table !== ''));
                $message = $tableNames === []
                    ? 'Čtu sloupce a vazby vybraných tabulek.'
                    : 'Čtu sloupce a vazby tabulek: ' . implode(', ', $tableNames) . '.';
                $progress('schema', $message, ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]);
                $toolResult = cb_ai_analytik_schema_popsat(
                    $tables
                );
                $nextOpenAiMessage = 'AI vyhodnocuje schéma a připravuje datový SQL dotaz.';
            } elseif ($name === 'run_readonly_sql') {
                $sqlCalls++;
                $purpose = trim((string)($arguments['purpose'] ?? ''));
                if ($purpose === '') {
                    $purpose = 'Analytický dotaz';
                }
                $progress(
                    'sql',
                    'Ověřuji a spouštím SQL dotaz č. ' . $sqlCalls . ': ' . $purpose . '.',
                    ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]
                );
                $toolResult = cb_ai_analytik_sql_spustit(
                    $idAudit,
                    $sqlCalls,
                    $purpose,
                    (string)($arguments['sql'] ?? '')
                );
                $progress(
                    'sql_done',
                    'SQL dotaz dokončen: ' . (int)$toolResult['row_count'] . ' řádků za '
                        . number_format(((int)$toolResult['duration_ms']) / 1000, 1, ',', ' ') . ' s.',
                    ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]
                );
                $nextOpenAiMessage = 'AI vyhodnocuje výsledek SQL a rozhoduje, zda potřebuje další data.';
            } else {
                throw new RuntimeException('AI požádala o neznámý nástroj: ' . $name);
            }

            $conversation[] = [
                'type' => 'function_call_output',
                'call_id' => $callId,
                'output' => json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ];
        }
    }
}
