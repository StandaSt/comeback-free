<?php
declare(strict_types=1);
/* Účel: Překládá stejné popisky historie do SQL pro standardní sloupcové filtry před stránkováním. */
require_once __DIR__.'/admin_historie_popis.php';

/** Pevné programové popisky nejsou vstup uživatele; hledaná hodnota jde vždy parametrem. */
function cb_historie_sql_text(string $value): string
{
    return "'".str_replace("'", "''", $value)."'";
}

/** Slovník je sdílený s vykreslením, aby český název události šel hledat i mimo aktuální stránku. */
function cb_historie_sql_slovnik(string $expression, array $labels, string $fallback): string
{
    $sql = 'CASE '.$expression;
    foreach ($labels as $key=>$label) $sql .= ' WHEN '.cb_historie_sql_text((string)$key).' THEN '.cb_historie_sql_text($label);
    return $sql.' ELSE '.$fallback.' END';
}

/** Vrací skutečný název a výsledek; nepřidává obsah zpráv ani jiné soukromé podklady. */
function cb_historie_sql_popis(string $key, array $s): array
{
    $maps = cb_historie_popisky(); $title=$s['action'];
    $state="CASE WHEN ({$s['result']})=1 THEN 'Evidováno' ELSE 'Neúspěch' END";
    $context="COALESCE({$s['context']},'{}')";
    if ($key === 'akce') {
        $code="COALESCE(JSON_UNQUOTE(JSON_EXTRACT({$context},'$.udalost')),'')";
        $title='CASE WHEN '.$code."<>'' THEN ".cb_historie_sql_slovnik($code,$maps['udalosti'],"REPLACE({$code},'_',' ')")
            .' ELSE '.cb_historie_sql_slovnik($s['object'],$maps['objekty'],cb_historie_sql_slovnik($s['type'],$maps['typy'],"'Akce IS'")).' END';
        $state="CASE WHEN ({$s['result']})<>1 THEN 'Neúspěch' WHEN JSON_UNQUOTE(JSON_EXTRACT({$context},'$.puvod'))='prohlizec' THEN 'Ovládání' WHEN JSON_UNQUOTE(JSON_EXTRACT({$context},'$.faze'))='potvrzeno' THEN 'Potvrzeno' ELSE 'Evidováno' END";
    } elseif ($key === 'prihlaseni') $title="CASE WHEN ({$s['type']})=1 THEN 'Přihlášení' ELSE 'Odhlášení / ukončení relace' END";
    elseif ($key === 'smeny' || $key === 'co_link') $title=cb_historie_sql_slovnik($s['action'],$key==='co_link'?cb_historie_co_popisky():$maps['udalosti'],"REPLACE({$s['action']},'_',' ')");
    if (str_starts_with($key,'hr_') || $key==='reporty') $state="'Uložený podklad'";
    return [$title,$state];
}

/** Šest filtrů pracuje nad všemi autorizovanými zdroji; LIKE bere procento i podtržítko doslova. */
function cb_historie_sloupce_sql(string $key, array $s, array $filters, array &$where, array &$params, array $visiblePeople = []): void
{
    if (!array_filter($filters, static fn(string $value): bool=>$value!=='')) return;
    [$title,$state]=cb_historie_sql_popis($key,$s);
    $personName=cb_osoba_jmeno_sql('hc_person.id_person',true);
    $actor="COALESCE((SELECT NULLIF({$personName},'') FROM hr_person hc_person WHERE hc_person.id_user=({$s['actor']}) LIMIT 1),CASE WHEN ({$s['actor']})>0 THEN CONCAT('Uživatel #',{$s['actor']}) ELSE 'Autor nedoložen' END)";
    $visibleIds=implode(',',array_map('intval',$visiblePeople))?:'0';
    $person="COALESCE((SELECT NULLIF({$personName},'') FROM hr_person hc_person WHERE hc_person.id_person=({$s['person']}) AND hc_person.id_person IN ({$visibleIds}) LIMIT 1),CONCAT('Osoba #',{$s['person']}),'')";
    $context="COALESCE({$s['context']},'{}')";
    $expressions=[
        'cas'=>"CONCAT_WS(' ',DATE_FORMAT({$s['time']},'%e. %c. %Y %H:%i:%s'),{$s['time']})",
        'autor'=>$actor,
        'agenda'=>"COALESCE((SELECT modul FROM cis_moduly WHERE id_modul=({$s['module']})),'Přihlášení / systém')",
        'udalost'=>"CONCAT_WS(' ',{$title},REPLACE({$s['object']},'_',' '),{$s['object_id']},JSON_UNQUOTE(JSON_EXTRACT({$context},'$.page')),JSON_UNQUOTE(JSON_EXTRACT({$context},'$.stranka')),JSON_UNQUOTE(JSON_EXTRACT({$context},'$.cil')))",
        'cil'=>"CONCAT_WS(' ',{$person},(SELECT nazev FROM pobocka WHERE id_pob=({$s['branch']})),DATE_FORMAT({$s['day']},'%e. %c. %Y'),JSON_UNQUOTE(JSON_EXTRACT({$context},'$.cil')))",
        'vysledek'=>$state,
    ];
    foreach ($filters as $column=>$value) {
        if ($value==='') continue;
        $where[]='('.$expressions[$column].") LIKE ? ESCAPE '!'";
        $params[]='%'.strtr($value,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
    }
}
