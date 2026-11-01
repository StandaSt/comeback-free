<?php
/* Co-Link: provede parametrizovany dotaz se zachovanim puvodniho vazani typu. */
declare(strict_types=1);

/** Parametrizovany dotaz; nazvy tabulek a SQL jsou vzdy pevne v kodu. */
function co_sql(string $sql, array $params = []): mysqli_stmt
{
    $stmt = db()->prepare($sql);
    if ($params !== []) {
        $types = implode('', array_map(static fn($v): string => is_int($v) ? 'i' : 's', $params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}
