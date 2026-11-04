<?php
/* E-mail: pocet priloh z IMAP BODYSTRUCTURE bez stahovani tel; cte puvodni odpoved, protoze parser knihovny muze ztratit vnorenou strukturu. */
declare(strict_types=1);

/** Omezeny parser seznamu IMAP respektuje uvozovky, escapovani i literalni retezce a obsah priloh vubec nepotrebuje. */
function em_imap_struktura_hodnota(string $raw, int &$pos, int $depth=0): mixed
{
    $length=strlen($raw);
    if ($depth>64 || $length>2*1024*1024) throw new RuntimeException('Neplatna struktura IMAP.');
    while($pos<$length && ctype_space($raw[$pos]))$pos++;
    if($pos>=$length)throw new RuntimeException('Neuplna struktura IMAP.');
    $char=$raw[$pos++];
    if($char==='('){
        $items=[];
        while(true){
            while($pos<$length && ctype_space($raw[$pos]))$pos++;
            if($pos>=$length)throw new RuntimeException('Neuplny seznam IMAP.');
            if($raw[$pos]===')'){$pos++;return $items;}
            $items[]=em_imap_struktura_hodnota($raw,$pos,$depth+1);
        }
    }
    if($char==='"'){
        $text='';
        while($pos<$length){$c=$raw[$pos++];if($c==='"')return $text;if($c==='\\'){if($pos>=$length)break;$c=$raw[$pos++];}$text.=$c;}
        throw new RuntimeException('Neuplny retezec IMAP.');
    }
    if($char==='{'){
        if(!preg_match('/\G(\d+)\+?}\r?\n/',$raw,$match,0,$pos))throw new RuntimeException('Neplatny literal IMAP.');
        $pos+=strlen($match[0]);$bytes=(int)$match[1];
        if($bytes>$length-$pos)throw new RuntimeException('Neuplny literal IMAP.');
        $text=substr($raw,$pos,$bytes);$pos+=$bytes;return $text;
    }
    $start=$pos-1;
    while($pos<$length && !ctype_space($raw[$pos]) && $raw[$pos]!==')' && $raw[$pos]!=='(')$pos++;
    $atom=substr($raw,$start,$pos-$start);
    return strcasecmp($atom,'NIL')===0?null:$atom;
}

/** Pojmenovane parametry zahrnuji RFC2231 filename* i rozdelene name*0*, hodnotu jmena pro pocet nemusime dekodovat. */
function em_struktura_ma_jmeno(mixed $params): bool
{
    if(!is_array($params))return false;
    for($i=0;$i<count($params)-1;$i+=2)if(is_string($params[$i]) && preg_match('/^(name|filename)(\*.*)?$/i',$params[$i]) && $params[$i+1]!==null && $params[$i+1]!=='')return true;
    return false;
}

/** Pocitame stejne souborove casti jako detail zpravy, vcetne pojmenovanych vlozenych obrazku; tela plain/html nejsou prilohou. */
function em_struktura_pocet(array $part): int
{
    if(is_array($part[0]??null)){
        $count=0;foreach($part as $child){if(!is_array($child))break;$count+=em_struktura_pocet($child);}return $count;
    }
    if(count($part)<7)throw new RuntimeException('Neuplna cast IMAP.');
    $type=strtolower((string)($part[0]??''));$subtype=strtolower((string)($part[1]??''));
    $index=$type==='text'?9:($type==='message' && $subtype==='rfc822'?11:8);
    $disposition=is_array($part[$index]??null)?$part[$index]:[];
    $disp=strtolower((string)($disposition[0]??''));
    $name=em_struktura_ma_jmeno($part[2]??null)||em_struktura_ma_jmeno($disposition[1]??null);
    if($type==='text' && in_array($subtype,['plain','html',''],true) && !in_array($disp,['attachment','inline'],true) && !$name)return 0;
    if($disp==='inline' && !$name && empty($part[3]))return 0;
    return 1;
}

/** Z cele odpovedi FETCH cteme UID i BODYSTRUCTURE; retezec uvnitr literalniho nazvu se nikdy nepovazuje za dalsi odpoved. */
function em_struktura_pocty(string $raw): array
{
    $counts=[];$pos=0;
    while(preg_match('/^\* \d+ FETCH\s+/im',$raw,$match,PREG_OFFSET_CAPTURE,$pos)){
        $pos=$match[0][1]+strlen($match[0][0]);$fields=em_imap_struktura_hodnota($raw,$pos);
        if(!is_array($fields))throw new RuntimeException('Neplatna odpoved FETCH.');
        $uid=0;$body=null;
        for($i=0;$i<count($fields)-1;$i+=2){
            $key=strtoupper((string)$fields[$i]);if($key==='UID')$uid=(int)$fields[$i+1];if($key==='BODYSTRUCTURE')$body=$fields[$i+1];
        }
        if($uid>0 && is_array($body))$counts[$uid]=em_struktura_pocet($body);
    }
    return $counts;
}
