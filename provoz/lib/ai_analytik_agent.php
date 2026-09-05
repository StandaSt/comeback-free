<?php
declare(strict_types=1);

require_once __DIR__ . '/ai_analytik_openai.php';
require_once __DIR__ . '/../db/db_ai_analytik.php';
require_once __DIR__ . '/../db/db_ai_analytik_audit.php';
require_once __DIR__ . '/../db/db_ai_analytik_usage.php';

function cb_ai_analytik_katalog_soubor(string $filename): string
{
    if ($filename === '' || basename($filename) !== $filename) {
        throw new RuntimeException('Katalog má neplatný název souboru.');
    }
    return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . $filename;
}

function cb_ai_analytik_katalog_nacist_json(string $filename): array
{
    $path = cb_ai_analytik_katalog_soubor($filename);
    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        throw new RuntimeException('Katalog není dostupný: ' . $filename);
    }
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data)) {
        throw new RuntimeException('Katalog nemá platný formát: ' . $filename);
    }
    return ['raw' => $raw, 'data' => $data];
}

function cb_ai_analytik_katalog_manifest(): array
{
    $manifest = cb_ai_analytik_katalog_nacist_json('ai_analytik_katalog_manifest.json');
    if ((int)($manifest['data']['format_version'] ?? 0) !== 1
        || !is_array($manifest['data']['catalogs'] ?? null)
        || trim((string)($manifest['data']['generation_id'] ?? '')) === '') {
        throw new RuntimeException('Manifest katalogů není platný.');
    }
    return $manifest;
}

function cb_ai_analytik_katalogy_nacist(array $manifest, array $requested, array &$loaded): array
{
    $available = is_array($manifest['catalogs'] ?? null) ? $manifest['catalogs'] : [];
    $generationId = (string)($manifest['generation_id'] ?? '');
    $loadedNow = [];
    $objectCount = 0;

    foreach ($requested as $requestedId) {
        $id = trim((string)$requestedId);
        if ($id === '' || !isset($available[$id]) || isset($loaded[$id])) {
            continue;
        }
        $metadata = $available[$id];
        $filename = is_array($metadata) ? (string)($metadata['file'] ?? '') : '';
        $catalog = cb_ai_analytik_katalog_nacist_json($filename);
        if ((string)($catalog['data']['catalog_id'] ?? '') !== $id
            || (string)($catalog['data']['generation_id'] ?? '') !== $generationId
            || !is_array($catalog['data']['objects'] ?? null)) {
            throw new RuntimeException('Katalog ' . $id . ' neodpovídá aktuálnímu manifestu.');
        }
        $loaded[$id] = $catalog;
        $loadedNow[] = $id;
        $objectCount += count($catalog['data']['objects']);
    }

    return [
        'ok' => true,
        'loaded_now' => $loadedNow,
        'loaded_catalogs' => array_keys($loaded),
        'object_count' => $objectCount,
        'message' => $loadedNow === [] ? 'Požadované katalogy už byly načteny.' : 'Požadované katalogy byly načteny.',
    ];
}

function cb_ai_analytik_agent_instructions(
    string $manifestRaw,
    array $loadedCatalogs,
    string $today,
    array $requestedOutput,
    array $context
): string {
    $loadedText = '';
    foreach ($loadedCatalogs as $id => $catalog) {
        $loadedText .= "\n\n=== NAČTENÝ KATALOG " . strtoupper((string)$id) . " ===\n" . rtrim((string)$catalog['raw']);
    }
    if ($loadedText === '') {
        $loadedText = "\n\nZatím není načten žádný podrobný katalog. Podle promptu si zvol potřebný katalog nástrojem load_catalog.";
    }

    $outputText = $requestedOutput['text'] ? 'ano' : 'ne';
    $outputTable = $requestedOutput['tabulka'] ? 'ano' : 'ne';
    $outputChart = $requestedOutput['graf'] ? 'ano' : 'ne';
    $yearsText = implode(', ', array_map('strval', $context['years']));
    $ambiguityInstruction = $context['ambiguity_mode'] === 'upresnit'
        ? 'Pokud má zadání více rozumných výkladů, které mohou vést k podstatně jinému výsledku, nehádej. Vrať response_type=clarification a polož jednu krátkou konkrétní doplňující otázku. Pro takovou otázku nemusíš spouštět SQL.'
        : 'Pokud má zadání více rozumných výkladů, zpracuj nejvýše tři nejrelevantnější a věcně odlišné varianty. Jasně je pojmenuj a neuváděj okrajové nebo samoúčelné možnosti.';

    return <<<TEXT
Jsi interní AI analytik vedení společnosti Comeback. Sám rozhoduješ, jak zadání vyřešit, která data potřebuješ a kolik SQL kroků použiješ.
Databázový účet je fyzicky pouze pro čtení. SQL nikdy nesmí měnit data.
Gateway pouze technicky vykonává tvoje nástroje; neposuzuje obchodní význam promptu ani správnost tvého řešení.
Pro Objednávky a tržby, HR nebo Směny si podle promptu načti jeden nebo více katalogů nástrojem load_catalog. Pro objekty mimo katalogy nebo nově přidané části DB použij inspect_schema.
Katalogy jsou technický popis, nikoli omezení přístupu. Můžeš kombinovat libovolné tabulky a view dostupné read-only účtu.
SQL formuluj účelně. Pokud je výsledek příliš velký, můžeš jej v dalším kroku agregovat, filtrovat nebo stránkovat.
Odpověz česky pouze z výsledků nástrojů.
V textové části odpovědi nepoužívej Markdown ani jeho značky, například `**`, `#` nebo markdownové seznamy.
{$loadedText}

=== MANIFEST DOSTUPNÝCH KATALOGŮ ===
{$manifestRaw}

=== AKTUÁLNÍ POŽADAVEK ===
Dnes je {$today} (Europe/Prague).
Vybrané roky jsou: {$yearsText}. Pro každou časově závislou část analýzy použij pouze tyto roky a správný datový sloupec podle katalogu. U otázky bez časového významu nevytvářej umělý časový filtr.
{$ambiguityInstruction}
Požadované části výstupu: text={$outputText}, tabulka={$outputTable}, graf={$outputChart}.
Pro hotovou odpověď vrať response_type=answer a clarification=null. Pro doplňující otázku vrať response_type=clarification, vyplň clarification a ostatní požadované části nech prázdné nebo null.
Vrať pouze požadované části výstupu. Graf vytvoř jen tehdy, když je pro výsledek věcně vhodný. Řádky tabulky vrať jako pole hodnot přesně ve stejném pořadí jako columns.
TEXT;
}

function cb_ai_analytik_agent_tools(array $manifest): array
{
    $catalogIds = array_values(array_map('strval', array_keys(
        is_array($manifest['catalogs'] ?? null) ? $manifest['catalogs'] : []
    )));
    return [
        [
            'type' => 'function',
            'name' => 'load_catalog',
            'description' => 'Načte jeden nebo více podrobných katalogů, které sis sám vybral podle promptu. Pro kombinovaný dotaz můžeš načíst více katalogů najednou.',
            'strict' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'catalogs' => [
                        'type' => 'array',
                        'items' => ['type' => 'string', 'enum' => $catalogIds],
                    ],
                ],
                'required' => ['catalogs'],
                'additionalProperties' => false,
            ],
        ],
        [
            'type' => 'function',
            'name' => 'inspect_schema',
            'description' => 'Pomocný živý pohled do schématu pro nejasné, nově přidané nebo katalogem nepokryté tabulky a view. Vrátí sloupce, datové typy a cizí klíče.',
            'strict' => true,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'searches' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'tables' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['searches', 'tables'],
                'additionalProperties' => false,
            ],
        ],
        [
            'type' => 'function',
            'name' => 'run_readonly_sql',
            'description' => 'Spustí SQL vytvořené AI přes fyzicky read-only databázový účet. Pokud je překročen technický limit času nebo velikosti výsledku, vrátí přesnou chybu a můžeš zvolit další krok.',
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

function cb_ai_analytik_agent_output_format(array $requestedOutput): array
{
    $cell = [
        'anyOf' => [
            ['type' => 'string'],
            ['type' => 'number'],
            ['type' => 'boolean'],
            ['type' => 'null'],
        ],
    ];
    $properties = [
        'response_type' => ['type' => 'string', 'enum' => ['answer', 'clarification']],
        'clarification' => ['anyOf' => [['type' => 'string'], ['type' => 'null']]],
    ];
    $required = ['response_type', 'clarification'];
    if ($requestedOutput['text']) {
        $properties['text'] = ['type' => 'string'];
        $required[] = 'text';
    }
    if ($requestedOutput['tabulka']) {
        $properties['table'] = [
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
        ];
        $required[] = 'table';
    }
    if ($requestedOutput['graf']) {
        $properties['chart'] = [
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
        ];
        $required[] = 'chart';
    }

    return [
        'type' => 'json_schema',
        'name' => 'ai_analytik_vysledek',
        'strict' => true,
        'schema' => [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
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
    $responseType = (string)($raw['response_type'] ?? 'answer');
    $clarification = trim((string)($raw['clarification'] ?? ''));
    if ($responseType === 'clarification') {
        if ($clarification === '') {
            throw new RuntimeException('AI nevrátila doplňující otázku.');
        }
        return [
            'response_type' => 'clarification',
            'clarification' => $clarification,
            'text' => '',
            'columns' => [],
            'rows' => [],
            'chart' => null,
        ];
    }
    if ($responseType !== 'answer') {
        throw new RuntimeException('AI vrátila neplatný typ odpovědi.');
    }
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
    return [
        'response_type' => 'answer',
        'clarification' => null,
        'text' => $text,
        'columns' => $columns,
        'rows' => $rows,
        'chart' => $chart,
    ];
}

function cb_ai_analytik_agent_spustit(
    int $idAudit,
    string $model,
    string $prompt,
    array $requestedOutput,
    array $context,
    callable $progress,
    ?array $resumeState = null,
    string $clarificationAnswer = ''
): array {
    $lastHeartbeatAt = 0;
    $watchdog = static function () use ($idAudit, $progress, &$lastHeartbeatAt, &$apiCalls, &$sqlCalls): void {
        if (cb_ai_analytik_audit_je_zruseni_pozadovano($idAudit)) {
            throw new CbAiAnalytikZrusenoUzivatelem('Uživatel zrušil probíhající analýzu.');
        }
        if (connection_aborted()) {
            throw new CbAiAnalytikSpojeniPreruseno('Prohlížeč ukončil spojení během zpracování dotazu.');
        }
        $now = time();
        if ($now - $lastHeartbeatAt >= 5) {
            $lastHeartbeatAt = $now;
            $progress('working', 'Franta stále pracuje na aktuálním kroku.', [
                'api_calls' => $apiCalls,
                'sql_count' => $sqlCalls,
                'heartbeat' => true,
            ]);
        }
    };
    $today = (new DateTimeImmutable('today', new DateTimeZone('Europe/Prague')))->format('Y-m-d');
    $manifestFile = cb_ai_analytik_katalog_manifest();
    $manifest = $manifestFile['data'];
    $manifestRaw = rtrim((string)$manifestFile['raw']);
    $loadedCatalogs = [];
    $toolOrder = 0;
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

    if ($resumeState !== null) {
        $savedConversation = $resumeState['conversation'] ?? null;
        $savedCatalogIds = $resumeState['catalogs'] ?? null;
        $savedUsage = $resumeState['usage'] ?? null;
        if (!is_array($savedConversation) || $savedConversation === []
            || !is_array($savedCatalogIds) || !is_array($savedUsage)
            || trim($clarificationAnswer) === '') {
            throw new CbAiAnalytikUzivatelskaChyba('Rozpracovanou analýzu nelze obnovit. Spusťte zadání znovu.');
        }
        $conversation = array_values($savedConversation);
        $conversation[] = ['role' => 'user', 'content' => trim($clarificationAnswer)];
        cb_ai_analytik_katalogy_nacist($manifest, array_values($savedCatalogIds), $loadedCatalogs);
        foreach (array_keys($usageTotal) as $usageKey) {
            if ($usageKey === 'cost_usd') {
                $usageTotal[$usageKey] = (float)($savedUsage[$usageKey] ?? 0.0);
            } else {
                $usageTotal[$usageKey] = (int)($savedUsage[$usageKey] ?? 0);
            }
        }
        $apiCalls = max(0, (int)($resumeState['api_calls'] ?? 0));
        $sqlCalls = max(0, (int)($resumeState['sql_count'] ?? 0));
        $toolOrder = max(0, (int)($resumeState['tool_order'] ?? 0));
        $lastResponseId = (string)($resumeState['last_response_id'] ?? '');
        $nextOpenAiMessage = 'AI navazuje na rozpracovanou analýzu podle upřesnění uživatele.';
    }

    while (true) {
        $watchdog();
        $apiCalls++;
        $progress('openai', $nextOpenAiMessage, ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]);
        $idUsage = cb_ai_analytik_usage_start($idAudit, 'agent_' . $apiCalls, $model);
        $openAiStartedAt = hrtime(true);
        $instructions = cb_ai_analytik_agent_instructions(
            $manifestRaw,
            $loadedCatalogs,
            $today,
            $requestedOutput,
            $context
        );
        $cacheCatalogs = $loadedCatalogs === [] ? 'manifest' : implode('-', array_keys($loadedCatalogs));
        $cacheEnvironment = strtolower((string)($manifest['environment'] ?? 'unknown'));
        try {
            $response = cb_ai_analytik_openai_request([
                'model' => $model,
                'store' => false,
                'prompt_cache_key' => substr(
                    'ai-analytik-v4-' . $model . '-' . $cacheEnvironment . '-' . $cacheCatalogs,
                    0,
                    64
                ),
                'include' => ['reasoning.encrypted_content'],
                'instructions' => $instructions,
                'input' => $conversation,
                'tools' => cb_ai_analytik_agent_tools($manifest),
                'tool_choice' => 'auto',
                'parallel_tool_calls' => false,
                'text' => ['format' => cb_ai_analytik_agent_output_format($requestedOutput)],
            ], $watchdog);
            cb_ai_analytik_usage_finish($idUsage, $response);
        } catch (Throwable $error) {
            cb_ai_analytik_usage_error(
                $idUsage,
                $error,
                (int)((hrtime(true) - $openAiStartedAt) / 1_000_000)
            );
            throw $error;
        }
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
            $normalized = cb_ai_analytik_agent_normalizovat_vysledek($raw, $requestedOutput);
            $progress(
                $normalized['response_type'] === 'clarification' ? 'awaiting_clarification' : 'formatting',
                $normalized['response_type'] === 'clarification'
                    ? 'AI žádá upřesnění: ' . $normalized['clarification']
                    : 'Připravuji výsledek pro zobrazení.',
                ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]
            );
            $result = $normalized + [
                'usage' => $usageTotal,
                'api_calls' => $apiCalls,
                'sql_count' => $sqlCalls,
                'catalogs' => array_keys($loadedCatalogs),
                'last_response_id' => $lastResponseId,
            ];
            if ($normalized['response_type'] === 'clarification') {
                $result['continuation_state'] = [
                    'version' => 1,
                    'conversation' => $conversation,
                    'catalogs' => array_keys($loadedCatalogs),
                    'usage' => $usageTotal,
                    'api_calls' => $apiCalls,
                    'sql_count' => $sqlCalls,
                    'tool_order' => $toolOrder,
                    'last_response_id' => $lastResponseId,
                ];
            }
            return $result;
        }

        foreach ($functionCalls as $call) {
            $name = (string)($call['name'] ?? '');
            $callId = (string)($call['call_id'] ?? '');
            $arguments = json_decode((string)($call['arguments'] ?? '{}'), true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($arguments) || $callId === '') {
                throw new RuntimeException('AI předala neplatné volání nástroje.');
            }

            if ($name === 'load_catalog') {
                $requestedCatalogs = is_array($arguments['catalogs'] ?? null) ? $arguments['catalogs'] : [];
                $requestedCatalogs = array_values(array_unique(array_map('strval', $requestedCatalogs)));
                $labels = [];
                foreach ($requestedCatalogs as $catalogId) {
                    $catalogMeta = $manifest['catalogs'][$catalogId] ?? null;
                    if (is_array($catalogMeta)) {
                        $labels[] = (string)($catalogMeta['label'] ?? $catalogId);
                    }
                }
                $progress(
                    'catalog',
                    'AI zvolila katalog: ' . ($labels !== [] ? implode(', ', $labels) : 'neplatný požadavek') . '.',
                    ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls, 'catalogs' => $requestedCatalogs]
                );
                $toolOrder++;
                $catalogStartedAt = hrtime(true);
                $idCatalogAudit = cb_ai_analytik_tool_audit_start(
                    $idAudit,
                    $toolOrder,
                    'load_catalog',
                    ['catalogs' => $requestedCatalogs]
                );
                try {
                    $toolResult = cb_ai_analytik_katalogy_nacist($manifest, $requestedCatalogs, $loadedCatalogs);
                    cb_ai_analytik_tool_audit_finish($idCatalogAudit, [
                        'status' => 'completed',
                        'duration_ms' => (int)((hrtime(true) - $catalogStartedAt) / 1_000_000),
                        'result_count' => (int)$toolResult['object_count'],
                    ]);
                } catch (Throwable $error) {
                    cb_ai_analytik_tool_audit_finish($idCatalogAudit, [
                        'status' => 'error',
                        'duration_ms' => (int)((hrtime(true) - $catalogStartedAt) / 1_000_000),
                        'error_type' => get_class($error),
                        'error_message' => $error->getMessage(),
                    ]);
                    throw $error;
                }
                $progress(
                    'catalog_done',
                    'Katalog načten: ' . (int)$toolResult['object_count'] . ' databázových objektů.',
                    [
                        'api_calls' => $apiCalls,
                        'sql_count' => $sqlCalls,
                        'catalogs' => array_keys($loadedCatalogs),
                        'object_count' => (int)$toolResult['object_count'],
                    ]
                );
                $nextOpenAiMessage = 'AI studuje zvolený katalog a připravuje potřebné SQL kroky.';
            } elseif ($name === 'inspect_schema') {
                $searches = is_array($arguments['searches'] ?? null) ? $arguments['searches'] : [];
                $tables = is_array($arguments['tables'] ?? null) ? $arguments['tables'] : [];
                $searchTerms = array_values(array_filter(array_map('strval', $searches), static fn(string $search): bool => trim($search) !== ''));
                $tableNames = array_values(array_filter(array_map('strval', $tables), static fn(string $table): bool => $table !== ''));
                $parts = [];
                if ($tableNames !== []) {
                    $parts[] = 'tabulky: ' . implode(', ', $tableNames);
                }
                if ($searchTerms !== []) {
                    $parts[] = 'hledané výrazy: ' . implode(', ', $searchTerms);
                }
                $message = $parts === []
                    ? 'Načítám seznam dostupných tabulek a view.'
                    : 'V jednom kroku zkoumám DB schéma – ' . implode('; ', $parts) . '.';
                $progress('schema', $message, ['api_calls' => $apiCalls, 'sql_count' => $sqlCalls]);
                $toolOrder++;
                $schemaStartedAt = hrtime(true);
                $idSchemaAudit = cb_ai_analytik_tool_audit_start(
                    $idAudit,
                    $toolOrder,
                    'inspect_schema',
                    ['searches' => $searchTerms, 'tables' => $tableNames]
                );
                try {
                    $toolResult = cb_ai_analytik_schema_prozkoumat($searches, $tables);
                    cb_ai_analytik_tool_audit_finish($idSchemaAudit, [
                        'status' => 'completed',
                        'duration_ms' => (int)((hrtime(true) - $schemaStartedAt) / 1_000_000),
                        'result_count' => count(is_array($toolResult['tables'] ?? null) ? $toolResult['tables'] : []),
                    ]);
                } catch (Throwable $error) {
                    cb_ai_analytik_tool_audit_finish($idSchemaAudit, [
                        'status' => 'error',
                        'duration_ms' => (int)((hrtime(true) - $schemaStartedAt) / 1_000_000),
                        'error_type' => get_class($error),
                        'error_message' => $error->getMessage(),
                    ]);
                    throw $error;
                }
                $nextOpenAiMessage = 'AI vyhodnocuje schéma a připravuje datový SQL dotaz.';
            } elseif ($name === 'run_readonly_sql') {
                $sqlCalls++;
                $purpose = trim((string)($arguments['purpose'] ?? ''));
                if ($purpose === '') {
                    $purpose = 'Analytický dotaz';
                }
                $progress(
                    'sql',
                    'Spouštím SQL dotaz č. ' . $sqlCalls . ': ' . $purpose . '.',
                    [
                        'api_calls' => $apiCalls,
                        'sql_count' => $sqlCalls,
                        'sql_index' => $sqlCalls,
                        'purpose' => $purpose,
                        'sql' => (string)($arguments['sql'] ?? ''),
                    ]
                );
                $sql = (string)($arguments['sql'] ?? '');
                $toolResult = cb_ai_analytik_sql_spustit(
                    $idAudit,
                    $sqlCalls,
                    $purpose,
                    $sql,
                    $watchdog
                );
                if (($toolResult['ok'] ?? false) === true) {
                    $progress(
                        'sql_done',
                        'SQL dotaz dokončen: ' . (int)$toolResult['row_count'] . ' řádků za '
                            . number_format(((int)$toolResult['duration_ms']) / 1000, 1, ',', ' ') . ' s.',
                        [
                            'api_calls' => $apiCalls,
                            'sql_count' => $sqlCalls,
                            'sql_index' => $sqlCalls,
                            'row_count' => (int)$toolResult['row_count'],
                            'result_bytes' => (int)$toolResult['result_bytes'],
                            'duration_ms' => (int)$toolResult['duration_ms'],
                        ]
                    );
                    $nextOpenAiMessage = 'AI vyhodnocuje výsledek SQL a rozhoduje, zda potřebuje další data.';
                } elseif (($toolResult['error_type'] ?? '') === 'sql_error') {
                    $progress(
                        'sql_error',
                        'SQL dotaz obsahuje chybu. AI dostane chybu a může dotaz opravit.',
                        [
                            'api_calls' => $apiCalls,
                            'sql_count' => $sqlCalls,
                            'sql_index' => $sqlCalls,
                            'error' => (string)($toolResult['message'] ?? ''),
                        ]
                    );
                    $nextOpenAiMessage = 'AI opravuje SQL podle vrácené chyby; podle potřeby znovu ověří schéma.';
                } else {
                    $progress(
                        'sql_too_large',
                        'Výsledek SQL překročil nastavený limit. AI může dotaz upravit nebo uživateli vrátit přesnou informaci.',
                        [
                            'api_calls' => $apiCalls,
                            'sql_count' => $sqlCalls,
                            'sql_index' => $sqlCalls,
                            'result_bytes' => (int)($toolResult['result_bytes_at_least'] ?? 0),
                            'byte_limit' => (int)($toolResult['byte_limit'] ?? 0),
                        ]
                    );
                    $nextOpenAiMessage = 'AI upravuje příliš rozsáhlý SQL výsledek pomocí agregace nebo přesnějšího filtru.';
                }
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
