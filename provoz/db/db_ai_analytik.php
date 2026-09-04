<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ai_analytik_sql.php';
require_once __DIR__ . '/../lib/ai_analytik_pravidla.php';
require_once __DIR__ . '/db_ai_analytik_sql_audit.php';

function cb_ai_analytik_schema_popisy(mysqli $conn): array
{
    $result = $conn->query(
        'SELECT object_name, column_name, description FROM ai_analytik_schema_popis'
    );
    $descriptions = [];
    while ($row = $result->fetch_assoc()) {
        $descriptions[(string)$row['object_name']][(string)$row['column_name']] = (string)$row['description'];
    }
    $result->free();
    return $descriptions;
}

function cb_ai_analytik_schema_komentar(array $descriptions, string $object, string $column, string $fallback): string
{
    $description = trim((string)($descriptions[$object][$column] ?? ''));
    return $description !== '' ? $description : $fallback;
}

function cb_ai_analytik_db(): mysqli
{
    $host = trim((string)getenv('AI_ANALYTIK_DB_HOST'));
    $name = trim((string)getenv('AI_ANALYTIK_DB_NAME'));
    $user = trim((string)getenv('AI_ANALYTIK_DB_USER'));
    $password = (string)getenv('AI_ANALYTIK_DB_PASSWORD');
    $portRaw = trim((string)getenv('AI_ANALYTIK_DB_PORT'));
    $port = $portRaw !== '' ? (int)$portRaw : 3306;

    if ($host === '' || $name === '' || $user === '' || $password === '' || $port <= 0) {
        throw new RuntimeException('Read-only databáze AI analytika není nakonfigurována.');
    }

    $conn = new mysqli($host, $user, $password, $name, $port);
    if ($conn->connect_errno !== 0) {
        throw new RuntimeException('Read-only databáze AI analytika není dostupná.');
    }
    if (!$conn->set_charset('utf8mb4')) {
        $conn->close();
        throw new RuntimeException('Read-only databáze AI analytika nepodporuje požadované kódování.');
    }

    return $conn;
}

function cb_ai_analytik_schema_hledat(string $search): array
{
    $conn = cb_ai_analytik_db();
    try {
        $descriptions = cb_ai_analytik_schema_popisy($conn);
        $search = trim($search);
        if ($search === '') {
            $result = $conn->query(
                "SELECT TABLE_NAME, TABLE_TYPE, TABLE_COMMENT
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                 ORDER BY TABLE_NAME"
            );
        } else {
            $like = '%' . $search . '%';
            $stmt = $conn->prepare(
                "SELECT DISTINCT t.TABLE_NAME, t.TABLE_TYPE, t.TABLE_COMMENT
                 FROM information_schema.TABLES t
                 LEFT JOIN information_schema.COLUMNS c
                   ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
                 LEFT JOIN ai_analytik_schema_popis d
                   ON d.object_name = t.TABLE_NAME
                 WHERE t.TABLE_SCHEMA = DATABASE()
                   AND (t.TABLE_NAME LIKE ? OR t.TABLE_COMMENT LIKE ? OR c.COLUMN_NAME LIKE ? OR c.COLUMN_COMMENT LIKE ?
                        OR d.description LIKE ?)
                 ORDER BY t.TABLE_NAME"
            );
            $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $tables[] = [
                'name' => (string)$row['TABLE_NAME'],
                'type' => (string)$row['TABLE_TYPE'],
                'comment' => cb_ai_analytik_schema_komentar(
                    $descriptions,
                    (string)$row['TABLE_NAME'],
                    '',
                    (string)$row['TABLE_COMMENT']
                ),
            ];
        }
        $result->free();
        if (isset($stmt)) {
            $stmt->close();
        }
        return ['search' => $search, 'tables' => $tables];
    } finally {
        $conn->close();
    }
}

function cb_ai_analytik_schema_popsat(array $requestedTables): array
{
    $conn = cb_ai_analytik_db();
    try {
        $descriptions = cb_ai_analytik_schema_popisy($conn);
        $available = [];
        $result = $conn->query(
            "SELECT TABLE_NAME, TABLE_TYPE, TABLE_COMMENT FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()"
        );
        while ($row = $result->fetch_assoc()) {
            $table = (string)$row['TABLE_NAME'];
            $available[$table] = [
                'name' => $table,
                'type' => (string)$row['TABLE_TYPE'],
                'comment' => cb_ai_analytik_schema_komentar(
                    $descriptions,
                    $table,
                    '',
                    (string)$row['TABLE_COMMENT']
                ),
            ];
        }
        $result->free();

        $tables = [];
        foreach ($requestedTables as $requested) {
            $table = trim((string)$requested);
            if ($table === '' || !isset($available[$table]) || isset($tables[$table])) {
                continue;
            }
            $stmt = $conn->prepare(
                "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_COMMENT
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                 ORDER BY ORDINAL_POSITION"
            );
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $result = $stmt->get_result();
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = [
                    'name' => (string)$row['COLUMN_NAME'],
                    'type' => (string)$row['COLUMN_TYPE'],
                    'nullable' => (string)$row['IS_NULLABLE'] === 'YES',
                    'key' => (string)$row['COLUMN_KEY'],
                    'comment' => cb_ai_analytik_schema_komentar(
                        $descriptions,
                        $table,
                        (string)$row['COLUMN_NAME'],
                        (string)$row['COLUMN_COMMENT']
                    ),
                ];
            }
            $result->free();
            $stmt->close();
            $tables[$table] = $available[$table] + [
                'columns' => $columns,
                'relations' => [],
                'referenced_by' => [],
            ];
        }

        if ($tables !== []) {
            $stmt = $conn->prepare(
                "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND REFERENCED_TABLE_NAME IS NOT NULL
                   AND (TABLE_NAME = ? OR REFERENCED_TABLE_NAME = ?)"
            );
            foreach (array_keys($tables) as $table) {
                $stmt->bind_param('ss', $table, $table);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $owner = (string)$row['TABLE_NAME'];
                    $referenced = (string)$row['REFERENCED_TABLE_NAME'];
                    if (isset($tables[$owner])) {
                        $relation = [
                            'column' => (string)$row['COLUMN_NAME'],
                            'references_table' => $referenced,
                            'references_column' => (string)$row['REFERENCED_COLUMN_NAME'],
                        ];
                        if (!in_array($relation, $tables[$owner]['relations'], true)) {
                            $tables[$owner]['relations'][] = $relation;
                        }
                    }
                    if (isset($tables[$referenced])) {
                        $incoming = [
                            'table' => $owner,
                            'column' => (string)$row['COLUMN_NAME'],
                            'references_column' => (string)$row['REFERENCED_COLUMN_NAME'],
                        ];
                        if (!in_array($incoming, $tables[$referenced]['referenced_by'], true)) {
                            $tables[$referenced]['referenced_by'][] = $incoming;
                        }
                    }
                }
                $result->free();
            }
            $stmt->close();
        }

        return ['tables' => array_values($tables)];
    } finally {
        $conn->close();
    }
}

function cb_ai_analytik_schema_prozkoumat(array $searches, array $requestedTables): array
{
    $conn = cb_ai_analytik_db();
    try {
        $descriptions = cb_ai_analytik_schema_popisy($conn);
        $available = [];
        $result = $conn->query(
            "SELECT TABLE_NAME, TABLE_TYPE, TABLE_COMMENT
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
             ORDER BY TABLE_NAME"
        );
        while ($row = $result->fetch_assoc()) {
            $name = (string)$row['TABLE_NAME'];
            $available[$name] = [
                'name' => $name,
                'type' => (string)$row['TABLE_TYPE'],
                'comment' => cb_ai_analytik_schema_komentar(
                    $descriptions,
                    $name,
                    '',
                    (string)$row['TABLE_COMMENT']
                ),
            ];
        }
        $result->free();

        $normalizedSearches = [];
        foreach ($searches as $search) {
            $search = trim((string)$search);
            if ($search !== '' && !in_array($search, $normalizedSearches, true)) {
                $normalizedSearches[] = $search;
            }
        }

        $selected = [];
        foreach ($requestedTables as $requestedTable) {
            $table = trim((string)$requestedTable);
            if (isset($available[$table])) {
                $selected[$table] = $available[$table];
            }
        }

        $matches = [];
        if ($normalizedSearches !== []) {
            $stmt = $conn->prepare(
                "SELECT DISTINCT t.TABLE_NAME
                 FROM information_schema.TABLES t
                 LEFT JOIN information_schema.COLUMNS c
                   ON c.TABLE_SCHEMA = t.TABLE_SCHEMA AND c.TABLE_NAME = t.TABLE_NAME
                 LEFT JOIN ai_analytik_schema_popis d
                   ON d.object_name = t.TABLE_NAME
                 WHERE t.TABLE_SCHEMA = DATABASE()
                   AND (t.TABLE_NAME LIKE ? OR t.TABLE_COMMENT LIKE ? OR c.COLUMN_NAME LIKE ? OR c.COLUMN_COMMENT LIKE ?
                        OR d.description LIKE ?)
                 ORDER BY CASE WHEN t.TABLE_NAME = ? THEN 0 WHEN t.TABLE_NAME LIKE ? THEN 1 ELSE 2 END,
                          t.TABLE_NAME"
            );
            foreach ($normalizedSearches as $search) {
                $like = '%' . $search . '%';
                $prefix = $search . '%';
                $stmt->bind_param('sssssss', $like, $like, $like, $like, $like, $search, $prefix);
                $stmt->execute();
                $result = $stmt->get_result();
                $matches[$search] = [];
                while ($row = $result->fetch_assoc()) {
                    $table = (string)$row['TABLE_NAME'];
                    $matches[$search][] = $table;
                    $selected[$table] = $available[$table];
                }
                $result->free();
            }
            $stmt->close();
        }

        if ($selected === []) {
            return [
                'searches' => $normalizedSearches,
                'matches' => $matches,
                'available_tables' => array_values($available),
                'tables' => [],
            ];
        }

        $tables = [];
        $stmt = $conn->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_COMMENT
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION"
        );
        foreach ($selected as $table => $metadata) {
            $stmt->bind_param('s', $table);
            $stmt->execute();
            $result = $stmt->get_result();
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = [
                    'name' => (string)$row['COLUMN_NAME'],
                    'type' => (string)$row['COLUMN_TYPE'],
                    'nullable' => (string)$row['IS_NULLABLE'] === 'YES',
                    'key' => (string)$row['COLUMN_KEY'],
                    'comment' => cb_ai_analytik_schema_komentar(
                        $descriptions,
                        $table,
                        (string)$row['COLUMN_NAME'],
                        (string)$row['COLUMN_COMMENT']
                    ),
                ];
            }
            $result->free();
            $tables[$table] = $metadata + ['columns' => $columns, 'relations' => [], 'referenced_by' => []];
        }
        $stmt->close();

        if ($tables !== []) {
            $stmt = $conn->prepare(
                "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND REFERENCED_TABLE_NAME IS NOT NULL
                   AND (TABLE_NAME = ? OR REFERENCED_TABLE_NAME = ?)"
            );
            foreach (array_keys($tables) as $table) {
                $stmt->bind_param('ss', $table, $table);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $owner = (string)$row['TABLE_NAME'];
                    $referenced = (string)$row['REFERENCED_TABLE_NAME'];
                    if (isset($tables[$owner])) {
                        $relation = [
                            'column' => (string)$row['COLUMN_NAME'],
                            'references_table' => $referenced,
                            'references_column' => (string)$row['REFERENCED_COLUMN_NAME'],
                        ];
                        if (!in_array($relation, $tables[$owner]['relations'], true)) {
                            $tables[$owner]['relations'][] = $relation;
                        }
                    }
                    if (isset($tables[$referenced])) {
                        $incoming = [
                            'table' => $owner,
                            'column' => (string)$row['COLUMN_NAME'],
                            'references_column' => (string)$row['REFERENCED_COLUMN_NAME'],
                        ];
                        if (!in_array($incoming, $tables[$referenced]['referenced_by'], true)) {
                            $tables[$referenced]['referenced_by'][] = $incoming;
                        }
                    }
                }
                $result->free();
            }
            $stmt->close();
        }

        return [
            'searches' => $normalizedSearches,
            'matches' => $matches,
            'available_tables' => [],
            'tables' => array_values($tables),
        ];
    } finally {
        $conn->close();
    }
}

function cb_ai_analytik_sql_typ(int $mysqliType): string
{
    return match ($mysqliType) {
        MYSQLI_TYPE_TINY, MYSQLI_TYPE_SHORT, MYSQLI_TYPE_LONG, MYSQLI_TYPE_INT24,
        MYSQLI_TYPE_LONGLONG, MYSQLI_TYPE_DECIMAL, MYSQLI_TYPE_NEWDECIMAL,
        MYSQLI_TYPE_FLOAT, MYSQLI_TYPE_DOUBLE => 'number',
        MYSQLI_TYPE_DATE, MYSQLI_TYPE_NEWDATE => 'date',
        MYSQLI_TYPE_DATETIME, MYSQLI_TYPE_TIMESTAMP => 'datetime',
        default => 'text',
    };
}

function cb_ai_analytik_php_limit_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
}

function cb_ai_analytik_sql_result_budget_bytes(): int
{
    $budget = CB_AI_ANALYTIK_MAX_TOOL_RESULT_BYTES;
    $memoryLimit = cb_ai_analytik_php_limit_bytes((string)ini_get('memory_limit'));
    if ($memoryLimit <= 0) {
        return $budget;
    }
    $available = $memoryLimit - memory_get_usage(true);
    if ($available < 262144) {
        throw new RuntimeException('PHP nemá dostatek volné paměti pro bezpečné načtení výsledku SQL.');
    }
    return min($budget, max(65536, (int)floor($available * 0.20)));
}

function cb_ai_analytik_sql_state(mysqli_sql_exception $error): string
{
    return method_exists($error, 'getSqlState') ? (string)$error->getSqlState() : '';
}

function cb_ai_analytik_sql_spustit(int $idAudit, int $poradi, string $ucel, string $sql): array
{
    $idSqlAudit = cb_ai_analytik_sql_audit_start($idAudit, $poradi, $ucel, $sql);
    $startedAt = hrtime(true);
    $rowCount = 0;
    $resultBytes = 0;
    $conn = null;
    try {
        $sql = cb_ai_analytik_sql_overit($sql);
        $budgetBytes = cb_ai_analytik_sql_result_budget_bytes();
        $conn = cb_ai_analytik_db();
        $conn->query('START TRANSACTION READ ONLY');
        $result = $conn->query($sql, MYSQLI_USE_RESULT);
        if (!$result instanceof mysqli_result) {
            throw new RuntimeException('Read-only SQL nevrátilo tabulkový výsledek.');
        }

        $columns = [];
        foreach ($result->fetch_fields() as $field) {
            $columns[] = [
                'name' => (string)$field->name,
                'type' => cb_ai_analytik_sql_typ((int)$field->type),
            ];
        }
        $resultBytes = strlen(json_encode($columns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $rows = [];
        $tooLarge = false;
        while ($row = $result->fetch_assoc()) {
            $rowCount++;
            $rowBytes = strlen(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if ($resultBytes + $rowBytes > $budgetBytes) {
                $resultBytes += $rowBytes;
                $tooLarge = true;
                break;
            }
            $resultBytes += $rowBytes;
            $rows[] = $row;
        }
        $result->free();
        $conn->rollback();

        $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
        if ($tooLarge) {
            $message = 'Výsledek SQL je příliš velký pro bezpečný přenos do AI. Dotaz agreguj, seskup, zpřesni filtrem nebo rozumně stránkuj.';
            cb_ai_analytik_sql_audit_finish($idSqlAudit, [
                'status' => 'result_too_large',
                'duration_ms' => $durationMs,
                'row_count' => $rowCount,
                'result_bytes' => $resultBytes,
                'error_type' => 'result_too_large',
                'error_message' => $message,
            ]);
            return [
                'ok' => false,
                'error_type' => 'result_too_large',
                'message' => $message,
                'columns' => $columns,
                'rows_read_at_least' => $rowCount,
                'result_bytes_at_least' => $resultBytes,
                'byte_limit' => $budgetBytes,
                'duration_ms' => $durationMs,
            ];
        }
        cb_ai_analytik_sql_audit_finish($idSqlAudit, [
            'status' => 'completed',
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'result_bytes' => $resultBytes,
        ]);
        return [
            'ok' => true,
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => $rowCount,
            'result_bytes' => $resultBytes,
            'duration_ms' => $durationMs,
        ];
    } catch (CbAiAnalytikSqlOpravitelnaChyba $error) {
        $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
        cb_ai_analytik_sql_audit_finish($idSqlAudit, [
            'status' => 'sql_error',
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'result_bytes' => $resultBytes,
            'error_type' => get_class($error),
            'error_code' => (string)$error->getCode(),
            'error_message' => $error->getMessage(),
        ]);
        return [
            'ok' => false,
            'error_type' => 'sql_error',
            'message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'sqlstate' => '',
            'suggestion' => 'Oprav formát nebo syntaxi SQL a spusť opravený dotaz.',
            'duration_ms' => $durationMs,
        ];
    } catch (CbAiAnalytikSqlBezpecnostniChyba $error) {
        $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
        cb_ai_analytik_sql_audit_finish($idSqlAudit, [
            'status' => 'security_rejected',
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'result_bytes' => $resultBytes,
            'error_type' => get_class($error),
            'error_code' => (string)$error->getCode(),
            'error_message' => $error->getMessage(),
        ]);
        throw $error;
    } catch (mysqli_sql_exception $error) {
        if ($conn instanceof mysqli) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
            }
        }
        $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
        $repairable = cb_ai_analytik_sql_chyba_je_opravitelna($error);
        cb_ai_analytik_sql_audit_finish($idSqlAudit, [
            'status' => $repairable ? 'sql_error' : 'error',
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'result_bytes' => $resultBytes,
            'error_type' => get_class($error),
            'error_code' => (string)$error->getCode(),
            'sqlstate' => cb_ai_analytik_sql_state($error),
            'error_message' => $error->getMessage(),
        ]);
        if ($repairable) {
            return [
                'ok' => false,
                'error_type' => 'sql_error',
                'message' => mb_substr($error->getMessage(), 0, 1000),
                'error_code' => $error->getCode(),
                'sqlstate' => cb_ai_analytik_sql_state($error),
                'suggestion' => 'Použij inspect_schema, oprav názvy objektů, sloupců, aliasů nebo syntaxi a spusť opravený dotaz.',
                'duration_ms' => $durationMs,
            ];
        }
        throw $error;
    } catch (Throwable $error) {
        if ($conn instanceof mysqli) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
            }
        }
        $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
        cb_ai_analytik_sql_audit_finish($idSqlAudit, [
            'status' => 'error',
            'duration_ms' => $durationMs,
            'row_count' => $rowCount,
            'result_bytes' => $resultBytes,
            'error_type' => get_class($error),
            'error_code' => (string)$error->getCode(),
            'error_message' => $error->getMessage(),
        ]);
        throw $error;
    } finally {
        if ($conn instanceof mysqli) {
            $conn->close();
        }
    }
}
