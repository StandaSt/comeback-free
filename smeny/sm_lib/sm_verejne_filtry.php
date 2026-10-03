<?php
declare(strict_types=1);
/* Účel: Připraví a aplikuje filtry tabulky veřejných směn bez rozšiřování oprávněného rozsahu dat. */

/** Načte hodnoty ve společném formátu prefix_f[sloupec], který obsluhuje provoz/js/filtry.js. */
function cb_smeny_verejne_filtry_hodnoty(array $query): array
{
    $raw=is_array($query['sm_f']??null)?$query['sm_f']:[];
    return [
        'den'=>max(0,min(7,(int)($raw['den']??0))),
        'pobocka'=>max(0,(int)($raw['pobocka']??0)),
        'slot'=>max(0,(int)($raw['slot']??0)),
        'jmeno'=>trim((string)($raw['jmeno']??'')),
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
