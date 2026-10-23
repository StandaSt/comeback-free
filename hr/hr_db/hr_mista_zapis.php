<?php
declare(strict_types=1);

/* Ucel: Atomicky uklada kombinovany vyber oblasti a pobocek a mnozinu MAIN; chrani casove vazby na sloty a pozice. */
require_once __DIR__ . '/hr_zamestnanci_pristup.php';
require_once __DIR__ . '/hr_pracoviste_historie.php';
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';

// Po zmene overime kazdou hranici ucinnosti, ne jen dnesni stav; intervaly jsou uzavrene vcetne posledniho dne.
function hr_mista_over_casovou_osu(mysqli $db, int $idPerson, string $od): void
{
    $dates=[$od=>true];
    foreach (['hr_misto_vykonu_prace','hr_pracoviste_main','hr_slot','hr_funkce'] as $table) {
        $stmt=$db->prepare("SELECT platnost_od,platnost_do FROM $table WHERE id_person=? AND platny=1");
        $stmt->bind_param('i',$idPerson); $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            if (!empty($row['platnost_od']) && $row['platnost_od']>=$od) $dates[$row['platnost_od']]=true;
            if (!empty($row['platnost_do']) && $row['platnost_do']>=$od && $row['platnost_do']<'9999-12-31') {
                $dates[(new DateTimeImmutable($row['platnost_do']))->modify('+1 day')->format('Y-m-d')]=true;
            }
        }
        $stmt->close();
    }
    // Budouci presun pobocky mezi oblastmi je rovnez hranici pokryti MAIN.
    $result=$db->query('SELECT platnost_od,platnost_do FROM pobocka_oblast_prace WHERE platny=1 AND zruseno IS NULL');
    foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
        if ($row['platnost_od']>=$od) $dates[$row['platnost_od']]=true;
        if ($row['platnost_do']!==null && $row['platnost_do']>=$od && $row['platnost_do']<'9999-12-31') {
            $dates[(new DateTimeImmutable($row['platnost_do']))->modify('+1 day')->format('Y-m-d')]=true;
        }
    }
    $result->free(); ksort($dates);
    foreach (array_keys($dates) as $date) {
        $errors=cb_hr_zarazeni_chyby($db,$idPerson,$date);
        // Rozpracovana karta bez slotu i pozice musi jit doplnovat po blocich; rozpor existujicich vazeb nepovolime.
        $errors=array_values(array_diff($errors,['Chybí pracovní slot nebo manažerská pozice.']));
        if ($errors!==[]) throw new CbUserVisibleException('Chyba od '.(new DateTimeImmutable($date))->format('d.m.Y').': '.implode(' ',$errors));
    }
}

// Vstupni ID overujeme jako mnozinu, bez ztraty hodnot a bez vynuceni jedine MAIN.
function hr_mista_id(array $ids): array
{
    $out=[];
    foreach ($ids as $id) {
        if (!is_scalar($id) || !ctype_digit((string)$id) || (int)$id<=0) throw new CbUserVisibleException('Neplatný výběr místa výkonu práce.');
        $out[(int)$id]=(int)$id;
    }
    sort($out); return array_values($out);
}

// Oba druhy polozek overujeme nezavisle; prazdna cast vyberu nesmi vytvorit neplatne IN ().
function hr_mista_vyber_overit(mysqli $db, array $oblasti, array $pobocky, int $firma): void
{
    if ($oblasti===[] && $pobocky===[]) throw new CbUserVisibleException('Vyberte alespoň jednu oblast nebo pobočku.');
    foreach (['oblasti'=>$oblasti,'pobocky'=>$pobocky] as $typ=>$ids) {
        if ($ids===[]) continue;
        $idsSql=implode(',',$ids);
        $valid=$typ==='oblasti'
            ? $db->query("SELECT COUNT(*) FROM cis_oblast_prace WHERE aktivni=1 AND id_oblast_prace IN ($idsSql)")->fetch_row()[0]
            : $db->query("SELECT COUNT(*) FROM pobocka WHERE aktivni=1 AND id_firma=$firma AND id_pob IN ($idsSql)")->fetch_row()[0];
        if ((int)$valid!==count($ids)) throw new CbUserVisibleException('Vyberte pouze aktivní oblasti nebo pobočky zaměstnancovy firmy.');
    }
}

// Jedna polozka je oblast, nebo pobocka; hlavicka muze obsahovat oba druhy soucasne.
function hr_mista_polozky_vlozit(mysqli $db, int $hlavicka, array $oblasti, array $pobocky): void
{
    foreach (['id_oblast_prace'=>$oblasti,'id_pob'=>$pobocky] as $col=>$ids) {
        if ($ids===[]) continue;
        $stmt=$db->prepare("INSERT INTO hr_misto_vykonu_prace_polozka(id_hr_misto_vykonu_prace,$col) VALUES(?,?)");
        foreach ($ids as $id) { $stmt->bind_param('ii',$hlavicka,$id); $stmt->execute(); }
        $stmt->close();
    }
}

// Celou verzi mist menime jen pri zmene clenstvi; nezmeneny vyber zachova puvodni platnost.
function hr_mista_zmenit(mysqli $db, int $idPerson, array $oblasti, array $pobocky, array $main, string $od, string $mainOd, int $idUser): void
{
    hr_zamestnanci_vyzaduj_osobu($db,$idUser,$idPerson);
    $oblasti=hr_mista_id($oblasti); $pobocky=hr_mista_id($pobocky); $main=hr_mista_id($main);
    if ($oblasti===[] && $pobocky===[]) throw new CbUserVisibleException('Vyberte alespoň jednu oblast nebo pobočku.');
    $db->begin_transaction();
    try {
        cb_hr_osobu_zamkni($db,$idPerson);
        $company=(int)$db->query('SELECT id_firma FROM hr_person WHERE id_person='.(int)$idPerson)->fetch_row()[0];
        hr_mista_vyber_overit($db,$oblasti,$pobocky,$company);
        $stmt=$db->prepare('SELECT * FROM hr_misto_vykonu_prace WHERE id_person=? AND platny=1 AND zruseno IS NULL ORDER BY platnost_od FOR UPDATE');
        $stmt->bind_param('i',$idPerson); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $current=[];
        foreach ($rows as $row) if ($row['platnost_od']<=$od && ($row['platnost_do']===null || $row['platnost_do']>=$od)) $current[]=$row;
        if (count($current)>1) throw new CbUserVisibleException('Místa výkonu práce mají překrývající se verze.');
        $old=$current[0]??null; $oldOblasti=[]; $oldPobocky=[];
        if ($old) {
            $r=$db->query('SELECT id_oblast_prace,id_pob FROM hr_misto_vykonu_prace_polozka WHERE id_hr_misto_vykonu_prace='.(int)$old['id_hr_misto_vykonu_prace']);
            foreach ($r->fetch_all(MYSQLI_ASSOC) as $i) {
                if ($i['id_oblast_prace']!==null) $oldOblasti[]=(int)$i['id_oblast_prace'];
                if ($i['id_pob']!==null) $oldPobocky[]=(int)$i['id_pob'];
            }
            $r->free(); sort($oldOblasti); sort($oldPobocky);
        }
        if (!$old || $oldOblasti!==$oblasti || $oldPobocky!==$pobocky) {
            foreach ($rows as $r) if ($r['platnost_od']>$od || ($r['platnost_do']!==null && $r['platnost_do']>=$od)) {
                throw new CbUserVisibleException('Po zadaném datu již existuje plánovaná změna míst. Nejprve upravte plán.');
            }
            if ($old) {
                $key=(int)$old['id_hr_misto_vykonu_prace'];
                if ($old['platnost_od']===$od) $db->query("UPDATE hr_misto_vykonu_prace SET platny=0,zruseno=NOW() WHERE id_hr_misto_vykonu_prace=$key");
                else {
                    $pred=(new DateTimeImmutable($od))->modify('-1 day')->format('Y-m-d');
                    $stmt=$db->prepare('UPDATE hr_misto_vykonu_prace SET platnost_do=? WHERE id_hr_misto_vykonu_prace=?');
                    $stmt->bind_param('si',$pred,$key); $stmt->execute(); $stmt->close();
                }
            }
            $stmt=$db->prepare('INSERT INTO hr_misto_vykonu_prace(id_person,platnost_od,id_user_zadal) VALUES(?,?,?)');
            $stmt->bind_param('isi',$idPerson,$od,$idUser); $stmt->execute(); $key=(int)$db->insert_id; $stmt->close();
            hr_mista_polozky_vlozit($db,$key,$oblasti,$pobocky);
        }
        $stmt=$db->prepare('SELECT id_main AS id_pracoviste,id_pob,platnost_od,platnost_do,platny,zruseno FROM hr_pracoviste_main WHERE id_person=? FOR UPDATE');
        $stmt->bind_param('i',$idPerson); $stmt->execute(); $mainRows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $diff=hr_pracoviste_rozdily($mainRows,$main,0,$mainOd);
        $pred=(new DateTimeImmutable($mainOd))->modify('-1 day')->format('Y-m-d');
        foreach ($diff['ukoncit'] as $key) {
            $stmt=$db->prepare('UPDATE hr_pracoviste_main SET platnost_do=? WHERE id_main=?');
            $stmt->bind_param('si',$pred,$key); $stmt->execute(); $stmt->close();
        }
        foreach ($diff['zrusit'] as $key) $db->query('UPDATE hr_pracoviste_main SET platny=0,zruseno=NOW() WHERE id_main='.(int)$key);
        foreach ($diff['pridat'] as $branch=>$unused) cb_hr_main_vlozit($db,$idPerson,(int)$branch,$mainOd,$idUser);
        hr_mista_over_casovou_osu($db,$idPerson,min($od,$mainOd));
        hr_update_employee_completeness($db,$idPerson);
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
