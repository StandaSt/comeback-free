<?php
/* Co-Link: uzavre pokus a zvysi pocitadlo pri uspechu i vycerpani opakovani. */
declare(strict_types=1);

/** Jedina DB operace; samotne nacteni souboru ji nespousti. */
function co_push_pokus_dokoncit(int $id): void
{
    co_sql('UPDATE co_push_fronta SET hotovo=NOW(),pokusy=pokusy+1 WHERE id=?', [$id])->close();
}
