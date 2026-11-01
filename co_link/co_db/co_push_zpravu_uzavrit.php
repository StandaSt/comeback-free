<?php
/* Co-Link: jednim UPDATE uzavre dosud nehotove push pokusy prislusne zpravy. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** Uzavreni ve stejne transakci zabrani dalsimu zpracovani cekajicich pushu smazane zpravy. */
function co_push_zpravu_uzavrit(int $id): void
{
    co_sql('UPDATE co_push_fronta SET hotovo=NOW() WHERE zprava=? AND hotovo IS NULL', [$id])->close();
}
