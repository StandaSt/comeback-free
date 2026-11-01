<?php
/* Co-Link: vybere osoby s pravem Co-Linku a zachova puvodni prirozene razeni jmen. */
declare(strict_types=1);
require_once __DIR__ . '/co_prava.php';
require_once __DIR__ . '/../co_db/co_lidi_nacist.php';

/** Zverejnuje pouze jmeno a identifikator aktivnich netestovacich uctu, ktere smeji pouzivat link. */
function co_lide(): array
{
    // Stejne jako HR vynechame testovaci identity; tento vyber chrani i volbu prijemcu a clenu skupin.
    $ids = co_lidi_nacist();
    $people = [];
    foreach ($ids as $id) {
        $uid=(int)$id['id_user'];
        if (co_pravo($uid,700)) $people[]=['id'=>$uid,'jmeno'=>$id['jmeno']];
    }
    usort($people,static fn($a,$b)=>strnatcasecmp($a['jmeno'],$b['jmeno']));
    return $people;
}
