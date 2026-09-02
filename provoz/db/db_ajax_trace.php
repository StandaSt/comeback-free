<?php
declare(strict_types=1);

/*
 * Účel souboru: Načte z databáze skutečný stav globálního přepínače log_3
 * pro diagnostický AJAX endpoint. Soubor nic nevykresluje a nic nezapisuje.
 */

if (!function_exists('cb_db_ajax_trace_enabled')) {
    /**
     * Vrátí aktuální stav set_system.log_3. Chybějící systémový řádek je chyba,
     * protože diagnostika nesmí potichu přejít na náhradní hodnotu.
     */
    function cb_db_ajax_trace_enabled(mysqli $conn): bool
    {
        $result = $conn->query('SELECT log_3 FROM set_system WHERE id_set = 1 LIMIT 1');
        if (!$result instanceof mysqli_result) {
            throw new RuntimeException('Nepodařilo se načíst nastavení log_3.');
        }

        $row = $result->fetch_assoc();
        $result->free();
        if (!is_array($row) || !array_key_exists('log_3', $row)) {
            throw new RuntimeException('V set_system chybí nastavení log_3.');
        }

        return (int)$row['log_3'] === 1;
    }
}
