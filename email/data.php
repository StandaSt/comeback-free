<?php
/* E-mail: osobni ucty, spolecne vyrizovani a seznamy od nejnovejsich; centralni spravu sdilenych schranek deleguje na pravo 802. */
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../common/lib/osoba_jmeno.php';
require_once __DIR__.'/posta.php';
require_once __DIR__.'/nastaveni.php';
require_once __DIR__.'/sprava.php';

/** Z klienta se vracejí jen bezpečné vlastnosti, nikdy šifrované heslo. */
function em_stav(mysqli $db): array
{
    $person=em_osoba($db); $accounts=[];
    foreach (em_ucty($db,$person) as $a) {
        $unlocked=true; try { em_heslo($a); } catch (EmailChyba) { $unlocked=false; }
        $accounts[]=['id'=>(int)$a['id'],'email'=>$a['email'],'type'=>$a['typ'],'mode'=>$a['rezim'],'unlocked'=>$unlocked];
    }
    return ['accounts'=>$accounts,'manager'=>$person['manager'],
        'shared'=>em_pravo(801),'manage_shared'=>em_pravo(802),'shared_management'=>em_sprava_cti($db,$person),
        'owner'=>em_identita(),'preferences'=>em_nastaveni_cti($db)];
}

/** Uložení připojení mění jen vlastní osobní účet nebo předem povolenou sdílenou adresu. */
function em_pripojit(mysqli $db, array $d): void
{
    if (($d['type']??'osobni')==='shared') { em_sdilena_uloz($db,$d); return; }
    $p=em_osoba($db); $email=em_firemni_adresa((string)($d['local_part']??'')); $pw=(string)($d['password']??'');
    $mode=(string)($d['mode']??'is'); $type=(string)($d['type']??'osobni');
    if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !str_ends_with($email,'@pizzacomeback.cz') || $pw==='' || strlen($pw)>1024
        || !in_array($mode,['is','heslo'],true)) throw new EmailChyba('Vyplňte firemní e-mail, heslo a způsob přihlášení.');
    $branch=null; $user=null;
    if ($type==='osobni') {
        if (!$p['manager']) throw new EmailChyba('Osobní připojení firemní schránky je dostupné uživatelům s manažerským zařazením.');
        $user=$p['id_user'];
    } else throw new EmailChyba('Neplatný typ schránky.');
    // Před změnou připojení ověříme oba protokoly, SMTP bez odeslání jakékoli zprávy.
    $a=['email'=>$email,'id'=>0,'rezim'=>'is','heslo_sifra'=>em_zasifruj($pw,'ucet:'.$email)];
    $client=em_imap($a,$pw);
    try {
        $mail=em_mailer($a,['to'=>$email]);
        if (!$mail->smtpConnect()) throw new EmailChyba('SMTP přihlášení se nepodařilo.');
        $mail->smtpClose();
    } finally { $client->disconnect(); }
    $cipher=$mode==='is'?$a['heslo_sifra']:null;
    $db->begin_transaction();
    try {
        $s=$db->prepare('SELECT * FROM email_ucet WHERE email=? OR (id_user IS NOT NULL AND id_user=?) FOR UPDATE');
        $s->bind_param('si',$email,$user); $s->execute(); $rows=$s->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($rows)>1) throw new EmailChyba('Připojení koliduje s existující schránkou.');
        $old=$rows[0]??null;
        if ($old) {
            if ($old['typ']!==$type || (int)$old['id_firma']!==(int)$p['id_firma'] || (int)$old['id_user']!==(int)$user || (int)$old['id_pob']!==(int)$branch || $old['email']!==$email) throw new EmailChyba('Existující schránka má jiné přiřazení.');
            $id=(int)$old['id']; $rev=(int)$old['revize']+1;
            $s=$db->prepare('UPDATE email_ucet SET heslo_sifra=?,rezim=?,revize=?,aktivni=1 WHERE id=?'); $s->bind_param('ssii',$cipher,$mode,$rev,$id); $s->execute();
        } else {
            $s=$db->prepare('INSERT INTO email_ucet(id_firma,typ,id_user,id_pob,email,heslo_sifra,rezim) VALUES(?,?,?,?,?,?,?)');
            $s->bind_param('isiisss',$p['id_firma'],$type,$user,$branch,$email,$cipher,$mode); $s->execute(); $id=(int)$db->insert_id; $rev=1;
        }
        em_audit($db,$id,'pripojeni'); $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
    $_SESSION['email_pristup'][$id]=['identita'=>em_identita(),'revize'=>$rev,'heslo'=>em_zasifruj($pw,'session:'.em_identita().':'.$id)];
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

/** Přehled má stránkování a hledání na serveru; jednotlivé složky i souhrn zobrazují nejnovější zprávy nahoře. */
function em_seznam(mysqli $db, array $accounts, array $d): array
{
    $page=max(1,min(100,(int)($d['page']??1))); $multi=count($accounts)>1; $rows=[]; $total=0; $errors=[];
    if ($multi && $page>20) throw new EmailChyba('Pro starší zprávy vyberte konkrétní schránku nebo použijte hledání.');
    foreach ($accounts as $a) {
        $client=null; $phase='pripojeni';
        try {
            $client=em_imap($a); $phase='slozka'; $path=$multi?'INBOX':(string)($d['folder']??'INBOX'); $folder=em_slozka($client,$path);
            $phase='identita_slozky';
            $validity=(int)($folder->examine()['uidvalidity']??0);
            if ($validity<1) throw new EmailChyba('Server neposkytl identitu složky.');
            $phase='dotaz'; $q=$folder->query()->leaveUnread()->fetchBody(false)->setFetchOrderDesc()->all()->whereUndeleted();
            if (!empty($d['search'])) $q->whereText(mb_substr((string)$d['search'],0,200));
            if (!empty($d['unseen'])) $q->whereUnseen();
            $phase='pocet'; $total+=$q->count(); $phase='hlavicky';
            foreach ($q->limit($multi?$page*50:50,$multi?1:$page)->get() as $message) {
                $phase='radek_a_stav';
                $row=em_radek($message,$a,$path,$validity); $row['work']=em_pracovni_stav($db,$a,$row); $rows[]=$row;
            }
        } catch (Throwable $e) {
            // Zprava vyjimky ani argumenty stacku sem nepatri: mohou obsahovat hesla nebo obsah posty.
            error_log('EMAIL_READ account='.(int)$a['id'].' phase='.$phase.' class='.get_class($e).' at='.basename($e->getFile()).':'.$e->getLine());
            $errors[]=$a['email'].': schránku se nepodařilo načíst.';
        }
        finally { if ($client) $client->disconnect(); }
    }
    // IMAP vybira stranku od nejnovejsich UID, ale hlavicky muze vratit vzestupne; vystup proto vzdy radime podle skutecneho casu vcetne pasma.
    usort($rows,static fn($a,$b)=>((strtotime($b['date'])?:0)<=>(strtotime($a['date'])?:0))?:($b['uid']<=>$a['uid'])?:($a['account']<=>$b['account']));
    if ($multi) $rows=array_slice($rows,($page-1)*50,50);
    return ['messages'=>$rows,'total'=>$total,'page'=>$page,'errors'=>$errors];
}
