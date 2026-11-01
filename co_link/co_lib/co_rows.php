<?php
/* Co-Link: nacte vysledne radky parametrizovaneho dotazu a uzavre statement. */
declare(strict_types=1);
require_once __DIR__ . '/co_sql.php';

/** Cte radky a uzavira statement i u castych synchronizaci chatu. */
function co_rows(string $sql, array $params = []): array
{
    $s = co_sql($sql, $params);
    $rows = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
    return $rows;
}
