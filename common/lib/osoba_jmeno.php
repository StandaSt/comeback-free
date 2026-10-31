<?php
declare(strict_types=1);
/* Sestaveni zobrazovaneho jmena vcetne titulu za; zdroj osobnich udaju zustava v osoba_udaje.php. */
require_once __DIR__.'/osoba_udaje.php';

/** Zachová vybrané pořadí jména; prázdné části ani titul netvoří nadbytečné mezery. */
function cb_osoba_jmeno(array $person, bool $surnameFirst=false): string
{
    $first=trim((string)($person['jmeno']??$person['name']??''));
    $middle=trim((string)($person['druhe_jmeno']??''));
    $last=trim((string)($person['prijmeni']??$person['surname']??''));
    $suffix=trim((string)($person['titul_za']??''));
    $parts=$surnameFirst?[$last,$first,$middle]:[$first,$middle,$last];
    $name=implode(' ',array_filter($parts,static fn(string $part): bool=>$part!==''));
    // Titul pochází z odděleného pole HR a připojuje se právě jednou, nikdy k uloženému příjmení.
    return $name!==''&&$suffix!==''?$name.' '.$suffix:$name;
}

/** Rozdělí příjmení a zbytek hotového jména pro stávající dvouřádkové karty a plánovač. */
function cb_osoba_jmeno_radky(array $person): array
{
    $surname=trim((string)($person['prijmeni']??$person['surname']??''));
    $full=cb_osoba_jmeno($person,true);
    return ['prijmeni'=>$surname,'zbytek'=>trim(mb_substr($full,mb_strlen($surname,'UTF-8'),null,'UTF-8'))];
}

/** Stejný výstup uvnitř SQL; čte tentýž zdroj a ponechá řazení či filtrování ve stávajícím dotazu. */
function cb_osoba_jmeno_sql(string $idExpression, bool $surnameFirst=false): string
{
    // Přijímáme pouze programátorem určený identifikátor, nikoli hodnotu nebo výraz od uživatele.
    if(!preg_match('/^(?:[a-zA-Z_][a-zA-Z0-9_]*\.)?[a-zA-Z_][a-zA-Z0-9_]*$/D',$idExpression)) {
        throw new InvalidArgumentException('Neplatny identifikator osoby pro SQL.');
    }
    $columns=$surnameFirst?['prijmeni','jmeno','druhe_jmeno']:['jmeno','druhe_jmeno','prijmeni'];
    $parts=array_map(static fn(string $column): string=>'NULLIF(TRIM(cb_jmeno.'.$column.'),\'\')',$columns);
    $name='CONCAT_WS(\' \','.implode(',',$parts).')';
    // Stejne jako PHP nevydavame samotny titul za existujici jmeno chybejici osoby.
    $display='CASE WHEN '.$name.'=\'\' THEN \'\' ELSE CONCAT_WS(\' \','.$name.',NULLIF(TRIM(cb_jmeno.titul_za),\'\')) END';
    return '(SELECT '.$display.' FROM '.cb_osoby_zdroj_sql().' cb_jmeno WHERE cb_jmeno.id_person='.$idExpression.')';
}
