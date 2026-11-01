<?php
/* Co-Link: priradi nazvy aktualnich funkci jednotlivym osobam. */
declare(strict_types=1);

/** Duplicity se odstrani az po spojeni se sloty, aby zustalo puvodni poradi zarazeni. */
function co_adresar_funkce(array $rows): array
{
    $labels = [];
    foreach ($rows as $r) $labels[(int)$r['id_person']][] = $r['nazev'];
    return $labels;
}
