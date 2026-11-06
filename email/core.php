<?php
/* E-mail: MAIN vedoucich a volba treninku ve sdilenych schrankach; konfigurace, sifrovani, prava a jednotne rozhodnuti o schrankach vcetne duvodu pristupu pro spravu. */
declare(strict_types=1);
require_once __DIR__.'/../common/lib/hr_pracoviste_main.php';
require_once __DIR__.'/../common/lib/pobockove_pc.php';
require_once __DIR__.'/precteni.php';
require_once __DIR__.'/chyby.php';
require_once __DIR__.'/pristup.php';
require_once __DIR__.'/osobni.php';

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

/** Pred instalaci zustava IS funkcni; docasne odesilani pouziva soubory a nevyzaduje vlastni DB tabulku. */
function em_schema(mysqli $db): bool
{
    foreach (['email_ucet','email_stav','email_audit','email_cache'] as $table) {
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
    $stmt=$db->prepare('SELECT p.id_role,r.role FROM hr_pristupovy_profil p JOIN cis_role r ON r.id_role=p.id_role AND r.aktivni=1 WHERE p.id_person=? AND p.id_role IN(5,7)');
    $stmt->bind_param('i',$p['id_person']); $stmt->execute();
    $p['branch_mail_roles']=array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC),'role','id_role');
    $p['branch_mail_role']=(bool)$p['branch_mail_roles'];
    // Trenink cteme z dnes platne funkce, vcetne role ktera jeste nemusi byt v pristupovem profilu.
    $p['training']=!empty($function['v_treninku']);$p['function_role']=(int)($function['id_role']??0);
    if($p['training'] && in_array($p['function_role'],[5,7],true)){
        $stmt=$db->prepare('SELECT id_role,role FROM cis_role WHERE id_role=? AND aktivni=1');
        $stmt->bind_param('i',$p['function_role']);$stmt->execute();
        foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $role)$p['branch_mail_roles'][(int)$role['id_role']]=$role['role'];
        $p['branch_mail_role']=(bool)$p['branch_mail_roles'];
    }
    $p['id_user']=$id;
    return $p;
}

/** Limit priloh urcuje aktualni HR odesilatele, nikoli schranka ani hodnota z prohlizece. */
function em_limit_priloh(bool $manager): int
{
    return ($manager?100:20)*1024*1024;
}

/** Pobockovou postu urcuji vsechny dnes platne MAIN osoby; zarizeni ani jeho registrace nerozhoduji. */
function em_pobocky_main(mysqli $db, array $person): array
{
    if (!$person['branch_user']) return [];
    return cb_hr_main_ids($db,(int)$person['id_person']);
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
    $p=$person??em_osoba($db); $branches=em_pobocky_main($db,$p); $shared=em_pravo(801);$access=em_pristup_mapa($db,$p);
    $stmt=$db->prepare('SELECT * FROM email_ucet WHERE aktivni=1 AND id_firma=? ORDER BY typ,email');
    $stmt->bind_param('i',$p['id_firma']); $stmt->execute(); $out=[];
    $personalMode=null;
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $a) {
        if (!em_vidi_ucet($p,$a,$branches,$shared,$access[(int)$a['id']]??null)) continue;
        // Vsechny prihlasovaci cesty dostavaji volbu vlastnika; sdilena posta se tim nemeni.
        if($a['typ']==='osobni'){$personalMode??=em_osobni_rezim($db,(int)$p['id_user'],$a['rezim']);$a['rezim']=$personalMode['mode'];}
        $out[]=$a;
    }
    return $out;
}

/** Pristup k pobockove schrance porovnavame s MAIN osoby; cizi firmy a osobni posta zustavaji oddelene. */
function em_vidi_ucet(array $p, array $a, array $branches, bool $shared, ?array $access=null): bool
{
    return em_pristup_cesty($p,$a,$branches,$shared,$access)!==[];
}

/** Cteni posty i spravni prehled sdileji jedine rozhodnuti; soubezne platne duvody se neztrati za OR. */
function em_pristup_cesty(array $p, array $a, array $branches, bool $shared, ?array $access=null): array
{
    if ((int)$a['id_firma']!==(int)$p['id_firma']) return [];
    if ($a['typ']==='osobni') return $p['manager'] && (int)$a['id_user']===(int)$p['id_user']?['personal']:[];
    if(!in_array($a['typ'],['pobocka','info'],true))return [];
    $main=$p['branch_user'] && !empty($p['branch_mail_role']) && $a['typ']==='pobocka' && (int)$a['id_pob']>0 && in_array((int)$a['id_pob'],$branches,true);
    // Po ulozeni vlastniho seznamu uz pravo 801 ani sprava 802 nesmi tento seznam obejit.
    $out=[];
    if($access!==null){if(!empty($access['granted']))$out[]='assigned';if(!empty($access['main']) && $main && (empty($p['training']) || !empty($access['training'])))$out[]='main';}
    else{if($shared)$out[]='shared';if($main && empty($p['training']))$out[]='main';}
    return $out;
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
    $accounts=[];
    foreach (em_ucty($db) as $a) {
        try { em_heslo($a); $accounts[]=$a; } catch (EmailChyba) {}
    }
    return (int)array_sum(em_precteni_pocty($db,$accounts));
}
