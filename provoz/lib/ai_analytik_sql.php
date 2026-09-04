<?php
declare(strict_types=1);

final class CbAiAnalytikSqlBezpecnostniChyba extends RuntimeException
{
}

final class CbAiAnalytikSqlOpravitelnaChyba extends RuntimeException
{
}

/**
 * Vrátí SQL bez obsahu literálů a escapovaných identifikátorů.
 * Současně odmítne komentáře a více příkazů.
 */
function cb_ai_analytik_sql_normalizovat(string $sql): string
{
    $sql = trim($sql);
    if ($sql === '') {
        throw new CbAiAnalytikSqlOpravitelnaChyba('AI navrhla prázdný SQL dotaz.');
    }
    if (str_contains($sql, "\0")) {
        throw new CbAiAnalytikSqlBezpecnostniChyba('AI navrhla neplatný SQL dotaz.');
    }

    $out = '';
    $state = 'normal';
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($state === 'normal') {
            if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))
                || $char === '#'
                || ($char === '/' && $next === '*')) {
                throw new CbAiAnalytikSqlBezpecnostniChyba('SQL komentáře nejsou v AI dotazu povoleny.');
            }
            if ($char === "'") {
                $state = 'single';
                $out .= ' ';
                continue;
            }
            if ($char === '"') {
                $state = 'double';
                $out .= ' ';
                continue;
            }
            if ($char === '`') {
                $state = 'backtick';
                $out .= ' ';
                continue;
            }
            $out .= $char;
            continue;
        }

        if ($char === '\\') {
            $i++;
            $out .= '  ';
            continue;
        }

        $quote = $state === 'single' ? "'" : ($state === 'double' ? '"' : '`');
        if ($char === $quote) {
            if ($next === $quote) {
                $i++;
                $out .= '  ';
                continue;
            }
            $state = 'normal';
        }
        $out .= ' ';
    }

    if ($state !== 'normal') {
        throw new CbAiAnalytikSqlOpravitelnaChyba('AI navrhla SQL s neuzavřeným literálem.');
    }

    $trimmed = rtrim($out);
    if (str_ends_with($trimmed, ';')) {
        $trimmed = rtrim(substr($trimmed, 0, -1));
    }
    if (str_contains($trimmed, ';')) {
        throw new CbAiAnalytikSqlBezpecnostniChyba('AI smí předat pouze jeden SQL dotaz.');
    }

    return $trimmed;
}

function cb_ai_analytik_sql_overit(string $sql): string
{
    $normalized = cb_ai_analytik_sql_normalizovat($sql);
    if (preg_match('/^\s*(SELECT\b|WITH\b)/i', $normalized) !== 1) {
        throw new CbAiAnalytikSqlBezpecnostniChyba('AI smí používat pouze SELECT dotazy.');
    }

    $forbidden = '/\b(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE|RENAME|GRANT|REVOKE|CALL|DO|HANDLER|LOAD|LOCK|UNLOCK|SET|USE|SHOW|DESCRIBE|EXPLAIN|ANALYZE|OPTIMIZE|REPAIR|KILL)\b/i';
    if (preg_match($forbidden, $normalized) === 1
        || preg_match('/\b(INTO\s+(OUTFILE|DUMPFILE)|PROCEDURE\s+ANALYSE)\b/i', $normalized) === 1
        || preg_match('/\b(SLEEP|BENCHMARK|GET_LOCK|RELEASE_LOCK|IS_USED_LOCK|IS_FREE_LOCK|LOAD_FILE)\s*\(/i', $normalized) === 1
        || str_contains($normalized, '@')) {
        throw new CbAiAnalytikSqlBezpecnostniChyba('AI navrhla SQL operaci, která není v read-only režimu povolena.');
    }

    return trim($sql);
}

function cb_ai_analytik_sql_chyba_je_opravitelna(mysqli_sql_exception $error): bool
{
    return in_array($error->getCode(), [1052, 1054, 1055, 1064, 1066, 1109, 1111, 1140, 1146, 1222, 1241, 1242, 1305, 1582], true);
}
