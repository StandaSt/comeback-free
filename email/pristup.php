<?php
/* E-mail: MAIN vedoucich a volba treninku ve sdilenych schrankach; rozsah sdilenych schranek i konkretni prirazeni, ktera pristup umoznuji; zadne obchazeni prava 800. */
declare(strict_types=1);

/** Pred migraci zustava puvodni rozsah; nekompletni instalaci nelze pouzit jako duvod k obejiti omezeni. */
function em_pristup_schema(mysqli $db): bool
{
    $found=0;
    foreach(['email_pristup_nastaveni','email_pristup_clen'] as $table){$r=$db->query("SHOW TABLES LIKE '".str_replace('_','\\_',$table)."'");$found+=(int)($r->num_rows===1);}
    if($found===1)throw new EmailChyba('Instalace přístupu ke sdíleným schránkám není dokončená. Dokončete připravené SQL.');
    return $found===2;
}

/** Chybejici sloupec neblokuje cteni posty; nove nastaveni vyzaduje rucni SQL. */
function em_pristup_trenink_schema(mysqli $db): bool
{
    return $db->query("SHOW COLUMNS FROM email_pristup_nastaveni LIKE 'zahrnout_trenink'")->num_rows===1;
}

/** Jedinym dotazem vyhodnoti vlastni vyjimky a soucasne aktivni role; hesla ani cleny cizich firem nevraci. */
function em_pristup_mapa(mysqli $db, array $person): array
{
    if(!em_pristup_schema($db))return [];
    $training=em_pristup_trenink_schema($db);
    $functionRole=!empty($person['training']) && isset($person['branch_mail_roles'][(int)($person['function_role']??0)])?(int)$person['function_role']:0;
    $branches=em_pobocky_main($db,$person);
    $match='(c.typ="user" AND c.subjekt=?) OR (c.typ="role" AND c.subjekt IN (SELECT p.id_role FROM hr_pristupovy_profil p JOIN cis_role r ON r.id_role=p.id_role AND r.aktivni=1 WHERE p.id_person=?)) OR (c.typ="role" AND c.subjekt='.(int)$functionRole.' AND a.typ="pobocka" AND '.($training?'n.zahrnout_trenink':'0').'=1)';
    $s=$db->prepare('SELECT n.ucet,n.main_pobocka,'.($training?'n.zahrnout_trenink':'0').' AS zahrnout_trenink,a.typ AS ucet_typ,a.id_pob,c.typ,c.subjekt,CASE WHEN '.$match.' THEN 1 ELSE 0 END prideleno FROM email_pristup_nastaveni n JOIN email_ucet a ON a.id=n.ucet AND a.id_firma=? LEFT JOIN email_pristup_clen c ON c.ucet=n.ucet');
    $s->bind_param('iii',$person['id_user'],$person['id_person'],$person['id_firma']);$s->execute();$out=[];
    foreach($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r){
        $id=(int)$r['ucet'];$out[$id]??=['main'=>(bool)$r['main_pobocka'],'granted'=>false,'roles'=>[],'user'=>false,'training'=>!empty($r['zahrnout_trenink'])];
        if(!$r['prideleno'])continue;
        // Pobockove vedouci role vyzaduji MAIN; prime prirazeni a ostatni role jsou vedome vyjimky.
        if($r['typ']==='role' && in_array((int)$r['subjekt'],[5,7],true) && ($r['ucet_typ']??'')==='pobocka'){
            if(empty($person['branch_user']) || !in_array((int)$r['id_pob'],$branches,true)
                || (!empty($person['training']) && empty($r['zahrnout_trenink'])))continue;
        }
        $out[$id]['granted']=true;
        if($r['typ']==='role')$out[$id]['roles'][]=(int)$r['subjekt'];elseif($r['typ']==='user')$out[$id]['user']=true;
    }
    return $out;
}
