<?php
/* E-mail: IMAP/SMTP a vazby na samostatne MIME/HTML zobrazeni; editor ma vlastni prisne formatovani, citace zachova MIME obsah. */
declare(strict_types=1);
require_once __DIR__.'/core.php';
require_once __DIR__.'/../common/lib/diagnostika_vykonu.php';
require_once __DIR__.'/mime.php';
require_once __DIR__.'/zobrazeni.php';

/** Oddělený Composer nemění balíčky, které používají ostatní moduly IS. */
function em_knihovny(): void
{
    foreach ([dirname(__DIR__,2).'/vendor/autoload.php',dirname(__DIR__,2).'/vendor-email/autoload.php'] as $file) {
        if (!is_file($file)) throw new EmailChyba('Nejsou nainstalované knihovny e-mailového klienta.');
        require_once $file;
    }
}

/** Host ani zabezpečení se nepřebírají z formuláře; cas pripojeni neobsahuje heslo ani adresu. */
function em_imap(array $a, ?string $password=null): Webklex\PHPIMAP\Client
{
    em_knihovny(); $c=em_config()['imap'];
    $cm=new Webklex\PHPIMAP\ClientManager(em_imap_options());
    $client=$cm->make(['host'=>$c['host'],'port'=>(int)$c['port'],'encryption'=>$c['encryption'],'validate_cert'=>true,
        'username'=>$a['email'],'password'=>$password??em_heslo($a),'protocol'=>'imap','timeout'=>12]);
    $diagStart=cb_diag_clock();
    try {$client->connect();} finally {cb_diag_span('imap_connect',$diagStart);}
    return $client;
}

/** Hlavičky dekoduje iconv i pri prefixu, uvozovkach a jine znakove sade nez UTF-8. */
function em_imap_options(): array
{
    require_once __DIR__.'/mime_hlavicky.php';
    return ['options'=>['fetch'=>Webklex\PHPIMAP\IMAP::FT_PEEK,'sequence'=>Webklex\PHPIMAP\IMAP::ST_UID,'soft_fail'=>false],
        'decoding'=>['decoder'=>['header'=>EmailMimeHeaderDecoder::class,'message'=>Webklex\PHPIMAP\Decoder\MessageDecoder::class,'attachment'=>Webklex\PHPIMAP\Decoder\AttachmentDecoder::class],
            'options'=>['header'=>'iconv','message'=>'iconv','attachment'=>'iconv']]];
}

/** Presnou serverovou cestu zachovame; diagnostika meri cas vyberu bez ukladani nazvu slozky. */
function em_slozka(Webklex\PHPIMAP\Client $c, string $path): Webklex\PHPIMAP\Folder
{
    $diagStart=cb_diag_clock();
    try {
    if ($path==='' || strlen($path)>1000 || preg_match('/[\x00-\x1f]/',$path)) throw new EmailChyba('Neplatná složka.');
    // Seznam slozek vraci uz zakodovanou cestu; presna shoda zabrani druhemu zakodovani znaku &.
    $f=$c->getFolders(false)->where('path',$path)->first();
    // Konfigurace smi obsahovat i lidsky citelny nazev v UTF-8; ten prevadime pouze pri chybejici presne shode.
    if (!$f) $f=$c->getFolderByPath($path,false,true);
    if (!$f || $f->no_select) throw new EmailChyba('Složka již není dostupná. Obnovte seznam složek.');
    return $f;
    } finally {cb_diag_span('imap_folder',$diagStart);}
}

/** UID plati jen ve spojeni s uctem/slozkou/UIDVALIDITY; zvlast merime hlavicky a telo vcetne priloh. */
function em_zprava(Webklex\PHPIMAP\Client $c, array $ref, bool $body=true): Webklex\PHPIMAP\Message
{
    $f=em_slozka($c,(string)($ref['folder']??'')); $status=$f->examine();
    if ((int)($ref['validity']??0)<1 || (int)($status['uidvalidity']??0)!==(int)$ref['validity'] || (int)($ref['uid']??0)<1) throw new EmailChyba('Seznam zpráv se změnil. Načtěte jej znovu.');
    $diagStart=cb_diag_clock();
    try {$message=$f->query()->leaveUnread()->fetchBody(false)->getMessageByUid((int)$ref['uid']);}
    finally {cb_diag_span('imap_message_headers',$diagStart);}
    if ($body) {
        if ((int)$message->size>32*1024*1024) throw new EmailChyba('Zpráva přesahuje limit náhledu 32 MB. Otevřete ji v původním webmailu.');
        $diagStart=cb_diag_clock();
        try {$message->parseBody();} finally {cb_diag_span('imap_message_body',$diagStart);}
    }
    return $message;
}

/** Adresy z MIME mají jednotný tvar pro čtení i přípravu odpovědi. */
function em_adresy(mixed $attribute): array
{
    $out=[];
    foreach ($attribute?->toArray()??[] as $a) {
        $mail=(string)($a->mail??'');
        if (filter_var($mail,FILTER_VALIDATE_EMAIL)) $out[]=['email'=>$mail,'name'=>em_mime_hlavicka((string)($a->personal??''))];
    }
    return $out;
}

/** Náhled seznamu nenačítá těla ani přílohy a nemění stav přečtení. */
function em_radek(Webklex\PHPIMAP\Message $m, array $a, string $folder, int $validity): array
{
    $date=$m->date->first();
    return ['account'=>(int)$a['id'],'mailbox'=>$a['email'],'shared'=>$a['typ']!=='osobni','folder'=>$folder,'validity'=>$validity,
        'uid'=>(int)$m->uid,'subject'=>em_mime_hlavicka((string)$m->subject),'from'=>em_adresy($m->from),'to'=>em_adresy($m->to),
        'date'=>$date instanceof DateTimeInterface?$date->format(DATE_ATOM):'',
        'seen'=>$m->flags->has('seen'),'answered'=>$m->flags->has('answered'),'flagged'=>$m->flags->has('flagged')];
}

/** Povoli jen vyjmenovane hodnoty zakladniho formatovani; CSS nesmi obsahovat URL, polohovani ani aktivni obsah. */
function em_html_styl(string $style, string $classes=''): string
{
    $safe=[];
    foreach (explode(';',$style) as $part) {
        if (!str_contains($part,':')) continue;
        [$property,$value]=array_map('trim',explode(':',$part,2)); $property=strtolower($property); $lower=strtolower($value);
        $valid=false;
        if (in_array($property,['color','background-color'],true)) {
            $valid=preg_match('/^#[a-f0-9]{3}(?:[a-f0-9]{3})?$/i',$value)===1 || in_array($lower,['black','white','red','green','blue','gray','grey','yellow','orange','purple','navy','teal','maroon','silver','transparent'],true);
            if (preg_match('/^rgb\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i',$value,$rgb)) $valid=max((int)$rgb[1],(int)$rgb[2],(int)$rgb[3])<=255;
        } elseif ($property==='font-family') {
            $valid=true;
            foreach (explode(',',$lower) as $font) if (!in_array(trim($font," \t\"'"),['arial','verdana','georgia','courier new','times new roman','sans-serif','serif','monospace'],true)) $valid=false;
        } elseif ($property==='font-size') {
            $valid=preg_match('/^(\d{1,2})(px|pt)$/',$lower,$size)===1 && (int)$size[1]>=8 && (int)$size[1]<=48;
        } elseif ($property==='text-align') $valid=in_array($lower,['left','center','right','justify'],true);
        elseif ($property==='font-weight') $valid=in_array($lower,['normal','bold','400','500','600','700','800','900'],true);
        elseif ($property==='font-style') $valid=in_array($lower,['normal','italic'],true);
        elseif ($property==='text-decoration') $valid=in_array($lower,['none','underline','line-through','underline line-through'],true);
        elseif ($property==='padding-left') $valid=preg_match('/^(0|24|48|72|96|120|144|168|192)px$/',$lower)===1;
        if ($valid) $safe[$property]=$value;
    }
    // Quill umi formaty ulozit i jako tridy; do MIME je prevedeme na sobestacne inline styly.
    foreach (preg_split('/\s+/',trim($classes)) as $class) {
        if (preg_match('/^ql-align-(center|right|justify)$/',$class,$m)) $safe['text-align']=$m[1];
        if (preg_match('/^ql-indent-([1-8])$/',$class,$m)) $safe['padding-left']=(24*(int)$m[1]).'px';
        if ($class==='ql-font-serif') $safe['font-family']='Georgia';
        if ($class==='ql-font-monospace') $safe['font-family']='Courier New';
        $sizes=['ql-size-small'=>'10px','ql-size-large'=>'18px','ql-size-huge'=>'32px'];
        if (isset($sizes[$class])) $safe['font-size']=$sizes[$class];
    }
    return implode(';',array_map(static fn($key,$value)=>$key.':'.$value,array_keys($safe),array_values($safe)));
}

/** Povolena HTML podmnozina se zakladnim formatovanim; bez skriptu, formularu a automatickych sitovych pozadavku. */
function em_html(string $html, bool $remote=false): string
{
    $doc=new DOMDocument(); $old=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
    libxml_clear_errors(); libxml_use_internal_errors($old);
    $allow=['p','br','div','span','strong','b','em','i','u','s','blockquote','pre','code','ul','ol','li','table','thead','tbody','tr','td','th','hr','h1','h2','h3','h4','a','img'];
    $walk=function(DOMNode $node) use (&$walk,$allow,$remote): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (!$child instanceof DOMElement) { if (!$child instanceof DOMText) $node->removeChild($child); continue; }
            $tag=strtolower($child->tagName);
            if (in_array($tag,['script','style','iframe','object','embed','form','input','button','textarea','select','svg','math','link','meta','base','video','audio','source'],true)) { $node->removeChild($child); continue; }
            $walk($child);
            if (!in_array($tag,$allow,true)) { while ($child->firstChild) $node->insertBefore($child->firstChild,$child); $node->removeChild($child); continue; }
            $href=$child->getAttribute('href'); $src=$child->getAttribute('src'); $alt=$child->getAttribute('alt');
            $style=em_html_styl($child->getAttribute('style'),$child->getAttribute('class'));
            foreach (iterator_to_array($child->attributes) as $attr) $child->removeAttribute($attr->name);
            if ($style!=='') $child->setAttribute('style',$style);
            if ($tag==='a' && preg_match('~^(https?://|mailto:)[^\x00-\x20]+$~i',$href)) { $child->setAttribute('href',$href); $child->setAttribute('target','_blank'); $child->setAttribute('rel','noopener noreferrer'); }
            if ($tag==='img') {
                $child->setAttribute('alt',$alt?:'[obrázek]');
                if ($remote && preg_match('~^https://[^\x00-\x20]+$~i',$src)) { $child->setAttribute('src',$src); $child->setAttribute('referrerpolicy','no-referrer'); }
            }
        }
    };
    $body=$doc->getElementsByTagName('body')->item(0); if (!$body) return '';
    $walk($body); $out=''; foreach ($body->childNodes as $child) $out.=$doc->saveHTML($child);
    return $out;
}

/** Textovou alternativu odvozujeme z vycisteneho HTML, aby prijemce bez HTML dostal stejne sdeleni i podpis. */
function em_html_text(string $html): string
{
    $doc=new DOMDocument(); $old=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><body>'.em_html($html).'</body>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);
    libxml_clear_errors(); libxml_use_internal_errors($old);
    $walk=function(DOMNode $node) use (&$walk): string {
        if ($node instanceof DOMText) return $node->nodeValue;
        if (!$node instanceof DOMElement) return '';
        $tag=strtolower($node->tagName); if ($tag==='br') return "\n";
        $text=''; foreach ($node->childNodes as $child) $text.=$walk($child);
        if ($tag==='a') { $href=$node->getAttribute('href'); if ($href!=='' && $text!==$href) $text.=' ('.$href.')'; }
        if ($tag==='li') $text='- '.$text;
        if (in_array($tag,['p','div','li','ul','ol','blockquote','pre','tr','h1','h2','h3','h4'],true)) $text.="\n";
        return $text;
    };
    $body=$doc->getElementsByTagName('body')->item(0);
    return trim(preg_replace('/\n{3,}/',"\n\n",str_replace("\xc2\xa0",' ',$body?$walk($body):'')));
}

/** Samostatný SMTP klient používá identitu připojené schránky; systémové rozesílání se nemění. */
function em_mailer(array $a, array $input, array $files=[]): PHPMailer\PHPMailer\PHPMailer
{
    em_knihovny(); $cfg=em_config()['smtp']; $m=new PHPMailer\PHPMailer\PHPMailer(true);
    $m->isSMTP(); $m->Host=$cfg['host']; $m->Port=(int)$cfg['port']; $m->SMTPAuth=true;
    $m->Username=$a['email']; $m->Password=em_heslo($a); $m->Timeout=15;
    $m->SMTPSecure=$cfg['encryption']==='ssl'?'ssl':'tls'; $m->CharSet='UTF-8'; $m->Encoding='base64';
    $m->setFrom($a['email']);
    foreach (['to'=>'addAddress','cc'=>'addCC','bcc'=>'addBCC'] as $field=>$method) {
        $value=trim((string)($input[$field]??'')); if ($value==='') continue;
        // Formular prijima konkretni adresy oddelene carkou; zadna neplatna polozka se nesmi potichu vynechat.
        $addresses=array_map('trim',preg_split('/[;,]/',$value));
        if (!$addresses || count($addresses)>50) throw new EmailChyba('Zkontrolujte e-mailové adresy příjemců.');
        foreach ($addresses as $addr) { if (!filter_var($addr,FILTER_VALIDATE_EMAIL)) throw new EmailChyba('Zadejte platné e-mailové adresy oddělené čárkou.'); $m->$method($addr); }
    }
    if (!$m->getToAddresses()) throw new EmailChyba('Vyplňte příjemce zprávy.');
    $m->Subject=mb_substr(trim((string)($input['subject']??'')),0,998);
    $m->Body=(string)($input['body']??''); $m->isHTML(false);
    if (array_key_exists('body_html',$input)) {
        $html=(string)$input['body_html'];
        if (strlen($html)>1048576) throw new EmailChyba('Text zprávy je příliš dlouhý.');
        $m->Body=em_html($html); $m->AltBody=em_html_text($m->Body); $m->isHTML(true);
        if(!empty($input['_draft_format']))$m->Body='<div data-em-draft-editor="1">'.$m->Body.'</div>';
        // Citace pochazi ze serverem overene puvodni zpravy, nikoli z HTML zaslaneho klientem.
        if (!empty($input['_quote_html'])) {
            $m->Body.='<blockquote data-em-draft-quote="1">'.$input['_quote_html'].'</blockquote>';$m->AltBody.="\n\n".em_zobrazeni_text($input['_quote_html']);
        }
    }
    if (strlen($m->Body)>8*1024*1024) throw new EmailChyba('Text zprávy je příliš dlouhý.');
    foreach ($files as $file) {
        if (!empty($file['cid'])) $m->addStringEmbeddedImage($file['content'],$file['cid'],$file['name'],'base64',$file['type'],'inline');
        else $m->addStringAttachment($file['content'],$file['name'],'base64',$file['type']);
    }
    return $m;
}

/** Typ nahledu urcujeme z obsahu; HTML a dalsi textove formaty se zobrazuji jen jako text, nikoli jako aktivni dokument. */
function em_priloha_typ(string $content): string
{
    if (!class_exists('finfo')) return 'application/octet-stream';
    $type=(new finfo(FILEINFO_MIME_TYPE))->buffer($content);
    if (is_string($type) && (str_starts_with($type,'text/') || in_array($type,['application/json','application/xml'],true))) return 'text/plain';
    return in_array($type,['application/pdf','image/png','image/jpeg','image/gif','image/webp','image/avif',
        'audio/mpeg','audio/wav','audio/x-wav','audio/ogg','video/mp4','video/webm','video/ogg'],true)?$type:'application/octet-stream';
}

/** Nazev prilohy nesmi obsahovat cestu ani znaky schopne zmenit HTTP hlavicky. */
function em_nazev_prilohy(string $name): string
{
    return mb_substr(preg_replace('~[\x00-\x1f\x7f/\\\\]~u','_', $name)?:'priloha',0,180);
}

/** Zasifrovany kontext konceptu zachova vazbu na puvodni sdilenou zpravu bez duvery v cizi MIME hlavicky. */
function em_kontext_konceptu(array $a, Webklex\PHPIMAP\Message $message): ?array
{
    $value=trim((string)$message->getHeader()?->get('x_comeback_koncept'));
    if ($value==='') return null;
    try {
        $ctx=json_decode(em_rozsifruj($value,'koncept:'.$a['email']),true,16,JSON_THROW_ON_ERROR);
        if (!is_array($ctx) || !in_array($ctx['kind']??'',['new','reply','reply_all','forward'],true) || (isset($ctx['source']) && $ctx['source']!==null && (int)($ctx['source']['account']??0)!==(int)$a['id'])) return null;
        return $ctx;
    } catch (Throwable) { return null; }
}

/** Potvrzeni COPYUID vaze cilovou zpravu presne na presouvane UID i bez hlavicky Message-ID. */
function em_presun_uid(Webklex\PHPIMAP\Client $client, array $ref, string $target): array
{
    $client->openFolder((string)$ref['folder'],true);
    $response=$client->getConnection()->requestAndResponse('UID MOVE',[(string)(int)$ref['uid'],$client->getConnection()->escapeString($target)],true)->validatedData();
    $text=implode(' ',array_map('strval',(array)$response));
    if (!preg_match('/\[COPYUID ([1-9][0-9]*) ([1-9][0-9]*) ([1-9][0-9]*)\]/i',$text,$match) || (int)$match[2] !== (int)$ref['uid']) {
        throw new EmailChyba('Zpráva byla přesunuta, ale server nepotvrdil její nové UID. Obnovte složku.');
    }
    return ['folder'=>$target,'validity'=>(int)$match[1],'uid'=>(int)$match[3]];
}

/** RFC4315: pri chybejicim UIDPLUS docasne ochrani ostatni Deleted zpravy a jejich priznaky obnovi i po chybe. */
function em_expunge_vyber(Webklex\PHPIMAP\Connection\Protocols\ProtocolInterface $connection, array $uids, array $caps, callable $deletedNow): void
{
    $uidplus=in_array('UIDPLUS',$caps,true)||in_array('IMAP4REV2',$caps,true);
    $original=array_map('intval',$deletedNow());
    $protected=$uidplus?[]:array_values(array_diff($original,$uids));$cleared=[];$marked=[];$completed=false;
    try {
        foreach($protected as $uid) {
            // Evidujeme pokus pred STORE: pri vypadku odpovedi mohl server zmenu jiz provest.
            $cleared[]=$uid;$connection->store(['\\Deleted'],$uid,null,'-',true,Webklex\PHPIMAP\IMAP::ST_UID)->validate();
        }
        foreach($uids as $uid) {
            if(!in_array($uid,$original,true))$marked[]=$uid;
            $connection->store(['\\Deleted'],$uid,null,'+',true,Webklex\PHPIMAP\IMAP::ST_UID)->validate();
        }
        if($uidplus)$connection->requestAndResponse('UID EXPUNGE',[implode(',',$uids)])->validate();
        else {
            // Znamou soubeznou zmenu jineho klienta neresime smazanim cizich zprav.
            $current=array_map('intval',$deletedNow());
            if(array_diff($current,$uids) || array_diff($uids,$current))throw new EmailChyba('Jiný poštovní klient mezitím změnil zprávy v koši. Obnovte koš a výběr opakujte.');
            $connection->expunge()->validate();
        }
        $completed=true;
    } finally {
        $restoreFailed=false;
        if(!$completed)foreach($marked as $uid)try{$connection->store(['\\Deleted'],$uid,null,'-',true,Webklex\PHPIMAP\IMAP::ST_UID)->validate();}catch(Throwable){$restoreFailed=true;}
        foreach($cleared as $uid)try{$connection->store(['\\Deleted'],$uid,null,'+',true,Webklex\PHPIMAP\IMAP::ST_UID)->validate();}catch(Throwable){$restoreFailed=true;}
        if($restoreFailed)throw new EmailChyba('Spojení se přerušilo při obnově příznaků zpráv. Neopakujte mazání naslepo; nejprve obnovte koš.');
    }
}

/** Vyber musi byt potvrzen a patrit do kose; server bez UIDPLUS pouziva standardni chraneny postup. */
function em_smazat_z_kose(Webklex\PHPIMAP\Client $client, array $refs, string $confirmed): void
{
    $trash=(string)(em_config()['folders']['trash']??'');
    if ($trash==='' || $confirmed!=='smazat' || !$refs) throw new EmailChyba('Trvalé smazání musí být potvrzené.');
    foreach ($refs as $ref) {
        if (($ref['folder']??'')!==$trash) throw new EmailChyba('Trvale mazat lze pouze zprávy z koše.');
        em_zprava($client,$ref,false);
    }
    $caps=array_map('strtoupper',(array)$client->getConnection()->getCapabilities()->validatedData());
    $uids=array_values(array_unique(array_map(static fn($r)=>(int)$r['uid'],$refs)));
    $folder=em_slozka($client,$trash);
    $client->openFolder($trash,true);
    em_expunge_vyber($client->getConnection(),$uids,$caps,static fn()=>array_values($folder->query()->leaveUnread()->fetchBody(false)->all()->whereDeleted()->search()->all()));
}
