<?php
/* E-mail: autorizovane API se session IS a CRF; cteni sdilene posty chrani 801, centralni spravu 802 a DB konfiguraci nacita pred session. */
declare(strict_types=1);
require_once __DIR__.'/../common/lib/chyby_bootstrap.php';
require_once __DIR__.'/../common/lib/session_boot.php';
require_once __DIR__.'/../common/lib/app.php';
// Kontrola session otevírá DB; konfigurace nesmí vzniknout teprve v lokálním rozsahu funkce db().
require_once __DIR__.'/../common/config/secrets.php';
require_once __DIR__.'/../common/lib/ochrana_crf.php';
require_once __DIR__.'/../common/db/db_prava.php';
require_once __DIR__.'/odeslani.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
$client=null; $mutationLock='';
try {
    cb_session_guard_entry();
    if (empty($_SESSION['login_ok'])) { http_response_code(401); throw new EmailChyba('Přihlášení do IS skončilo.'); }
    if (!hash_equals((string)($_SESSION['cb_pc_session_token']??''),(string)($_SERVER['HTTP_X_COMEBACK_PC_SESSION']??'')) || empty($_SESSION['cb_pc_session_token'])) {
        http_response_code(409); throw new EmailChyba('Platnost tohoto okna skončila. Otevřete modul znovu z IS.');
    }
    $db=db(); cb_db_prava_nacti_do_session($db,(int)$_SESSION['cb_user']['id_user']);
    if (!em_pravo(800)) { http_response_code(403); throw new EmailChyba('K modulu E-mail nemáte přístup.'); }
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if (!in_array($method,['GET','POST'],true)) { http_response_code(405); throw new EmailChyba('Nepodporovaná metoda.'); }
    cb_crf_vyzaduj(); $action=(string)($_GET['action']??'state');
    $d=$method==='POST'?(isset($_POST['data'])?json_decode((string)$_POST['data'],true,32,JSON_THROW_ON_ERROR):json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR)):$_GET;
    if (!is_array($d)) throw new EmailChyba('Neplatný požadavek.');
    if ($action==='state' && !em_schema($db)) { echo json_encode(['ok'=>true,'installed'=>false,'message'=>'Modul čeká na spuštění připraveného instalačního SQL.']); exit; }
    if (!em_schema($db)) throw new EmailChyba('Nejprve spusťte instalační SQL modulu E-mail.');
    if ($action==='state') {
        try { em_config(); $out=em_stav($db)+['installed'=>true]; }
        catch (EmailChyba $e) { $out=['installed'=>true,'configured'=>false,'message'=>$e->getMessage()]; }
    } elseif ($action==='save_signature') {
        if ($method!=='POST') throw new EmailChyba('Uložení podpisu vyžaduje formulář.');
        $out=em_podpis_uloz($db,(string)($d['html']??''));
    } elseif ($action==='shared_active') {
        if ($method!=='POST') throw new EmailChyba('Změna dostupnosti vyžaduje formulář.');
        em_sdilena_aktivita($db,$d); $out=em_stav($db);
    } elseif ($action==='background') {
        // Samostatny pozadavek obnovuje pocty i po opusteni modulu; login ani hlavickovy endpoint na sit necekaji.
        $accounts=[];
        foreach (em_ucty($db) as $one) {
            try { $accounts[]=['account'=>$one,'password'=>em_heslo($one)]; } catch (EmailChyba) {}
        }
        session_write_close(); $checked=0;
        foreach ($accounts as $entry) {
            $connection=null;
            try {
                $connection=em_imap($entry['account'],$entry['password']);
                $unread=(int)(em_slozka($connection,'INBOX')->status()['unseen']??0); $id=(int)$entry['account']['id'];
                $s=$db->prepare('INSERT INTO email_cache(ucet,neprecteno,aktualizovano) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE neprecteno=VALUES(neprecteno),aktualizovano=NOW()');
                $s->bind_param('ii',$id,$unread); $s->execute(); $checked++;
            } catch (Throwable) { /* Posledni platny pocet se nesmi pri vypadku serveru vynulovat. */ }
            finally { if ($connection) { try { $connection->disconnect(); } catch (Throwable) {} } }
        }
        $out=['checked'=>$checked];
    } elseif ($action==='connect') {
        if ($method!=='POST') throw new EmailChyba('Připojení vyžaduje formulář.');
        $attempt=$_SESSION['email_pokusy']??['start'=>time(),'count'=>0];
        if (time()-(int)$attempt['start']>300) $attempt=['start'=>time(),'count'=>0];
        if ((int)$attempt['count']>=6) throw new EmailChyba('Příliš mnoho pokusů. Vyčkejte pět minut.');
        $attempt['count']++; $_SESSION['email_pokusy']=$attempt;
        em_pripojit($db,$d); unset($_SESSION['email_pokusy']); $out=em_stav($db);
    } else {
        $a=em_ucet($db,(int)($d['account']??0));
        if ($action==='unlock' || $action==='logout') {
            if ($a['typ']!=='osobni') throw new EmailChyba('Společná schránka používá přihlášení do IS.');
            if ($method!=='POST') throw new EmailChyba('Změna přihlášení vyžaduje formulář.');
            if ($action==='logout') $_SESSION['email_pristup'][$a['id']]=['identita'=>em_identita(),'zamceno'=>true];
            else {
                $pw=(string)($d['password']??'');
                if ($a['rezim']==='heslo') {
                    $last=(int)($_SESSION['email_posledni_pokus']??0);
                    if (time()-$last<3) throw new EmailChyba('Před dalším pokusem krátce vyčkejte.');
                    $_SESSION['email_posledni_pokus']=time();
                    if ($pw==='' || strlen($pw)>1024) throw new EmailChyba('Zadejte heslo ke schránce.');
                    $client=em_imap($a,$pw);
                } else $pw=em_rozsifruj((string)$a['heslo_sifra'],'ucet:'.$a['email']);
                $_SESSION['email_pristup'][$a['id']]=['identita'=>em_identita(),'revize'=>(int)$a['revize'],'heslo'=>em_zasifruj($pw,'session:'.em_identita().':'.$a['id'])];
            }
            $out=em_stav($db);
        } else {
            $password=em_heslo($a);
            // Pomalé IMAP/SMTP operace nezamykají session ostatním kartám a modulům.
            session_write_close();
            if ($action==='list') {
                $accounts=!empty($d['aggregate'])?array_values(array_filter(em_ucty($db),static fn($x)=>$x['typ']!=='osobni')):[$a];
                if (!empty($d['aggregate']) && !em_pravo(801)) throw new EmailChyba('Společná pošta není povolená.');
                $out=em_seznam($db,$accounts,$d);
            } else {
                $client=em_imap($a,$password);
                $writes=['read','move','flag','folder_create','folder_rename','folder_delete','claim','release','done','renew','send','draft','repair_sent'];
                if (in_array($action,$writes,true) && $method!=='POST') throw new EmailChyba('Tato akce vyžaduje potvrzený požadavek.');
                if (in_array($action,$writes,true) && !in_array($action,['send','draft'],true)) {
                    // Zmenu stavu nebo presun nelze provest mezi posledni kontrolou odpovedi a SMTP prenosem.
                    $key='email:send:'.$a['id']; $s=$db->prepare('SELECT GET_LOCK(?,0) n'); $s->bind_param('s',$key); $s->execute();
                    if ((int)$s->get_result()->fetch_assoc()['n']!==1) throw new EmailChyba('Ve schránce právě probíhá odesílání. Vyčkejte a akci opakujte.');
                    $mutationLock=$key;
                }
                if ($action==='folders') {
                    $folders=[];
                    foreach ($client->getFolders(false) as $f) {
                        $status=$f->no_select?[]:$f->status();
                        $folders[]=['path'=>$f->path,'name'=>$f->full_name,'delimiter'=>$f->delimiter,'selectable'=>!$f->no_select,'unseen'=>(int)($status['unseen']??0)];
                    }
                    $unread=0; foreach ($folders as $f) if (strcasecmp($f['path'],'INBOX')===0) $unread=$f['unseen'];
                    $s=$db->prepare('INSERT INTO email_cache(ucet,neprecteno,aktualizovano) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE neprecteno=VALUES(neprecteno),aktualizovano=NOW()');
                    $s->bind_param('ii',$a['id'],$unread); $s->execute();
                    $out=['folders'=>$folders,'special'=>em_config()['folders']??[],'order'=>em_poradi_cti($db,(int)$a['id'])];
                } elseif ($action==='save_order') {
                    if ($method!=='POST' || !is_array($d['order']??null)) throw new EmailChyba('Neplatný požadavek na pořadí složek.');
                    $paths=[]; foreach ($client->getFolders(false) as $f) $paths[]=$f->path;
                    em_poradi_uloz($db,(int)$a['id'],$d['order'],$paths); $out=['order'=>$d['order']];
                } elseif ($action==='message' || $action==='attachment') {
                    $m=em_zprava($client,$d);
                    if ($action==='attachment') {
                        $att=$m->getAttachments()->values()->get((int)($d['part']??-1));
                        if (!$att) throw new EmailChyba('Příloha již není dostupná.');
                        header('Content-Type: application/octet-stream'); header("Content-Disposition: attachment; filename=\"priloha\"; filename*=UTF-8''".rawurlencode(em_nazev_prilohy((string)$att->name)));
                        header("Content-Security-Policy: sandbox; default-src 'none'"); echo $att->content; exit;
                    }
                    $attachments=[]; foreach ($m->getAttachments()->values() as $i=>$at) $attachments[]=['part'=>$i,'name'=>(string)$at->name,'size'=>(int)$at->size];
                    $out=['message'=>em_radek($m,$a,(string)$d['folder'],(int)$d['validity'])+['text'=>$m->getTextBody(),'html'=>em_html($m->getHTMLBody(),!empty($d['remote'])),
                        'draft_context'=>(string)$d['folder']===(em_config()['folders']['drafts']??'')?em_kontext_konceptu($a,$m):null,
                        'reply_to'=>em_adresy($m->reply_to),'cc'=>em_adresy($m->cc),'bcc'=>em_adresy($m->bcc),'attachments'=>$attachments,'work'=>em_pracovni_stav($db,$a,$d)]];
                } elseif (in_array($action,['claim','release','done','renew'],true)) {
                    if ($a['typ']==='osobni') throw new EmailChyba('Pracovní stavy jsou určeny pro společné schránky.');
                    $message=em_zprava($client,$d,false); $out=['work'=>em_prevzit($db,$a,$d,$action,$message->flags->has('answered'))];
                } elseif (in_array($action,['read','flag','move'],true)) {
                    $refs=$d['messages']??[$d]; if (!is_array($refs) || count($refs)>50) throw new EmailChyba('Vyberte nejvýše 50 zpráv.');
                    foreach ($refs as $ref) {
                        if ((int)($ref['account']??0)!==(int)$a['id']) throw new EmailChyba('Hromadná akce musí patřit jedné schránce.');
                        $m=em_zprava($client,$ref,false);
                        if ($action==='move') {
                            $target=em_slozka($client,(string)($d['target']??''));
                            if ($target->path===$ref['folder']) continue;
                            $work=em_pracovni_stav($db,$a,$ref);
                            if ($work['stav']==='resi' && !empty($work['zamceno']) && (int)$work['resitel']!==(int)$_SESSION['cb_user']['id_user']) throw new EmailChyba('Zprávu právě vyřizuje jiný uživatel. Přesun nyní není možný.');
                            $messageId=trim((string)$m->message_id);
                            if ((int)$work['verze']>0 && ($messageId==='' || $target->query()->whereMessageId($messageId)->whereUndeleted()->count()>0)) throw new EmailChyba('Nelze bezpečně přenést stav vyřízení. Zprávu ponechte v původní složce.');
                            // Cil pochazi ze serveroveho seznamu, proto je uz v UTF7-IMAP a nesmi se znovu kodovat.
                            if (!$m->move($target->path,false,true)) throw new EmailChyba('Zprávu se nepodařilo přesunout.');
                            if ((int)$work['verze']>0) {
                                $found=$target->query()->whereMessageId($messageId)->whereUndeleted()->fetchBody(false)->get();
                                if ($found->count()!==1) throw new EmailChyba('Zpráva byla přesunuta, ale stav vyřízení se nepodařilo jednoznačně přenést. Obnovte složku.');
                                $newHash=hash('sha256',$target->path); $newValidity=(int)$target->examine()['uidvalidity']; $newUid=(int)$found->first()->uid;
                                [$oldId,$oldHash,$oldValidity,$oldUid]=em_ref($a,$ref);
                                $q=$db->prepare('UPDATE email_stav SET slozka_hash=?,slozka=?,uidvalidity=?,uid=? WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
                                $q->bind_param('ssiiisii',$newHash,$target->path,$newValidity,$newUid,$oldId,$oldHash,$oldValidity,$oldUid); $q->execute();
                            }
                        } else { $flag=$action==='read'?'Seen':'Flagged'; if (!empty($d['value'])) $m->setFlag($flag); else $m->unsetFlag($flag); }
                    }
                    em_audit($db,(int)$a['id'],$action,(string)count($refs)); $out=[];
                } elseif (str_starts_with($action,'folder_')) {
                    $path=(string)($d['folder']??''); $protected=array_merge(['INBOX'],array_values(em_config()['folders']??[]));
                    if ($action!=='folder_create' && in_array($path,$protected,true)) throw new EmailChyba('Zvláštní složku nelze přejmenovat nebo smazat.');
                    $name=trim((string)($d['name']??''));
                    if ($action!=='folder_delete' && ($name==='' || strlen($name)>500 || preg_match('/[\x00-\x1f]/',$name))) throw new EmailChyba('Zadejte platný název složky.');
                    if ($action==='folder_create') $client->createFolder($name,false);
                    elseif ($action==='folder_rename') {
                        $oldFolder=em_slozka($client,$path); $delimiter=$oldFolder->delimiter;
                        $oldFolder->rename($name,false);
                        // Prejmenovani slozky nebo jejiho predka zachovava vazbu pracovnich stavu na presnou novou cestu.
                        $s=$db->prepare('SELECT * FROM email_stav WHERE ucet=?'); $s->bind_param('i',$a['id']); $s->execute();
                        foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $work) {
                            if ($work['slozka']!==$path && !str_starts_with($work['slozka'],$path.$delimiter)) continue;
                            $next=$name.substr($work['slozka'],strlen($path)); $hash=hash('sha256',$next);
                            $q=$db->prepare('UPDATE email_stav SET slozka=?,slozka_hash=? WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
                            $q->bind_param('ssisii',$next,$hash,$a['id'],$work['slozka_hash'],$work['uidvalidity'],$work['uid']); $q->execute();
                        }
                    }
                    elseif ($action==='folder_delete') {
                        if (($d['confirm']??'')!==$path) throw new EmailChyba('Smazání složky musí být výslovně potvrzené.');
                        em_slozka($client,$path)->delete(false);
                    } else throw new EmailChyba('Neznámá akce složky.');
                    em_audit($db,(int)$a['id'],$action,$path?:$name); $out=[];
                } elseif ($action==='send' || $action==='draft') $out=em_odeslat($db,$a,$d,$client,$action==='draft');
                elseif ($action==='repair_sent') {
                    $token=(string)($d['token']??''); $user=(int)$_SESSION['cb_user']['id_user'];
                    $s=$db->prepare('SELECT * FROM email_odeslani WHERE token=? AND ucet=? AND autor=? AND stav="odeslano"');
                    $s->bind_param('sii',$token,$a['id'],$user); $s->execute(); $row=$s->get_result()->fetch_assoc();
                    if (!$row) throw new EmailChyba('Odeslaná zpráva není dostupná.');
                    em_uloz_odeslane($db,$client,$row); $out=['stored'=>true];
                } else throw new EmailChyba('Neznámá akce.');
            }
        }
    }
    echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if (http_response_code()<400) http_response_code($e instanceof EmailChyba?400:502);
    // Protokolové výjimky mohou obsahovat heslo nebo MIME; nesmějí do společného chybového logu.
    echo json_encode(['ok'=>false,'error'=>$e instanceof EmailChyba?$e->getMessage():'Poštovní operaci se nepodařilo dokončit. Zkontrolujte připojení nebo přihlašovací údaje.'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} finally {
    if ($mutationLock!=='' && isset($db)) { try { $s=$db->prepare('SELECT RELEASE_LOCK(?)'); $s->bind_param('s',$mutationLock); $s->execute(); } catch (Throwable) {} }
    if ($client) { try { $client->disconnect(); } catch (Throwable) {} }
}
