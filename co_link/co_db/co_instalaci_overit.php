<?php
/* Co-Link: jednim SELECT overi pritomnost instalace Co-Linku. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';

/** Chybejici instalace nesmi rozbit prihlaseni ani ostatni moduly IS. */
function co_pripraveno(): bool
{
    return co_rows("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='co_nastaveni'") !== [];
}
