<?php
/* E-mail: osobni podpis, volby obrazku a mazani i poradi schranek a slozek bez zmen sdileneho obsahu posty. */
declare(strict_types=1);
require_once __DIR__.'/posta.php';

/** Chybejici rozsireni schematu nevyradi dosavadni cteni ani odesilani e-mailu. */
function em_nastaveni_pripraveno(mysqli $db): bool
{
    static $cache; $cache??=new WeakMap();
    if (!isset($cache[$db])) {
        $cache[$db]=true;
        foreach (['email_nastaveni','email_poradi_slozek'] as $table) {
            $r=$db->query("SHOW TABLES LIKE '".str_replace('_','\\_',$table)."'");
            if ($r->num_rows!==1) $cache[$db]=false;
        }
    }
    return $cache[$db];
}

/** Podpis patri prihlasene osobe bez ohledu na to, z jake opravnenene schranky odesila. */
function em_nastaveni_cti(mysqli $db): array
{
    $ready=em_nastaveni_pripraveno($db); $html='';
    if ($ready) {
        $user=(int)$_SESSION['cb_user']['id_user'];
        $s=$db->prepare('SELECT podpis_html FROM email_nastaveni WHERE id_user=?'); $s->bind_param('i',$user); $s->execute();
        $html=em_html((string)($s->get_result()->fetch_assoc()['podpis_html']??''));
    }
    $extended=$ready && em_volby_pripraveno($db);
    $preferences=['ready'=>$ready,'signature'=>$html,'settings_ready'=>$extended,'show_images'=>false,'confirm_delete'=>true,'account_order'=>[]];
    if ($extended) {
        $s=$db->prepare('SELECT poradi_schranek,zobrazovat_obrazky,potvrzovat_mazani FROM email_nastaveni WHERE id_user=?');
        $s->bind_param('i',$user); $s->execute(); $row=$s->get_result()->fetch_assoc();
        if ($row) {
            $order=json_decode((string)($row['poradi_schranek']??'[]'),true);
            $preferences['account_order']=is_array($order)?array_values(array_filter($order,fn($id)=>is_int($id) && $id>=0)):[];
            $preferences['show_images']=(bool)$row['zobrazovat_obrazky'];
            $preferences['confirm_delete']=(bool)$row['potvrzovat_mazani'];
        }
    }
    return $preferences;
}

/** Stare schema ponecha podpis funkcni; nove volby se zpřistupni az po rucni migraci. */
function em_volby_pripraveno(mysqli $db): bool
{
    static $cache; $cache??=new WeakMap();
    if (!isset($cache[$db])) {
        $columns=[]; $r=$db->query('SHOW COLUMNS FROM email_nastaveni');
        while ($row=$r->fetch_assoc()) $columns[]=$row['Field'];
        $cache[$db]=!array_diff(['poradi_schranek','zobrazovat_obrazky','potvrzovat_mazani'],$columns);
    }
    return $cache[$db];
}

/** Casove nezavisle dilci zapisy neprepisuji podpis ani jinou soucasne menenou volbu. */
function em_volby_uloz(mysqli $db, array $data): array
{
    if (!em_nastaveni_pripraveno($db) || !em_volby_pripraveno($db)) throw new EmailChyba('Nová nastavení čekají na spuštění SQL pro osobní nastavení pošty.');
    $columns=[]; $values=[]; $types='i'; $params=[(int)$_SESSION['cb_user']['id_user']];
    foreach (['show_images'=>'zobrazovat_obrazky','confirm_delete'=>'potvrzovat_mazani','account_order'=>'poradi_schranek'] as $key=>$column) {
        if (!array_key_exists($key,$data)) continue;
        $value=$data[$key];
        if ($key==='account_order') {
            $available=array_map(fn($a)=>(int)$a['id'],array_filter(em_ucty($db),fn($a)=>$a['typ']!=='osobni'));
            if (em_pravo(801) && $available) $available[]=0; // Nula oznacuje souhrn vsech dostupnych spolecnych schranek.
            $value=em_poradi_schranek_over($value,$available);
            $value=json_encode($value,JSON_THROW_ON_ERROR); $types.='s';
        } else {
            if (!is_bool($value)) throw new EmailChyba('Neplatná osobní volba.');
            $value=(int)$value; $types.='i';
        }
        $columns[]=$column; $values[]=$column.'=VALUES('.$column.')'; $params[]=$value;
    }
    if (!$columns) throw new EmailChyba('Chybí nastavení k uložení.');
    // Novy zaznam potrebuje prazdny podpis; pri aktualizaci se existujici podpis nikdy neprepise.
    $s=$db->prepare("INSERT INTO email_nastaveni(id_user,podpis_html,".implode(',',$columns).",zmeneno) VALUES(?,'',".implode(',',array_fill(0,count($columns),'?')).",NOW()) ON DUPLICATE KEY UPDATE ".implode(',',$values).',zmeneno=NOW()');
    $s->bind_param($types,...$params); $s->execute();
    return em_nastaveni_cti($db);
}

/** Ulozit lze jen jedinecna ciselna ID schranek dostupnych prave prihlasene osobe. */
function em_poradi_schranek_over(mixed $order, array $available): array
{
    if (!is_array($order) || !array_is_list($order) || count($order)>2000) throw new EmailChyba('Neplatné pořadí schránek.');
    foreach ($order as $id) if (!is_int($id) || !in_array($id,$available,true)) throw new EmailChyba('Dostupné schránky se změnily. Otevřete nastavení znovu.');
    if (count($order)!==count(array_unique($order))) throw new EmailChyba('Schránka je v pořadí vícekrát.');
    return $order;
}

/** Ulozeni vzdy cisti HTML na serveru a nedovoli vybrat ciziho uzivatele z pozadavku. */
function em_podpis_uloz(mysqli $db, string $html): array
{
    if (!em_nastaveni_pripraveno($db)) throw new EmailChyba('Osobní nastavení zatím není připravené k ukládání.');
    if (strlen($html)>65536) throw new EmailChyba('Podpis je příliš dlouhý.');
    $html=em_html($html); $user=(int)$_SESSION['cb_user']['id_user'];
    if (trim(em_html_text($html))==='') $html='';
    $s=$db->prepare('INSERT INTO email_nastaveni(id_user,podpis_html,zmeneno) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE podpis_html=VALUES(podpis_html),zmeneno=NOW()');
    $s->bind_param('is',$user,$html); $s->execute();
    return ['signature'=>$html];
}

/** Pri poradi se pouzivaji presne serverove cesty, nikdy prelozene nebo duplicitni zobrazovane nazvy. */
function em_poradi_cti(mysqli $db, int $account): array
{
    if (!em_nastaveni_pripraveno($db)) return [];
    $user=(int)$_SESSION['cb_user']['id_user'];
    $s=$db->prepare('SELECT poradi FROM email_poradi_slozek WHERE id_user=? AND ucet=?'); $s->bind_param('ii',$user,$account); $s->execute();
    $value=json_decode((string)($s->get_result()->fetch_assoc()['poradi']??'[]'),true);
    return is_array($value)?array_values(array_filter($value,'is_string')):[];
}

/** Zmeny na mailserveru mezi otevrenim a ulozenim editoru nesmeji ulozit cizi nebo neexistujici cesty. */
function em_poradi_over(array $order, array $available): array
{
    if (!array_is_list($order) || count($order)>2000) throw new EmailChyba('Neplatné pořadí složek.');
    foreach ($order as $path) {
        if (!is_string($path) || strlen($path)>1000 || !in_array($path,$available,true)) throw new EmailChyba('Seznam složek se změnil. Otevřete úpravu pořadí znovu.');
    }
    if (count($order)!==count(array_unique($order))) throw new EmailChyba('Složka je v pořadí vícekrát.');
    return $order;
}

/** Prazdny seznam znamena navrat k vychozimu poradi a ponecha ostatni uzivatele sdilene schranky beze zmeny. */
function em_poradi_uloz(mysqli $db, int $account, array $order, array $available): void
{
    if (!em_nastaveni_pripraveno($db)) throw new EmailChyba('Osobní nastavení zatím není připravené k ukládání.');
    $json=json_encode(em_poradi_over($order,$available),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); $user=(int)$_SESSION['cb_user']['id_user'];
    $s=$db->prepare('INSERT INTO email_poradi_slozek(id_user,ucet,poradi,zmeneno) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE poradi=VALUES(poradi),zmeneno=NOW()');
    $s->bind_param('iis',$user,$account,$json); $s->execute();
}
