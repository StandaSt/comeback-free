<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
/* Co-Link: sprava kanalu a spolecne nacitani sluzeb modulu; presun do co_lib zachovava funkce i SQL. */
// Spolecny vstup nacita konfiguraci a rozdelene jednoucelove knihovny.
require_once __DIR__ . '/co_lib.php';
require_once __DIR__ . '/co_message_format.php';

// Pristup, sprava a seznam konverzaci jsou samostatne sluzby se zachovanymi verejnymi funkcemi.
require_once __DIR__ . '/co_pristup_sql.php';
require_once __DIR__ . '/co_konverzace.php';
require_once __DIR__ . '/co_spravce_kanalu.php';
require_once __DIR__ . '/co_seznam.php';

// Vyber osob a adresar jsou samostatne sluzby; ostatni datove operace zustavaji v tomto souboru.
require_once __DIR__ . '/co_lide.php';
require_once __DIR__ . '/co_adresar.php';

// Nacitani zprav sklada samostatna sluzba s presnou detekci dalsi stranky.
require_once __DIR__ . '/co_zpravy.php';

// Odesilani zpravy vcetne priloh a push fronty zajistuje samostatna transakcni sluzba.
require_once __DIR__ . '/co_odeslat.php';

// Upravu a smazani zpravy ridi samostatna sluzba se spolecnymi kontrolami a auditem.
require_once __DIR__ . '/co_men_zpravu.php';

// Potvrzovani precteni ridi samostatna sluzba vcetne revizi a transakce.
require_once __DIR__ . '/co_precteno.php';

// Reakce overuje a v transakci prepina samostatna sluzba.
require_once __DIR__ . '/co_reakce.php';

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

// Soukrome konverzace a skupiny zaklada samostatna sluzba s kontrolou clenu.
require_once __DIR__ . '/co_soukroma.php';
