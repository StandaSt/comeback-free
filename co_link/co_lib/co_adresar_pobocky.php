<?php
/* Co-Link: priradi osobam mnozinu vsech nactenych MAIN pobocek. */
declare(strict_types=1);

/** Zadna MAIN nema hlavni prioritu; opakovane stejne ID se nezobrazi vicekrat. */
function co_adresar_pobocky(array $rows): array
{
    $branches = [];
    foreach ($rows as $r) $branches[(int)$r['id_person']][(int)$r['id_pob']] = true;
    return $branches;
}
