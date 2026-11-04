<?php
declare(strict_types=1);
/* Účel: Čte číselníky a ukládá novou jednotnou činnost do jedné z osmi povolených tabulek; neprovádí instalaci ani změny schématu. */

/** Všechny hodnoty jsou vázané; názvy tabulek smí pocházet pouze z pevné mapy. */
function cb_cinnost_db_jeden(mysqli $db, string $sql, array $values): ?array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) throw new RuntimeException('Nelze připravit čtení činnosti.');
    try {
        if ($values !== []) $stmt->bind_param(str_repeat('s', count($values)), ...$values);
        if (!$stmt->execute()) throw new RuntimeException('Nelze přečíst podklad činnosti.');
        return $stmt->get_result()->fetch_assoc();
    } finally { $stmt->close(); }
}

/** Číselníky určují význam akce i stránek; popisky nejsou používány pro rozhodování programu. */
function cb_cinnost_db_katalog(mysqli $db, string $module, int $action, array $p): array
{
    $a = cb_cinnost_db_jeden($db, 'SELECT id_akce,id_stav_vychozi,id_cil_typ,pravidla_json FROM cis_cinnost_akce WHERE id_akce=? AND aktivni=1', [$action]);
    if (!$a) throw new InvalidArgumentException('Neznámá nebo neaktivní akce činnosti.');
    foreach (['id_stranka'=>['cis_cinnost_stranka','id_stranka'], 'id_pole'=>['cis_cinnost_pole','id_pole']] as $key => [$table,$pk]) {
        if (!isset($p[$key])) continue;
        $row = cb_cinnost_db_jeden($db, "SELECT * FROM {$table} WHERE {$pk}=? AND aktivni=1 AND (modul IS NULL OR modul=?)", [$p[$key],$module]);
        if (!$row) throw new InvalidArgumentException('Neplatná stránka nebo pole pro modul.');
        if ($key === 'id_pole') $field = $row;
    }
    if (isset($p['id_cil_typ']) && !cb_cinnost_db_jeden($db, 'SELECT id_cil_typ FROM cis_cinnost_cil_typ WHERE id_cil_typ=? AND aktivni=1', [$p['id_cil_typ']])) throw new InvalidArgumentException('Neplatný typ cíle.');
    return ['akce'=>$a, 'pole'=>$field ?? null];
}

/** Firma autora se čte ze skutečné osoby; profil pro zkoušení práv ji nenahrazuje. */
function cb_cinnost_db_firma(mysqli $db, int $user, ?int $firm): int
{
    $row = cb_cinnost_db_jeden($db, 'SELECT id_firma FROM hr_person WHERE id_user=?', [$user]);
    if (!$row || (int)$row['id_firma'] < 1) throw new RuntimeException('Nelze určit firmu autora činnosti.');
    $firm ??= (int)$row['id_firma'];
    if (!cb_cinnost_db_jeden($db, 'SELECT id_firma FROM firma WHERE id_firma=?', [$firm])) throw new InvalidArgumentException('Neznámá firma činnosti.');
    return $firm;
}

/** Opakovaný UUID musí patřit stejnému autorovi i obsahu; cizí nebo pozměněná událost se nikdy nepotvrdí. */
function cb_cinnost_db_vlozit(mysqli $db, string $module, array $row): array
{
    $table = cb_cinnost_tabulka($module);
    $columns = array_keys($row);
    $values = array_values($row);
    $stmt = $db->prepare('INSERT INTO '.$table.' ('.implode(',', $columns).',cas_zapisu) VALUES ('.implode(',', array_fill(0, count($columns), '?')).',UTC_TIMESTAMP(3))');
    if (!$stmt) throw new RuntimeException('Nelze připravit zápis činnosti.');
    try {
        $stmt->bind_param(str_repeat('s', count($values)), ...$values);
        if (!$stmt->execute()) throw new mysqli_sql_exception('Zápis činnosti selhal.', $stmt->errno);
        return ['id_cinnost'=>(string)$stmt->insert_id, 'opakovani'=>false];
    } catch (mysqli_sql_exception $error) {
        if ($error->getCode() !== 1062) throw $error;
        $existing = cb_cinnost_db_jeden($db, 'SELECT * FROM '.$table.' WHERE udalost_uuid=? FOR UPDATE', [$row['udalost_uuid']]);
        if (!$existing) throw $error;
        foreach ($row as $column => $value) {
            // Čas prvního přijetí zůstává zachován i při pozdějším opakování stejného volání.
            if ($column === 'cas_udalosti') continue;
            if ($column === 'parametry_json' && $value !== null && $existing[$column] !== null) {
                // MySQL může JSON přeuspořádat a doplnit mezery; porovnáváme typované hodnoty, nikoli jeho formát.
                $old = json_decode($existing[$column], true, 16, JSON_THROW_ON_ERROR);
                $new = json_decode($value, true, 16, JSON_THROW_ON_ERROR);
                ksort($old); ksort($new);
                if ($old !== $new) throw new RuntimeException('UUID již patří jiné činnosti.');
                continue;
            }
            if (($existing[$column] === null) !== ($value === null) || (string)$existing[$column] !== (string)$value) throw new RuntimeException('UUID již patří jiné činnosti.');
        }
        return ['id_cinnost'=>(string)$existing['id_cinnost'], 'opakovani'=>true];
    } finally { $stmt->close(); }
}
