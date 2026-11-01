<?php
/* Co-Link: pripravi nazvy slotu a mnozinu skupin kuryr a pizzar. */
declare(strict_types=1);

/** Osoba muze mit vice slotu; skupiny se nevylucuji a poradi zustava podle puvodnich radku. */
function co_adresar_sloty(array $rows): array
{
    $groups = []; $labels = [];
    foreach ($rows as $r) {
        if (in_array((int)$r['id_slot'], [1,2], true)) $groups[(int)$r['id_person']][(int)$r['id_slot'] === 2 ? 'kuryr' : 'pizzar'] = true;
        $labels[(int)$r['id_person']][] = $r['slot'];
    }
    return ['skupiny' => $groups, 'nazvy' => $labels];
}
