<?php
/* E-mail: centralni sprava pobockovych schranek a INFO pouze s pravem 802; nikdy nevraci ulozena hesla klientovi. */
declare(strict_types=1);

/** Spravce vidi metadata vlastni firmy i bez prava 801; pravo spravy samo neumoznuje cteni posty. */
function em_sprava_cti(mysqli $db, array $person): array
{
    if (!em_pravo(802)) return ['accounts'=>[],'branches'=>[]];
    $s=$db->prepare('SELECT id,email,typ,id_pob,aktivni,revize FROM email_ucet WHERE id_firma=? AND typ IN("pobocka","info") ORDER BY email');
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
            $s=$db->prepare('INSERT INTO email_ucet(id_firma,typ,id_pob,email,heslo_sifra,rezim) VALUES(?,?,?,?,?,"is")');
            $s->bind_param('isiss',$p['id_firma'],$type,$branch,$email,$cipher); $s->execute(); $id=(int)$db->insert_id;
        }
        em_audit($db,$id,$old?'sdilena_uprava':'sdilena_pripojeni'); $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        if ($e instanceof mysqli_sql_exception && $e->getCode()===1062) throw new EmailChyba('Tato adresa nebo pobočka již má připojenou schránku. Upravte existující připojení.');
        throw $e;
    }
}
