<?php
/* Co-Link: jednim UPDATE nastavi prvni precteni pouze pokud zprava tento cas jeste nema. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_sql.php';

/** COALESCE zachovava jiz ulozeny cas prvniho precteni i pri dalsich potvrzenich. */
function co_prvni_precteni_nastavit(int $id): void
{
    co_sql('UPDATE co_zprava SET prvni_precteni=COALESCE(prvni_precteni,NOW(6)) WHERE id=?', [$id])->close();
}
