<?php
declare(strict_types=1);

/*
 * Ucel souboru: Sjednocuje nazvy HR tabulek behem dvoukrokove migrace slotu a funkci.
 * Aplikace diky nemu funguje pred prejmenovanim tabulek i po nem, bez docasne odstávky.
 */

/**
 * Vrati existujici tabulku pro logicky HR celek; povolene nazvy jsou pevne dane zde.
 */
function cb_hr_schema_table(mysqli $db, string $logicalName): string
{
    static $resolved = [];
    $connectionId = spl_object_id($db);
    $cacheKey = $connectionId . ':' . $logicalName;
    if (isset($resolved[$cacheKey])) {
        return $resolved[$cacheKey];
    }

    $candidates = match ($logicalName) {
        'slot' => ['hr_slot', 'hr_zarazeni'],
        'funkce' => ['hr_funkce', 'hr_org_funkce'],
        'cis_funkce' => ['cis_funkce', 'cis_org_funkce'],
        default => throw new InvalidArgumentException('Neznámý logický název HR tabulky.'),
    };
    foreach ($candidates as $table) {
        $stmt = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $exists = $stmt->get_result()->fetch_row() !== null;
        $stmt->close();
        if ($exists) {
            return $resolved[$cacheKey] = $table;
        }
    }

    throw new RuntimeException('Chybí databázová tabulka pro HR ' . $logicalName . '.');
}

/** Vrati primarni klic tabulky slotu podle faze migrace. */
function cb_hr_slot_pk(mysqli $db): string
{
    return cb_hr_schema_table($db, 'slot') === 'hr_slot' ? 'id_hr_slot' : 'id_zarazeni';
}

/** Vrati cizi klic funkce podle faze migrace. */
function cb_hr_funkce_fk(mysqli $db): string
{
    return cb_hr_schema_table($db, 'funkce') === 'hr_funkce' ? 'id_funkce' : 'id_org_funkce';
}

/** Vrati primarni klic evidence funkce podle faze migrace. */
function cb_hr_funkce_pk(mysqli $db): string
{
    return cb_hr_schema_table($db, 'funkce') === 'hr_funkce' ? 'id_hr_funkce' : 'id_hr_org_funkce';
}

/** Vrati primarni klic ciselniku funkci podle faze migrace. */
function cb_hr_cis_funkce_pk(mysqli $db): string
{
    return cb_hr_schema_table($db, 'cis_funkce') === 'cis_funkce' ? 'id_funkce' : 'id_org_funkce';
}

