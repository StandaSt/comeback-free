<?php
/* Co-Link: overi ze odpoved do vlakna patri hlavni zprave dane konverzace. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/../co_db/co_zpravu_rodice_nacist.php';

/** Puvodni kontrola se provadi pouze pro kladne ID rodice a az po kontrole duplicitniho odeslani. */
function co_zprava_rodic_overit(int $parent, int $cid): void
{
    if ($parent > 0) {
        $row = co_zpravu_rodice_nacist($parent, $cid)[0] ?? [];
        if (!$row || $row['rodic'] !== null) co_chyba('Vlákno musí patřit hlavní zprávě této konverzace.');
    }
}
