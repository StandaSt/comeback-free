<?php
/* E-mail: jednorazove SMTP a samostatne doplneni Odeslanych z chranene docasne kopie; koncepty i overene prilohy zachovavaji HR limity. */
declare(strict_types=1);
require_once __DIR__.'/data.php';
require_once __DIR__.'/fronta_odeslani.php';

/** Přílohy z formuláře neopouštějí požadavek; celkový limit zahrnuje i původní přeposílané přílohy. */
function em_uploady(int $limit): array
{
    $out=[]; $size=0; $f=$_FILES['attachments']??null;
    if (!$f) return [];
    if (!is_array($f['name']??null) || count($f['name'])>20) throw new EmailChyba('Lze připojit nejvýše 20 příloh.');
    foreach ($f['name'] as $i=>$name) {
        if (($f['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
        if (($f['error'][$i]??-1)!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) throw new EmailChyba('Přílohu se nepodařilo nahrát.');
        $size+=(int)$f['size'][$i];
        if ($size>$limit) throw new EmailChyba('Celková velikost příloh nesmí překročit '.($limit/1024/1024).' MB.');
        $out[]=['name'=>em_nazev_prilohy((string)$name),'content'=>file_get_contents($f['tmp_name'][$i]),'type'=>'application/octet-stream'];
    }
    return $out;
}

/** CID z editoru smi oznacovat jen skutecne nahrany a overeny rastrovy obrazek. */
function em_editor_obrazky(array $files, mixed $inline, string $html): array
{
    if (!is_array($inline) || !array_is_list($inline) || count($inline)>20) throw new EmailChyba('Neplatné vložené obrázky.');
    $indexes=[]; $cids=[];
    foreach ($inline as $entry) {
        $index=$entry['index']??null; $cid=$entry['cid']??null;
        if (!is_int($index) || !isset($files[$index]) || !is_string($cid) || !preg_match('/^cbpaste-[a-f0-9-]{36}@comeback$/D',$cid)
            || in_array($index,$indexes,true) || in_array($cid,$cids,true)) throw new EmailChyba('Neplatné přiřazení vloženého obrázku.');
        $type=em_priloha_typ($files[$index]['content']);
        if (!in_array($type,['image/png','image/jpeg','image/gif','image/webp','image/avif'],true) || @getimagesizefromstring($files[$index]['content'])===false) throw new EmailChyba('Vložený soubor není podporovaný rastrový obrázek.');
        $files[$index]['type']=$type; $files[$index]['cid']=$cid; $indexes[]=$index; $cids[]=$cid;
    }
    foreach (em_editor_cids($html) as $cid) if (!in_array($cid,$cids,true)) throw new EmailChyba('Chybí vložený obrázek. Otevřete koncept znovu.');
    return $files;
}

/** Seznam CID z overovaneho HTML oddeluje obrazky editoru od obrazku puvodni citace. */
function em_editor_cids(string $html): array
{
    $doc=new DOMDocument();$old=libxml_use_internal_errors(true);
    try {$doc->loadHTML('<?xml encoding="UTF-8"><body>'.em_html_utf8($html).'</body>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);}
    finally {libxml_clear_errors();libxml_use_internal_errors($old);}
    $cids=[];foreach ($doc->getElementsByTagName('img') as $img) if (str_starts_with($img->getAttribute('src'),'cid:')) $cids[]=substr($img->getAttribute('src'),4);
    return array_values(array_unique($cids));
}

/** Klient vybira jen bezne prilohy autorizovane zpravy; CID obrazky zavisi na pripojene citaci. */
function em_vybrane_prilohy(array $parts, array $selected, bool $quote, bool $all): array
{
    if (!array_is_list($selected) || count($selected)>20) throw new EmailChyba('Neplatný výběr příloh.');
    $known=array_column(array_filter($parts,static fn($p)=>$p['cid']===''),'part');
    foreach ($selected as $part) if (!is_string($part) || !in_array($part,$known,true)) throw new EmailChyba('Výběr příloh již neodpovídá zprávě. Otevřete koncept znovu.');
    return array_values(array_filter($parts,static fn($p)=>$p['cid']!==''?$quote:($all && in_array($p['part'],$selected,true))));
}

/** Samostatny pokus smi opakovat pouze IMAP APPEND; stabilni Message-ID zabrani druhe kopii po ztrate odpovedi. */
function em_oprav_odeslane(mysqli $db, array $a, array $d): array
{
    $token=(string)($d['token']??'');em_fronta_cesta($a,$token);
    $key='email:send:'.$a['id'];$s=$db->prepare('SELECT GET_LOCK(?,0) n');$s->bind_param('s',$key);$s->execute();
    if((int)$s->get_result()->fetch_assoc()['n']!==1)return ['send_state'=>'busy','sent'=>false,'stored'=>false];
    $fileLock=null;$client=null;
    try {
        $fileLock=em_fronta_zamek($a);if($fileLock===null)return ['send_state'=>'busy','sent'=>false,'stored'=>false];
        em_fronta_uklid($a);$row=em_fronta_cti($a,$token);
        if(!$row)throw new EmailChyba('Stav odeslané zprávy již není dostupný. Odeslání neopakujte.');
        if($row['state']==='complete'){em_fronta_smaz_mime($a,$token);return em_fronta_vysledek($row);}
        if(!in_array($row['state'],['sent','expired'],true))return em_fronta_vysledek($row);
        try {
            $client=em_imap($a,em_heslo($a));$folder=em_slozka($client,$row['slozka_odeslane']);
            if($folder->query()->whereMessageId($row['message_id'])->whereUndeleted()->count()===0){
                if((int)$row['created']<time()-86400 || !is_file(em_fronta_cesta($a,$token).'.mime')){
                    $row['state']='expired';em_fronta_zapis($a,$token,$row);return em_fronta_vysledek($row);
                }
                $folder->appendMessage(em_fronta_mime($a,$token,$row),['\\Seen']);
            }
            if($folder->query()->whereMessageId($row['message_id'])->whereUndeleted()->count()<1)throw new RuntimeException('IMAP nepotvrdil kopii.');
        }catch(Throwable){return em_fronta_vysledek($row);}
        // Dokonceni kopie nesmi zpochybnit nasledna chyba priznaku nebo evidence vyrizeni.
        $row['work_saved']=true;
        try {
            if(is_array($row['draft_ref']??null))em_zprava($client,$row['draft_ref'],false)->delete(false);
            $ref=$row['source']??null;
            if(is_array($ref)){
                $source=em_zprava($client,$ref,false);
                if(($row['kind']??'')==='draft_edit' && $source->getFolderPath()===(em_config()['folders']['drafts']??''))$source->delete(false);
                if(in_array($row['kind']??'',['reply','reply_all'],true))$source->setFlag('Answered');
                if($a['typ']!=='osobni'){
                    [$id,$hash,$v,$uid]=em_ref($a,$ref);
                    $s=$db->prepare('UPDATE email_stav SET stav="odpovezeno",zamek_do=NULL,verze=verze+1,zmeneno=NOW() WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
                    $s->bind_param('isii',$id,$hash,$v,$uid);$s->execute();
                }
            }
            em_audit($db,(int)$a['id'],'odeslani',$row['message_id']);
        }catch(Throwable){$row['work_saved']=false;}
        $row['state']='complete';em_fronta_zapis($a,$token,$row);em_fronta_smaz_mime($a,$token);
        return em_fronta_vysledek($row);
    }finally{
        if($client)try{$client->disconnect();}catch(Throwable){}
        if($fileLock){flock($fileLock,LOCK_UN);fclose($fileLock);}
        try{$s=$db->prepare('SELECT RELEASE_LOCK(?)');$s->bind_param('s',$key);$s->execute();}catch(Throwable){}
    }
}

/** Uspesny SMTP prenos se neopakuje kvuli ukladani kopie; jeho diagnostika nesmi zmenit vysledek odeslani. */
function em_odeslat(mysqli $db, array $a, array $d, Webklex\PHPIMAP\Client $client, bool $draft=false): array
{
    if((int)($d['compose_format']??0)!==4)throw new EmailChyba('Před odesláním obnovte IS, aby se načetla aktuální verze editoru.');
    $token=(string)($d['token']??''); $user=(int)$_SESSION['cb_user']['id_user'];
    em_fronta_cesta($a,$token);
    // Jeden zámek na schránku chrání souběh odeslání i dvojkliků bez dlouhé DB transakce přes síť.
    $lock='email:send:'.$a['id']; $s=$db->prepare('SELECT GET_LOCK(?,0) n'); $s->bind_param('s',$lock); $s->execute();
    if ((int)$s->get_result()->fetch_assoc()['n']!==1) throw new EmailChyba('Ve schránce právě probíhá odesílání. Vyčkejte a zkuste to znovu.');
    $fileLock=null;$mail=null;
    try {
        $fileLock=em_fronta_zamek($a);
        if($fileLock===null)throw new EmailChyba('Ve schránce právě probíhá odesílání. Vyčkejte.');
        em_fronta_uklid($a);$existing=em_fronta_cti($a,$token);
        if($existing){
            if($draft)throw new EmailChyba('Tato zpráva už byla předána k odeslání.');
            if($existing['state']==='sending'){$existing['state']='uncertain';em_fronta_zapis($a,$token,$existing);}
            if(!in_array($existing['state'],['preparing','prepared','cancelled'],true))return em_fronta_vysledek($existing);
            throw new EmailChyba('Tento pokus o odeslání byl přerušen. Ověřte stav zprávy.');
        }
        if(!$draft){
            $started=(int)($d['started']??0);
            if($started<time()-3600 || $started>time()+300)throw new EmailChyba('Pokus o odeslání vypršel. Ověřte stav zprávy.');
            em_fronta_zapis($a,$token,['owner'=>em_identita(),'account'=>(int)$a['id'],'created'=>time(),'state'=>'preparing']);
        }
        $limit=em_limit_priloh(em_osoba($db)['manager']);
        if (!is_array($d['retained_parts']??null)) throw new EmailChyba('Chybí výběr příloh. Otevřete editor znovu.');
        $files=em_editor_obrazky(em_uploady($limit),$d['inline_images']??[],(string)($d['body_html']??''));
        $source=null; $ref=$d['source']??null; $draftMessage=null;$quote='';$includeQuote=!empty($d['include_quote']);
        if (is_array($d['draft_ref']??null)) {
            $draftRef=$d['draft_ref'];
            if ((int)($draftRef['account']??0)!==(int)$a['id'] || ($draftRef['folder']??'')!==(em_config()['folders']['drafts']??'')) throw new EmailChyba('Neplatny puvodni koncept.');
            $draftMessage=em_zprava($client,$draftRef,false);$draftDetail=em_mime_detail($client,$draftRef);
            $draftCtx=em_kontext_konceptu($a,$draftMessage);
            if($includeQuote) {
                $stored=(int)($draftCtx['format']??0)===2?em_mime_koncept_casti($draftDetail['html'])['quote']:($draftDetail['html']?:'<pre>'.htmlspecialchars($draftDetail['text'],ENT_QUOTES,'UTF-8').'</pre>');
                $quote=em_zobrazeni_html($stored,true,true);
            }
            $parts=em_vybrane_prilohy($draftDetail['attachments'],$d['retained_parts'],$includeQuote,true);
            // Obrazky vlastniho textu se nahravaji z editoru znovu; z konceptu zachovame jen CID citace.
            $quoteCids=em_editor_cids($quote);
            $parts=array_values(array_filter($parts,static fn($p)=>$p['cid']==='' || in_array($p['cid'],$quoteCids,true)));
            $files=array_merge($files,em_mime_soubory($client,$draftRef,$parts,true,$limit));
        }
        if (is_array($ref)) {
            if ((int)($ref['account']??0)!==(int)$a['id']) throw new EmailChyba('Odpověď musí odejít z původní schránky.');
            $source=em_zprava($client,$ref,false);
            if (($d['kind']??'')==='draft_edit' && $source->getFolderPath()!==(em_config()['folders']['drafts']??'')) throw new EmailChyba('Upravovat lze pouze zprávu ze složky Rozepsané.');
            if ($a['typ']!=='osobni' && !$draft && ($d['kind']??'')!=='draft_edit') {
                $work=em_pracovni_stav($db,$a,$ref);
                if ($work['stav']!=='resi' || (int)$work['resitel']!==$user || (int)$work['zamceno']!==1 || (int)$work['verze']!==(int)($d['version']??-1)) throw new EmailChyba('Převzetí zprávy se změnilo nebo vypršelo. Obnovte její stav.');
                if ($source->flags->has('answered') && empty($work['odpovezeno_predem'])) throw new EmailChyba('Na zprávu mezitím přišla odpověď z jiného klienta. Před odesláním zkontrolujte Odeslané.');
            }
            if ((!$draftMessage || ($includeQuote && $quote==='')) && ($includeQuote || ($d['kind']??'')==='forward')) {
                $detail=em_mime_detail($client,$ref,(string)($d['quote_encoding']??'auto'));
                if($includeQuote)$quote=em_mime_citace($detail,$source,(string)($d['kind']??''));
                // Ulozeny koncept uz vlastni vsechny zachovane prilohy; z puvodni zpravy je znovu nepridavame.
                if (!$draftMessage) {
                    $parts=em_vybrane_prilohy($detail['attachments'],$d['retained_parts'],$includeQuote,($d['kind']??'')==='forward');
                    $files=array_merge($files,em_mime_soubory($client,$ref,$parts,true,$limit));
                } elseif ($includeQuote) {
                    // Pri znovupripojeni citace obnovime chybejici CID obrazky, bez odebranych souborovych priloh.
                    $knownCids=array_column($draftDetail['attachments'],'cid');
                    $parts=array_filter($detail['attachments'],static fn($p)=>$p['cid']!=='' && !in_array($p['cid'],$knownCids,true));
                    $files=array_merge($files,em_mime_soubory($client,$ref,$parts,true,$limit));
                }
            }
        }
        if (count($files)>20 || array_sum(array_map(static fn($f)=>strlen($f['content']),$files))>$limit) throw new EmailChyba('Celková velikost příloh nesmí překročit '.($limit/1024/1024).' MB a 20 souborů.');
        $draftWithoutRecipient=$draft && trim((string)($d['to']??''))==='';
        $mailInput=$d;
        // Interni obsah sestavuje pouze tento handler; stejnojmenna klientova pole se ignoruji.
        $mailInput['_quote_html']=$quote;$mailInput['_draft_format']=$draft;
        if ($draftWithoutRecipient) $mailInput['to']=$a['email'];
        $mail=em_mailer($a,$mailInput,$files);
        // Stejne rozpracovane zprave patri stabilni Message-ID i pri opakovanem ulozeni konceptu.
        $mail->MessageID='<'.$token.'@pizzacomeback.cz>';
        if ($draft) {
            $mail->addCustomHeader('X-Comeback-Koncept',em_zasifruj(json_encode(['source'=>$ref,'kind'=>$d['kind']??'new','format'=>2,'quote'=>$includeQuote,'quote_encoding'=>$d['quote_encoding']??'auto'],JSON_THROW_ON_ERROR),'koncept:'.$a['email']));
        }
        if ($source && in_array($d['kind']??'',['reply','reply_all'],true)) {
            $messageId=trim((string)$source->message_id,'<> ');
            if ($messageId!=='' && !preg_match('/[\r\n]/',$messageId)) {
                $mail->addCustomHeader('In-Reply-To','<'.$messageId.'>');
                $mail->addCustomHeader('References','<'.$messageId.'>');
            }
        }
        if ($draftMessage && !$source) {
            // Koncept muze pochazet i z jineho klienta; zachovame jeho vazbu na puvodni vlakno.
            $replyId=trim((string)$draftMessage->in_reply_to,'<> ');
            if ($replyId!=='' && !preg_match('/[\r\n]/',$replyId)) $mail->addCustomHeader('In-Reply-To','<'.$replyId.'>');
        }
        $folders=em_config()['folders']??[]; $target=(string)($folders[$draft?'drafts':'sent']??'');
        em_slozka($client,$target); // Neúplné přiřazení složek zastaví akci ještě před SMTP.
        $mail->preSend(); $mime=$mail->getSentMIMEMessage();
        // Skryti prijemci patri do soukrome kopie v Odeslanych, nikdy do MIME hlavicky odesilane prijemcum.
        $bcc=$mail->getBccAddresses();
        if ($bcc) $mime='Bcc: '.implode(', ',array_column($bcc,0))."\r\n".$mime;
        if ($draftWithoutRecipient) {
            // PHPMailer vyzaduje adresata pro sestaveni MIME; do ulozeneho prazdneho konceptu se pomocna adresa nedostane.
            [$head,$body]=explode("\r\n\r\n",$mime,2);
            $head=preg_replace('/^To:[^\r\n]*(?:\r\n[ \t][^\r\n]*)*\r\n/m','',$head."\r\n");
            $mime=rtrim($head,"\r\n")."\r\n\r\n".$body;
        }
        if ($draft) {
            $draftFolder=em_slozka($client,$target);
            $previous=$draftFolder->query()->whereMessageId($mail->getLastMessageID())->fetchBody(false)->get();
            $draftFolder->appendMessage($mime,['\\Draft','\\Seen']);
            foreach ($previous as $oldDraft) $oldDraft->delete(false);
            if ($draftMessage) $draftMessage->delete(false);
            if (($d['kind']??'')==='draft_edit' && $source && $source->getFolderPath()===$target) $source->delete(false);
            em_audit($db,(int)$a['id'],'koncept',$mail->getLastMessageID());
            $saved=$draftFolder->query()->whereMessageId($mail->getLastMessageID())->whereUndeleted()->fetchBody(false)->get();
            $newRef=null;
            if ($saved->count()===1) $newRef=['account'=>(int)$a['id'],'folder'=>$target,'validity'=>(int)$draftFolder->examine()['uidvalidity'],'uid'=>(int)$saved->first()->uid];
            if (!$newRef) throw new EmailChyba('Koncept byl uložen, ale server nepotvrdil jeho novou identitu. Zavřete editor a otevřete jej ze složky Rozepsané.');
            return ['draft'=>true,'draft_ref'=>$newRef,'draft_attachments'=>array_values(array_filter(em_mime_struktura($client,$newRef['uid']),static fn($p)=>!$p['body']))];
        }
        // Presny MIME pro Odeslane je pouze v chranenem docasnem souboru, nikdy v DB.
        $row=['owner'=>em_identita(),'account'=>(int)$a['id'],'created'=>time(),'state'=>'prepared',
            'message_id'=>$mail->getLastMessageID(),'slozka_odeslane'=>$target,'source'=>$ref,
            'draft_ref'=>$d['draft_ref']??null,'kind'=>$d['kind']??'new'];
        em_fronta_uloz_mime($a,$token,$mime);em_fronta_zapis($a,$token,$row);
        // Limit SMTP zahrnuje prenosove kodovani i hlavicky cele zpravy.
        if(!$mail->smtpConnect())throw new EmailChyba('Spojení s poštovním serverem se nepodařilo navázat.');
        $smtpSize=$mail->getSMTPInstance()->getServerExt('SIZE');
        if(is_numeric($smtpSize) && (int)$smtpSize>0 && strlen($mime)>(int)$smtpSize)throw new EmailChyba('Poštovní server nepřijme zprávu této velikosti včetně přenosového kódování. Odeberte část příloh.');
        $diagStart=cb_diag_clock();
        try{return em_fronta_predat($a,$token,$row,static fn()=>$mail->postSend());}
        finally{cb_diag_span('smtp_send',$diagStart);}
    }catch(Throwable $e){
        if(!$draft && $fileLock){
            // Pred vstupem do SMTP lze pokus zrusit; pozdejsi stavy se nikdy nevraceji k novemu odeslani.
            try{$row=em_fronta_cti($a,$token);if($row && in_array($row['state'],['preparing','prepared'],true)){$row['state']='cancelled';em_fronta_zapis($a,$token,$row);em_fronta_smaz_mime($a,$token);}}catch(Throwable){}
        }
        throw $e;
    }finally{
        if($mail)try{$mail->smtpClose();}catch(Throwable){}
        if($fileLock){flock($fileLock,LOCK_UN);fclose($fileLock);}
        try{$s=$db->prepare('SELECT RELEASE_LOCK(?)');$s->bind_param('s',$lock);$s->execute();}catch(Throwable){}
    }
}
