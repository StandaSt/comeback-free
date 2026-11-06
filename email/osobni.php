<?php
/* E-mail: vice osobnich schranek, spolecny rezim vlastnika a spravni pripojeni/odpojeni bez pristupu k cizi poste. */
declare(strict_types=1);

/** Pred rucni migraci zustava cteni funkcni; zapis vice schranek se nepokousi menit schema za behu. */
function em_osobni_schema(mysqli $db): bool
{
    static $cache;$cache??=new WeakMap();if(isset($cache[$db]))return $cache[$db];
    $r=$db->query("SHOW TABLES LIKE 'email\\_nastaveni'");if($r->num_rows!==1)return $cache[$db]=false;
    $columns=array_column($db->query('SHOW COLUMNS FROM email_nastaveni')->fetch_all(MYSQLI_ASSOC),'Field');
    $unique=[];foreach($db->query('SHOW INDEX FROM email_ucet')->fetch_all(MYSQLI_ASSOC) as $index){
        if((int)$index['Non_unique']===0)$unique[$index['Key_name']][]=$index['Column_name'];
    }
    return $cache[$db]=!array_diff(['rezim_posty','posta_revize'],$columns) && !in_array(['id_user'],$unique,true);
}

/** Volba patri uzivateli; stary rezim uctu slouzi pouze pro cteni pred migraci. */
function em_osobni_rezim(mysqli $db, int $user, string $fallback='is'): array
{
    if(!em_osobni_schema($db))return ['mode'=>$fallback,'revision'=>0];
    $s=$db->prepare('SELECT rezim_posty,posta_revize FROM email_nastaveni WHERE id_user=?');$s->bind_param('i',$user);$s->execute();$r=$s->get_result()->fetch_assoc();
    return ['mode'=>$r['rezim_posty']??'is','revision'=>(int)($r['posta_revize']??0)];
}

/** Vlastnik muze spravovat sve pripojeni, cizi osobni metadata a zmeny vyzaduji 802 a stejnou firmu. */
function em_osobni_vlastnik(mysqli $db, array $d, bool $pripojeni=true): array
{
    $actor=em_osoba($db);$id=(int)($d['target_user']??$actor['id_user']);
    if($id<1 || ($id!==(int)$actor['id_user'] && !em_pravo(802)))throw new EmailChyba('Správa cizí osobní schránky vyžaduje právo 802.');
    $owner=$id===(int)$actor['id_user']?$actor:em_osoba($db,$id);
    if((int)$owner['id_firma']!==(int)$actor['id_firma'] || ($pripojeni && !$owner['manager']))throw new EmailChyba('Vybranému uživateli nelze připojit osobní firemní schránku.');
    return $owner;
}

/** Vracime pouze metadata; hesla ani cizi obsah nejsou soucasti katalogu pripojeni. */
function em_osobni_katalog(mysqli $db): array
{
    $actor=em_osoba($db);$users=[];$name=cb_osoba_jmeno_sql('p.id_person',true);$admin=em_pravo(802);
    $s=$db->prepare('SELECT DISTINCT p.id_user,'.$name.' AS jmeno FROM hr_person p JOIN user u ON u.id_user=p.id_user WHERE p.id_firma=? AND p.aktivni=1'.($admin?'':' AND p.id_user=?').' ORDER BY jmeno,p.id_user');
    if($admin)$s->bind_param('i',$actor['id_firma']);else $s->bind_param('ii',$actor['id_firma'],$actor['id_user']);$s->execute();$people=$s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s=$db->prepare('SELECT id,id_user,email,revize,rezim,(heslo_sifra IS NOT NULL AND heslo_sifra<>"") AS ma_heslo FROM email_ucet WHERE id_firma=? AND typ="osobni" AND aktivni=1'.($admin?'':' AND id_user=?').' ORDER BY email,id');
    if($admin)$s->bind_param('i',$actor['id_firma']);else $s->bind_param('ii',$actor['id_firma'],$actor['id_user']);$s->execute();$byUser=[];
    foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $a)$byUser[(int)$a['id_user']][]=$a;
    foreach($people as $one){
        $p=em_osoba($db,(int)$one['id_user']);$accounts=$byUser[(int)$one['id_user']]??[];
        if(!$p['manager'] && !$accounts)continue;
        $policy=em_osobni_rezim($db,(int)$one['id_user'],$accounts[0]['rezim']??'is');
        $users[]=['id'=>(int)$one['id_user'],'name'=>$one['jmeno'],'can_connect'=>(bool)$p['manager'],'mode'=>$policy['mode'],'mode_revision'=>$policy['revision'],
            'accounts'=>array_map(static fn($a)=>['id'=>(int)$a['id'],'email'=>$a['email'],'revision'=>(int)$a['revize'],'has_secret'=>(bool)$a['ma_heslo']],$accounts)];
    }
    return ['self'=>(int)$actor['id_user'],'ready'=>em_osobni_schema($db),'admin'=>$admin,'users'=>$users];
}

/** Zmeny jednoho vlastnika se nesmi krizit ani zneplatnit prihlaseni uprostred odesilani. */
function em_osobni_zamky(mysqli $db, array $keys): array
{
    $held=[];
    try{foreach($keys as $key){$s=$db->prepare('SELECT GET_LOCK(?,0) n');$s->bind_param('s',$key);$s->execute();if((int)$s->get_result()->fetch_assoc()['n']!==1)throw new EmailChyba('Ve schránkách právě probíhá jiná operace. Vyčkejte a akci opakujte.');$held[]=$key;}}
    catch(Throwable $e){em_osobni_odemkni($db,$held);throw $e;}
    return $held;
}

/** Uvolneni bezi i po chybe overeni nebo rollbacku, nikdy neprepise puvodni chybu. */
function em_osobni_odemkni(mysqli $db, array $keys): void
{
    foreach(array_reverse($keys) as $key){try{$s=$db->prepare('SELECT RELEASE_LOCK(?)');$s->bind_param('s',$key);$s->execute();}catch(Throwable){}}
}

/** Overeni pouzije oba protokoly, SMTP nikdy neposila zpravu; klienty zavirame i po chybe. */
function em_osobni_over_heslo(string $email, string $password): string
{
    if($password==='' || strlen($password)>1024)throw new EmailChyba('Vyplňte heslo ke schránce '.$email.'.');
    $cipher=em_zasifruj($password,'ucet:'.$email);$a=['id'=>0,'email'=>$email,'rezim'=>'is','heslo_sifra'=>$cipher];$client=null;$mail=null;
    try{$client=em_imap($a,$password);$mail=em_mailer($a,['to'=>$email]);if(!$mail->smtpConnect())throw new EmailChyba('SMTP přihlášení se nepodařilo.');}
    finally{try{if($mail)$mail->smtpClose();}finally{if($client)$client->disconnect();}}
    return $cipher;
}

/** Vice pripojeni zapisujeme podle ID/adresy; spolecna volba i vsechna dotcena hesla se meni atomicky. */
function em_osobni_pripojit(mysqli $db, array $d): void
{
    if(!em_osobni_schema($db))throw new EmailChyba('Nejprve spusťte SQL pro více osobních schránek.');
    $p=em_osobni_vlastnik($db,$d);$user=(int)$p['id_user'];$remote=$user!==(int)($_SESSION['cb_user']['id_user']??0);
    $email=em_firemni_adresa((string)($d['local_part']??''));$mode=(string)($d['mode']??'');$id=(int)($d['personal_id']??0);$revision=(int)($d['personal_revision']??0);
    if(!in_array($mode,['is','heslo'],true))throw new EmailChyba('Vyberte způsob přístupu uživatele.');
    $held=em_osobni_zamky($db,['email:personal:'.$user]);
    try{
        $s=$db->prepare('SELECT * FROM email_ucet WHERE id_user=? AND id_firma=? AND typ="osobni" AND aktivni=1 ORDER BY id');$s->bind_param('ii',$user,$p['id_firma']);$s->execute();$accounts=$s->get_result()->fetch_all(MYSQLI_ASSOC);$selected=null;
        $held=array_merge($held,em_osobni_zamky($db,array_map(static fn($a)=>'email:send:'.$a['id'],$accounts)));
        foreach($accounts as $a)if((int)$a['id']===$id)$selected=$a;
        if($id>0 && (!$selected || $selected['email']!==$email || (int)$selected['revize']!==$revision))throw new EmailChyba('Vybrané připojení se změnilo. Otevřete formulář znovu.');
        $policy=em_osobni_rezim($db,$user);if($policy['revision']!==(int)($d['mode_revision']??-1))throw new EmailChyba('Nastavení uživatele se mezitím změnilo. Otevřete formulář znovu.');
        $changed=$policy['mode']!==$mode;$pw=(string)($d['password']??'');$cipher=null;
        // Pri uprave lze zachovat overene ulozene heslo; novy ucet vzdy potrebuje vlastni heslo.
        if($pw!=='')$cipher=em_osobni_over_heslo($email,$pw);
        elseif($selected && !empty($selected['heslo_sifra']))$cipher=$selected['heslo_sifra'];
        elseif(!$selected || $mode==='is')throw new EmailChyba('Vyplňte heslo ke schránce '.$email.'.');
        $extra=$d['extra_passwords']??[];if(!is_array($extra) || count($extra)>1000)throw new EmailChyba('Neplatný seznam hesel.');$otherCiphers=[];$allowedExtra=[];
        foreach($accounts as $a){
            if((int)$a['id']===$id)continue;$key=(int)$a['id'];
            if($mode==='is' && empty($a['heslo_sifra'])){
                $allowedExtra[]=$key;$otherCiphers[$key]=em_osobni_over_heslo($a['email'],(string)($extra[$key]??''));
            }else $otherCiphers[$key]=$a['heslo_sifra'];
        }
        if(array_diff(array_map('intval',array_keys($extra)),$allowedExtra))throw new EmailChyba('Seznam osobních schránek se změnil. Otevřete formulář znovu.');
        $db->begin_transaction();
        try{
            $s=$db->prepare('SELECT rezim_posty,posta_revize FROM email_nastaveni WHERE id_user=? FOR UPDATE');$s->bind_param('i',$user);$s->execute();$locked=$s->get_result()->fetch_assoc();
            if((int)($locked['posta_revize']??0)!==$policy['revision'])throw new EmailChyba('Nastavení uživatele se mezitím změnilo. Otevřete formulář znovu.');
            $s=$db->prepare('SELECT * FROM email_ucet WHERE email=? FOR UPDATE');$s->bind_param('s',$email);$s->execute();$old=$s->get_result()->fetch_assoc();
            if($old && ((int)$old['id_firma']!==(int)$p['id_firma'] || ((int)$old['aktivni']===1 && (!$selected || (int)$old['id']!==$id || (int)$old['id_user']!==$user || (int)$old['revize']!==$revision))))throw new EmailChyba('Tato adresa již má jiné aktivní připojení.');
            if($old && $old['typ']!=='osobni' && !em_pravo(802))throw new EmailChyba('Změnu typu schránky musí provést správce.');
            $stored=$mode==='is'?$cipher:null;
            if($old){
                $id=(int)$old['id'];$rev=(int)$old['revize']+1;
                $s=$db->prepare('UPDATE email_ucet SET typ="osobni",id_user=?,id_pob=NULL,heslo_sifra=?,rezim=?,revize=?,aktivni=1 WHERE id=?');$s->bind_param('issii',$user,$stored,$mode,$rev,$id);$s->execute();
                if((int)$old['aktivni']===0 && em_pristup_schema($db)){$s=$db->prepare('DELETE FROM email_pristup_nastaveni WHERE ucet=?');$s->bind_param('i',$id);$s->execute();}
            }else{
                $branch=null;$type='osobni';$s=$db->prepare('INSERT INTO email_ucet(id_firma,typ,id_user,id_pob,email,heslo_sifra,rezim) VALUES(?,?,?,?,?,?,?)');$s->bind_param('isiisss',$p['id_firma'],$type,$user,$branch,$email,$stored,$mode);$s->execute();$id=(int)$db->insert_id;$rev=1;
            }
            // Pri prepnuti rezimu zneplatnime odemceni vsech osobnich schranek, nejen prave upravovane.
            foreach($accounts as $a){
                if((int)$a['id']===$id || (!$changed && !in_array((int)$a['id'],$allowedExtra,true)))continue;$other=(int)$a['id'];$oldRev=(int)$a['revize'];$secret=$mode==='is'?$otherCiphers[$other]:null;
                $s=$db->prepare('UPDATE email_ucet SET rezim=?,heslo_sifra=?,revize=revize+1 WHERE id=? AND revize=? AND aktivni=1');$s->bind_param('ssii',$mode,$secret,$other,$oldRev);$s->execute();if($s->affected_rows!==1)throw new EmailChyba('Připojení se mezitím změnilo. Otevřete formulář znovu.');
            }
            $next=$policy['revision']+($changed || $policy['revision']===0?1:0);
            $s=$db->prepare('INSERT INTO email_nastaveni(id_user,podpis_html,rezim_posty,posta_revize,zmeneno) VALUES(?,"",?,?,NOW()) ON DUPLICATE KEY UPDATE rezim_posty=VALUES(rezim_posty),posta_revize=VALUES(posta_revize),zmeneno=NOW()');$s->bind_param('isi',$user,$mode,$next);$s->execute();
            em_audit($db,$id,$remote?'osobni_pripojeni_spravcem':'pripojeni','user:'.$user.($changed?';rezim:'.$mode:''));$db->commit();
        }catch(Throwable $e){$db->rollback();throw $e;}
        if(!$remote){
            if($changed)foreach($accounts as $a)unset($_SESSION['email_pristup'][(int)$a['id']]);
            if($pw!=='')$_SESSION['email_pristup'][$id]=['identita'=>em_identita(),'revize'=>$rev,'heslo'=>em_zasifruj($pw,'session:'.em_identita().':'.$id)];
            else unset($_SESSION['email_pristup'][$id]);
        }
    }finally{em_osobni_odemkni($db,$held);}
}

/** Spravni odpojeni vyzaduje konkretni ID a revizi; vlastnika urcuje server a pravo 802 nezpristupni postu. */
function em_osobni_odpojit_spravcem(mysqli $db, array $d): void
{
    if(!em_pravo(802))throw new EmailChyba('Odpojení jiného uživatele vyžaduje právo 802.');
    $p=em_osobni_vlastnik($db,$d,false);$id=(int)($d['personal_id']??0);
    $s=$db->prepare('SELECT * FROM email_ucet WHERE id=? AND id_user=? AND id_firma=? AND typ="osobni" AND aktivni=1');$s->bind_param('iii',$id,$p['id_user'],$p['id_firma']);$s->execute();$a=$s->get_result()->fetch_assoc();
    if(!$a || (int)$a['revize']!==(int)($d['personal_revision']??0))throw new EmailChyba('Připojení se mezitím změnilo. Otevřete formulář znovu.');
    em_odpojit_osobni($db,$a);
}

/** Vlastnik i spravce odpojuji jen jednu vybranou schranku; ostatni pripojeni a zpravy na serveru zustavaji. */
function em_odpojit_osobni(mysqli $db, array $a): void
{
    $p=em_osoba($db);$actor=(int)($_SESSION['cb_user']['id_user']??0);$owner=(int)$a['id_user'];$id=(int)$a['id'];$revision=(int)$a['revize'];$company=(int)$p['id_firma'];
    if($actor<1 || $a['typ']!=='osobni' || ($owner!==$actor && !em_pravo(802)) || (int)$a['id_firma']!==$company || empty($a['aktivni']))throw new EmailChyba('Tuto osobní schránku nelze odpojit.');
    $held=em_osobni_zamky($db,['email:personal:'.$owner,'email:send:'.$id]);
    try{
        $db->begin_transaction();
        try{
            $s=$db->prepare('UPDATE email_ucet SET aktivni=0,heslo_sifra=NULL,revize=revize+1 WHERE id=? AND id_user=? AND id_firma=? AND typ="osobni" AND aktivni=1 AND revize=?');$s->bind_param('iiii',$id,$owner,$company,$revision);$s->execute();
            if($s->affected_rows!==1)throw new EmailChyba('Připojení se mezitím změnilo. Otevřete formulář znovu.');
            em_audit($db,$id,'osobni_odpojeni','user:'.$owner);$db->commit();
        }catch(Throwable $e){$db->rollback();throw $e;}
        unset($_SESSION['email_pristup'][$id]);
    }finally{em_osobni_odemkni($db,$held);}
}
