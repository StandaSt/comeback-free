<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/ai_analytik_sql.php';
require_once __DIR__ . '/db_ai_analytik_sql_audit.php';

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
                 WHERE t.TABLE_SCHEMA = DATABASE()
                   AND (t.TABLE_NAME LIKE ? OR t.TABLE_COMMENT LIKE ? OR c.COLUMN_NAME LIKE ? OR c.COLUMN_COMMENT LIKE ?)
                 ORDER BY t.TABLE_NAME"
            );
            $stmt->bind_param('ssss', $like, $like, $like, $like);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $tables[] = [
                'name' => (string)$row['TABLE_NAME'],
                'type' => (string)$row['TABLE_TYPE'],
                'comment' => (string)$row['TABLE_COMMENT'],
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
        $available = [];
        $result = $conn->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()"
        );
        while ($row = $result->fetch_assoc()) {
            $available[(string)$row['TABLE_NAME']] = true;
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
                    'comment' => (string)$row['COLUMN_COMMENT'],
                ];
            }
            $result->free();
            $stmt->close();
            $tables[$table] = ['name' => $table, 'columns' => $columns, 'relations' => []];
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
                    if (!isset($tables[$owner])) {
                        continue;
                    }
                    $tables[$owner]['relations'][] = [
                        'column' => (string)$row['COLUMN_NAME'],
                        'references_table' => (string)$row['REFERENCED_TABLE_NAME'],
                        'references_column' => (string)$row['REFERENCED_COLUMN_NAME'],
                    ];
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

function cb_ai_analytik_sql_spustit(int $idAudit, int $poradi, string $ucel, string $sql): array
{
    $sql = cb_ai_analytik_sql_overit($sql);
    $idSqlAudit = cb_ai_analytik_sql_audit_start($idAudit, $poradi, $ucel, $sql);
    $startedAt = hrtime(true);
    $rowCount = 0;
    $conn = cb_ai_analytik_db();
    try {
        $conn->query('START TRANSACTION READ ONLY');
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
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
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $rowCount = count($rows);
        $result->free();
        $stmt->close();
        $conn->rollback();

        $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
        cb_ai_analytik_sql_audit_finish($idSqlAudit, 'completed', $durationMs, $rowCount);
        return [
            'columns' => $columns,
            'rows' => $rows,
            'row_count' => $rowCount,
            'duration_ms' => $durationMs,
        ];
    } catch (Throwable $error) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
        }
        $durationMs = (int)((hrtime(true) - $startedAt) / 1_000_000);
        cb_ai_analytik_sql_audit_finish($idSqlAudit, 'error', $durationMs, $rowCount, $error->getMessage());
        throw $error;
    } finally {
        $conn->close();
    }
}
