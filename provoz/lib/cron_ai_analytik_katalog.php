<?php
declare(strict_types=1);

/*
 * Denní generátor katalogů Chytrého Franty.
 *
 * Lokálně: php cron_ai_analytik_katalog.php
 * Server:  php cron_ai_analytik_katalog.php --server
 *
 * Skript pouze čte databázovou strukturu a zapisuje JSON soubory mimo webový root.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../common/config/secrets.php';

function cb_ai_katalog_rows(mysqli $conn, string $sql): array
{
    $result = $conn->query($sql);
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
    return $rows;
}

function cb_ai_katalog_object_selected(string $name, array $definition): bool
{
    if (in_array($name, $definition['exclude'], true)) {
        return false;
    }
    if (in_array($name, $definition['objects'], true)) {
        return true;
    }
    foreach ($definition['prefixes'] as $prefix) {
        if (str_starts_with($name, (string)$prefix)) {
            return true;
        }
    }
    return false;
}

function cb_ai_katalog_json(array $data): string
{
    return json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
    ) . "\n";
}

function cb_ai_katalog_write(string $path, string $content): string
{
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(6));
    if (file_put_contents($tmp, $content, LOCK_EX) !== strlen($content)) {
        @unlink($tmp);
        throw new RuntimeException('Katalog nelze zapsat: ' . basename($path));
    }
    json_decode((string)file_get_contents($tmp), true, 512, JSON_THROW_ON_ERROR);
    @chmod($tmp, 0640);
    return $tmp;
}

function cb_ai_katalog_replace(string $tmp, string $path): void
{
    if (PHP_OS_FAMILY !== 'Windows') {
        if (!rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException('Katalog nelze aktivovat: ' . basename($path));
        }
        return;
    }

    $backup = $path . '.previous';
    @unlink($backup);
    if (is_file($path) && !rename($path, $backup)) {
        @unlink($tmp);
        throw new RuntimeException('Původní katalog nelze bezpečně odložit: ' . basename($path));
    }
    if (!rename($tmp, $path)) {
        if (is_file($backup)) {
            @rename($backup, $path);
        }
        @unlink($tmp);
        throw new RuntimeException('Katalog nelze aktivovat: ' . basename($path));
    }
    @unlink($backup);
}

$isServer = in_array('--server', $argv, true);
$environment = $isServer ? 'SERVER' : 'LOCAL';
$dbKey = $isServer ? 'server' : 'local';
$dbConfig = $SECRETS['db'][$dbKey] ?? null;
if (!is_array($dbConfig)) {
    throw new RuntimeException('Chybí DB konfigurace pro prostředí ' . $environment . '.');
}

$host = (string)($dbConfig['host'] ?? '');
$user = (string)($dbConfig['user'] ?? '');
$password = (string)($dbConfig['pass'] ?? '');
$database = (string)($dbConfig['name'] ?? '');
$port = (int)($dbConfig['port'] ?? 3306);
if ($host === '' || $user === '' || $database === '' || $port <= 0) {
    throw new RuntimeException('DB konfigurace katalogu není úplná.');
}

$definitions = require __DIR__ . '/../../common/config/ai_analytik_katalogy.php';
if (!is_array($definitions) || $definitions === []) {
    throw new RuntimeException('Definice katalogů nejsou dostupné.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($host, $user, $password, $database, $port);
$conn->set_charset('utf8mb4');
$conn->query('SET SESSION TRANSACTION READ ONLY');
$conn->query('START TRANSACTION READ ONLY');

try {
    $server = cb_ai_katalog_rows(
        $conn,
        "SELECT VERSION() AS version, @@sql_mode AS sql_mode, @@time_zone AS time_zone,
                @@system_time_zone AS system_time_zone, @@character_set_server AS character_set,
                @@collation_server AS collation, DATABASE() AS database_name"
    )[0];
    $tableRows = cb_ai_katalog_rows(
        $conn,
        "SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH,
                TABLE_COLLATION, TABLE_COMMENT
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
         ORDER BY TABLE_NAME"
    );
    $columnRows = cb_ai_katalog_rows(
        $conn,
        "SELECT TABLE_NAME, ORDINAL_POSITION, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE,
                COLUMN_DEFAULT, COLUMN_KEY, EXTRA, CHARACTER_SET_NAME, COLLATION_NAME, COLUMN_COMMENT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         ORDER BY TABLE_NAME, ORDINAL_POSITION"
    );
    $indexRows = cb_ai_katalog_rows(
        $conn,
        "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME,
                COLLATION, SUB_PART, INDEX_TYPE
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
         ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX"
    );
    $foreignKeyRows = cb_ai_katalog_rows(
        $conn,
        "SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME,
                k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                r.UPDATE_RULE, r.DELETE_RULE
         FROM information_schema.KEY_COLUMN_USAGE k
         LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
          AND r.TABLE_NAME = k.TABLE_NAME
          AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
         WHERE k.TABLE_SCHEMA = DATABASE()
           AND k.REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION"
    );
    $viewRows = cb_ai_katalog_rows(
        $conn,
        "SELECT TABLE_NAME, VIEW_DEFINITION, CHECK_OPTION, IS_UPDATABLE, SECURITY_TYPE
         FROM information_schema.VIEWS
         WHERE TABLE_SCHEMA = DATABASE()
         ORDER BY TABLE_NAME"
    );
    try {
        $descriptionRows = cb_ai_katalog_rows(
            $conn,
            'SELECT object_name, column_name, description FROM ai_analytik_schema_popis ORDER BY object_name, column_name'
        );
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() !== 1146) {
            throw $error;
        }
        $descriptionRows = [];
    }
    $conn->rollback();
} finally {
    $conn->close();
}

$descriptions = [];
foreach ($descriptionRows as $row) {
    $descriptions[(string)$row['object_name']][(string)$row['column_name']] = trim((string)$row['description']);
}

$allObjects = [];
$statistics = [];
foreach ($tableRows as $row) {
    $name = (string)$row['TABLE_NAME'];
    $comment = trim((string)($descriptions[$name][''] ?? ''));
    if ($comment === '') {
        $comment = trim((string)$row['TABLE_COMMENT']);
        if (strcasecmp($comment, 'VIEW') === 0) {
            $comment = '';
        }
    }
    $allObjects[$name] = [
        'name' => $name,
        'kind' => (string)$row['TABLE_TYPE'] === 'VIEW' ? 'view' : 'table',
        'engine' => $row['ENGINE'],
        'collation' => $row['TABLE_COLLATION'],
        'comment' => $comment,
        'columns' => [],
        'primary_key' => [],
        'foreign_keys' => [],
        'referenced_by' => [],
        'indexes' => [],
    ];
    $statistics[$name] = [
        'approx_rows' => $row['TABLE_ROWS'] === null ? null : (int)$row['TABLE_ROWS'],
        'data_bytes' => $row['DATA_LENGTH'] === null ? null : (int)$row['DATA_LENGTH'],
        'index_bytes' => $row['INDEX_LENGTH'] === null ? null : (int)$row['INDEX_LENGTH'],
    ];
}

foreach ($columnRows as $row) {
    $table = (string)$row['TABLE_NAME'];
    if (!isset($allObjects[$table])) {
        continue;
    }
    $column = (string)$row['COLUMN_NAME'];
    $comment = trim((string)($descriptions[$table][$column] ?? ''));
    if ($comment === '') {
        $comment = trim((string)$row['COLUMN_COMMENT']);
    }
    $allObjects[$table]['columns'][] = [
        'position' => (int)$row['ORDINAL_POSITION'],
        'name' => $column,
        'type' => (string)$row['COLUMN_TYPE'],
        'nullable' => (string)$row['IS_NULLABLE'] === 'YES',
        'default' => $row['COLUMN_DEFAULT'],
        'key' => (string)$row['COLUMN_KEY'],
        'extra' => (string)$row['EXTRA'],
        'character_set' => $row['CHARACTER_SET_NAME'],
        'collation' => $row['COLLATION_NAME'],
        'comment' => $comment,
    ];
}

$indexes = [];
foreach ($indexRows as $row) {
    $table = (string)$row['TABLE_NAME'];
    $indexName = (string)$row['INDEX_NAME'];
    $indexes[$table][$indexName] ??= [
        'name' => $indexName,
        'unique' => (int)$row['NON_UNIQUE'] === 0,
        'type' => (string)$row['INDEX_TYPE'],
        'columns' => [],
    ];
    $indexes[$table][$indexName]['columns'][] = [
        'name' => $row['COLUMN_NAME'],
        'order' => $row['COLLATION'],
        'prefix_length' => $row['SUB_PART'] === null ? null : (int)$row['SUB_PART'],
    ];
}
foreach ($indexes as $table => $tableIndexes) {
    if (!isset($allObjects[$table])) {
        continue;
    }
    foreach ($tableIndexes as $index) {
        if ($index['name'] === 'PRIMARY') {
            $allObjects[$table]['primary_key'] = array_values(array_filter(array_map(
                static fn(array $column): mixed => $column['name'],
                $index['columns']
            ), static fn(mixed $name): bool => $name !== null));
        }
        $allObjects[$table]['indexes'][] = $index;
    }
}

foreach ($foreignKeyRows as $row) {
    $owner = (string)$row['TABLE_NAME'];
    $referenced = (string)$row['REFERENCED_TABLE_NAME'];
    $outgoing = [
        'name' => (string)$row['CONSTRAINT_NAME'],
        'column' => (string)$row['COLUMN_NAME'],
        'references_table' => $referenced,
        'references_column' => (string)$row['REFERENCED_COLUMN_NAME'],
        'update_rule' => (string)($row['UPDATE_RULE'] ?? ''),
        'delete_rule' => (string)($row['DELETE_RULE'] ?? ''),
    ];
    if (isset($allObjects[$owner])) {
        $allObjects[$owner]['foreign_keys'][] = $outgoing;
    }
    if (isset($allObjects[$referenced])) {
        $allObjects[$referenced]['referenced_by'][] = [
            'table' => $owner,
            'constraint' => (string)$row['CONSTRAINT_NAME'],
            'column' => (string)$row['COLUMN_NAME'],
            'references_column' => (string)$row['REFERENCED_COLUMN_NAME'],
        ];
    }
}

foreach ($viewRows as $row) {
    $name = (string)$row['TABLE_NAME'];
    if (!isset($allObjects[$name])) {
        continue;
    }
    $allObjects[$name]['view'] = [
        'definition' => (string)$row['VIEW_DEFINITION'],
        'check_option' => (string)$row['CHECK_OPTION'],
        'updatable' => (string)$row['IS_UPDATABLE'] === 'YES',
        'security_type' => (string)$row['SECURITY_TYPE'],
    ];
}

$generatedAt = (new DateTimeImmutable('now', new DateTimeZone('Europe/Prague')))->format(DATE_ATOM);
$generationId = gmdate('YmdHis') . '-' . substr(hash('sha256', cb_ai_katalog_json($allObjects)), 0, 12);
$catalogPayloads = [];
$manifestCatalogs = [];
foreach ($definitions as $id => $definition) {
    $selectedObjects = [];
    $selectedStatistics = [];
    foreach ($allObjects as $name => $object) {
        if (!cb_ai_katalog_object_selected($name, $definition)) {
            continue;
        }
        $selectedObjects[$name] = $object;
        $selectedStatistics[$name] = $statistics[$name];
    }
    $filename = 'ai_analytik_katalog_' . $id . '.json';
    $catalogPayloads[$filename] = [
        'format_version' => 1,
        'generation_id' => $generationId,
        'catalog_id' => $id,
        'label' => (string)$definition['label'],
        'description' => (string)$definition['description'],
        'environment' => $environment,
        'database' => (string)$server['database_name'],
        'server' => [
            'version' => (string)$server['version'],
            'sql_mode' => (string)$server['sql_mode'],
            'time_zone' => (string)$server['time_zone'],
            'system_time_zone' => (string)$server['system_time_zone'],
            'character_set' => (string)$server['character_set'],
            'collation' => (string)$server['collation'],
        ],
        'notes' => array_values($definition['notes']),
        'objects' => $selectedObjects,
        'statistics' => $selectedStatistics,
        'generated_at' => $generatedAt,
    ];
    $manifestCatalogs[$id] = [
        'label' => (string)$definition['label'],
        'description' => (string)$definition['description'],
        'file' => $filename,
        'object_count' => count($selectedObjects),
        'object_names' => array_keys($selectedObjects),
    ];
}

$manifest = [
    'format_version' => 1,
    'generation_id' => $generationId,
    'environment' => $environment,
    'database' => (string)$server['database_name'],
    'server_version' => (string)$server['version'],
    'sql_mode' => (string)$server['sql_mode'],
    'catalogs' => $manifestCatalogs,
    'outside_catalogs' => ['Úkoly', 'Helpdesk', 'administrace a technické tabulky'],
    'outside_catalogs_tool' => 'inspect_schema',
    'generated_at' => $generatedAt,
];

$dataDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDirectory) || !is_writable($dataDirectory)) {
    throw new RuntimeException('Adresář data pro katalogy neexistuje nebo do něj nelze zapisovat.');
}

$temporaryFiles = [];
try {
    foreach ($catalogPayloads as $filename => $payload) {
        $path = $dataDirectory . DIRECTORY_SEPARATOR . $filename;
        $temporaryFiles[$path] = cb_ai_katalog_write($path, cb_ai_katalog_json($payload));
    }
    $manifestPath = $dataDirectory . DIRECTORY_SEPARATOR . 'ai_analytik_katalog_manifest.json';
    $temporaryFiles[$manifestPath] = cb_ai_katalog_write($manifestPath, cb_ai_katalog_json($manifest));

    foreach ($catalogPayloads as $filename => $payload) {
        $path = $dataDirectory . DIRECTORY_SEPARATOR . $filename;
        cb_ai_katalog_replace($temporaryFiles[$path], $path);
        unset($temporaryFiles[$path]);
    }
    cb_ai_katalog_replace($temporaryFiles[$manifestPath], $manifestPath);
    unset($temporaryFiles[$manifestPath]);
} finally {
    foreach ($temporaryFiles as $tmp) {
        @unlink($tmp);
    }
}

echo 'OK: katalogy vygenerovány pro ' . $environment . ', generace ' . $generationId . '.' . PHP_EOL;
foreach ($manifestCatalogs as $catalog) {
    echo '- ' . $catalog['label'] . ': ' . $catalog['object_count'] . ' objektů' . PHP_EOL;
}

