<?php
/* E-mail: jednorazove odesilani, oddelena puvodni citace a MIME obrazky; koncept uchovava text editoru samostatne. */
declare(strict_types=1);
require_once __DIR__.'/data.php';

/** Přílohy z formuláře neopouštějí požadavek; celkový limit zahrnuje i původní přeposílané přílohy. */
function em_uploady(): array
{
    $out=[]; $f=$_FILES['attachments']??null;
    if (!$f) return [];
    if (!is_array($f['name']??null) || count($f['name'])>20) throw new EmailChyba('Lze připojit nejvýše 20 příloh.');
    foreach ($f['name'] as $i=>$name) {
        if (($f['error'][$i]??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) continue;
        if (($f['error'][$i]??-1)!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'][$i])) throw new EmailChyba('Přílohu se nepodařilo nahrát.');
        if ((int)$f['size'][$i]>20*1024*1024) throw new EmailChyba('Příloha je příliš velká.');
        $out[]=['name'=>em_nazev_prilohy((string)$name),'content'=>file_get_contents($f['tmp_name'][$i]),'type'=>'application/octet-stream'];
    }
    return $out;
}

/** Při opravě nejprve hledáme Message-ID, aby přerušené potvrzení APPEND nevytvořilo druhou kopii. */
function em_uloz_odeslane(mysqli $db, Webklex\PHPIMAP\Client $client, array $row): void
{
    $folder=em_slozka($client,$row['slozka_odeslane']);
    if ($folder->query()->whereMessageId($row['message_id'])->count()===0) {
        $folder->appendMessage(em_rozsifruj($row['mime_sifra'],'odeslani:'.$row['token']),['\\Seen']);
    }
    $s=$db->prepare('UPDATE email_odeslani SET ulozeno=1 WHERE token=?'); $s->bind_param('s',$row['token']); $s->execute();
}

/** Uspesny SMTP prenos se neopakuje kvuli ukladani kopie; jeho diagnostika nesmi zmenit vysledek odeslani. */
function em_odeslat(mysqli $db, array $a, array $d, Webklex\PHPIMAP\Client $client, bool $draft=false): array
{
    if((int)($d['compose_format']??0)!==2)throw new EmailChyba('Před odesláním obnovte IS, aby se načetla aktuální verze editoru.');
    $token=(string)($d['token']??''); $user=(int)$_SESSION['cb_user']['id_user'];
    if (!preg_match('/^[a-f0-9-]{36}$/',$token)) throw new EmailChyba('Chybí identifikátor zprávy.');
    // Jeden zámek na schránku chrání souběh odeslání i dvojkliků bez dlouhé DB transakce přes síť.
    $lock='email:send:'.$a['id']; $s=$db->prepare('SELECT GET_LOCK(?,0) n'); $s->bind_param('s',$lock); $s->execute();
    if ((int)$s->get_result()->fetch_assoc()['n']!==1) throw new EmailChyba('Ve schránce právě probíhá odesílání. Vyčkejte a zkuste to znovu.');
    try {
        $s=$db->prepare('SELECT * FROM email_odeslani WHERE token=?'); $s->bind_param('s',$token); $s->execute(); $existing=$s->get_result()->fetch_assoc();
        if ($existing) {
            if ((int)$existing['ucet']!==(int)$a['id'] || (int)$existing['autor']!==$user) throw new EmailChyba('Neplatný identifikátor zprávy.');
            if ($existing['stav']==='odeslano') return ['sent'=>true,'stored'=>(bool)$existing['ulozeno']];
            throw new EmailChyba('Tato zpráva již byla předána k odeslání. Před dalším pokusem ověřte její stav v Odeslaných.');
        }
        $files=em_uploady(); $source=null; $ref=$d['source']??null; $draftMessage=null;$quote='';$includeQuote=!empty($d['include_quote']);
        if (is_array($d['draft_ref']??null)) {
            $draftRef=$d['draft_ref'];
            if ((int)($draftRef['account']??0)!==(int)$a['id'] || ($draftRef['folder']??'')!==(em_config()['folders']['drafts']??'')) throw new EmailChyba('Neplatny puvodni koncept.');
            $draftMessage=em_zprava($client,$draftRef,false);$draftDetail=em_mime_detail($client,$draftRef);
            $draftCtx=em_kontext_konceptu($a,$draftMessage);
            if($includeQuote) {
                $stored=(int)($draftCtx['format']??0)===2?em_mime_koncept_casti($draftDetail['html'])['quote']:($draftDetail['html']?:'<pre>'.htmlspecialchars($draftDetail['text'],ENT_QUOTES,'UTF-8').'</pre>');
                $quote=em_zobrazeni_html($stored,true,true);
            }
            $parts=array_filter($draftDetail['attachments'],static fn($p)=>$includeQuote || $p['cid']==='');
            $files=array_merge($files,em_mime_soubory($client,$draftRef,$parts,true));
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
                $parts=array_filter($detail['attachments'],static fn($p)=>$includeQuote || $p['cid']==='');
                $files=array_merge($files,em_mime_soubory($client,$ref,$parts,!$draftMessage && ($d['kind']??'')==='forward'));
            }
        }
        if (count($files)>20 || array_sum(array_map(static fn($f)=>strlen($f['content']),$files))>20*1024*1024) throw new EmailChyba('Celková velikost příloh nesmí překročit 20 MB a 20 souborů.');
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
            return ['draft'=>true,'draft_ref'=>$newRef];
        }
        $messageId=$mail->getLastMessageID(); $cipher=em_zasifruj($mime,'odeslani:'.$token);
        $s=$db->prepare('INSERT INTO email_odeslani(token,ucet,autor,stav,mime_sifra,message_id,slozka_odeslane) VALUES(?,?,?,"odesila",?,?,?)');
        $s->bind_param('siisss',$token,$a['id'],$user,$cipher,$messageId,$target); $s->execute();
        $diagStart=cb_diag_clock();
        try { if (!$mail->postSend()) throw new RuntimeException('SMTP'); }
        catch (Throwable) {
            $s=$db->prepare('UPDATE email_odeslani SET stav="nejiste" WHERE token=?'); $s->bind_param('s',$token); $s->execute();
            throw new EmailChyba('Server nepotvrdil dokončení odeslání. Zprávu automaticky neposíláme znovu; nejprve ověřte doručení.');
        }
        finally {cb_diag_span('smtp_send',$diagStart);}
        $s=$db->prepare('UPDATE email_odeslani SET stav="odeslano",odeslano=NOW() WHERE token=?'); $s->bind_param('s',$token); $s->execute();
        // Po SMTP se žádná následná chyba nesmí vydávat za neodeslanou zprávu.
        $stored=false; $workSaved=true;
        $diagStart=cb_diag_clock();
        try { em_uloz_odeslane($db,$client,['token'=>$token,'message_id'=>$messageId,'mime_sifra'=>$cipher,'slozka_odeslane'=>$target]); $stored=true; } catch (Throwable) {}
        finally {cb_diag_span('imap_store_sent',$diagStart);}
        try {
            if ($draftMessage) $draftMessage->delete(false);
            if ($source) {
                if (($d['kind']??'')==='draft_edit' && $source->getFolderPath()===(em_config()['folders']['drafts']??'')) $source->delete(false);
                if (in_array($d['kind']??'',['reply','reply_all'],true)) $source->setFlag('Answered');
                if ($a['typ']!=='osobni') {
                    [$id,$hash,$v,$uid]=em_ref($a,$ref);
                    $s=$db->prepare('UPDATE email_stav SET stav="odpovezeno",zamek_do=NULL,verze=verze+1,zmeneno=NOW() WHERE ucet=? AND slozka_hash=? AND uidvalidity=? AND uid=?');
                    $s->bind_param('isii',$id,$hash,$v,$uid); $s->execute();
                }
            }
            em_audit($db,(int)$a['id'],'odeslani',$messageId);
        } catch (Throwable) { $workSaved=false; }
        return ['sent'=>true,'stored'=>$stored,'work_saved'=>$workSaved];
    } finally {
        $s=$db->prepare('SELECT RELEASE_LOCK(?)'); $s->bind_param('s',$lock); $s->execute();
    }
}
