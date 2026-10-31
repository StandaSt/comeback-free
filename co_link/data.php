<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../common/lib/osoba_jmeno.php';
/* Co-Link: opravneny pristup, transakcni formatovane zpravy, push a adresar s HR fotografii. */
require_once __DIR__ . '/lib.php';
require_once __DIR__ . '/message_format.php';

/** Clenstvi kanalu je soucet vyberu; zmena HR udaju odebere i pristup k historii a priloham. */
function co_pristup_sql(int $user, string $alias = 'k'): string
{
    $user = max(0,$user);
    return "(EXISTS(SELECT 1 FROM co_clen c WHERE c.konverzace=$alias.id AND c.uzivatel=$user)
      OR ($alias.typ='kanal' AND ($alias.pristup_vsem=1
        OR EXISTS(SELECT 1 FROM co_pristup r JOIN hr_slot s ON s.id_slot=r.hodnota AND s.id_person=$user
          WHERE r.konverzace=$alias.id AND r.druh='slot' AND s.platny=1 AND s.zruseno IS NULL
          AND (s.platnost_od IS NULL OR s.platnost_od<=CURDATE()) AND (s.platnost_do IS NULL OR s.platnost_do>=CURDATE()))
        OR EXISTS(SELECT 1 FROM co_pristup r JOIN hr_funkce f ON f.id_funkce=r.hodnota AND f.id_person=$user
          WHERE r.konverzace=$alias.id AND r.druh='funkce' AND f.platny=1
          AND (f.platnost_od IS NULL OR f.platnost_od<=CURDATE()) AND (f.platnost_do IS NULL OR f.platnost_do>=CURDATE())))))";
}

/** Kontrola jednoho objektu se pouziva i pro stahovani, vyhledavani a push frontu. */
function co_konverzace(int $user, int $id, bool $lock = false): array
{
    $row = co_rows('SELECT k.* FROM co_konverzace k WHERE k.id=? AND '.co_pristup_sql($user).($lock?' FOR UPDATE':''),[$id])[0] ?? [];
    if (!$row) co_chyba('Do této konverzace nemáte přístup.',403);
    return $row;
}

/** Spravce meni nastaveni kanalu, ale tato kontrola mu nedava cteni jeho zprav. */
function co_spravce_kanalu(int $user, array $channel): bool
{
    return $channel['typ']==='kanal' && (co_pravo($user,702) || ((int)$channel['autor']===$user && co_pravo($user,701)));
}

/** Seznam chatu vraci jen dostupne konverzace a pocty zprav bez skutecneho precteni. */
function co_seznam(int $user): array
{
    $list=co_rows('SELECT k.id,k.typ,k.nazev,k.autor,k.archiv,k.zmeneno,COALESCE(v.ztiseno,0) ztiseno,
        (SELECT COUNT(*) FROM co_zprava z WHERE z.konverzace=k.id AND z.smazano IS NULL AND z.autor<>?
          AND NOT EXISTS(SELECT 1 FROM co_precteni p WHERE p.zprava=z.id AND p.uzivatel=?)) neprecteno
        FROM co_konverzace k LEFT JOIN co_volba v ON v.konverzace=k.id AND v.uzivatel=?
        WHERE '.co_pristup_sql($user).' ORDER BY k.archiv,k.zmeneno DESC,k.id DESC',[$user,$user,$user]);
    $members=[];
    foreach(co_rows('SELECT c.konverzace,c.uzivatel FROM co_clen c JOIN co_konverzace k ON k.id=c.konverzace WHERE k.typ<>"kanal" AND '.co_pristup_sql($user)) as $m) $members[(int)$m['konverzace']][]=(int)$m['uzivatel'];
    foreach($list as &$channel) $channel['clenove']=$members[(int)$channel['id']]??[];
    unset($channel);
    return $list;
}

/** Zverejnuje pouze jmeno a identifikator aktivnich uctu, ktere smeji pouzivat link. */
function co_lide(): array
{
    // Jmena celeho adresare se nactou v jednom dotazu; kontroly pristupu zustavaji oddelene.
    $ids = co_rows('SELECT u.id_user,COALESCE('.cb_osoba_jmeno_sql('p.id_person').',u.email) jmeno
        FROM user u JOIN hr_person p ON p.id_person=u.id_user AND p.id_user=u.id_user AND p.aktivni=1 ORDER BY u.id_user');
    $people = [];
    foreach ($ids as $id) {
        $uid=(int)$id['id_user'];
        if (co_pravo($uid,700)) $people[]=['id'=>$uid,'jmeno'=>$id['jmeno']];
    }
    usort($people,static fn($a,$b)=>strnatcasecmp($a['jmeno'],$b['jmeno']));
    return $people;
}

/** Filtry adresare pouze usnadnuji vyber; nemeni pravo vstupu ani automaticky nevybiraji prijemce. */
function co_adresar(): array
{
    require_once __DIR__.'/../common/lib/hr_pracoviste_main.php';
    $people=co_lide();
    $ids=array_map('intval',array_column($people,'id'));
    $groups=[]; $branches=[]; $labels=[]; $roleLabels=[]; $photos=[];
    if($ids!==[]) {
        $in=implode(',',$ids);
        // Stejna aktualni verze fotografie jako na HR karte; ven neposilame dalsi osobni udaje.
        foreach(co_rows('SELECT o.id_person,o.foto FROM hr_osobni_udaje o WHERE o.id_person IN ('.$in.') AND o.id_osobni_udaje=(SELECT MAX(n.id_osobni_udaje) FROM hr_osobni_udaje n WHERE n.id_person=o.id_person AND n.platny=1)') as $r) {
            $photo=(string)($r['foto']??'');
            if(preg_match('~^hr/img/zamestnanci/[a-f0-9]{32}\.(jpg|png|webp)$~D',$photo)) $photos[(int)$r['id_person']]='../'.$photo;
        }
        // Manager zahrnuje admina a top management; vedouci pobocky ma vlastni roli.
        foreach(co_rows('SELECT p.id_person,p.id_role,r.role FROM hr_pristupovy_profil p JOIN cis_role r ON r.id_role=p.id_role AND r.aktivni=1 WHERE p.id_person IN ('.$in.')') as $r) {
            $role=(int)$r['id_role'];$person=(int)$r['id_person'];
            $roleLabels[$person][]=$r['role'];
            if(in_array($role,[1,2,3],true)) $groups[$person]['manager']=true;
            if($role===5) $groups[$person]['vedouci']=true;
        }
        // Sloty jsou mnozina: kolega muze byt soucasne kuryr i pizzar.
        foreach(co_rows('SELECT DISTINCT s.id_person,s.id_slot,c.slot FROM hr_slot s JOIN cis_slot c ON c.id_slot=s.id_slot AND c.aktivni=1 WHERE s.id_person IN ('.$in.') AND '.cb_hr_ucinne_sql('s')) as $r) {
            if(in_array((int)$r['id_slot'],[1,2],true)) $groups[(int)$r['id_person']][(int)$r['id_slot']===2?'kuryr':'pizzar']=true;
            $labels[(int)$r['id_person']][]=$r['slot'];
        }
        foreach(co_rows('SELECT DISTINCT f.id_person,c.nazev FROM hr_funkce f JOIN cis_funkce c ON c.id_funkce=f.id_funkce AND c.aktivni=1 WHERE f.id_person IN ('.$in.') AND f.platny=1 AND (f.platnost_od IS NULL OR f.platnost_od<=CURRENT_DATE()) AND (f.platnost_do IS NULL OR f.platnost_do>=CURRENT_DATE())') as $r) $labels[(int)$r['id_person']][]=$r['nazev'];
        // Pro pobocky pouzivame cely platny rozsah mist vykonu prace, vcetne oblasti a vypomoci.
        foreach(co_rows('SELECT DISTINCT w.id_person,w.id_pob FROM '.cb_hr_pracoviste_sql(db()).' w WHERE w.id_person IN ('.$in.')') as $r) $branches[(int)$r['id_person']][(int)$r['id_pob']]=true;
    }
    foreach($people as &$person) {
        $person['skupiny']=array_keys($groups[$person['id']]??[]);
        $person['pobocky']=array_map('intval',array_keys($branches[$person['id']]??[]));
        $person['zarazeni']=array_values(array_unique($labels[$person['id']]??$roleLabels[$person['id']]??[]));
        $person['foto']=$photos[$person['id']]??null;
    }
    unset($person);
    return ['lide'=>$people,'pobocky'=>co_rows('SELECT id_pob id,nazev FROM pobocka WHERE aktivni=1 ORDER BY id_pob')];
}

/** Cas a revize jsou soucasti stavu; klient neoznaci jako prectenou nove upravenou nevidenou verzi. */
function co_zpravy(int $user, int $conversation, int $before = 0, string $search = '', int $around = 0, int $thread = 0): array
{
    $channel=co_konverzace($user,$conversation);
    $where='z.konverzace=?'; $params=[$conversation];
    if ($before>0) { $where.=' AND z.id<?'; $params[]=$before; }
    if ($around>0) {
        $where.=' AND z.id<=?'; $params[]=$around;
    }
    if ($search!=='') { $where.=" AND z.smazano IS NULL AND SUBSTRING_INDEX(z.text,CONCAT(CHAR(10),CHAR(30),'CO1:'),1) LIKE ?"; $params[]='%'.mb_substr($search,0,120).'%'; }
    elseif($thread>0) { $where.=' AND (z.rodic=? OR z.id=?)'; $params[]=$thread; $params[]=$thread; }
    else $where.=' AND z.rodic IS NULL';
    $messages=co_rows('SELECT z.*,COALESCE(' . cb_osoba_jmeno_sql('z.autor') . ',"Uživatel") jmeno,
        (SELECT COUNT(*) FROM co_precteni p WHERE p.zprava=z.id) precteno_pocet,
        EXISTS(SELECT 1 FROM co_precteni p WHERE p.zprava=z.id AND p.uzivatel='.(int)$user.') precteno_mnou,
        (SELECT COUNT(*) FROM co_zprava odpoved WHERE odpoved.rodic=z.id AND odpoved.smazano IS NULL) odpovedi
        FROM co_zprava z WHERE '.$where.' ORDER BY z.id DESC LIMIT 80',$params);
    $messages=array_reverse($messages);
    $ids=array_map('intval',array_column($messages,'id'));
    $files=[]; $reactions=[];
    if ($ids!==[]) {
        $in=implode(',',$ids);
        foreach(co_rows('SELECT id,zprava,nazev,mime,velikost FROM co_priloha WHERE zprava IN ('.$in.')') as $f) $files[(int)$f['zprava']][]=$f;
        foreach(co_rows('SELECT zprava,emoji,COUNT(*) pocet,MAX(uzivatel=?) moje FROM co_reakce WHERE zprava IN ('.$in.') GROUP BY zprava,emoji',[$user]) as $r) $reactions[(int)$r['zprava']][]=$r;
    }
    foreach($messages as &$m) {
        $m['prilohy']=$m['smazano']===null?($files[(int)$m['id']]??[]):[];
        $m['reakce']=$m['smazano']===null?($reactions[(int)$m['id']]??[]):[];
        if($m['smazano']!==null) $m['text']='';
        $m['lze_menit']=(int)$m['autor']===$user && $m['prvni_precteni']===null && $m['smazano']===null;
    }
    unset($m);
    return ['kanal'=>$channel,'zpravy'=>$messages,'dalsi'=>count($messages)===80];
}

/** Identifikator vygenerovany klientem prezije vypadek spojeni; stejna zprava se ulozi pouze jednou. */
function co_odeslat(int $user, array $d): array
{
    $client=(string)($d['klient_id']??'');
    if(!preg_match('/^[a-zA-Z0-9-]{20,36}$/D',$client)) co_chyba('Chybí identifikátor zprávy.');
    $text=co_zprava_text((string)($d['text']??''));
    $files=array_values(array_unique(array_map('intval',(array)($d['prilohy']??[]))));
    if(count($files)>20 || ($text==='' && $files===[])) co_chyba('Zadejte zprávu do 20 000 znaků nebo nejvýše 20 příloh.');
    $cid=(int)($d['konverzace']??0);
    return co_transakce(static function() use($user,$d,$client,$text,$files,$cid): array {
        $channel=co_konverzace($user,$cid,true);
        if((int)$channel['archiv']===1) co_chyba('Kanál je archivovaný.');
        $existing=co_rows('SELECT id,konverzace FROM co_zprava WHERE autor=? AND klient_id=?',[$user,$client])[0]??[];
        if($existing) {
            if((int)$existing['konverzace']!==$cid) co_chyba('Identifikátor zprávy už byl použit.',409);
            return ['id'=>(int)$existing['id']];
        }
        $parent=(int)($d['rodic']??0);
        if($parent>0) {
            $p=co_rows('SELECT id,rodic FROM co_zprava WHERE id=? AND konverzace=?',[$parent,$cid])[0]??[];
            if(!$p || $p['rodic']!==null) co_chyba('Vlákno musí patřit hlavní zprávě této konverzace.');
        }
        co_sql('INSERT INTO co_zprava(konverzace,autor,klient_id,rodic,text) VALUES(?,?,?,?,?)',[$cid,$user,$client,$parent?:null,$text])->close();
        $id=(int)db()->insert_id;
        foreach($files as $file) {
            $s=co_sql('UPDATE co_priloha SET zprava=? WHERE id=? AND vlastnik=? AND zprava IS NULL',[$id,$file,$user]);
            if($s->affected_rows!==1) co_chyba('Příloha už není dostupná. Nahrajte ji znovu.',409);
            $s->close();
        }
        co_sql('UPDATE co_konverzace SET zmeneno=NOW(6) WHERE id=?',[$cid])->close();
        // Fronta vznika ve stejne transakci jako zprava; selhani push sluzby neztrati text.
        foreach(co_rows('SELECT d.id,d.uzivatel FROM co_zarizeni d WHERE d.uzivatel<>? AND d.zruseno IS NULL AND (d.pc_hash IS NULL OR d.platnost_do>NOW()) AND d.endpoint IS NOT NULL
            AND NOT EXISTS(SELECT 1 FROM co_volba v WHERE v.konverzace=? AND v.uzivatel=d.uzivatel AND v.ztiseno=1)',[$user,$cid]) as $device) {
            $recipient=(int)$device['uzivatel'];
            if(!co_osoba($recipient) || !co_pravo($recipient,700)) continue;
            if(co_rows('SELECT k.id FROM co_konverzace k WHERE k.id=? AND '.co_pristup_sql($recipient),[$cid])===[]) continue;
            co_sql('INSERT INTO co_push_fronta(zprava,zarizeni) VALUES(?,?)',[$id,(int)$device['id']])->close();
        }
        return ['id'=>$id];
    });
}

/** Precteni, editace i smazani zamykaji stejnou zpravu a respektuji prvni cizi zobrazeni. */
function co_men_zpravu(int $user, array $d): array
{
    return co_transakce(static function() use($user,$d): array {
        $id=(int)($d['zprava']??0);
        $m=co_rows('SELECT * FROM co_zprava WHERE id=? FOR UPDATE',[$id])[0]??[];
        if(!$m) co_chyba('Zpráva neexistuje.',404);
        $channel=co_konverzace($user,(int)$m['konverzace']);
        if((int)$channel['archiv']===1) co_chyba('Archivovaný kanál je pouze pro čtení.',409);
        if((int)$m['autor']!==$user || $m['prvni_precteni']!==null || $m['smazano']!==null) co_chyba('Přečtenou nebo cizí zprávu nelze změnit ani smazat.',409);
        if((int)($d['revize']??0)!==(int)$m['revize']) co_chyba('Zpráva se mezitím změnila. Obnovte ji.',409);
        if(($d['akce']??'')==='smazat') {
            co_sql('UPDATE co_zprava SET text="",smazano=NOW(6),revize=revize+1 WHERE id=?',[$id])->close();
            co_sql('UPDATE co_push_fronta SET hotovo=NOW() WHERE zprava=? AND hotovo IS NULL',[$id])->close();
        } else {
            $text=co_zprava_text((string)($d['text']??''));
            if($text==='' && co_rows('SELECT 1 FROM co_priloha WHERE zprava=? LIMIT 1',[$id])===[]) co_chyba('Zpráva nesmí být prázdná ani delší než 20 000 znaků.');
            co_sql('UPDATE co_zprava SET text=?,upraveno=NOW(6),revize=revize+1 WHERE id=?',[$text,$id])->close();
        }
        co_audit($user,($d['akce']??'')==='smazat'?'zprava_smazana':'zprava_upravena',$id);
        return [];
    });
}

/** Zobrazeni stare revize neuzamkne novou verzi, kterou prijemce jeste nevidel. */
function co_precteno(int $user, int $cid, array $items): void
{
    co_konverzace($user,$cid);
    if(count($items)>80) co_chyba('Příliš mnoho potvrzení.');
    usort($items,static fn($a,$b)=>(int)($a['id']??0)<=>(int)($b['id']??0));
    co_transakce(static function() use($user,$cid,$items): void {
        foreach($items as $item) {
            $m=co_rows('SELECT id,autor,revize,smazano FROM co_zprava WHERE id=? AND konverzace=? FOR UPDATE',[(int)($item['id']??0),$cid])[0]??[];
            if(!$m || (int)$m['autor']===$user || $m['smazano']!==null || (int)$m['revize']!==(int)($item['revize']??0)) continue;
            co_sql('INSERT IGNORE INTO co_precteni(zprava,uzivatel) VALUES(?,?)',[(int)$m['id'],$user])->close();
            co_sql('UPDATE co_zprava SET prvni_precteni=COALESCE(prvni_precteni,NOW(6)) WHERE id=?',[(int)$m['id']])->close();
        }
    });
}

/** Ucastnik muze reagovat i po precteni; reakce nemeni obsah autorovy zpravy. */
function co_reakce(int $user, int $id, string $emoji): void
{
    if(!in_array($emoji,['👍','❤️','✅','😂','🙏','👀'],true)) co_chyba('Nepodporovaná reakce.');
    $m=co_rows('SELECT konverzace FROM co_zprava WHERE id=? AND smazano IS NULL',[$id])[0]??[];
    if(!$m) co_chyba('Zpráva není dostupná.',404);
    $channel=co_konverzace($user,(int)$m['konverzace']);
    if((int)$channel['archiv']===1) co_chyba('Archivovaný kanál je pouze pro čtení.',409);
    co_transakce(static function() use($user,$id,$emoji): void {
        co_sql('SELECT id FROM co_zprava WHERE id=? FOR UPDATE',[$id])->close();
        $s=co_sql('DELETE FROM co_reakce WHERE zprava=? AND uzivatel=? AND emoji=?',[$id,$user,$emoji]);
        $removed=$s->affected_rows>0; $s->close();
        if(!$removed) co_sql('INSERT INTO co_reakce(zprava,uzivatel,emoji) VALUES(?,?,?)',[$id,$user,$emoji])->close();
    });
}

/** Nastaveni kanalu je zvlast; spravce nemusi mit pristup k obsahu kanalu. */
function co_kanal_uloz(int $user, array $d): array
{
    $id=(int)($d['id']??0);
    $old=$id?co_rows('SELECT * FROM co_konverzace WHERE id=?',[$id])[0]??[]:[];
    if($id ? (!$old || !co_spravce_kanalu($user,$old)) : !co_pravo($user,701)) co_chyba('Nemáte právo spravovat tento kanál.',403);
    $name=trim((string)($d['nazev']??''));
    if($name==='' || mb_strlen($name)>100) co_chyba('Název kanálu musí mít 1 až 100 znaků.');
    $people=array_values(array_unique(array_map('intval',(array)($d['lide']??[]))));
    $slots=array_values(array_unique(array_map('intval',(array)($d['sloty']??[]))));
    $functions=array_values(array_unique(array_map('intval',(array)($d['funkce']??[]))));
    $all=!empty($d['vsichni'])?1:0;
    if(!$all && !$people && !$slots && !$functions) co_chyba('Vyberte alespoň jednu podmínku přístupu.');
    $allowed=array_column(co_lide(),'id');
    if(array_diff($people,$allowed)) co_chyba('Výběr obsahuje neaktivního uživatele nebo účet bez přístupu do linku.');
    foreach([['cis_slot','id_slot',$slots],['cis_funkce','id_funkce',$functions]] as [$table,$key,$values]) {
        $valid=array_map('intval',array_column(co_rows("SELECT $key FROM $table WHERE aktivni=1"),$key));
        if(array_diff($values,$valid)) co_chyba('Výběr obsahuje neplatný slot nebo funkci.');
    }
    return co_transakce(static function() use($user,$d,$id,$name,$people,$slots,$functions,$all): array {
        if($id) co_sql('UPDATE co_konverzace SET nazev=?,pristup_vsem=?,archiv=?,zmeneno=NOW(6) WHERE id=?',[$name,$all,!empty($d['archiv'])?1:0,$id])->close();
        else { co_sql('INSERT INTO co_konverzace(typ,nazev,pristup_vsem,autor) VALUES("kanal",?,?,?)',[$name,$all,$user])->close(); $id=(int)db()->insert_id; }
        co_sql('DELETE FROM co_clen WHERE konverzace=?',[$id])->close();
        co_sql('DELETE FROM co_pristup WHERE konverzace=?',[$id])->close();
        foreach($people as $p) co_sql('INSERT INTO co_clen(konverzace,uzivatel) VALUES(?,?)',[$id,$p])->close();
        foreach(['slot'=>$slots,'funkce'=>$functions] as $kind=>$values) foreach($values as $v) co_sql('INSERT INTO co_pristup(konverzace,druh,hodnota) VALUES(?,?,?)',[$id,$kind,$v])->close();
        co_audit($user,'kanal_nastaven',$id);
        return ['id'=>$id];
    });
}

/** Soukromy chat jedne dvojice ma stabilni klic; skupina ma pevne vybrane cleny. */
function co_soukroma(int $user, array $d): array
{
    $members=array_values(array_unique(array_merge([$user],array_map('intval',(array)($d['lide']??[])))));
    sort($members);
    if(count($members)<2 || count($members)>100 || array_diff($members,array_column(co_lide(),'id'))) co_chyba('Vyberte 1 až 99 dalších aktivních uživatelů linku.');
    $pair=count($members)===2?implode(':',$members):null;
    $name=trim((string)($d['nazev']??''));
    if(!$pair && ($name==='' || mb_strlen($name)>100)) co_chyba('Pojmenujte skupinu (nejvýše 100 znaků).');
    return co_transakce(static function() use($user,$members,$pair,$name): array {
        co_sql('INSERT INTO co_konverzace(typ,nazev,autor,soukromy_klic) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',[$pair?'soukroma':'skupina',$name,$user,$pair])->close();
        $id=(int)db()->insert_id;
        foreach($members as $member) co_sql('INSERT IGNORE INTO co_clen(konverzace,uzivatel) VALUES(?,?)',[$id,$member])->close();
        return ['id'=>$id];
    });
}
