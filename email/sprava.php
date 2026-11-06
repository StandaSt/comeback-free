<?php
/* E-mail: MAIN vedoucich a volba treninku ve sdilenych schrankach; sprava sdilenych schranek s pravem 802, cteci prehled skutecnych pristupu a overeni hesla bez zmeny na mailserveru. */
declare(strict_types=1);
require_once __DIR__.'/../common/db/db_prava.php';

/** Spravce vidi metadata vlastni firmy i bez prava 801; pravo spravy samo neumoznuje cteni posty. */
function em_sprava_cti(mysqli $db, array $person): array
{
    if (!em_pravo(802)) return ['accounts'=>[],'branches'=>[]];
    $s=$db->prepare('SELECT id,email,typ,id_pob,aktivni,revize FROM email_ucet WHERE id_firma=? AND typ IN("pobocka","info") AND aktivni=1 ORDER BY email');
    $s->bind_param('i',$person['id_firma']); $s->execute(); $accounts=$s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s=$db->prepare('SELECT id_pob,nazev FROM pobocka WHERE id_firma=? AND aktivni=1 ORDER BY nazev,id_pob');
    $s->bind_param('i',$person['id_firma']); $s->execute();
    return ['accounts'=>$accounts,'branches'=>$s->get_result()->fetch_all(MYSQLI_ASSOC)];
}

/** Ani znalost ID nebo prava cteni 801 nesmi dovolit zmenu cizi nebo osobni schranky. */
function em_sdilena_over(array $person, ?array $account): void
{
    if (!em_pravo(802)) throw new EmailChyba('Správa společných schránek vyžaduje právo 802.');
    if ($account && ((int)$account['id_firma']!==(int)$person['id_firma'] || !in_array($account['typ'],['pobocka','info'],true))) {
        throw new EmailChyba('Tuto schránku nelze spravovat.');
    }
}

/** Vypnuti pouze odebere pristup v IS; nemaže schranku, zpravy ani historii a je vratne. */
function em_sdilena_aktivita(mysqli $db, array $d): void
{
    em_sdilena_over($p=em_osoba($db),null);
    if (!is_bool($d['active']??null)) throw new EmailChyba('Neplatný požadavek na dostupnost schránky.');
    $id=(int)($d['shared_id']??0); $rev=(int)($d['revision']??0); $active=$d['active']?1:0;
    $s=$db->prepare('SELECT * FROM email_ucet WHERE id=?'); $s->bind_param('i',$id); $s->execute(); $old=$s->get_result()->fetch_assoc();
    if (!$old) throw new EmailChyba('Schránka již není dostupná.');
    em_sdilena_over($p,$old);
    $db->begin_transaction();
    try {
        $s=$db->prepare('UPDATE email_ucet SET aktivni=?,revize=revize+1 WHERE id=? AND revize=?');
        $s->bind_param('iii',$active,$id,$rev); $s->execute();
        if ($s->affected_rows!==1) throw new EmailChyba('Nastavení mezitím změnil jiný správce. Otevřete jej znovu.');
        em_audit($db,$id,$active?'sdilena_zapnuti':'sdilena_vypnuti'); $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

/** Heslo overime pres IMAP i SMTP bez odeslani zpravy; existujici adresu nelze omylem nahradit jinou identitou. */
function em_sdilena_uloz(mysqli $db, array $d): void
{
    em_sdilena_over($p=em_osoba($db),null);
    $email=em_firemni_adresa((string)($d['local_part']??'')); $id=(int)($d['shared_id']??0); $old=null;
    if ($id>0) {
        $s=$db->prepare('SELECT * FROM email_ucet WHERE id=?'); $s->bind_param('i',$id); $s->execute(); $old=$s->get_result()->fetch_assoc();
        if (!$old) throw new EmailChyba('Schránka již není dostupná.');
        em_sdilena_over($p,$old);
        if ($old['email']!==$email) throw new EmailChyba('Adresu existující schránky nelze změnit. Připojte novou schránku.');
    }
    $type=(string)($d['shared_kind']??''); $branch=$type==='pobocka'?(int)($d['branch']??0):null;
    if (!in_array($type,['pobocka','info'],true)) throw new EmailChyba('Vyberte pobočkovou schránku nebo INFO.');
    if ($branch!==null) {
        $s=$db->prepare('SELECT 1 FROM pobocka WHERE id_pob=? AND id_firma=? AND aktivni=1'); $s->bind_param('ii',$branch,$p['id_firma']); $s->execute();
        if (!$s->get_result()->fetch_row()) throw new EmailChyba('Vyberte aktivní pobočku své firmy.');
    }
    $pw=(string)($d['password']??'');
    if ($pw==='' && $old) $pw=em_rozsifruj((string)$old['heslo_sifra'],'ucet:'.$email);
    if ($pw==='' || strlen($pw)>1024) throw new EmailChyba('Zadejte heslo schránky.');
    $cipher=em_zasifruj($pw,'ucet:'.$email); $a=['id'=>0,'email'=>$email,'rezim'=>'is','heslo_sifra'=>$cipher];
    $client=em_imap($a,$pw);
    try { $mail=em_mailer($a,['to'=>$email]); try { if (!$mail->smtpConnect()) throw new EmailChyba('SMTP přihlášení se nepodařilo.'); } finally { $mail->smtpClose(); } }
    finally { $client->disconnect(); }
    $db->begin_transaction();
    try {
        if ($old) {
            $rev=(int)($d['revision']??0);
            $s=$db->prepare('UPDATE email_ucet SET typ=?,id_pob=?,heslo_sifra=?,rezim="is",revize=revize+1 WHERE id=? AND revize=?');
            $s->bind_param('sisii',$type,$branch,$cipher,$id,$rev); $s->execute();
            if ($s->affected_rows!==1) throw new EmailChyba('Nastavení mezitím změnil jiný správce. Otevřete jej znovu.');
        } else {
            // Odpojeni nema rezervovat adresu; stejne ID zachova audit, nova revize zrusi puvodni odemceni.
            $s=$db->prepare('SELECT * FROM email_ucet WHERE email=? FOR UPDATE');$s->bind_param('s',$email);$s->execute();$detached=$s->get_result()->fetch_assoc();
            if($detached){
                if((int)$detached['aktivni']===1 || (int)$detached['id_firma']!==(int)$p['id_firma'])throw new EmailChyba('Tato adresa již má aktivní připojení. Nejprve je odpojte.');
                if(!em_pristup_schema($db))throw new EmailChyba('Nejprve spusťte připravené SQL pro přístupy ke sdíleným schránkám.');
                $id=(int)$detached['id'];
                $s=$db->prepare('UPDATE email_ucet SET typ=?,id_user=NULL,id_pob=?,heslo_sifra=?,rezim="is",revize=revize+1,aktivni=1 WHERE id=?');$s->bind_param('sisi',$type,$branch,$cipher,$id);$s->execute();
                // Po odpojeni se clenstvi nastavi znovu; prazdne pravidlo zabrani automatickemu pristupu pres 801/MAIN.
                $s=$db->prepare('DELETE FROM email_pristup_nastaveni WHERE ucet=?');$s->bind_param('i',$id);$s->execute();
                $s=$db->prepare('INSERT INTO email_pristup_nastaveni(ucet,main_pobocka) VALUES(?,0)');$s->bind_param('i',$id);$s->execute();
            }else{
                $s=$db->prepare('INSERT INTO email_ucet(id_firma,typ,id_pob,email,heslo_sifra,rezim) VALUES(?,?,?,?,?,"is")');
                $s->bind_param('isiss',$p['id_firma'],$type,$branch,$email,$cipher); $s->execute(); $id=(int)$db->insert_id;
            }
        }
        em_audit($db,$id,$old?'sdilena_uprava':'sdilena_pripojeni'); $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof mysqli_sql_exception && $e->getCode()===1062) throw new EmailChyba('Tato adresa nebo pobočka již má připojenou schránku. Upravte existující připojení.');
        throw $e;
    }
}

/** Oddelena sprava nabizi jen existujici aktivni sdilena pripojeni; puvodni pripojovaci formular se nemeni. */
function em_sprava_sdilenych_cti(mysqli $db): array
{
    $person=em_osoba($db);em_sdilena_over($person,null);
    $management=em_sprava_cti($db,$person);$accounts=array_values(array_filter($management['accounts'],static fn($a)=>(int)$a['aktivni']===1));
    $roles=$db->query('SELECT id_role,role FROM cis_role WHERE aktivni=1 ORDER BY role,id_role')->fetch_all(MYSQLI_ASSOC);
    $name=cb_osoba_jmeno_sql('p.id_person',true);
    $s=$db->prepare('SELECT p.id_user,'.$name.' AS jmeno FROM hr_person p JOIN user u ON u.id_user=p.id_user WHERE p.id_firma=? AND p.aktivni=1 ORDER BY jmeno,p.id_user');
    $s->bind_param('i',$person['id_firma']);$s->execute();$users=$s->get_result()->fetch_all(MYSQLI_ASSOC);$ready=em_pristup_schema($db);$trainingReady=$ready && em_pristup_trenink_schema($db);$rules=[];
    if($ready){
        $s=$db->prepare('SELECT n.ucet,n.main_pobocka,'.($trainingReady?'n.zahrnout_trenink':'0').' AS zahrnout_trenink FROM email_pristup_nastaveni n JOIN email_ucet a ON a.id=n.ucet WHERE a.id_firma=?');$s->bind_param('i',$person['id_firma']);$s->execute();
        foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r)$rules[(int)$r['ucet']]=['main'=>(bool)$r['main_pobocka'],'roles'=>[],'users'=>[],'training'=>!empty($r['zahrnout_trenink'])];
        $s=$db->prepare('SELECT c.ucet,c.typ,c.subjekt FROM email_pristup_clen c JOIN email_ucet a ON a.id=c.ucet WHERE a.id_firma=?');$s->bind_param('i',$person['id_firma']);$s->execute();
        foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r)$rules[(int)$r['ucet']][$r['typ']==='role'?'roles':'users'][]=(int)$r['subjekt'];
    }
    // Starsi MAIN se nabidne jako obe vedouci role; dalsi ulozeni pouziva jediny seznam.
    foreach($rules as &$rule){if($rule['main'])$rule['roles']=array_values(array_unique(array_merge($rule['roles'],[5,7])));}unset($rule);
    return ['accounts'=>$accounts,'roles'=>$roles,'users'=>$users,'access'=>$rules,'access_ready'=>$trainingReady];
}

/** Pouze popisuje cesty ze spolecneho rozhodnuti; pravo 800 se kontroluje, ale nevypisuje jako duvod. */
function em_sprava_pristup_duvody(array $paths, array $rights, array $person, ?array $access, array $roleNames, string $branch): array
{
    $out=[];
    if(in_array('shared',$paths,true)){
        foreach($rights['duvody'][801]['roles']??[] as $name)$out[]='Role '.$name.' → právo 801';
        if(($rights['duvody'][801]['individual']??null)===true)$out[]='Individuální právo 801';
    }
    if(in_array('assigned',$paths,true)){
        if(!empty($access['user']))$out[]='Přímé přiřazení ke schránce';
        foreach($access['roles']??[] as $id)$out[]='Role '.($roleNames[$id]??$id).(in_array((int)$id,[5,7],true) && $branch!==''?' + MAIN '.$branch.(!empty($person['training'])?' (v tréninku)':''):' přiřazená ke schránce');
    }
    if(in_array('main',$paths,true))$out[]=implode(' / ',array_values($person['branch_mail_roles']??[])).' + MAIN '.$branch.(!empty($person['training'])?' (v tréninku)':'');
    return array_values(array_unique($out));
}

/** Ulozeny stav jedne schranky cteme v konzistentnim snimku; zadny IMAP ani zmena session dotazovanych lidi. */
function em_sprava_pristupy_cti(mysqli $db, int $id): array
{
    $actor=em_osoba($db);em_sdilena_over($actor,null);
    $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY|MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
    try{
        $s=$db->prepare('SELECT id,id_firma,email,typ,id_pob,aktivni FROM email_ucet WHERE id=?');$s->bind_param('i',$id);$s->execute();$a=$s->get_result()->fetch_assoc();
        if(!$a || (int)$a['aktivni']!==1)throw new EmailChyba('Sdílená schránka již není připojená.');em_sdilena_over($actor,$a);
        $management=em_sprava_sdilenych_cti($db);$roleNames=array_column($management['roles'],'role','id_role');$branch='';
        if($a['typ']==='pobocka'){$s=$db->prepare('SELECT nazev FROM pobocka WHERE id_pob=? AND id_firma=?');$s->bind_param('ii',$a['id_pob'],$a['id_firma']);$s->execute();$branch=(string)($s->get_result()->fetch_assoc()['nazev']??'');}
        $users=[];$unresolved=[];
        foreach($management['users'] as $candidate){
            $user=(int)$candidate['id_user'];$rights=cb_db_prava_cti($db,$user);
            if(empty($rights['prava'][800]) || (int)($rights['prava_stav'][800]??0)!==1)continue;
            try{$person=em_osoba($db,$user);$branches=em_pobocky_main($db,$person);}
            catch(EmailChyba|DomainException){$unresolved[]=['id'=>$user,'name'=>$candidate['jmeno']];continue;}
            $shared=!empty($rights['prava'][801]) && (int)($rights['prava_stav'][801]??0)===1;
            $access=em_pristup_mapa($db,$person)[$id]??null;
            $paths=em_pristup_cesty($person,$a,$branches,$shared,$access);
            if(!$paths)continue;
            $users[]=['id'=>$user,'name'=>$candidate['jmeno'],'reasons'=>em_sprava_pristup_duvody($paths,$rights,$person,$access,$roleNames,$branch)];
        }
        $db->commit();
        return ['account'=>['id'=>$id,'email'=>$a['email']],'users'=>$users,'unresolved'=>$unresolved,
            'custom'=>isset($management['access'][$id]),'checked_at'=>(new DateTimeImmutable('now',new DateTimeZone('Europe/Prague')))->format('d. m. Y H:i:s')];
    }catch(Throwable $e){$db->rollback();throw $e;}
}

/** Formulare predavaji pouze kladna celočíselna ID, nikoli nazvy ci SQL podminky. */
function em_sprava_ids(mixed $values): array
{
    if(!is_array($values) || count($values)>1000)throw new EmailChyba('Neplatný seznam přístupů.');
    foreach($values as $id)if(!is_int($id) || $id<1)throw new EmailChyba('Neplatný seznam přístupů.');
    return array_values(array_unique($values));
}

/** Vsechny spravni zmeny kontroluji 802, firmu, typ a revizi; stejny ucet se nesmi menit behem odesilani. */
function em_sprava_sdilenych_zapis(mysqli $db, string $action, array $d): void
{
    $person=em_osoba($db);em_sdilena_over($person,null);$id=(int)($d['shared_id']??0);$revision=(int)($d['revision']??0);
    if(!in_array($action,['shared_access','shared_password','shared_remove'],true))throw new EmailChyba('Neplatná správní operace.');
    $s=$db->prepare('SELECT * FROM email_ucet WHERE id=?');$s->bind_param('i',$id);$s->execute();$a=$s->get_result()->fetch_assoc();
    if(!$a || (int)$a['aktivni']!==1)throw new EmailChyba('Sdílená schránka již není připojená.');em_sdilena_over($person,$a);
    if((int)$a['revize']!==$revision)throw new EmailChyba('Nastavení se mezitím změnilo. Otevřete správu schránky znovu.');
    $roles=[];$users=[];$training=0;$cipher=null;
    if($action==='shared_access'){
        if(!em_pristup_schema($db))throw new EmailChyba('Nejprve spusťte připravené SQL pro přístupy ke sdíleným schránkám.');
        if(!em_pristup_trenink_schema($db))throw new EmailChyba('Nejprve přidejte sloupec zahrnout_trenink podle připraveného SQL.');
        if(!is_bool($d['training']??null) || ($d['training'] && $a['typ']!=='pobocka'))throw new EmailChyba('Neplatná volba tréninku.');
        $training=$d['training']?1:0;
        $roles=em_sprava_ids($d['roles']??null);$users=em_sprava_ids($d['users']??null);
        // Starsi otevreny formular nesmi prepsat novou politiku pristupu.
        if(array_key_exists('main',$d))throw new EmailChyba('Obnovte stránku a otevřete správu schránky znovu.');
        $allowed=em_sprava_sdilenych_cti($db);
        if(array_diff($roles,array_map('intval',array_column($allowed['roles'],'id_role'))) || array_diff($users,array_map('intval',array_column($allowed['users'],'id_user'))))throw new EmailChyba('Vybraná role nebo uživatel už není dostupný v této firmě.');
        if(!$roles && !$users && ($d['confirm_empty']??false)!==true)throw new EmailChyba('Potvrďte uložení schránky bez příjemců přístupu.');
    }elseif($action==='shared_remove' && ($d['confirm']??'')!==$a['email'])throw new EmailChyba('Potvrďte odebrání vybrané schránky z IS.');
    $lock='email:send:'.$id;$s=$db->prepare('SELECT GET_LOCK(?,0) n');$s->bind_param('s',$lock);$s->execute();
    if((int)$s->get_result()->fetch_assoc()['n']!==1)throw new EmailChyba('Ve schránce právě probíhá jiná operace. Vyčkejte a změnu opakujte.');
    try{
        if($action==='shared_password'){
            $password=(string)($d['password']??'');if($password==='' || strlen($password)>1024)throw new EmailChyba('Vyplňte nové heslo nastavené na poštovním serveru.');
            $cipher=em_zasifruj($password,'ucet:'.$a['email']);$test=$a;$test['heslo_sifra']=$cipher;$client=null;$mail=null;
            // Overeni provede prihlaseni k obema sluzbam, nikdy neposila zpravu ani nemeni heslo na serveru.
            try{$client=em_imap($test,$password);$mail=em_mailer($test,['to'=>$a['email']]);if(!$mail->smtpConnect())throw new EmailChyba('SMTP přihlášení se nepodařilo.');}
            finally{if($mail)$mail->smtpClose();if($client)$client->disconnect();}
        }
        $db->begin_transaction();
        try{
            if($action==='shared_remove'){$s=$db->prepare('UPDATE email_ucet SET aktivni=0,heslo_sifra=NULL,revize=revize+1 WHERE id=? AND revize=? AND aktivni=1');$s->bind_param('ii',$id,$revision);}
            elseif($action==='shared_password'){$s=$db->prepare('UPDATE email_ucet SET heslo_sifra=?,revize=revize+1 WHERE id=? AND revize=? AND aktivni=1');$s->bind_param('sii',$cipher,$id,$revision);}
            else{$s=$db->prepare('UPDATE email_ucet SET revize=revize+1 WHERE id=? AND revize=? AND aktivni=1');$s->bind_param('ii',$id,$revision);}
            $s->execute();if($s->affected_rows!==1)throw new EmailChyba('Nastavení se mezitím změnilo. Otevřete správu schránky znovu.');
            if($action==='shared_access'){
                $s=$db->prepare('INSERT INTO email_pristup_nastaveni(ucet,main_pobocka,zahrnout_trenink,zmeneno) VALUES(?,0,?,NOW()) ON DUPLICATE KEY UPDATE main_pobocka=0,zahrnout_trenink=VALUES(zahrnout_trenink),zmeneno=NOW()');$s->bind_param('ii',$id,$training);$s->execute();
                $s=$db->prepare('DELETE FROM email_pristup_clen WHERE ucet=?');$s->bind_param('i',$id);$s->execute();
                $s=$db->prepare('INSERT INTO email_pristup_clen(ucet,typ,subjekt) VALUES(?,?,?)');
                foreach(['role'=>$roles,'user'=>$users] as $type=>$values)foreach($values as $subject){$s->bind_param('isi',$id,$type,$subject);$s->execute();}
            }
            em_audit($db,$id,$action,$action==='shared_access'?json_encode(['roles'=>$roles,'users'=>$users,'training'=>(bool)$training],JSON_THROW_ON_ERROR):'');$db->commit();
        }catch(Throwable $e){$db->rollback();throw $e;}
    }finally{try{$s=$db->prepare('SELECT RELEASE_LOCK(?)');$s->bind_param('s',$lock);$s->execute();}catch(Throwable){}}
}
