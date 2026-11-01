<?php
/* Co-Link: jednim UPDATE pripoji prilohu pouze pokud patri odesilateli a jeste nema zpravu. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Statement predava sluzbe pro puvodni kontrolu affected_rows a nasledne uzavreni. */
function co_prilohu_ke_zprave_pripojit(int $id, int $file, int $user): mysqli_stmt
{
    return co_sql('UPDATE co_priloha SET zprava=? WHERE id=? AND vlastnik=? AND zprava IS NULL', [$id, $file, $user]);
}
