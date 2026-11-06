<?php
/* E-mail: ucty, spravcem prirazene osobni pripojeni a seznamy posty; cizi osobni posta se spravci neodemyka. */
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../common/lib/osoba_jmeno.php';
require_once __DIR__.'/posta.php';
require_once __DIR__.'/struktura.php';
require_once __DIR__.'/nastaveni.php';
require_once __DIR__.'/sprava.php';

/** Klient dostane bezpecne vlastnosti a posledni zname pocty INBOX; jejich cteni neotevira dalsi IMAP spojeni. */
function em_stav(mysqli $db): array
{
    $person=em_osoba($db); $accounts=[];$available=em_ucty($db,$person);$counts=[];
    // Zobrazovany nazev cteme z ciselniku stejne firmy; identita a opravneni nadale pouzivaji ID uctu.
    $branchNames=[];
    if(array_filter($available,static fn($a)=>$a['typ']==='pobocka')){
        $s=$db->prepare('SELECT id_pob,nazev FROM pobocka WHERE id_firma=?');$s->bind_param('i',$person['id_firma']);$s->execute();
        foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $branch)$branchNames[(int)$branch['id_pob']]=$branch['nazev'];
    }
    $counts=em_precteni_pocty($db,$available);
    foreach ($available as $a) {
        $unlocked=true; try { em_heslo($a); } catch (EmailChyba) { $unlocked=false; }
        $accounts[]=['id'=>(int)$a['id'],'email'=>$a['email'],'name'=>$a['typ']==='pobocka'?($branchNames[(int)$a['id_pob']]??''):'','type'=>$a['typ'],'mode'=>$a['rezim'],'unlocked'=>$unlocked,'unread'=>$unlocked?($counts[(int)$a['id']]??null):null];
    }
    return ['accounts'=>$accounts,'manager'=>$person['manager'],'attachment_limit'=>em_limit_priloh($person['manager']),
        'shared'=>em_pravo(801),'manage_shared'=>em_pravo(802),'shared_management'=>em_sprava_cti($db,$person),
        'owner'=>em_identita(),'preferences'=>em_nastaveni_cti($db)];
}

/** Sdilena a osobni pripojeni maji vlastni autorizovanou obsluhu bez duplicitni zapisove logiky. */
function em_pripojit(mysqli $db, array $d): void
{
    if (($d['type']??'osobni')==='shared') { em_sdilena_uloz($db,$d); return; }
    if (($d['type']??'osobni')!=='osobni') throw new EmailChyba('Neplatny typ schranky.');
    em_osobni_pripojit($db,$d);
}

/** Jednotný klíč pracovního stavu odolá přesunu, opětovnému použití UID a změně kódování názvu složky. */
function em_ref(array $a, array $d): array
{
    return [(int)$a['id'],hash('sha256',(string)$d['folder']),(int)$d['validity'],(int)$d['uid']];
}

/** Pracovní stavy se vytvářejí až výslovnou akcí, běžné čtení pošty do nich nezapisuje. */
function em_pracovni_stav(mysqli $db, array $a, array $d): array
{
    [$id,$hash,$v,$uid]=em_ref($a,$d);
    $s=$db->prepare('SELECT e.*,' . cb_osoba_jmeno_sql('o.id_person') . ' jmeno,(zamek_do>NOW()) zamceno FROM email_stav e
        LEFT JOIN ' . cb_osoby_zdroj_sql() . ' o ON o.id_person=e.resitel
        WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
    $s->bind_param('isii',$id,$hash,$v,$uid); $s->execute();
    return $s->get_result()->fetch_assoc()?:['stav'=>'nove','verze'=>0,'resitel'=>null,'jmeno'=>'','zamceno'=>0];
}

/** Zámek se obnovuje pouze vlastníkem; cizí rozpracovanou odpověď nelze převzít tichým zápisem. */
function em_prevzit(mysqli $db, array $a, array $d, string $action, bool $answered=false): array
{
    [$id,$hash,$v,$uid]=em_ref($a,$d); $user=(int)$_SESSION['cb_user']['id_user'];
    $db->begin_transaction();
    try {
        $path=(string)$d['folder'];
        $s=$db->prepare('INSERT IGNORE INTO email_stav(ucet,slozka_hash,slozka,uidvalidity,uid) VALUES(?,?,?,?,?)'); $s->bind_param('issii',$id,$hash,$path,$v,$uid); $s->execute();
        $s=$db->prepare('SELECT *,zamek_do>NOW() zamceno FROM email_stav WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=? FOR UPDATE');
        $s->bind_param('isii',$id,$hash,$v,$uid); $s->execute(); $state=$s->get_result()->fetch_assoc();
        if ($state['stav']==='resi' && (int)$state['resitel']!==$user && (int)$state['zamceno']===1) throw new EmailChyba('Zprávu právě vyřizuje jiný uživatel.');
        if ($action==='renew') {
            if ($state['stav']!=='resi' || (int)$state['resitel']!==$user) throw new EmailChyba('Zprávu již nemáte převzatou.');
            $sql='UPDATE email_stav SET zamek_do=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?';
            $s=$db->prepare($sql); $s->bind_param('isii',$id,$hash,$v,$uid);
        } else {
            if ($action==='claim' && in_array($state['stav'],['odpovezeno','vyrizeno'],true)) throw new EmailChyba('Zpráva je již vyřízená. Nejdříve ji výslovně vraťte k řešení.');
            $next=match($action) {'claim'=>'resi','done'=>'vyrizeno','release'=>'nove',default=>throw new EmailChyba('Neplatný pracovní stav.')};
            $s=$db->prepare('UPDATE email_stav SET stav=?,resitel=?,zamek_do=IF(?="resi",DATE_ADD(NOW(),INTERVAL 5 MINUTE),NULL),verze=verze+1,zmeneno=NOW() WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
            $s->bind_param('sisisii',$next,$user,$next,$id,$hash,$v,$uid);
        }
        $s->execute();
        if ($action==='claim') {
            $baseline=$answered?1:0;
            $s=$db->prepare('UPDATE email_stav SET odpovezeno_predem=? WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
            $s->bind_param('iisii',$baseline,$id,$hash,$v,$uid); $s->execute();
        }
        if ($action!=='renew') em_audit($db,$id,$action,(string)$d['folder'].':'.$uid);
        $db->commit(); return em_pracovni_stav($db,$a,$d);
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

/** Hledani spojuje viditelne hlavicky pres OR, volitelne telo; hodnoty se escapují jako IMAP retezec, nikoli prikaz. */
function em_hledani_dotaz(Webklex\PHPIMAP\Query\WhereQuery $q, array $d): void
{
    $search=mb_substr(trim((string)($d['search']??'')),0,200);
    if($search==='') return;
    if(preg_match('/[\x00-\x1f\x7f]/',$search)) throw new EmailChyba('Hledaný text nesmí obsahovat řídicí znaky.');
    // Knihovna hodnoty obali uvozovkami, ale sama neescapuje jejich obsah.
    $search=str_replace(['\\','"'],['\\\\','\\"'],$search);
    $fields=['SUBJECT','FROM','TO','CC'];if(!empty($d['body']))$fields[]='BODY';
    foreach($fields as $i=>$field) {if($i<count($fields)-1)$q->orWhere();$q->where($field,$search);}
}

/** SORT vraci jen identifikatory podle data; hlavicky a prilohy dostane az konkretni stranka. */
function em_serazene_uid(Webklex\PHPIMAP\Client $client, Webklex\PHPIMAP\Query\WhereQuery $q): Illuminate\Support\Collection
{
    $response=$client->getConnection()->requestAndResponse('UID SORT',['(REVERSE DATE)','UTF-8',$q->generate_query()])->validate();
    foreach($response->data() as $line) {
        if(is_array($line) && ($line[0]??'')==='SORT') return new Illuminate\Support\Collection(array_map('intval',array_slice($line,1)));
    }
    throw new EmailChyba('Poštovní server neposkytl seřazený seznam zpráv.');
}

/** Nejlehci index pro porovnani schranek: jen UID, datum a vnitrni datum jako zaloha; bez predmetu, adres, struktur priloh nebo SQL stavu. */
function em_datumovy_index(Webklex\PHPIMAP\Client $client, array $uids, array $a, string $path, int $validity): array
{
    if(!$uids)return [];
    $response=$client->getConnection()->fetch(['UID','INTERNALDATE','BODY.PEEK[HEADER.FIELDS (DATE)]'],$uids,null,Webklex\PHPIMAP\IMAP::ST_UID)->validate();
    $raw=implode('',$response->getResponse());$pos=0;$byUid=[];
    while(preg_match('/^\* \d+ FETCH\s+/im',$raw,$match,PREG_OFFSET_CAPTURE,$pos)) {
        $pos=$match[0][1]+strlen($match[0][0]);$fields=em_imap_struktura_hodnota($raw,$pos);if(!is_array($fields))throw new RuntimeException('Neplatny datumovy index.');
        $uid=0;$internal='';$date='';
        foreach($fields as $i=>$value) {
            if(!is_string($value))continue;
            if(strtoupper($value)==='UID')$uid=(int)($fields[$i+1]??0);
            if(strtoupper($value)==='INTERNALDATE')$internal=(string)($fields[$i+1]??'');
            $header=preg_replace('/\r?\n[ \t]+/',' ',$value);
            if(preg_match('/^Date:\s*(.+)$/im',$header,$m))$date=trim($m[1]);
        }
        $time=strtotime($date);if($time===false)$time=strtotime($internal);if($time===false)throw new RuntimeException('Chybi datum indexu.');
        if($uid>0)$byUid[$uid]=['account'=>(int)$a['id'],'folder'=>$path,'validity'=>$validity,'uid'=>$uid,'date'=>gmdate(DATE_ATOM,$time)];
    }
    $out=[];foreach($uids as $uid){if(!isset($byUid[$uid]))throw new EmailChyba('Seznam zpráv se změnil. Obnovte přehled.');$out[]=$byUid[$uid];}return $out;
}

/** Vyber radku nesmi zmenit prikaz IMAP a nikdy nepovoli vice nez 50 UID v jednom pozadavku. */
function em_vybrane_uid(string $value): array
{
    if(strlen($value)>600 || !preg_match('/^[1-9][0-9]*(?:,[1-9][0-9]*)*$/D',$value))throw new EmailChyba('Neplatný výběr zpráv.');
    $uids=array_values(array_unique(array_map('intval',explode(',',$value))));
    if(count($uids)>50 || max($uids)>4294967295)throw new EmailChyba('Neplatný počet zpráv.');return $uids;
}

/** Obnovi jen priznaky a vyrizovani viditelnych zprav; zadne hlavicky ani prilohy znovu nestahuje. */
function em_stavy_viditelnych(mysqli $db, Webklex\PHPIMAP\Client $client, array $a, array $d): array
{
    $uids=em_vybrane_uid((string)($d['uids']??''));$path=(string)($d['folder']??'INBOX');$folder=em_slozka($client,$path);$status=$folder->examine();
    $validity=(int)($status['uidvalidity']??0);if($validity<1 || $validity!==(int)($d['validity']??0))throw new EmailChyba('Složka se změnila. Obnovte přehled.');
    $flags=$client->getConnection()->flags($uids,Webklex\PHPIMAP\IMAP::ST_UID)->validatedData();$rows=[];
    $readMap=$a['typ']!=='osobni'?em_precteni_mapa($db,$a,$folder,$validity):null;
    foreach($uids as $uid) {
        if(!isset($flags[$uid]))continue;$values=array_map(static fn($flag)=>strtolower(ltrim((string)$flag,'\\')),(array)$flags[$uid]);
        $ref=['account'=>(int)$a['id'],'folder'=>$path,'validity'=>$validity,'uid'=>$uid];
        $rows[]=$ref+['seen'=>$readMap!==null?!empty($readMap[$uid]):in_array('seen',$values,true),'answered'=>in_array('answered',$values,true),'flagged'=>in_array('flagged',$values,true),'work'=>em_pracovni_stav($db,$a,$ref)];
    }
    return ['rows'=>$rows,'uidnext'=>(int)($status['uidnext']??0)];
}

/** Prehled cte jen jednu stranku nebo presne vybrane radky; datumovy index nesmi spustit drahe nacitani ostatnich udaju. */
function em_seznam(mysqli $db, array $accounts, array $d): array
{
    $paged=!empty($d['paged']);$part=!empty($d['aggregate_part']);
    $indexOnly=!empty($d['index_only']);$selectedUids=array_key_exists('uids',$d)?em_vybrane_uid((string)$d['uids']):null;
    if($indexOnly && !$paged)throw new EmailChyba('Datumový index vyžaduje stránkovaný přehled.');
    if($selectedUids!==null && (!$paged || $indexOnly || (int)($d['snapshot_validity']??0)<1))throw new EmailChyba('Chybí platná identifikace seznamu.');
    $limit=(int)($d['limit']??50);if(!in_array($limit,[25,50,100],true))$limit=50;if($part)$limit=50;
    $page=max(1,min($paged?100000:100,(int)($d['page']??1))); $multi=count($accounts)>1 || $part; $rows=[]; $total=0; $errors=[];$errorDetails=[];$snapshot=[];
    if (!$paged && $multi && $page>20) throw new EmailChyba('Pro starší zprávy vyberte konkrétní schránku nebo použijte hledání.');
    foreach ($accounts as $a) {
        $client=null; $phase='pripojeni';
        try {
            $client=em_imap($a); $phase='slozka'; $path=$multi?'INBOX':(string)($d['folder']??'INBOX'); $folder=em_slozka($client,$path);
            $phase='identita_slozky';
            $diagStart=cb_diag_clock();
            try {$status=$folder->examine();$validity=(int)($status['uidvalidity']??0);}
            finally {cb_diag_span('imap_examine',$diagStart);}
            if ($validity<1) throw new EmailChyba('Server neposkytl identitu složky.');
            if($paged && !empty($d['snapshot_validity']) && (int)$d['snapshot_validity']!==$validity) throw new EmailChyba('Složka se změnila. Obnovte přehled.');
            $phase='dotaz'; $q=$folder->query()->leaveUnread()->fetchBody(false)->setFetchOrderDesc()->all()->whereUndeleted();
            $readMap=$a['typ']!=='osobni'?em_precteni_mapa($db,$a,$folder,$validity):null;
            if (!empty($d['unseen']) && $readMap===null) $q->whereUnseen();
            if($paged && (int)($d['max_uid']??0)>0)$q->where('CUSTOM UID 1:'.(int)$d['max_uid']);
            em_hledani_dotaz($q,$d);
            // Stejna mnozina UID poskytne pocet i hlavicky stranky; get() by provedlo druhy SEARCH.
            $phase='hledani';$diagStart=cb_diag_clock();
            try {$found=$selectedUids!==null?new Illuminate\Support\Collection($selectedUids):($paged?em_serazene_uid($client,$q):$q->search());} finally {cb_diag_span('imap_search',$diagStart);}
            // Osobni filtr musi predchazet strankovani i globalnimu datumovemu indexu.
            if (!empty($d['unseen']) && $readMap!==null) $found=$found->filter(static fn($uid)=>empty($readMap[(int)$uid]))->values();
            if($paged) {
                $q->setFetchOrderAsc();
                $snapshot=['validity'=>$validity,'max_uid'=>array_key_exists('max_uid',$d)?(int)$d['max_uid']:(int)($status['uidnext']??0)-1];
                if($snapshot['max_uid']<1)$snapshot['max_uid']=$found->count()?max($found->all()):0;
            }
            $total+=$found->count();$phase='hlavicky';
            if($indexOnly) {
                $phase='datumovy_index';$diagStart=cb_diag_clock();
                try {$rows=array_merge($rows,em_datumovy_index($client,array_values($found->slice(($page-1)*50,50)->all()),$a,$path,$validity));}
                finally {cb_diag_span('imap_date_index',$diagStart);}continue;
            }
            // Knihovne nesmime poslat prazdny vyrez UID na strane za poslednim vysledkem (sestavila by neplatny FETCH).
            $offset=$selectedUids!==null || ($multi && !$paged)?0:($page-1)*$limit;
            $diagStart=cb_diag_clock();
            try {$list=$found->count()>$offset?$q->limit($selectedUids!==null?50:($multi && !$paged?$page*$limit:$limit),$selectedUids!==null || ($multi && !$paged)?1:$page)->curate_messages($found):[];}
            finally {cb_diag_span('imap_headers',$diagStart);}
            $counts=[];$uids=[];
            foreach($list as $message)$uids[]=(int)$message->uid;
            // Jeden FETCH pro celou stranku necte tela ani data priloh a nemeni IMAP Seen.
            if($uids){
                $diagStart=cb_diag_clock();
                try {
                    $response=$client->getConnection()->fetch(['BODYSTRUCTURE'],$uids,null,Webklex\PHPIMAP\IMAP::ST_UID)->validate();
                    $counts=em_struktura_pocty(implode('',$response->getResponse()));
                } catch(Throwable $e) {error_log('EMAIL_ATTACHMENT_COUNT account='.(int)$a['id'].' class='.get_class($e).' at='.basename($e->getFile()).':'.$e->getLine());}
                finally {cb_diag_span('imap_bodystructure',$diagStart);}
            }
            foreach ($list as $message) {
                $phase='radek_a_stav';
                $diagStart=cb_diag_clock();
                try {$row=em_radek($message,$a,$path,$validity); if($readMap!==null)$row['seen']=!empty($readMap[$row['uid']]); $row['attachment_count']=$counts[$row['uid']]??null; $row['work']=em_pracovni_stav($db,$a,$row); $rows[]=$row;}
                finally {cb_diag_span('rows_and_work_sql',$diagStart);}
            }
        } catch (Throwable $e) {
            // Castecne selhani souhrnu se take musi ulozit, prestoze cele API vraci HTTP 200.
            $failure=em_chyba_vysledek($e,['action'=>'list','phase'=>$phase,'account'=>$a['id']]);
            $errors[]=$a['email'].': '.$failure['error'];$errorDetails[]=$failure['error_detail'];
        }
        finally { if ($client) $client->disconnect(); }
    }
    // IMAP vybira stranku od nejnovejsich UID, ale hlavicky muze vratit vzestupne; vystup proto vzdy radime podle skutecneho casu vcetne pasma.
    usort($rows,static fn($a,$b)=>((strtotime($b['date'])?:0)<=>(strtotime($a['date'])?:0))?:($paged?($a['uid']<=>$b['uid']):($b['uid']<=>$a['uid']))?:($a['account']<=>$b['account']));
    if ($multi && !$part && !$paged) $rows=array_slice($rows,($page-1)*$limit,$limit);
    return ['messages'=>$rows,'total'=>$total,'page'=>$page,'limit'=>$limit,'snapshot'=>$snapshot,'errors'=>$errors,'error_details'=>$errorDetails];
}
