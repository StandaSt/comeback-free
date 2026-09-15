<?php
declare(strict_types=1);

/*
 * Účel souboru: Sestaví SQL export povolených skupin tabulek Administrace.
 * Neřeší HTTP, session ani oprávnění; volající předává připojení a cílový soubor.
 */

/** @return array<string,array{label:string,prefixes:list<string>,tables:list<string>}> */
function cb_admin_db_export_group_definitions(): array
{
    return [
        'ciselniky' => [
            'label' => 'Číselníky',
            'prefixes' => ['cis_'],
            'tables' => [],
        ],
        'hr' => [
            'label' => 'HR komplet',
            'prefixes' => ['hr_'],
            'tables' => [],
        ],
        'prava' => [
            'label' => 'Práva a role',
            'prefixes' => [],
            'tables' => ['cis_moduly', 'cis_prava', 'cis_role', 'prava_global', 'prava_vyjimky'],
        ],
    ];
}

/** @return list<string> */
function cb_admin_db_export_available_tables(mysqli $db): array
{
    $result = $db->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('Nepodařilo se načíst seznam tabulek databáze.');
    }

    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $name = (string)($row['TABLE_NAME'] ?? '');
        if ($name !== '') {
            $tables[] = $name;
        }
    }
    $result->free();

    return $tables;
}

/**
 * @param list<string> $availableTables
 * @return array<string,array{label:string,tables:list<string>}>
 */
function cb_admin_db_export_group_overview(array $availableTables): array
{
    $available = array_fill_keys($availableTables, true);
    $overview = [];

    foreach (cb_admin_db_export_group_definitions() as $key => $definition) {
        $tables = [];
        foreach ($availableTables as $table) {
            foreach ($definition['prefixes'] as $prefix) {
                if (str_starts_with($table, $prefix)) {
                    $tables[$table] = true;
                    break;
                }
            }
        }
        foreach ($definition['tables'] as $table) {
            if (isset($available[$table])) {
                $tables[$table] = true;
            }
        }

        $tableNames = array_keys($tables);
        sort($tableNames, SORT_STRING);
        $overview[$key] = [
            'label' => $definition['label'],
            'tables' => $tableNames,
        ];
    }

    return $overview;
}

/**
 * @param mixed $selectedGroups
 * @return list<string>
 */
function cb_admin_db_export_selected_groups(mixed $selectedGroups): array
{
    if (!is_array($selectedGroups)) {
        throw new RuntimeException('Vyberte alespoň jednu skupinu tabulek.');
    }

    $allowed = cb_admin_db_export_group_definitions();
    $selected = [];
    foreach ($selectedGroups as $group) {
        $key = trim((string)$group);
        if ($key === '' || !isset($allowed[$key])) {
            throw new RuntimeException('Požadavek obsahuje nepovolenou skupinu tabulek.');
        }
        $selected[$key] = true;
    }

    if ($selected === []) {
        throw new RuntimeException('Vyberte alespoň jednu skupinu tabulek.');
    }

    return array_keys($selected);
}

/**
 * @param list<string> $selectedGroups
 * @param array<string,array{label:string,tables:list<string>}> $overview
 * @return list<string>
 */
function cb_admin_db_export_resolve_tables(array $selectedGroups, array $overview): array
{
    $tables = [];
    foreach ($selectedGroups as $group) {
        if (!isset($overview[$group])) {
            throw new RuntimeException('Vybraná skupina tabulek není dostupná.');
        }
        foreach ($overview[$group]['tables'] as $table) {
            $tables[$table] = true;
        }
    }

    $tableNames = array_keys($tables);
    sort($tableNames, SORT_STRING);
    if ($tableNames === []) {
        throw new RuntimeException('Vybrané skupiny neobsahují žádné existující tabulky.');
    }

    return $tableNames;
}

function cb_admin_db_export_identifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

/** @param resource $handle */
function cb_admin_db_export_write($handle, string $content): void
{
    $length = strlen($content);
    $written = 0;
    while ($written < $length) {
        $bytes = fwrite($handle, substr($content, $written));
        if ($bytes === false || $bytes === 0) {
            throw new RuntimeException('Zápis exportu do dočasného souboru selhal.');
        }
        $written += $bytes;
    }
}

function cb_admin_db_export_value(mysqli $db, mixed $value, object $field): string
{
    if ($value === null) {
        return 'NULL';
    }

    $binaryTypes = [
        MYSQLI_TYPE_BIT,
        MYSQLI_TYPE_TINY_BLOB,
        MYSQLI_TYPE_MEDIUM_BLOB,
        MYSQLI_TYPE_LONG_BLOB,
        MYSQLI_TYPE_BLOB,
        MYSQLI_TYPE_STRING,
        MYSQLI_TYPE_VAR_STRING,
        MYSQLI_TYPE_GEOMETRY,
    ];
    $isBinary = (int)($field->charsetnr ?? 0) === 63
        && in_array((int)($field->type ?? -1), $binaryTypes, true);
    if ($isBinary) {
        return '0x' . bin2hex((string)$value);
    }

    return "'" . $db->real_escape_string((string)$value) . "'";
}

/** @return list<string> */
function cb_admin_db_export_insert_columns(mysqli $db, string $table): array
{
    $stmt = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND EXTRA NOT LIKE '%GENERATED%' ORDER BY ORDINAL_POSITION");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $result = $stmt->get_result();
    $columns = [];
    while ($row = $result->fetch_assoc()) {
        $columns[] = (string)$row['COLUMN_NAME'];
    }
    $stmt->close();

    return $columns;
}

/**
 * @param resource $handle
 * @param list<string> $columns
 * @param null|callable(string,list<string>,array<int,mixed>):array<int,mixed> $rowTransform
 */
function cb_admin_db_export_table_data(mysqli $db, $handle, string $table, array $columns, ?callable $rowTransform = null): int
{
    if ($columns === []) {
        return 0;
    }

    $quotedColumns = array_map('cb_admin_db_export_identifier', $columns);
    $query = 'SELECT ' . implode(', ', $quotedColumns) . ' FROM ' . cb_admin_db_export_identifier($table);
    $result = $db->query($query, MYSQLI_USE_RESULT);
    if (!$result instanceof mysqli_result) {
        throw new RuntimeException('Načtení dat tabulky ' . $table . ' selhalo.');
    }

    $fields = $result->fetch_fields();
    $prefix = 'INSERT INTO ' . cb_admin_db_export_identifier($table)
        . ' (' . implode(', ', $quotedColumns) . ') VALUES' . "\n";
    $rows = [];
    $rowsBytes = 0;
    $rowCount = 0;

    while ($row = $result->fetch_row()) {
        if ($rowTransform !== null) {
            $row = $rowTransform($table, $columns, $row);
            if (count($row) !== count($columns)) {
                throw new RuntimeException('Transformace tabulky ' . $table . ' vrátila neplatný počet hodnot.');
            }
        }
        $values = [];
        foreach ($row as $index => $value) {
            $values[] = cb_admin_db_export_value($db, $value, $fields[$index]);
        }
        $sqlRow = '(' . implode(', ', $values) . ')';
        $rows[] = $sqlRow;
        $rowsBytes += strlen($sqlRow);
        $rowCount++;

        if (count($rows) >= 250 || $rowsBytes >= 1048576) {
            cb_admin_db_export_write($handle, $prefix . implode(",\n", $rows) . ";\n");
            $rows = [];
            $rowsBytes = 0;
        }
    }
    $result->free();

    if ($rows !== []) {
        cb_admin_db_export_write($handle, $prefix . implode(",\n", $rows) . ";\n");
    }

    return $rowCount;
}

/**
 * @param list<string> $tables
 * @param null|callable(string,list<string>,array<int,mixed>):array<int,mixed> $rowTransform
 * @return array{path:string,size:int,table_count:int,row_count:int,tables:list<string>}
 */
function cb_admin_db_export_create_from_tables(
    mysqli $db,
    array $tables,
    string $targetPath,
    string $environment,
    string $exportLabel,
    ?callable $rowTransform = null
): array {
    $available = array_fill_keys(cb_admin_db_export_available_tables($db), true);
    $resolvedTables = [];
    foreach ($tables as $table) {
        $table = trim((string)$table);
        if ($table === '' || !isset($available[$table])) {
            throw new RuntimeException('Požadovaná tabulka ' . ($table !== '' ? $table : '[prázdná]') . ' není v databázi dostupná.');
        }
        $resolvedTables[$table] = true;
    }
    $tables = array_keys($resolvedTables);
    sort($tables, SORT_STRING);
    if ($tables === []) {
        throw new RuntimeException('Export neobsahuje žádnou tabulku.');
    }

    $handle = fopen($targetPath, 'wb');
    if ($handle === false) {
        throw new RuntimeException('Dočasný soubor exportu nelze vytvořit.');
    }

    $transactionStarted = false;
    $rowCount = 0;
    try {
        $db->query("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
        $db->query('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        $db->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
        $transactionStarted = true;

        $databaseName = (string)($db->query('SELECT DATABASE()')->fetch_row()[0] ?? '');
        $header = "-- Comeback IS - " . $exportLabel . "\n"
            . '-- Vytvořeno: ' . date('Y-m-d H:i:s') . "\n"
            . '-- Prostředí: ' . strtoupper($environment) . "\n"
            . '-- Databáze: ' . $databaseName . "\n"
            . '-- Tabulek: ' . count($tables) . "\n\n"
            . "SET NAMES utf8mb4;\n"
            . "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;\n"
            . "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;\n"
            . "SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";
        cb_admin_db_export_write($handle, $header);

        foreach ($tables as $table) {
            $quotedTable = cb_admin_db_export_identifier($table);
            $createResult = $db->query('SHOW CREATE TABLE ' . $quotedTable);
            $createRow = $createResult instanceof mysqli_result ? $createResult->fetch_row() : null;
            if (!is_array($createRow) || !isset($createRow[1])) {
                throw new RuntimeException('Strukturu tabulky ' . $table . ' se nepodařilo načíst.');
            }
            $createSql = (string)$createRow[1];
            $createResult->free();

            cb_admin_db_export_write(
                $handle,
                "-- --------------------------------------------------------\n"
                . '-- Tabulka: ' . $quotedTable . "\n\n"
                . 'DROP TABLE IF EXISTS ' . $quotedTable . ";\n"
                . $createSql . ";\n\n"
            );

            $columns = cb_admin_db_export_insert_columns($db, $table);
            $rowCount += cb_admin_db_export_table_data($db, $handle, $table, $columns, $rowTransform);
            cb_admin_db_export_write($handle, "\n");
        }

        cb_admin_db_export_write(
            $handle,
            "SET SQL_MODE=@OLD_SQL_MODE;\n"
            . "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;\n"
            . "SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;\n"
        );
        $db->commit();
        $transactionStarted = false;

        if (!fflush($handle)) {
            throw new RuntimeException('Dokončení zápisu exportu selhalo.');
        }
    } catch (Throwable $e) {
        if ($transactionStarted) {
            $db->rollback();
        }
        throw $e;
    } finally {
        fclose($handle);
    }

    $size = filesize($targetPath);
    if ($size === false || $size <= 0) {
        throw new RuntimeException('Vytvořený export je prázdný.');
    }

    return [
        'path' => $targetPath,
        'size' => $size,
        'table_count' => count($tables),
        'row_count' => $rowCount,
        'tables' => $tables,
    ];
}

/**
 * @param list<string> $selectedGroups
 * @return array{path:string,size:int,table_count:int,row_count:int,tables:list<string>,groups:list<string>}
 */
function cb_admin_db_export_create(mysqli $db, array $selectedGroups, string $targetPath, string $environment): array
{
    $overview = cb_admin_db_export_group_overview(cb_admin_db_export_available_tables($db));
    $tables = cb_admin_db_export_resolve_tables($selectedGroups, $overview);
    $definitions = cb_admin_db_export_group_definitions();
    $groupLabels = [];
    foreach ($selectedGroups as $group) {
        $groupLabels[] = $definitions[$group]['label'];
    }

    $export = cb_admin_db_export_create_from_tables(
        $db,
        $tables,
        $targetPath,
        $environment,
        'export databáze - skupiny: ' . implode(', ', $groupLabels)
    );
    $export['groups'] = $selectedGroups;

    return $export;
}
