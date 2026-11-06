<?php
/* E-mail: bezpecny zaznam chyb a konkretni administratorske hlaseni s osobou, schrankou, pobockou a operaci. */
declare(strict_types=1);

/** Operace a faze jsou technicke identifikatory; neprijimame do nich adresy ani obsah formulare. */
function em_chyba_identifikator(string $value): string
{
    return preg_match('/^[a-zA-Z0-9_]{1,60}$/D',$value)?$value:'unknown';
}

/** Typ vyjimky urcuje pouze prokazanou pricinu; text protokolu muze obsahovat heslo a nesmi se pouzit. */
function em_chyba_popis(Throwable $error): string
{
    if ($error instanceof EmailChyba) return $error->getMessage();
    $chain=[];for($e=$error;$e && count($chain)<8;$e=$e->getPrevious())$chain[]=get_class($e);
    if(in_array('Webklex\\PHPIMAP\\Exceptions\\AuthFailedException',$chain,true))return 'Poštovní server odmítl přihlášení ke schránce. Ověřte její přihlašovací údaje nebo povolení přístupu přes IMAP.';
    if(in_array('Webklex\\PHPIMAP\\Exceptions\\ConnectionFailedException',$chain,true))return 'Nepodařilo se navázat spojení s poštovním serverem. Chyba nepotvrzuje nesprávné heslo.';
    if($error instanceof mysqli_sql_exception)return match((int)$error->getCode()){
        1054=>'V databázi IS chybí sloupec potřebný pro práci s poštou.',
        1146=>'V databázi IS chybí tabulka potřebná pro práci s poštou.',
        2002,2003,2006,2013=>'IS ztratil spojení s databází nebo se k ní nedokázal připojit.',
        1205,1213=>'Databázovou operaci pošty zastavil souběh jiných požadavků. Akci lze zopakovat.',
        default=>'Při práci s poštou selhala databázová operace IS.',
    };
    if($error instanceof TypeError || $error instanceof Error)return 'Při zpracování pošty nastala chyba aplikace IS.';
    return 'Poštovní operace selhala. Přesnou příčinu se nepodařilo určit; údaje pro její dohledání jsou v detailu chyby.';
}

/** Do zaznamu patri jen poloha a nazvy funkci, nikdy argumenty zasobniku nebo zprava protokolove vyjimky. */
function em_chyba_technicka(Throwable $error): array
{
    $out=[];
    for($e=$error;$e && count($out)<8;$e=$e->getPrevious()){
        $frames=[];
        foreach(array_slice($e->getTrace(),0,12) as $frame)$frames[]=['file'=>basename((string)($frame['file']??'')),'line'=>(int)($frame['line']??0),'function'=>(string)($frame['class']??'').(string)($frame['type']??'').(string)($frame['function']??'')];
        $out[]=['type'=>get_class($e),'code'=>(int)$e->getCode(),'file'=>basename($e->getFile()),'line'=>$e->getLine(),'trace'=>$frames];
    }
    return $out;
}

/** Popis operace je z naseho slovniku; zadny volny text prohlizece se do push neprebira. */
function em_chyba_operace(string $action): string
{
    return ['background'=>'Kontrola nepřečtené pošty','row_status'=>'Obnova stavů zpráv','state'=>'Načtení E-mailu',
        'loadState'=>'Načtení E-mailu','loadAccount'=>'Načtení schránky','folders'=>'Načtení složek','list'=>'Načtení seznamu zpráv',
        'loadList'=>'Načtení seznamu zpráv','message'=>'Otevření zprávy','openMessage'=>'Otevření zprávy','attachment'=>'Načtení přílohy',
        'openAttachment'=>'Otevření přílohy','send'=>'Odeslání zprávy','draft'=>'Uložení konceptu','move'=>'Přesun zprávy',
        'bulk_move'=>'Hromadný přesun','bulk_trash'=>'Hromadný přesun do koše nebo smazání','trash'=>'Přesun do koše nebo smazání',
        'delete'=>'Trvalé smazání','refresh'=>'Obnova schránky','read'=>'Změna přečtení','flag'=>'Změna označení',
        'shared_password'=>'Ověření připojení schránky','shared_access'=>'Uložení přístupů','shared_remove'=>'Odpojení sdílené schránky',
        'connect'=>'Připojení schránky','unlock'=>'Odemčení schránky','client'=>'Práce s poštou v prohlížeči'][$action]??'Poštovní operace ('.$action.')';
}

/** Pricinu odvozujeme z typu a pojmenovane faze zasobniku; AUTH text muze obsahovat heslo. */
function em_chyba_pricina(array $row): string
{
    $kind=$row['client']['kind']??'';
    if($kind==='network')return 'Prohlížeč nedostal odpověď z IS. Spojení se přerušilo; výsledek operace není potvrzený.';
    if($kind!=='')return 'Chyba v prohlížeči: '.$kind.'. Přesná příčina nebyla zaznamenána.';
    foreach($row['exceptions'] as $source){
        if($source['type']==='Webklex\\PHPIMAP\\Exceptions\\ImapServerErrorException'){
            foreach($source['trace'] as $frame)if(str_ends_with($frame['function'],'->login') || str_ends_with($frame['function'],'->authenticate'))return 'Poštovní server odmítl přihlášení přes IMAP. Přesný důvod nebyl zaznamenán.';
            return 'Poštovní server odmítl IMAP požadavek. Přesný důvod nebyl zaznamenán.';
        }
    }
    return (string)($row['description']??'Při zpracování pošty nastala technická chyba.');
}

/** Jmeno a identitu schranky cteme na serveru; neprebirame je z prihlasovaciho formulare ani klienta. */
function em_chyba_kontext(mysqli $db, array $row): array
{
    $out=['person'=>'Uživatel ID '.$row['user'],'mailbox'=>'Schránka nebyla určena','branch'=>'Pobočka nebyla určena'];
    if(($row['scope']??'')==='aggregate')$out['mailbox']='Souhrn více schránek';
    try{
        require_once __DIR__.'/../common/lib/osoba_jmeno.php';
        $s=$db->prepare('SELECT id_person FROM hr_person WHERE id_user=?');$s->bind_param('i',$row['user']);$s->execute();$person=$s->get_result()->fetch_assoc();$s->close();
        if($person){$people=cb_osoby_nacist($db,[(int)$person['id_person']]);$name=cb_osoba_jmeno($people[(int)$person['id_person']]??[]);if($name!=='')$out['person']=$name;}
    }catch(Throwable){/* I pri vypadku HR musi zustat puvodni chyba dohledatelna podle ID osoby. */}
    if($row['account']>0){
        try{
            $s=$db->prepare('SELECT a.email,a.typ,p.nazev FROM email_ucet a LEFT JOIN pobocka p ON p.id_pob=a.id_pob AND p.id_firma=a.id_firma WHERE a.id=?');
            $s->bind_param('i',$row['account']);$s->execute();$a=$s->get_result()->fetch_assoc();$s->close();
            if($a){$out['mailbox']=$a['email'];$out['branch']=$a['typ']==='pobocka'?($a['nazev']?:'Pobočka nebyla určena'):($a['typ']==='osobni'?'Osobní schránka':'Sdílená schránka');}
        }catch(Throwable){/* Selhani doplneni nazvu nesmi zmenit puvodni vyjimku. */}
    }
    foreach($out as &$value)$value=mb_substr(preg_replace('/[\x00-\x1f\x7f]/u',' ',(string)$value)??'',0,200);unset($value);
    return $out;
}

/** Soukromy denni log je omezeny velikosti; jeho selhani nesmi prepsat puvodni chybu. */
function em_chyba_zapis(array $row): bool
{
    try{
        $dir=dirname(__DIR__,2).'/data/email/chyby';
        if(!is_dir($dir) && !@mkdir($dir,0700,true) && !is_dir($dir))return false;
        if(!is_file($dir.'/.htaccess') && @file_put_contents($dir.'/.htaccess',"# Soukrome chyby E-mailu.\nRequire all denied\n",LOCK_EX)===false)return false;
        $time=new DateTimeImmutable('now',new DateTimeZone('Europe/Prague'));
        $line=json_encode(['at'=>$time->format(DATE_ATOM)]+$row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR)."\n";
        $file=@fopen($dir.'/'.$time->format('Y-m-d').'.jsonl','ab');if(!$file)return false;
        try{if(!flock($file,LOCK_EX))return false;$size=fstat($file)['size']??0;return $size+strlen($line)<=16777216 && fwrite($file,$line)===strlen($line);}
        finally{fclose($file);}
    }catch(Throwable){return false;}
}

/** Log i push dostanou srozumitelny kontext; opakovany stejny problem notifikujeme nejvyse jednou za pet minut. */
function em_chyba_db(array $row): bool
{
    try{
        require_once __DIR__.'/../common/db/zapis_log_chyby.php';
        $conn=db();$source=$row['exceptions'][0];
        $signature=[$row['user'],$row['account'],$row['action'],$row['phase'],$source['type'],$source['code'],$source['file'],$source['line'],$row['folder']??'',$row['client']];
        $code='EMAIL_'.substr(hash('sha256',json_encode($signature,JSON_THROW_ON_ERROR)),0,24);
        $query=$conn->prepare('SELECT 1 FROM log_chyby WHERE modul="EMAIL" AND kod=? AND kdy>=DATE_SUB(NOW(),INTERVAL 5 MINUTE) LIMIT 1');
        $query->bind_param('s',$code);$query->execute();$sendPush=$query->get_result()->fetch_row()===null;$query->close();
        $row['context']=em_chyba_kontext($conn,$row);$row['operation']=em_chyba_operace($row['action']);$row['cause']=em_chyba_pricina($row);
        $summary=$row['context']['person'].' · '.$row['context']['mailbox']."\n".$row['operation'].': '.$row['cause'];
        if(!empty($row['folder']))$summary.="\nSložka: ".$row['folder'];
        $data=json_encode($row,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
        // Titulek ponecha misto pro kanonicke ID, ktere doplni spolecny odesilac.
        db_zapis_log_chyby($conn,$row['user']>0?$row['user']:null,'EMAIL',$row['action'],$code,
            $summary,$data,$source['file'],$source['line'],null,$data,0,null,$sendPush,'E-mail · '.mb_substr($row['context']['branch'],0,60),'EMAIL_ERROR_ADMIN');
        return true;
    }catch(Throwable $error){
        // Selhani DB nebo notifikace nesmi vyvolat dalsi administratorske hlaseni v nekonecne smycce.
        error_log('EMAIL_ERROR_DB type='.get_class($error));return false;
    }
}

/** Stejny kod spojuje uzivatelsky detail, DB a soubor; protokolova data zustavaji v povolenem bezpecnem rozsahu. */
function em_chyba_vysledek(Throwable $error, array $context=[]): array
{
    $id=bin2hex(random_bytes(8));$action=em_chyba_identifikator((string)($context['action']??'unknown'));$phase=em_chyba_identifikator((string)($context['phase']??'unknown'));
    $technical=em_chyba_technicka($error);
    $client=[];
    if(isset($context['client_kind']))$client=['kind'=>em_chyba_identifikator((string)$context['client_kind']),'line'=>max(0,min(100000,(int)($context['client_line']??0))),'status'=>max(0,min(599,(int)($context['client_status']??0)))];
    $row=['id'=>$id,'user'=>(int)($_SESSION['cb_user']['id_user']??0),'account'=>(int)($context['account']??0),'action'=>$action,'phase'=>$phase,'exceptions'=>$technical,'client'=>$client,
        'scope'=>in_array($context['scope']??'',['account','aggregate','none'],true)?$context['scope']:'none','description'=>em_chyba_popis($error)];
    // Jen odmitnuti STATUS po uspesnem prihlaseni: odpoved neobsahuje telo posty ani prihlasovaci prikaz.
    // U jinych fazi (zejmena AUTH) volny text protokolu nadale nikdy neprebirame.
    $folder=mb_substr(preg_replace('/[\x00-\x1f\x7f]/u','',(string)($context['folder']??''))??'',0,1000);$statusReply='';
    if($folder!=='')$row['folder']=$folder;
    if($action==='folders' && $phase==='folder_status'){
        $folder=mb_substr(preg_replace('/[\x00-\x1f\x7f]/u','',(string)($context['folder']??''))??'',0,1000);
        if(is_a($error,'Webklex\\PHPIMAP\\Exceptions\\ImapServerErrorException') && preg_match('/^(NO|BAD|BYE)\b/i',trim($error->getMessage()))){
            $statusReply=mb_substr(preg_replace('/[\x00-\x1f\x7f]/u',' ',trim($error->getMessage()))??'',0,500);
        }
        $row['folder']=$folder;$row['status_reply']=$statusReply;
    }
    $logged=em_chyba_zapis($row);$dbLogged=em_chyba_db($row);
    $fallbackLogged=false;if(!$logged && !$dbLogged)$fallbackLogged=error_log('EMAIL_ERROR '.json_encode($row,JSON_INVALID_UTF8_SUBSTITUTE));
    $detail='Kód chyby: '.$id."\nOperace: ".$action."\nFáze: ".$phase."\nTyp: ".$technical[0]['type']."\nTechnický kód: ".$technical[0]['code']."\nMísto: ".$technical[0]['file'].':'.$technical[0]['line'];
    if($folder!=='')$detail.="\nSložka: ".$folder;
    if($statusReply!=='')$detail.="\nOdpověď na STATUS: ".$statusReply;
    if($client)$detail.="\nProhlížeč: ".$client['kind'].($client['line']?' · email.js:'.$client['line']:'').($client['status']?' · HTTP '.$client['status']:'');
    $detail.=($logged || $dbLogged)?"\nZáznam chyby byl uložen.":($fallbackLogged?"\nÚdaje byly předány serverovému chybovému logu.":"\nZáznam chyby se nepodařilo uložit.");
    $detail.=$dbLogged?"\nDetaily: databázová tabulka log_chyby.":"\nZápis do DB se nepodařil; zkontrolujte záložní souborový nebo serverový log.";
    return ['error'=>em_chyba_popis($error),'error_detail'=>$detail,'error_logged'=>$logged || $dbLogged || $fallbackLogged];
}
