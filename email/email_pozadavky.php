<?php
/* E-mail: autorizovane pozadavky posty a spravy; chybove hlaseni overuje schranku a predava konkretni kontext bez hesel. */
declare(strict_types=1);
require_once __DIR__.'/../common/lib/diagnostika_vykonu.php';
cb_diag_server_start('email');
require_once __DIR__.'/../common/lib/chyby_bootstrap.php';
require_once __DIR__.'/../common/lib/session_boot.php';
require_once __DIR__.'/../common/lib/app.php';
// Kontrola session otevírá DB; konfigurace nesmí vzniknout teprve v lokálním rozsahu funkce db().
require_once __DIR__.'/../common/config/secrets.php';
require_once __DIR__.'/../common/lib/ochrana_crf.php';
require_once __DIR__.'/../common/db/db_prava.php';
require_once __DIR__.'/odeslani.php';
require_once __DIR__.'/prilohy.php';
require_once __DIR__.'/prijemci.php';
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
$client=null; $mutationLock='';$streamingOriginal=false;$action='entry';$emailPhase='session';
try {
    cb_session_guard_entry();
    if (empty($_SESSION['login_ok'])) { http_response_code(401); throw new EmailChyba('Přihlášení do IS skončilo.'); }
    if (!hash_equals((string)($_SESSION['cb_pc_session_token']??''),(string)($_SERVER['HTTP_X_COMEBACK_PC_SESSION']??'')) || empty($_SESSION['cb_pc_session_token'])) {
        http_response_code(409); throw new EmailChyba('Platnost tohoto okna skončila. Otevřete modul znovu z IS.');
    }
    $emailPhase='permissions';$db=db(); cb_db_prava_nacti_do_session($db,(int)$_SESSION['cb_user']['id_user']);
    if (!em_pravo(800)) { http_response_code(403); throw new EmailChyba('K modulu E-mail nemáte přístup.'); }
    $method=$_SERVER['REQUEST_METHOD']??'GET';
    if (!in_array($method,['GET','POST'],true)) { http_response_code(405); throw new EmailChyba('Nepodporovaná metoda.'); }
    cb_crf_vyzaduj(); $action=(string)($_GET['action']??'state');
    cb_diag_bind((int)$_SESSION['cb_user']['id_user']);
    // Pri prekroceni PHP limitu je POST prazdny; vratime srozumitelnou chybu misto ztraty formulare.
    if ($method==='POST' && (int)($_SERVER['CONTENT_LENGTH']??0)>0 && empty($_POST) && str_starts_with((string)($_SERVER['CONTENT_TYPE']??''),'multipart/form-data')) {
        throw new EmailChyba('Nahrávání překročilo technický limit serveru. Zmenšete přílohy nebo požádejte správce o kontrolu limitu nahrávání.');
    }
    $d=$method==='POST'?(isset($_POST['data'])?json_decode((string)$_POST['data'],true,32,JSON_THROW_ON_ERROR):json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR)):$_GET;
    if (!is_array($d)) throw new EmailChyba('Neplatný požadavek.');
    cb_diag_bind((int)$_SESSION['cb_user']['id_user'],(array)$d+['search_chars'=>mb_strlen((string)($d['search']??'')),'background'=>$action==='background'?1:0]);
    // Klient neposila volny text chyby; schranku overime proti stejnym pristupum jako pri cteni posty.
    if($action==='client_error'){
        if($method!=='POST')throw new EmailChyba('Záznam chyby vyžaduje potvrzený požadavek.');
        $kind=(string)($d['kind']??'unknown');
        if(!in_array($kind,['TypeError','ReferenceError','SyntaxError','Error','network','response'],true))$kind='Error';
        $reportAccount=0;$reportFolder='';$scope=($d['scope']??'')==='aggregate'?'aggregate':'none';
        if((int)($d['account']??0)>0){
            try{$report=em_ucet($db,(int)$d['account']);$reportAccount=(int)$report['id'];$reportFolder=(string)($d['folder']??'');$scope='account';}
            catch(EmailChyba){/* Cizi nebo jiz odebrana schranka nesmi obohatit hlaseni o jeji identitu. */}
        }
        $out=em_chyba_vysledek(new EmailChyba('Chyba v prohlížeči při práci s poštou.'),['action'=>(string)($d['operation']??'client'),'phase'=>'browser','account'=>$reportAccount,'folder'=>$reportFolder,'scope'=>$scope,'client_kind'=>$kind,'client_line'=>$d['line']??0,'client_status'=>$d['status']??0]);
        echo json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);exit;
    }
    $emailPhase='configuration';
    if ($action==='state' && !em_schema($db)) { echo json_encode(['ok'=>true,'installed'=>false,'message'=>'Modul čeká na spuštění připraveného instalačního SQL.']); exit; }
    if (!em_schema($db)) throw new EmailChyba('Nejprve spusťte instalační SQL modulu E-mail.');
    if ($action==='state') {
        try { em_config(); $out=em_stav($db)+['installed'=>true]; }
        catch (EmailChyba $e) { $failure=em_chyba_vysledek($e,['action'=>'state','phase'=>'configuration']);$out=['installed'=>true,'configured'=>false,'message'=>$failure['error'],'error_detail'=>$failure['error_detail']]; }
    } elseif ($action==='save_signature') {
        if ($method!=='POST') throw new EmailChyba('Uložení podpisu vyžaduje formulář.');
        $out=em_podpis_uloz($db,(string)($d['html']??''));
    } elseif ($action==='save_preferences') {
        if ($method!=='POST') throw new EmailChyba('Uložení nastavení vyžaduje formulář.');
        $out=['preferences'=>em_volby_uloz($db,$d)];
    } elseif ($action==='personal_connections') {
        $out=em_osobni_katalog($db);
    } elseif ($action==='personal_disconnect') {
        if($method!=='POST')throw new EmailChyba('Odpojení schránky vyžaduje potvrzený požadavek.');
        em_osobni_odpojit_spravcem($db,$d);$out=['disconnected'=>true];
    } elseif ($action==='shared_admin') {
        $out=em_sprava_sdilenych_cti($db);
    } elseif ($action==='shared_access_report') {
        $out=em_sprava_pristupy_cti($db,(int)($d['shared_id']??0));
    } elseif (in_array($action,['shared_access','shared_password','shared_remove'],true)) {
        if($method!=='POST')throw new EmailChyba('Správa schránky vyžaduje potvrzený požadavek.');
        em_sprava_sdilenych_zapis($db,$action,$d);$out=['saved'=>true];
    } elseif ($action==='shared_active') {
        if ($method!=='POST') throw new EmailChyba('Změna dostupnosti vyžaduje formulář.');
        em_sdilena_aktivita($db,$d); $out=em_stav($db);
    } elseif ($action==='background') {
        // Samostatny pozadavek obnovuje pocty i po opusteni modulu; login ani hlavickovy endpoint na sit necekaji.
        $accounts=[];
        foreach (em_ucty($db) as $one) {
            try { $accounts[]=['account'=>$one,'password'=>em_heslo($one)]; } catch (EmailChyba) {}
        }
        session_write_close(); $checked=0;$counts=[];
        foreach ($accounts as $entry) {
            $connection=null;
            try {
                $connection=em_imap($entry['account'],$entry['password']);
                $unread=em_precteni_pocet($db,$entry['account'],em_slozka($connection,'INBOX')); $id=(int)$entry['account']['id'];
                if ($entry['account']['typ']==='osobni') {
                    $s=$db->prepare('INSERT INTO email_cache(ucet,neprecteno,aktualizovano) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE neprecteno=VALUES(neprecteno),aktualizovano=NOW()');
                    $s->bind_param('ii',$id,$unread); $s->execute();
                }
                $checked++;$counts[$id]=$unread;
            } catch (Throwable $e) {
                // Posledni platny pocet zachovame, ale skutecne selhani nesmi zustat bez zaznamu.
                em_chyba_vysledek($e,['action'=>'background','phase'=>'unread_count','account'=>$entry['account']['id']]);
            }
            finally { if ($connection) { try { $connection->disconnect(); } catch (Throwable) {} } }
        }
        $out=['checked'=>$checked,'unread'=>$counts];
    } elseif ($action==='connect') {
        if ($method!=='POST') throw new EmailChyba('Připojení vyžaduje formulář.');
        $attempt=$_SESSION['email_pokusy']??['start'=>time(),'count'=>0];
        if (time()-(int)$attempt['start']>300) $attempt=['start'=>time(),'count'=>0];
        if ((int)$attempt['count']>=6) throw new EmailChyba('Příliš mnoho pokusů. Vyčkejte pět minut.');
        $attempt['count']++; $_SESSION['email_pokusy']=$attempt;
        em_pripojit($db,$d); unset($_SESSION['email_pokusy']); $out=em_stav($db);
    } else {
        $emailPhase='account_access';$a=em_ucet($db,(int)($d['account']??0));
        if ($action==='disconnect') {
            if($method!=='POST')throw new EmailChyba('Odpojení schránky vyžaduje potvrzený požadavek.');
            if((int)($d['personal_revision']??-1)!==(int)$a['revize'])throw new EmailChyba('Připojení se mezitím změnilo. Otevřete formulář znovu.');
            // Odpojeni musi fungovat i pri neplatnem hesle nebo nedostupnem postovnim serveru.
            em_odpojit_osobni($db,$a);$out=['disconnected'=>true];
        } elseif ($action==='unlock' || $action==='logout') {
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
            $emailPhase='account_unlock';$password=em_heslo($a);
            // Obnovu hlaviček omezuje i server; opakovany POST nesmi znovu a znovu prochazet IMAP.
            $contactSync=false;
            if($action==='recipients') {
                if($method!=='POST') throw new EmailChyba('Našeptávání vyžaduje formulář.');
                $contactReady=em_kontakty_pripravene($db);
                $contactKey=(int)$a['id'].':'.(int)$a['revize'];
                if($contactReady && !empty($d['sync']) && time()-(int)($_SESSION['email_kontakty_obnova'][$contactKey]??0)>=300) {
                    $_SESSION['email_kontakty_obnova'][$contactKey]=time(); $contactSync=true;
                }
            }
            // Pomalé IMAP/SMTP operace nezamykají session ostatním kartám a modulům.
            session_write_close();
            if (in_array($action,['send_status','repair_sent'],true)) {
                if($method!=='POST')throw new EmailChyba('Tato akce vyžaduje potvrzený požadavek.');
                $out=$action==='send_status'?em_fronta_stav($a,(string)($d['token']??''),!empty($d['cancel_safe'])):em_oprav_odeslane($db,$a,$d);
            } elseif ($action==='recipients') {
                if($contactSync) em_kontakty_obnov($db,$a,$password);
                $out=['ready'=>$contactReady,'contacts'=>$contactReady?em_kontakty_hledej($db,(int)$a['id'],(string)($d['query']??'')):[]];
            } elseif ($action==='list') {
                if(!empty($d['paged']) && !empty($d['aggregate'])) throw new EmailChyba('Stránkovaný přehled načítejte po jednotlivých schránkách.');
                // Dilci souhrn smi vratit jen konkretni sdileny ucet, nikdy cizi osobni postu.
                if(!empty($d['aggregate_part']) && (!em_pravo(801) || !in_array($a['typ'],['pobocka','info'],true))) throw new EmailChyba('Společná pošta není povolená.');
                $accounts=!empty($d['aggregate']) && empty($d['aggregate_part'])?array_values(array_filter(em_ucty($db),static fn($x)=>$x['typ']!=='osobni')):[$a];
                if (!empty($d['aggregate']) && !em_pravo(801)) throw new EmailChyba('Společná pošta není povolená.');
                $out=em_seznam($db,$accounts,$d);
            } else {
                $emailPhase='imap_connect';$client=em_imap($a,$password);
                $writes=['read','move','delete','flag','folder_create','folder_rename','folder_delete','claim','release','done','renew','send','draft','repair_sent'];
                if (in_array($action,$writes,true) && $method!=='POST') throw new EmailChyba('Tato akce vyžaduje potvrzený požadavek.');
                if (in_array($action,$writes,true) && !in_array($action,['send','draft'],true)) {
                    // Zmenu stavu nebo presun nelze provest mezi posledni kontrolou odpovedi a SMTP prenosem.
                    $key='email:send:'.$a['id']; $s=$db->prepare('SELECT GET_LOCK(?,0) n'); $s->bind_param('s',$key); $s->execute();
                    if ((int)$s->get_result()->fetch_assoc()['n']!==1) throw new EmailChyba('Ve schránce právě probíhá odesílání. Vyčkejte a akci opakujte.');
                    $mutationLock=$key;
                }
                if ($action==='row_status') {
                    $out=em_stavy_viditelnych($db,$client,$a,$d);
                } elseif ($action==='folders') {
                    $emailPhase='folders_list';$folders=[];
                    foreach ($client->getFolders(false) as $f) {
                        $emailPhase='folder_status';
                        // Stav/pocty zjisti jednou em_precteni_pocet; dalsi STATUS by byl nepouzity a mohl zbytecne shodit pozadavek.
                        $folders[]=['path'=>$f->path,'name'=>$f->full_name,'delimiter'=>$f->delimiter,'selectable'=>!$f->no_select,'unseen'=>$f->no_select?0:em_precteni_pocet($db,$a,$f)];
                    }
                    $emailPhase='folder_cache';$unread=0; foreach ($folders as $f) if (strcasecmp($f['path'],'INBOX')===0) $unread=$f['unseen'];
                    if ($a['typ']==='osobni') {
                        $s=$db->prepare('INSERT INTO email_cache(ucet,neprecteno,aktualizovano) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE neprecteno=VALUES(neprecteno),aktualizovano=NOW()');
                        $s->bind_param('ii',$a['id'],$unread); $s->execute();
                    }
                    $emailPhase='folder_order';$out=['folders'=>$folders,'special'=>em_config()['folders']??[],'order'=>em_poradi_cti($db,(int)$a['id'])];
                } elseif ($action==='save_order') {
                    if ($method!=='POST' || !is_array($d['order']??null)) throw new EmailChyba('Neplatný požadavek na pořadí složek.');
                    $paths=[]; foreach ($client->getFolders(false) as $f) $paths[]=$f->path;
                    em_poradi_uloz($db,(int)$a['id'],$d['order'],$paths); $out=['order'=>$d['order']];
                } elseif (in_array($action,['message','attachment','original'],true)) {
                    $emailPhase='message_headers';$m=em_zprava($client,$d,false);
                    if ($action==='original') {
                        $originalSize=(int)$m->size;
                        if($originalSize<1)throw new EmailChyba('Server neposkytl velikost originálu zprávy.');
                        header('Content-Type: message/rfc822');header('Content-Disposition: attachment; filename="zprava.eml"');
                        header('Content-Length: '.$originalSize);$streamingOriginal=true;
                        header("Content-Security-Policy: sandbox; default-src 'none'");
                        // Bajty originalu se neprepisuji a nestava se z nich jeden velky retezec v pameti.
                        for($offset=0;$offset<$originalSize;$offset+=65536){$chunk=em_mime_blok($client,(int)$d['uid'],'',$offset,min(65536,$originalSize-$offset));if(strlen($chunk)!==min(65536,$originalSize-$offset))throw new EmailChyba('Server vrátil neúplný originál zprávy.');echo $chunk;}
                        exit;
                    }
                    if ($action==='attachment') {
                        $parts=em_mime_struktura($client,(int)$d['uid']);$att=null;
                        foreach($parts as $part)if(!$part['body'] && $part['part']===(string)($d['part']??'')){$att=$part;break;}
                        if (!$att) throw new EmailChyba('Příloha již není dostupná.');
                        $editorInline=!empty($d['editor']) && !empty($d['inline']);
                        if ($editorInline && ((string)$d['folder']!==(em_config()['folders']['drafts']??'') || (int)(em_kontext_konceptu($a,$m)['format']??0)!==2)) throw new EmailChyba('Obrázek editoru musí patřit vlastnímu konceptu.');
                        $content=em_mime_obsah($client,(int)$d['uid'],$att,!empty($d['inline']) && !$editorInline?8388608:(int)ceil(em_limit_priloh(em_osoba($db)['manager'])*1.37)); $type=em_priloha_typ($content);
                        if(!empty($d['inline']) && !in_array($type,['image/png','image/jpeg','image/gif','image/webp','image/avif'],true))throw new EmailChyba('Tento vložený obrázek nelze bezpečně zobrazit.');
                        $name=em_nazev_prilohy($att['name']);
                        // Nepovinna priprava pro Office nesmi pri nedostupne cache zablokovat stazeni puvodni prilohy.
                        try { if(empty($d['inline'])) {
                            $office=em_priloha_office($content,$name);
                            if ($office) {
                                $token=em_priloha_odkaz($content,$name,$office['type']);
                                header('X-Comeback-Office-Url: '.cb_root_url('email/otevrit.php/'.rawurlencode($name).'?token='.$token));
                                header('X-Comeback-Office-Scheme: '.$office['scheme']); header('X-Comeback-Office-Label: '.$office['label']);
                            } }
                        } catch (Throwable $e) {error_log('EMAIL_ATTACHMENT_OFFICE class='.get_class($e).' at='.basename($e->getFile()).':'.$e->getLine());}
                        header('Content-Type: '.$type); header("Content-Disposition: attachment; filename=\"priloha\"; filename*=UTF-8''".rawurlencode($name));
                        header("Content-Security-Policy: sandbox; default-src 'none'"); cb_diag_response(strlen($content)); echo $content; exit;
                    }
                    $emailPhase='message_body';$detail=em_mime_detail($client,$d,(string)($d['encoding']??'auto'));
                    // Externi obrazky povoli jen vyslovny pozadavek; bez parametru zustavaji skryte.
                    $out=['message'=>em_radek($m,$a,(string)$d['folder'],(int)$d['validity'])+['text'=>$detail['text'],'html'=>em_zobrazeni_html($detail['html'],!empty($d['remote'])),'encoding'=>$detail['encoding'],
                        'draft_context'=>(string)$d['folder']===(em_config()['folders']['drafts']??'')?em_kontext_konceptu($a,$m):null,
                        'reply_to'=>em_adresy($m->reply_to),'cc'=>em_adresy($m->cc),'bcc'=>em_adresy($m->bcc),'attachments'=>$detail['attachments'],'work'=>em_pracovni_stav($db,$a,$d)]];
                    if ($a['typ']!=='osobni') {
                        $map=em_precteni_mapa($db,$a,em_slozka($client,(string)$d['folder']),(int)$d['validity']);
                        $out['message']['seen']=!empty($map[(int)$d['uid']]);
                    }
                    if ((int)($out['message']['draft_context']['format']??0)===2) {
                        $draftParts=em_mime_koncept_casti($detail['html']);
                        $out['message']['draft_editor_html']=em_html($draftParts['editor'],false,array_column($detail['attachments'],'cid'));
                        $out['message']['draft_quote_html']=em_zobrazeni_html($draftParts['quote'],!empty($d['remote']));
                    }
                } elseif (in_array($action,['claim','release','done','renew'],true)) {
                    if ($a['typ']==='osobni') throw new EmailChyba('Pracovní stavy jsou určeny pro společné schránky.');
                    $message=em_zprava($client,$d,false); $out=['work'=>em_prevzit($db,$a,$d,$action,$message->flags->has('answered'))];
                } elseif (in_array($action,['read','flag','move','delete'],true)) {
                    $refs=$d['messages']??[$d]; if (!is_array($refs) || count($refs)>50) throw new EmailChyba('Vyberte nejvýše 50 zpráv.');
                    // Cely vyber se autorizuje pred prvni nevratnou operaci.
                    foreach ($refs as $ref) if (!is_array($ref) || (int)($ref['account']??0)!==(int)$a['id']) throw new EmailChyba('Hromadná akce musí patřit jedné schránce.');
                    if ($action==='delete') {
                        em_smazat_z_kose($client,$refs,(string)($d['confirm']??''));
                        if (em_precteni_schema($db)) {
                            $q=$db->prepare('DELETE FROM email_precteni_cache WHERE ucet=?'); $q->bind_param('i',$a['id']); $q->execute();
                        }
                    }
                    foreach ($refs as $ref) {
                        if ($action==='delete') continue;
                        if ((int)($ref['account']??0)!==(int)$a['id']) throw new EmailChyba('Hromadná akce musí patřit jedné schránce.');
                        $m=em_zprava($client,$ref,false);
                        if ($action==='move') {
                            $target=em_slozka($client,(string)($d['target']??''));
                            if ($target->path===$ref['folder']) continue;
                            $work=em_pracovni_stav($db,$a,$ref);
                            if ($work['stav']==='resi' && !empty($work['zamceno']) && (int)$work['resitel']!==(int)$_SESSION['cb_user']['id_user']) throw new EmailChyba('Zprávu právě vyřizuje jiný uživatel. Přesun nyní není možný.');
                            $messageId=trim((string)$m->message_id);
                            $transfer=(int)$work['verze']>0 || $a['typ']!=='osobni';
                            $caps=$a['typ']!=='osobni'?array_map('strtoupper',(array)$client->getConnection()->getCapabilities()->validatedData()):[];
                            $uidMove=(in_array('MOVE',$caps,true) && in_array('UIDPLUS',$caps,true)) || in_array('IMAP4REV2',$caps,true);
                            if ($transfer && !$uidMove && ($messageId==='' || $target->query()->whereMessageId($messageId)->whereUndeleted()->count()>0)) throw new EmailChyba('Nelze bezpečně přenést stav zprávy. Zprávu ponechte v původní složce.');
                            if ($a['typ']!=='osobni') {
                                $baseline=em_precteni_zaklad($db,$a,em_slozka($client,(string)$ref['folder']),(int)$ref['validity']);
                                em_precteni_zaklad($db,$a,$target,(int)$target->examine()['uidvalidity']);
                            }
                            // Cil pochazi ze serveroveho seznamu, proto je uz v UTF7-IMAP a nesmi se znovu kodovat.
                            $moved=$uidMove?em_presun_uid($client,$ref,$target->path):null;
                            if (!$uidMove && !$m->move($target->path,false,true)) throw new EmailChyba('Zprávu se nepodařilo přesunout.');
                            if ($transfer) {
                                if (!$uidMove) {
                                    $found=$target->query()->whereMessageId($messageId)->whereUndeleted()->fetchBody(false)->get();
                                    if ($found->count()!==1) throw new EmailChyba('Zpráva byla přesunuta, ale stav vyřízení se nepodařilo jednoznačně přenést. Obnovte složku.');
                                    $moved=['validity'=>(int)$target->examine()['uidvalidity'],'uid'=>(int)$found->first()->uid];
                                }
                                $newHash=hash('sha256',$target->path); $newValidity=$moved['validity']; $newUid=$moved['uid'];
                                [$oldId,$oldHash,$oldValidity,$oldUid]=em_ref($a,$ref);
                                if ($a['typ']!=='osobni') em_precteni_presun($db,$a,$ref,['folder'=>$target->path,'validity'=>$newValidity,'uid'=>$newUid],!empty($baseline[$oldUid]));
                                $q=$db->prepare('UPDATE email_stav SET slozka_hash=?,slozka=?,uidvalidity=?,uid=? WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
                                $q->bind_param('ssiiisii',$newHash,$target->path,$newValidity,$newUid,$oldId,$oldHash,$oldValidity,$oldUid); $q->execute();
                            }
                        } elseif ($action==='read' && $a['typ']!=='osobni') {
                            em_precteni_zaklad($db,$a,em_slozka($client,(string)$ref['folder']),(int)$ref['validity']);
                            em_precteni_uloz($db,$a,$ref,!empty($d['value']));
                        } else { $flag=$action==='read'?'Seen':'Flagged'; if (!empty($d['value'])) $m->setFlag($flag); else $m->unsetFlag($flag); }
                    }
                    em_audit($db,(int)$a['id'],$action,(string)count($refs)); $out=[];
                    if ($a['typ']!=='osobni') {
                        $out['unread']=[(int)$a['id']=>em_precteni_pocet($db,$a,em_slozka($client,'INBOX'))];
                    }
                } elseif (str_starts_with($action,'folder_')) {
                    $path=(string)($d['folder']??''); $protected=array_merge(['INBOX'],array_values(em_config()['folders']??[]));
                    if ($action!=='folder_create' && in_array($path,$protected,true)) throw new EmailChyba('Zvláštní složku nelze přejmenovat nebo smazat.');
                    $name=trim((string)($d['name']??''));
                    if ($action!=='folder_delete' && ($name==='' || strlen($name)>500 || preg_match('/[\x00-\x1f]/',$name))) throw new EmailChyba('Zadejte platný název složky.');
                    if ($action==='folder_create') $client->createFolder($name,false);
                    elseif ($action==='folder_rename') {
                        $oldFolder=em_slozka($client,$path); $delimiter=$oldFolder->delimiter;
                        $oldFolder->rename($name,false);
                        em_precteni_prejmenovat($db,(int)$a['id'],$path,$name,$delimiter);
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
                } elseif ($action==='send' || $action==='draft') {
                    $out=em_odeslat($db,$a,$d,$client,$action==='draft');
                    // Potvrzene adresy jsou ihned k dispozici i pred dalsi obnovou hlavicek; chyba adresare nesmi zpochybnit odeslani.
                    if($action==='send' && !empty($out['sent'])) {
                        try {
                            if(em_kontakty_pripravene($db)) {
                                $addresses=[];
                                foreach(['to','cc','bcc'] as $field) foreach(preg_split('/[;,]/',(string)($d[$field]??'')) as $email) $addresses[]=['email'=>$email,'name'=>''];
                                em_kontakty_uloz($db,(int)$a['id'],$addresses);
                            }
                        } catch(Throwable $e) {error_log('EMAIL_CONTACT_SAVE account='.(int)$a['id'].' class='.get_class($e));}
                    }
                }
                else throw new EmailChyba('Neznámá akce.');
            }
        }
    }
    $response=json_encode(['ok'=>true]+$out,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
    cb_diag_response(strlen($response)); echo $response;
} catch (Throwable $e) {
    if (!headers_sent() && http_response_code()<400) http_response_code($e instanceof EmailChyba?400:502);
    cb_diag_response();
    // Logujeme bezpecna metadata i pri stahovani; protokolovy text a argumenty mohou obsahovat heslo nebo MIME.
    // STATUS pracuje jen s nazvem slozky; jeho odmitnuti potrebujeme priradit ke konkretni slozce.
    $failure=em_chyba_vysledek($e,['action'=>$action,'phase'=>$emailPhase,'account'=>$a['id']??0,
        'folder'=>$action==='folders' && $emailPhase==='folder_status' && isset($f)?$f->path:'']);
    if(!$streamingOriginal)echo json_encode(['ok'=>false]+$failure,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} finally {
    if ($mutationLock!=='' && isset($db)) { try { $s=$db->prepare('SELECT RELEASE_LOCK(?)'); $s->bind_param('s',$mutationLock); $s->execute(); } catch (Throwable) {} }
    if ($client) { try { $client->disconnect(); } catch (Throwable) {} }
}
