<?php
/* Co-Link: parametrizovany dotaz se zachovanim vazani typu a vypinatelnym merenim bez obsahu SQL. */
declare(strict_types=1);
// Bez zapnute diagnostiky pouze vratime puvodni SQL vysledek; dotazy ani parametry nelogujeme.
require_once __DIR__.'/../../common/lib/diagnostika_vykonu.php';

/** Parametrizovany dotaz s volitelnym casem prepare/execute, bez dodatecneho SQL. */
function co_sql(string $sql, array $params = []): mysqli_stmt
{
    $diagStart=cb_diag_clock();
    try {
        $stmt = db()->prepare($sql);
        if ($params !== []) {
            $types = implode('', array_map(static fn($v): string => is_int($v) ? 'i' : 's', $params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    } finally {cb_diag_span('sql_prepare_execute',$diagStart);}
}
