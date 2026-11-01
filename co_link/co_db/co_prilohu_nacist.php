<?php
/* Co-Link: jediny dotaz pro nacteni prilohy vcetne konverzace a stavu smazani jeji zpravy. */
declare(strict_types=1);

/** Metadata vraci bez rozhodovani o opravneni; to provede volajici pred odeslanim souboru. */
function co_prilohu_nacist(int $id): array
{
    return co_rows('SELECT p.*,z.konverzace,z.smazano FROM co_priloha p JOIN co_zprava z ON z.id=p.zprava WHERE p.id=?', [$id])[0] ?? [];
}
