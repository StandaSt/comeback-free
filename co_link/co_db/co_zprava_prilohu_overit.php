<?php
/* Co-Link: jednim SELECT zjisti existenci prilohy zpravy pro povoleni prazdneho textu. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Zprava s prilohou muze mit prazdny text, dotaz proto hleda jen prvni zaznam. */
function co_zprava_prilohu_overit(int $id): bool
{
    return co_rows('SELECT 1 FROM co_priloha WHERE zprava=? LIMIT 1', [$id]) !== [];
}
