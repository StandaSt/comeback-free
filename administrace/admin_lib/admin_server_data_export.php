<?php
declare(strict_types=1);

/*
 * Účel souboru: Definuje pevný a bezpečný export provozních dat ze serveru
 * pro následný ruční import na lokál. Neřeší HTTP, session ani oprávnění.
 */

require_once __DIR__ . '/admin_db_export.php';

/** @return list<string> */
function cb_admin_server_data_export_tables(): array
{
    return [
        'user_login',
        'user_spy',
        'reporty_is',
        'reporty_is_osoby',
        'reporty_is_pokladna',
        'reporty_is_restia',
        'user_akce_new',
        'admin_info',
        'admin_info_user',
        'push_audit',
        'push_login_2fa',
    ];
}

/**
 * Serverové tokeny nesmějí být použitelné z exportovaného souboru.
 * Zachováváme pouze unikátní lokální zástupnou hodnotu a ostatní provozní data.
 *
 * @param list<string> $columns
 * @param array<int,mixed> $row
 * @return array<int,mixed>
 */
function cb_admin_server_data_export_sanitize_row(string $table, array $columns, array $row): array
{
    $tokenTables = [
        'admin_info_user' => 'id_admin_info_user',
        'push_login_2fa' => 'id',
    ];
    if (!isset($tokenTables[$table])) {
        return $row;
    }

    $tokenIndex = array_search('token', $columns, true);
    $idIndex = array_search($tokenTables[$table], $columns, true);
    if ($tokenIndex === false || $idIndex === false) {
        throw new RuntimeException('Tabulka ' . $table . ' nemá očekávané sloupce pro anonymizaci tokenu.');
    }

    $row[$tokenIndex] = hash('sha256', 'LOCAL_EXPORT:' . $table . ':' . (string)$row[$idIndex]);
    return $row;
}

/** @return array{path:string,size:int,table_count:int,row_count:int,tables:list<string>} */
function cb_admin_server_data_export_create(mysqli $db, string $targetPath, string $environment): array
{
    if ($environment !== 'server') {
        throw new RuntimeException('Export dat ze serveru na lokál lze vytvořit pouze na serveru.');
    }

    return cb_admin_db_export_create_from_tables(
        $db,
        cb_admin_server_data_export_tables(),
        $targetPath,
        $environment,
        'export dat ze serveru na lokál',
        'cb_admin_server_data_export_sanitize_row'
    );
}

