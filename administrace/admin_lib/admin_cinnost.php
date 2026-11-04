<?php
declare(strict_types=1);
/* Účel: Překládá novou jednotnou historii do srozumitelných vět a ověřuje její volitelné filtry. */

/** Přehled začíná všemi akcemi bez časového či jiného přednastaveného omezení. */
function cb_admin_cinnost_filtry(array $input): array
{
    $f=['od'=>'','do'=>'','autor'=>0,'akce'=>0,'tiket'=>0,'stav'=>0,'strana'=>1,'pocet'=>50,'smer'=>'desc'];
    foreach (['od','do'] as $key) {
        $value=$input['nc_'.$key] ?? '';
        if (!is_string($value)) throw new CbUserVisibleException('Neplatné datum filtru.');
        if ($value!=='') {
            $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Europe/Prague'));
            if (!$date || $date->format('Y-m-d')!==$value) throw new CbUserVisibleException('Vyberte platné datum.');
        }
        $f[$key]=$value;
    }
    if ($f['od']!=='' && $f['do']!=='' && $f['od']>$f['do']) throw new CbUserVisibleException('Datum Od musí předcházet datu Do.');
    foreach (['autor','akce','tiket','stav','strana','pocet'] as $key) {
        $value=$input['nc_'.$key] ?? $f[$key];
        if ($key==='tiket' && $value==='') $value=0;
        if (!is_scalar($value) || !ctype_digit((string)$value) || (float)$value>2147483647) throw new CbUserVisibleException('Neplatný filtr historie.');
        $f[$key]=(int)$value;
    }
    $f['strana']=max(1,$f['strana']);
    if (!in_array($f['pocet'],[25,50,100],true)) $f['pocet']=50;
    $f['smer']=($input['nc_smer'] ?? '')==='asc'?'asc':'desc';
    return $f;
}

/** Odkazy zachovají pouze filtry nové evidence, nikoli parametry původní historie. */
function cb_admin_cinnost_url(array $filters, array $changes=[]): string
{
    $p=['m'=>'administrace','page'=>'historie_cinnosti'];
    foreach (array_replace($filters,$changes) as $key=>$value) $p['nc_'.$key]=$value;
    return cb_root_url('index.php?'.http_build_query($p,'','&',PHP_QUERY_RFC3986));
}

/** Uživatel čte názvy voleb z číselníku; obsah textových polí zůstává nezveřejněný. */
function cb_admin_cinnost_hodnota(mixed $value, array $rules, bool $allowed): string
{
    if (!$allowed) return 'Obsah se do historie neukládá';
    if ($value===null) return 'Nevybráno';
    if (is_bool($value)) return $value?'Zapnuto':'Vypnuto';
    $label=$rules['popisky'][(string)$value] ?? null;
    if (is_string($label)) return $label;
    if (isset($rules['popisky'])) return 'Neznámá volba';
    return is_scalar($value)?(string)$value:'Údaj není dostupný';
}

/** Šablona pochází výhradně z číselníku, parametry se nahrazují jednou a výstup escapuje pohled. */
function cb_admin_cinnost_popis(array $row): array
{
    $data=json_decode((string)($row['parametry_json'] ?? '{}'),true,16,JSON_THROW_ON_ERROR) ?? [];
    $rules=json_decode((string)($row['pole_pravidla'] ?? '{}'),true,16,JSON_THROW_ON_ERROR) ?? [];
    $target=$row['cil_id']!==null?'tiket č. '.$row['cil_id']:'';
    if ($target!=='' && trim((string)($row['predmet'] ?? ''))!=='') $target.=' – '.$row['predmet'];
    $page=(string)($row['stranka_nazev'] ?? '');
    $field=(string)($row['pole_nazev'] ?? '');
    $old=cb_admin_cinnost_hodnota($data['puvodni'] ?? null,$rules,(bool)($row['hodnoty_povoleny'] ?? false));
    $new=cb_admin_cinnost_hodnota($data['nova'] ?? null,$rules,(bool)($row['hodnoty_povoleny'] ?? false));
    $text=strtr((string)$row['sablona'],['{modul}'=>'Helpdesk','{stranka}'=>$page,'{cil}'=>$target,
        '{pole}'=>$field,'{puvodni}'=>$old,'{nova}'=>$new,'{smer}'=>($data['smer'] ?? 1)===1?'vzestupně':'sestupně',
        '{strana}'=>(string)($data['strana'] ?? ''),'{pocet}'=>(string)($data['pocet'] ?? '')]);
    // Neúplný číselník nesmí zobrazit technický zástupný symbol jako vysvětlení akce.
    if (preg_match('/\{[a-z_]+\}/',$text)) $text=(string)$row['akce_nazev'];
    $fields=['Co udělal'=>$text];
    if ($page!=='') $fields['Kde']=$page;
    if ($target!=='') $fields['Tiket']=$target;
    if ($field!=='') $fields['Pole']=$field;
    if (array_key_exists('puvodni',$data)) $fields['Před změnou']=$old;
    if (array_key_exists('nova',$data)) $fields['Po změně']=$new;
    if ((int)$row['id_akce']===6001) $fields['Změna']='Upravil obsah pole';
    $time=(new DateTimeImmutable($row['cas_udalosti'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Europe/Prague'));
    return ['text'=>$text,'cil'=>$target,'fields'=>$fields,'cas'=>cb_format('dcs',$time->format('Y-m-d H:i:s'))];
}
