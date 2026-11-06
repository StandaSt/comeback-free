<?php
declare(strict_types=1);
/* Účel: Připraví a aplikuje filtry veřejných směn; vlastní režim určuje server, nikdy zadané jméno nebo ID osoby. */

/** Načte hodnoty ve společném formátu prefix_f[sloupec], který obsluhuje provoz/js/filtry.js. */
function cb_smeny_verejne_filtry_hodnoty(array $query): array
{
    $raw=is_array($query['sm_f']??null)?$query['sm_f']:[];
    // Ručně sestavený URL nesmí do skalárního filtru podstrčit pole.
    $value=static fn(string $key):string=>is_scalar($raw[$key]??null)?(string)$raw[$key]:'';
    return [
        'den'=>max(0,min(7,(int)$value('den'))),
        'pobocka'=>max(0,(int)$value('pobocka')),
        'slot'=>max(0,(int)$value('slot')),
        'jmeno'=>trim($value('jmeno')),
        'moje'=>$value('moje')==='1',
    ];
}

/** Filtry pracují až nad řádky, které SQL omezilo na vlastní osobu nebo povolené pobočky. */
function cb_smeny_verejne_filtrovat(array $rows, array $query): array
{
    $filters=cb_smeny_verejne_filtry_hodnoty($query);
    $day=$filters['den'];
    $branch=$filters['pobocka'];
    $slot=$filters['slot'];
    $name=$filters['jmeno'];
    return array_values(array_filter($rows,static function(array $row)use($day,$branch,$slot,$name):bool {
        if($day>0 && (int)(new DateTimeImmutable($row['datum']))->format('N')!==$day)return false;
        if($branch>0 && (int)$row['id_pob']!==$branch)return false;
        if($slot>0 && (int)$row['id_slot']!==$slot)return false;
        return $name==='' || mb_stripos((string)$row['pracovnik'],$name)!==false;
    }));
}

/** Nabídky filtrů vznikají jen z již oprávněných řádků, takže neprozradí cizí pobočky ani pozice. */
function cb_smeny_verejne_moznosti(array $rows, string $idKey, string $labelKey): array
{
    $options=[];
    foreach($rows as $row)$options[(int)$row[$idKey]]=(string)$row[$labelKey];
    natcasesort($options);
    return $options;
}
