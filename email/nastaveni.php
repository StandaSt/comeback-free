<?php
/* E-mail: osobni podpis uzivatele a poradi slozek pro konkretni schranku, oddelene od sdileneho obsahu posty. */
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
    return ['ready'=>$ready,'signature'=>$html];
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
