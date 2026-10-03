<?php
declare(strict_types=1);
/* Účel: Vybere týden pro přehledy včetně historie. Nemění období povolená pro zápis plánovače. */

/** Libovolný den z kalendáře převedeme na pondělí; neplatné datum odmítneme bez normalizace překlepů. */
function cb_smeny_prehled_tyden(array $weeks, array $query): array
{
    $index=max(0,min(count($weeks)-1,(int)($query['week']??0)));
    $raw=trim((string)($query['tyden_od']??''));
    if($raw==='' && !empty($query['historie'])) $raw=$weeks[0]['start']->modify('-7 days')->format('Y-m-d');
    if($raw==='') return $weeks[$index];
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$raw,new DateTimeZone('Europe/Prague'));
    if(!$date || $date->format('Y-m-d')!==$raw || $raw<'2000-01-01' || $raw>'2100-12-31') {
        throw new CbUserVisibleException('Vyberte platné datum týdne.');
    }
    return cb_smeny_pozadavky_tydny($date->modify('-7 days'))[0];
}
