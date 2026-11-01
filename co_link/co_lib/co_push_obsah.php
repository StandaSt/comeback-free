<?php
/* Co-Link: sestavi obsah oznameni z ulozene zpravy bez metadat editoru. */
declare(strict_types=1);
require_once __DIR__ . '/../../common/lib/format.php';

/** Pripravi kratky prosty text bez metadat editoru; cas vzdy patri ulozene zprave. */
function co_push_obsah(string $baseUrl, array $message): array
{
    $name=trim(preg_replace('/[\s\p{Cc}]+/u',' ',(string)($message['jmeno']??''))??'');
    // Vejdou se vsechny HR casti jmena i titul; puvodni limit 100 by mohl titul odriznout.
    $name=mb_substr($name!==''?$name:'Uživatel',0,260,'UTF-8');
    // Za oddelovacem jsou styly editoru, ktere do systemove notifikace nepatri.
    $text=explode("\n\x1eCO1:",(string)$message['text'],2)[0];
    $text=trim(preg_replace('/[\s\p{Cc}]+/u',' ',$text)??'');
    if(mb_strlen($text,'UTF-8')>120) $text=mb_substr($text,0,119,'UTF-8').'…';
    if($text==='') $text='Poslal(a) přílohu.';
    $time=new DateTimeImmutable((string)$message['vytvoreno'],new DateTimeZone('Europe/Prague'));
    return ['konverzace'=>(int)$message['konverzace'],'url'=>$baseUrl.'?konverzace='.$message['konverzace'],
        'body'=>cb_format('t',$message['vytvoreno']).' · '.$name."\n".$text,'timestamp'=>$time->getTimestamp()*1000];
}
