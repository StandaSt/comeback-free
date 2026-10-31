<?php
declare(strict_types=1);
/* Co-Link: overeni textu a omezenych stylu zpravy bez HTML a bez zmen databazoveho schematu. */

/** Zachova souvisly text pro hledani; metadata obsahuji pouze pozice a povolene styly. */
function co_zprava_text(string $raw): string
{
    $separator="\n\x1eCO1:";$at=strrpos($raw,$separator);
    if(strlen($raw)>300000) throw new DomainException('Zpráva obsahuje příliš mnoho formátování.',400);
    if($at===false){$text=trim($raw);if(mb_strlen($text)>20000)throw new DomainException('Zpráva může mít nejvýše 20 000 znaků.',400);return $text;}
    $text=substr($raw,0,$at);
    if(mb_strlen($text)>20000)throw new DomainException('Zpráva může mít nejvýše 20 000 znaků.',400);
    $encoded=substr($raw,$at+strlen($separator));$marks=json_decode($encoded,true);
    if(!str_starts_with(ltrim($encoded),'[')||!is_array($marks)||!array_is_list($marks)||count($marks)>2000)throw new DomainException('Neplatné formátování zprávy.',400);
    // JavaScript meri pozice v UTF-16; emoji proto mohou zabirat dve jednotky.
    $length=strlen(mb_convert_encoding($text,'UTF-16LE','UTF-8'))/2;$end=0;$clean=[];
    foreach($marks as $mark){
        if(!is_array($mark)||!isset($mark['s'],$mark['e'])||!is_int($mark['s'])||!is_int($mark['e'])||$mark['s']<$end||$mark['e']<=$mark['s']||$mark['e']>$length)throw new DomainException('Neplatné formátování zprávy.',400);
        $item=['s'=>$mark['s'],'e'=>$mark['e']];$end=$mark['e'];
        foreach(['b','i','u'] as $flag)if(($mark[$flag]??false)===true)$item[$flag]=true;
        if(isset($mark['c'])){if(!in_array($mark['c'],['#b42318','#175cd3','#18794e','#9333a8','#b45f06'],true))throw new DomainException('Neplatná barva textu.',400);$item['c']=$mark['c'];}
        if(count($item)>2)$clean[]=$item;
    }
    if(trim($text)==='')return '';
    return $clean!==[]?$text.$separator.json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR):$text;
}
