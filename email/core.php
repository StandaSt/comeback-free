<?php
/* E-mail: konfigurace, sifrovani a oddelena prava 800/801/802; pobockova posta vyzaduje roli 5 nebo 7, MAIN a overene PC. */
declare(strict_types=1);
require_once __DIR__.'/../common/lib/hr_pracoviste_main.php';
require_once __DIR__.'/../common/lib/pobockove_pc.php';

/** Veřejné chyby modulu nikdy nepřebírají protokolové výpisy obsahující přihlašovací údaje. */
final class EmailChyba extends RuntimeException {}

/** Uživatel zadává jen název schránky; firemní doménu určuje server, nikoli prohlížeč nebo kontakt v HR. */
function em_firemni_adresa(string $localPart): string
{
    $localPart=strtolower(trim($localPart));
    $email=$localPart.'@pizzacomeback.cz';
    if ($localPart==='' || strlen($localPart)>64 || str_contains($localPart,'@') || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        throw new EmailChyba('Zadejte pouze část firemní adresy před @pizzacomeback.cz.');
    }
    return $email;
}

/** Před instalací zůstává zbytek IS i navigace funkční. */
function em_schema(mysqli $db): bool
{
    foreach (['email_ucet','email_stav','email_odeslani','email_audit','email_cache'] as $table) {
        $r=$db->query("SHOW TABLES LIKE '".str_replace('_','\\_',$table)."'");
        if ($r->num_rows!==1) return false;
    }
    return true;
}

/** Společná konfigurace je chráněna před HTTP přístupem; její absence nesmí vyvolat síťové spojení. */
function em_config(): array
{
    $file=__DIR__.'/../common/config/email.php';
    if (!is_file($file)) throw new EmailChyba('Připojení poštovního serveru zatím není nastavené.');
    $c=require $file;
    if (!is_array($c) || preg_match('/^[a-f0-9]{64}$/i',(string)($c['key_hex']??''))!==1) throw new EmailChyba('Chybí platný šifrovací klíč e-mailu.');
    foreach (['imap','smtp'] as $type) {
        $s=$c[$type]??[];
        if (!is_string($s['host']??null) || !preg_match('/^[a-z0-9.-]+$/i',$s['host']) || (int)($s['port']??0)<1 || (int)$s['port']>65535
            || !in_array($s['encryption']??'',['ssl','tls'],true)) throw new EmailChyba('Chybí ověřené zabezpečené nastavení IMAP a SMTP.');
    }
    return $c;
}

/** AES-GCM ověřuje i neporušenost a vazbu ciphertextu na účet; klíč není uložen v DB. */
function em_zasifruj(string $plain, string $context): string
{
    $key=hex2bin(em_config()['key_hex']); $iv=random_bytes(12); $tag='';
    $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,$context);
    if ($cipher===false) throw new EmailChyba('Heslo se nepodařilo zabezpečit.');
    return base64_encode($iv.$tag.$cipher);
}

/** Poškozená hodnota ani špatný kontext nesmějí vydat část původního hesla. */
function em_rozsifruj(string $value, string $context): string
{
    $raw=base64_decode($value,true);
    if ($raw===false || strlen($raw)<29) throw new EmailChyba('Uložené připojení je neplatné. Připojte schránku znovu.');
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',hex2bin(em_config()['key_hex']),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$context);
    if ($plain===false) throw new EmailChyba('Uložené připojení nelze odemknout.');
    return $plain;
}

/** Identita zahrnuje konkrétní login, proto odemčení nepřežije nové přihlášení stejného člověka. */
function em_identita(): string
{
    return (int)($_SESSION['cb_user']['id_user']??0).':'.(int)($_SESSION['cb_id_login']??0);
}

/** Rozlišení osoby vychází z časově platného HR zařazení, nikoli z názvu role nebo jména. */
function em_osoba(mysqli $db, ?int $userId=null): array
{
    $id=$userId??(int)($_SESSION['cb_user']['id_user']??0);
    $stmt=$db->prepare('SELECT id_person,id_firma FROM hr_person WHERE id_user=? AND aktivni=1');
    $stmt->bind_param('i',$id); $stmt->execute(); $p=$stmt->get_result()->fetch_assoc();
    if (!$p) throw new EmailChyba('Chybí aktivní osoba v HR.');
    $function=cb_hr_funkce_osoby($db,(int)$p['id_person']);
    $stmt=$db->prepare('SELECT COUNT(*) n FROM hr_slot s WHERE id_person=? AND '.cb_hr_ucinne_sql('s'));
    $stmt->bind_param('i',$p['id_person']); $stmt->execute(); $slots=(int)$stmt->get_result()->fetch_assoc()['n'];
    $p['manager']=($function['zarazeni']??'')==='manazerske' && $slots===0;
    $p['branch_user']=$slots>0 && ($function['zarazeni']??'')!=='manazerske';
    // Role se overuje primo pro osobu v HR, nikoli podle nazvu funkce nebo neaktualniho stavu prohlizece.
    $stmt=$db->prepare('SELECT 1 FROM hr_pristupovy_profil p JOIN cis_role r ON r.id_role=p.id_role AND r.aktivni=1 WHERE p.id_person=? AND p.id_role IN(5,7) LIMIT 1');
    $stmt->bind_param('i',$p['id_person']); $stmt->execute();
    $p['branch_mail_role']=(bool)$stmt->get_result()->fetch_row();
    $p['id_user']=$id;
    return $p;
}

/** Používá pouze výslovně potvrzený profil PC, současný login a všechny aktuální MAIN pobočky. */
function em_pobocka_pc(mysqli $db, array $person): int
{
    if (!$person['branch_user'] || !cb_pobockove_pc_schema($db)) return 0;
    $hash=cb_pobockove_pc_hash($db);
    if ($hash==='') return 0;
    $stmt=$db->prepare('SELECT id_pob FROM pobockove_pc WHERE pc_hash=UNHEX(?) AND stav=1');
    $stmt->bind_param('s',$hash); $stmt->execute(); $branch=(int)($stmt->get_result()->fetch_assoc()['id_pob']??0);
    return in_array($branch,cb_hr_main_ids($db,(int)$person['id_person']),true)?$branch:0;
}

/** Obě práva musí být aktivní a skutečně přidělená; obecné vypnutí kontroly práv zde nesmí otevřít poštu. */
function em_pravo(int $id): bool
{
    return !empty($_SESSION['login_ok']) && empty($_SESSION['cb_prebrani_prav'])
        && (int)($_SESSION['prava_stav'][$id]??0)===1 && isset($_SESSION['prava'][$id])
        && ($id===800 || em_pravo(800));
}

/** Jediný serverový rozsah se používá pro detail, přílohy, zápisy i hlavičkový počet. */
function em_ucty(mysqli $db, ?array $person=null): array
{
    if (!em_pravo(800)) return [];
    $p=$person??em_osoba($db); $branch=em_pobocka_pc($db,$p); $shared=em_pravo(801);
    $stmt=$db->prepare('SELECT * FROM email_ucet WHERE aktivni=1 AND id_firma=? ORDER BY typ,email');
    $stmt->bind_param('i',$p['id_firma']); $stmt->execute(); $out=[];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $a) {
        if (em_vidi_ucet($p,$a,$branch,$shared)) $out[]=$a;
    }
    return $out;
}

/** Cista rozhodovaci matice zachovava oddeleni osobni, pobockove a spolecne posty i pri chybnych vstupnich datech. */
function em_vidi_ucet(array $p, array $a, int $branch, bool $shared): bool
{
    if ((int)$a['id_firma']!==(int)$p['id_firma']) return false;
    if ($a['typ']==='osobni') return $p['manager'] && (int)$a['id_user']===(int)$p['id_user'];
    return in_array($a['typ'],['pobocka','info'],true) && ($shared || ($p['branch_user'] && !empty($p['branch_mail_role']) && $a['typ']==='pobocka' && $branch>0 && (int)$a['id_pob']===$branch));
}

/** ID z prohlížeče samo o sobě přístup nikdy neuděluje. */
function em_ucet(mysqli $db, int $id): array
{
    foreach (em_ucty($db) as $a) if ((int)$a['id']===$id) return $a;
    throw new EmailChyba('K této schránce nemáte přístup.');
}

/** Pobočková pošta nevyžaduje heslo od jednotlivců; osobní odemčení je vázané na login a revizi účtu. */
function em_heslo(array $a): string
{
    // Sdilene heslo spravuje pouze drzitel 802; ctenar se overuje svym loginem IS a pristupem ke konkretnimu uctu.
    if (in_array($a['typ']??'',['pobocka','info'],true)) return em_rozsifruj((string)$a['heslo_sifra'],'ucet:'.$a['email']);
    $saved=$_SESSION['email_pristup'][$a['id']]??[];
    if (($saved['identita']??'')===em_identita() && !empty($saved['zamceno'])) throw new EmailChyba('Schránka je odhlášená. Přihlaste ji znovu.');
    if ($a['rezim']==='heslo') {
        if (($saved['identita']??'')!==em_identita() || (int)($saved['revize']??0)!==(int)$a['revize'] || empty($saved['heslo'])) throw new EmailChyba('Pro otevření schránky zadejte její heslo.');
        return em_rozsifruj($saved['heslo'],'session:'.em_identita().':'.$a['id']);
    }
    return em_rozsifruj((string)$a['heslo_sifra'],'ucet:'.$a['email']);
}

/** Audit obsahuje autora a význam akce, nikdy hesla, obsah zpráv nebo protokolové výpisy. */
function em_audit(mysqli $db, int $account, string $action, string $object=''): void
{
    $user=(int)($_SESSION['cb_user']['id_user']??0);
    $s=$db->prepare('INSERT INTO email_audit(ucet,autor,akce,objekt) VALUES(?,?,?,?)');
    $object=mb_substr($object,0,500); $s->bind_param('iiss',$account,$user,$action,$object); $s->execute();
}

/** Hlavička pouze čte poslední známé počty a nikdy nečeká na mailserver. */
function em_neprecteno(mysqli $db): int
{
    if (!em_pravo(800) || !em_schema($db)) return 0;
    $ids=[];
    foreach (em_ucty($db) as $a) {
        try { em_heslo($a); $ids[]=(int)$a['id']; } catch (EmailChyba) {}
    }
    if (!$ids) return 0;
    return (int)$db->query('SELECT COALESCE(SUM(neprecteno),0) n FROM email_cache WHERE ucet IN('.implode(',',$ids).')')->fetch_assoc()['n'];
}
