<?php
// Čte úplnou sadu platných archivních kontrol a HR kontakty pro e-mailový přehled.
declare(strict_types=1);
// Udaje a zobrazovane jmeno osoby vcetne titulu sdili centralni cteci zdroj.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
require_once __DIR__ . '/../lib/kontrola_trzeb_formular.php';
require_once __DIR__ . '/db_kontrola_trzeb_soucty.php';

const CB_KT_EMAIL_PRIJEMCE_PERSON = 57;

// Očekávají se všechny otevřené pobočky s platným reportem, nikoli jen právě zvolená pobočka.
function cb_kt_email_podklady(mysqli $db, array $allowed, string $from, string $to): ?array
{
    if (!cb_kt_archiv_pripraven($db) || !cb_kt_platnost_pripravena($db)) return null;
    $stmt=$db->prepare('SELECT DISTINCT p.id_pob,p.nazev FROM pobocka p JOIN reporty_is r ON r.id_pob=p.id_pob
        WHERE p.aktivni=1 AND p.id_pob<>7 AND r.platny=1 AND r.datum_reportu BETWEEN ? AND ?
        AND r.datum_reportu>=p.prvni_obj ORDER BY p.id_pob');
    $stmt->bind_param('ss',$from,$to); $stmt->execute();
    $branches=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    if ($branches===[]) return null;
    $controls=[]; $sumRows=[];
    foreach ($branches as $branch) {
        $id=(int)$branch['id_pob'];
        if (!isset($allowed[$id])) return null;
        $stmt=$db->prepare('SELECT id_kontrola FROM provoz_kontrola_trzeb
            WHERE id_pob=? AND datum_od=? AND datum_do=? AND platny=1 ORDER BY id_kontrola');
        $stmt->bind_param('iss',$id,$from,$to); $stmt->execute();
        $ids=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        if (count($ids)!==1) return null;
        $control=cb_kt_archiv_detail($db,(int)$ids[0]['id_kontrola'],$id);
        $rows=[];
        foreach ($control['rows'] as $row) {
            $key=$row['polozka'];
            $rows[$key]=['left'=>cb_kt_halere($row['odevzdane']), 'right'=>cb_kt_halere($row['spocitane']), 'note'=>(string)$row['poznamka']];
            if (in_array($key,['trzba','stravenky'],true)) $sumRows[]=$row+['id_pob'=>$id,'id_kontrola'=>(int)$control['id_kontrola']];
        }
        if (array_diff_key(cb_kt_polozky(),$rows)!==[]) return null;
        $controls[]=['id'=>(int)$control['id_kontrola'],'branch'=>$id,'name'=>(string)$branch['nazev'],'rows'=>cb_kt_souhrn($rows)];
    }
    $sums=cb_kt_ulozene_soucty($sumRows);
    if ($sums['incomplete'] || $sums['duplicates']!==[] || count($sums['branches'])!==count($branches)) return null;
    return ['from'=>$from,'to'=>$to,'controls'=>$controls,'trzba'=>$sums['trzba'],'stravenky'=>$sums['stravenky']];
}

// Jméno i adresa pocházejí z aktuální HR karty; adresa účtu ani text od klienta se nepoužijí.
function cb_kt_email_kontakt(mysqli $db, int $id, bool $byUser=false): array
{
    $stmt=$db->prepare('SELECT p.id_person,o.jmeno,o.prijmeni,o.druhe_jmeno,o.titul_za,e.email FROM hr_person p
        JOIN ' . cb_osoby_zdroj_sql() . ' o ON o.id_person=p.id_person AND o.platny=1
        JOIN hr_email e ON e.id_person=p.id_person AND e.platny=1 AND e.hlavni=1
        WHERE p.aktivni=1 AND p.' . ($byUser ? 'id_user' : 'id_person') . '=?');
    $stmt->bind_param('i',$id); $stmt->execute();
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    if (count($rows)!==1 || !filter_var(trim((string)$rows[0]['email']),FILTER_VALIDATE_EMAIL)) {
        throw new CbUserVisibleException($byUser ? 'Na své HR kartě musíte mít jeden platný hlavní e-mail.' : 'Jan Hušner nemá v HR jednoznačný platný hlavní e-mail.');
    }
    return ['name'=>cb_osoba_jmeno($rows[0]), 'email'=>trim($rows[0]['email'])];
}
