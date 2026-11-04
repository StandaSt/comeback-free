<?php
/* E-mail: MIME hlavicky, znakove sady a cteni jednotlivych casti pres BODY.PEEK bez stahovani vsech priloh. */
declare(strict_types=1);
require_once __DIR__.'/struktura.php';

/** Nazvy MIME znakovych sad prevedeme na jednoznacne podporovane varianty. */
function em_mime_charset(string $value): string
{
    $value=strtolower(trim($value," \t\r\n\"'"));
    if($value!=='' && !preg_match('/^[a-z0-9][a-z0-9_-]{0,40}$/D',$value))return '';
    return match($value) {'utf8','utf-8'=>'UTF-8','windows-1250','cp1250','win1250'=>'Windows-1250','iso-8859-2','iso8859-2','latin2'=>'ISO-8859-2','ascii','us-ascii'=>'ASCII',default=>$value};
}

/** Zadny //IGNORE: neplatne bajty se nesmi pri prevodu potichu ztratit. Nejasny vysledek lze zmenit v UI. */
function em_mime_text(string $bytes, string $declared='', string $override='auto'): array
{
    if (!in_array($override,['auto','UTF-8','Windows-1250','ISO-8859-2'],true)) throw new EmailChyba('Neplatná znaková sada.');
    $declared=em_mime_charset($declared); $uncertain=false;
    if ($override!=='auto') $charset=$override;
    elseif (str_starts_with($bytes,"\xEF\xBB\xBF")) { $bytes=substr($bytes,3); $charset='UTF-8'; }
    elseif (str_starts_with($bytes,"\xFF\xFE")) { $bytes=substr($bytes,2); $charset='UTF-16LE'; }
    elseif (str_starts_with($bytes,"\xFE\xFF")) { $bytes=substr($bytes,2); $charset='UTF-16BE'; }
    elseif (preg_match('/^utf-(?:16|32)(?:le|be)?$/iD',$declared)) $charset=$declared;
    elseif (mb_check_encoding($bytes,'UTF-8')) { $charset='UTF-8'; }
    else {
        $charset=$declared;
        // HTML meta je pouzitelna, kdyz MIME chybi nebo zjevne nesouhlasi s bajty.
        if (in_array($charset,['','UTF-8','ASCII'],true)) {
            if (preg_match('/<meta\b[^>]*charset\s*=\s*["\x27]?\s*([a-z0-9_-]+)/i',substr($bytes,0,8192),$m)) $charset=em_mime_charset($m[1]);
            if (in_array($charset,['','UTF-8','ASCII'],true)) { $charset='Windows-1250'; $uncertain=true; }
        }
    }
    if ($charset==='UTF-8' && mb_check_encoding($bytes,'UTF-8')) return ['text'=>$bytes,'charset'=>$charset,'uncertain'=>$uncertain];
    $converted=@iconv($charset,'UTF-8',$bytes);
    if ($converted===false) {
        if ($override!=='auto') throw new EmailChyba('Zvolenou znakovou sadou nelze zprávu úplně dekódovat.');
        $charset='ISO-8859-2'; $converted=iconv($charset,'UTF-8',$bytes); $uncertain=true;
    }
    // Ridici znaky C1 typicky znamenaji, ze odesilatel oznacil Windows text jako ISO.
    if ($override==='auto' && preg_match('/[\x{0080}-\x{009F}]/u',$converted)) {
        $candidate=@iconv('Windows-1250','UTF-8',$bytes);
        if ($candidate!==false && !preg_match('/[\x{0080}-\x{009F}]/u',$candidate)) { $converted=$candidate; $charset='Windows-1250'; $uncertain=true; }
    }
    return ['text'=>$converted,'charset'=>$charset,'uncertain'=>$uncertain];
}

/** MIME encoded-word lze mit uprostred predmetu, v uvozovkach i na vice radcich. */
function em_mime_hlavicka(?string $value): string
{
    $value=$value??'';
    if (str_contains($value,'=?')) {
        $decoded=@iconv_mime_decode($value,ICONV_MIME_DECODE_CONTINUE_ON_ERROR,'UTF-8');
        if (is_string($decoded) && $decoded!=='') $value=$decoded;
    }
    return em_mime_text($value)['text'];
}

/** RFC2231 pouziva procenta, nikoli formularove plus; navazujici dilky spojime pred dekodovanim. */
function em_mime_parametry(mixed $values): array
{
    $out=[]; $segments=[];
    if (!is_array($values)) return [];
    for($i=0;$i<count($values)-1;$i+=2) {
        $key=strtolower((string)$values[$i]); $value=(string)$values[$i+1];
        if (preg_match('/^([^*]+)\*(\d+)(\*)?$/D',$key,$m)) $segments[$m[1]][(int)$m[2]]=['value'=>$value,'encoded'=>isset($m[3])];
        elseif (str_ends_with($key,'*')) $out[substr($key,0,-1)]=em_mime_parametr($value,true);
        else $out[$key]??=em_mime_hlavicka($value);
    }
    foreach($segments as $key=>$parts) {
        ksort($parts); $raw='';$encoded=false;$expected=0;
        foreach($parts as $index=>$part) { if($index!==$expected++)continue 2; $raw.=$part['encoded']?rawurldecode($part['value']):$part['value']; $encoded=$encoded||$part['encoded']; }
        $out[$key]=em_mime_parametr($raw,$encoded,false);
    }
    return $out;
}

/** Rozsireny parametr muze obsahovat i jazyk mezi apostrofy. */
function em_mime_parametr(string $raw, bool $encoded, bool $percent=true): string
{
    $charset='';
    if ($encoded && preg_match("/^([^']*)'[^']*'(.*)$/s",$raw,$m)) { $charset=$m[1];$raw=$m[2]; }
    if ($encoded && $percent) $raw=rawurldecode($raw);
    return $encoded?em_mime_text($raw,$charset)['text']:em_mime_hlavicka($raw);
}

/** Strom BODYSTRUCTURE prevede na MIME casti se stabilnimi serverovymi cisly. Vnorene EML zustava prilohou. */
function em_mime_casti(array $node, string $prefix='', int $depth=0): array
{
    if($depth>32)throw new EmailChyba('Zpráva má příliš složitou MIME strukturu.');
    if (is_array($node[0]??null)) {
        $out=[];foreach($node as $i=>$child){if(!is_array($child))break;$out=array_merge($out,em_mime_casti($child,$prefix===''?(string)($i+1):$prefix.'.'.($i+1),$depth+1));if(count($out)>500)throw new EmailChyba('Zpráva má příliš mnoho MIME částí.');}return $out;
    }
    if(count($node)<7)throw new EmailChyba('Server poskytl neúplnou strukturu zprávy.');
    $type=strtolower((string)$node[0]); $sub=strtolower((string)$node[1]); $params=em_mime_parametry($node[2]);
    $di=$type==='text'?9:($type==='message'&&$sub==='rfc822'?11:8);$disp=is_array($node[$di]??null)?$node[$di]:[];
    $dp=em_mime_parametry($disp[1]??null);$name=$dp['filename']??$params['name']??'';$disposition=strtolower((string)($disp[0]??''));
    $body=$type==='text'&&in_array($sub,['plain','html'],true)&&$name===''&&$disposition!=='attachment';
    $mimeType=$type.'/'.$sub;if(!preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~iD',$mimeType))$mimeType='application/octet-stream';
    $cid=trim((string)($node[3]??''),'<>');if(!preg_match('/^[^\x00-\x20\x7f<>]{1,998}$/D',$cid))$cid='';
    return [['part'=>$prefix?:'1','type'=>$mimeType,'charset'=>$params['charset']??'','encoding'=>strtoupper((string)$node[5]),'size'=>max(0,(int)$node[6]),
        'cid'=>$cid,'name'=>$name?:($body?'':($type==='message'?'zprava.eml':'příloha')),'disposition'=>$disposition,'body'=>$body]];
}

/** Strukturu cteme z puvodni odpovedi, parser knihovny muze ztratit vnoreny seznam. */
function em_mime_struktura(Webklex\PHPIMAP\Client $client, int $uid): array
{
    $response=$client->getConnection()->fetch(['UID','BODYSTRUCTURE'],[$uid],null,Webklex\PHPIMAP\IMAP::ST_UID)->validate();
    $raw=implode('',$response->getResponse());$pos=0;
    while(preg_match('/^\* \d+ FETCH\s+/im',$raw,$m,PREG_OFFSET_CAPTURE,$pos)) {
        $pos=$m[0][1]+strlen($m[0][0]);$fields=em_imap_struktura_hodnota($raw,$pos);$id=0;$body=null;
        for($i=0;$i<count($fields)-1;$i+=2){if(strtoupper((string)$fields[$i])==='UID')$id=(int)$fields[$i+1];if(strtoupper((string)$fields[$i])==='BODYSTRUCTURE')$body=$fields[$i+1];}
        if($id===$uid && is_array($body))return em_mime_casti($body);
    }
    throw new EmailChyba('Server neposkytl strukturu zprávy.');
}

/** Omezeny FETCH vzdy pouziva PEEK; ani stazeni originalu nezmeni Seen na serveru. */
function em_mime_blok(Webklex\PHPIMAP\Client $client, int $uid, string $section, int $offset, int $length=65536): string
{
    if ($section!=='' && !preg_match('/^[1-9][0-9]*(?:\.[1-9][0-9]*)*$/D',$section)) throw new EmailChyba('Neplatná část zprávy.');
    $result=$client->getConnection()->fetch(['UID','BODY.PEEK['.$section.']<'.$offset.'.'.$length.'>'],[$uid],null,Webklex\PHPIMAP\IMAP::ST_UID)->validatedData();
    foreach((array)($result[$uid]??[]) as $key=>$value)if(preg_match('/^BODY\['.preg_quote($section,'/').'\](?:<\d+>)?$/i',(string)$key) && is_string($value))return $value;
    throw new EmailChyba('Server nevrátil požadovanou část zprávy.');
}

/** Nacitame jen vybranou cast, predem odmitneme nadlimitni telo ci prilohu misto vycerpani pameti. */
function em_mime_obsah(Webklex\PHPIMAP\Client $client, int $uid, array $part, int $limit=20971520): string
{
    if($part['size']>$limit)throw new EmailChyba('Tato část zprávy přesahuje limit načtení. Můžete stáhnout originál zprávy.');
    $raw='';for($offset=0;$offset<$part['size'];$offset+=65536){$chunk=em_mime_blok($client,$uid,$part['part'],$offset);$raw.=$chunk;if(strlen($chunk)<65536)break;}
    if(strlen($raw)<$part['size'])throw new EmailChyba('Server vrátil neúplnou část zprávy. Načtení opakujte.');
    $decoded=match($part['encoding']) {'BASE64'=>base64_decode($raw,true),'QUOTED-PRINTABLE'=>quoted_printable_decode($raw),default=>$raw};
    if($decoded===false)throw new EmailChyba('Část zprávy obsahuje neplatné přenosové kódování. Můžete stáhnout originál.');
    return $decoded;
}

/** Detail zpravy vraci telo a metadata, ale nikdy automaticky nestahuje obsah souborovych priloh. */
function em_mime_detail(Webklex\PHPIMAP\Client $client, array $ref, string $override='auto'): array
{
    $parts=em_mime_struktura($client,(int)$ref['uid']);$text=[];$html=[];$files=[];$charsets=[];$uncertain=false;
    if(array_sum(array_column(array_filter($parts,static fn($p)=>$p['body']),'size'))>8*1024*1024)throw new EmailChyba('Tělo zprávy přesahuje limit náhledu. Můžete stáhnout originál.');
    foreach($parts as $part) {
        if(!$part['body']){$files[]=$part;continue;}
        $decoded=em_mime_text(em_mime_obsah($client,(int)$ref['uid'],$part,4194304),$part['charset'],$override);
        if($part['type']==='text/html')$html[]=$decoded['text'];else $text[]=$decoded['text'];
        $charsets[]=$decoded['charset'];$uncertain=$uncertain||$decoded['uncertain'];
    }
    return ['text'=>implode("\n",$text),'html'=>implode("\n",$html),'attachments'=>$files,'encoding'=>['selected'=>$override,'detected'=>array_values(array_unique($charsets)),'uncertain'=>$uncertain]];
}

/** Vlastni koncept oddeluje editovatelny text a puvodni citaci; volajici nejprve overuje sifrovany kontext. */
function em_mime_koncept_casti(string $html): array
{
    $doc=new DOMDocument();$old=libxml_use_internal_errors(true);
    try{$doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
    finally{libxml_clear_errors();libxml_use_internal_errors($old);}
    $out=['editor'=>'','quote'=>''];$body=$doc->getElementsByTagName('body')->item(0);
    if(!$body)return $out;
    foreach($body->childNodes as $node)if($node instanceof DOMElement)foreach(['editor','quote'] as $key)if($node->getAttribute('data-em-draft-'.$key)==='1')foreach($node->childNodes as $child)$out[$key].=$doc->saveHTML($child);
    return $out;
}

/** Citaci sestavuje server z autentizovane puvodni zpravy, ne z pozmenitelneho HTML formulare. */
function em_mime_citace(array $detail, Webklex\PHPIMAP\Message $message, string $kind): string
{
    $subject=htmlspecialchars(em_mime_hlavicka((string)$message->subject),ENT_QUOTES,'UTF-8');
    $from=htmlspecialchars(implode(', ',array_map(static fn($a)=>($a['name']!==''?$a['name'].' ':'').'<'.$a['email'].'>',em_adresy($message->from))),ENT_QUOTES,'UTF-8');
    $body=$detail['html']!==''?em_zobrazeni_html($detail['html'],true,true):'<pre>'.htmlspecialchars($detail['text'],ENT_QUOTES,'UTF-8').'</pre>';
    return '<p>'.($kind==='forward'?'Přeposlaná zpráva':'Původní zpráva').': <strong>'.$subject.'</strong><br>Od: '.$from.'</p>'.$body;
}

/** Pri odpovedi preneseme pouze vlozene obrazky; pri preposlani i bezne prilohy. */
function em_mime_soubory(Webklex\PHPIMAP\Client $client, array $ref, array $parts, bool $all): array
{
    $files=[];$size=0;
    foreach($parts as $part) {
        if(!$all && $part['cid']==='')continue;
        $size+=$part['size'];if($size>28*1024*1024 || count($files)>=20)throw new EmailChyba('Původní přílohy přesahují limit odeslání.');
        $files[]=['name'=>em_nazev_prilohy($part['name']),'content'=>em_mime_obsah($client,(int)$ref['uid'],$part),'type'=>$part['type'],'cid'=>$part['cid']];
    }
    return $files;
}
