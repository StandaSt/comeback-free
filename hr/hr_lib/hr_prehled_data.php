<?php
/*
 * Ucel souboru: Pripravuje data pro vsechny bloky stranky HR prehled.
 * Nevykresluje HTML, neresi PP ani nezapisuje do databaze.
 */
declare(strict_types=1);

require_once __DIR__ . '/../hr_includes/hr_data.php';

/*
 * Nacte jeden souhrn dat a vrati jej ve jmenovanem kontextu pro bloky prehledu.
 */
function hr_prehled_data(mysqli $db): array
{
    $prehled = hr_fetch_prehled($db);

    return [
        'nabor' => $prehled['nabor'],
        'zamestnanci' => $prehled['zamestnanci'],
        'pozadavky' => $prehled['pozadavky'],
        'kReseni' => $prehled['k_reseni'],
        'dokumenty' => $prehled['dokumenty'],
        'lekarskeProhlidky' => $prehled['lekarske_prohlidky'],
        'skoleni' => $prehled['skoleni'],
        'dovolene' => $prehled['dovolene'],
        'latest' => $prehled['latest'],
    ];
}
