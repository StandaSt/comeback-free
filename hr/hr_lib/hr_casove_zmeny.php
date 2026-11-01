<?php
declare(strict_types=1);
/* Sdileny zapis casovych verzi a prepocet dnesni role, aktivity a kompletnosti HR.
 * Volajici drzi transakci a zamek osoby; cron i formular pouzivaji totozne funkce.
 */
require_once __DIR__.'/../../common/lib/hr_casova_platnost.php';
require_once __DIR__.'/../hr_db/zamestnanci.php';
require_once __DIR__.'/../hr_db/hr_mista_zapis.php';

/** Zastavi zapis pred migraci, ktera umozni soucasnou a budouci funkci jedne osoby. */
function hr_casove_schema_vyzaduj(mysqli $db): void
{
    static $ready;
    $ready ??= new WeakMap();
    if (isset($ready[$db])) return;
    $indexes=$db->query('SHOW INDEX FROM hr_funkce')->fetch_all(MYSQLI_ASSOC);
    foreach ($indexes as $index) if ($index['Key_name']==='uq_hr_funkce_jedna_platna') {
        throw new RuntimeException('Nejprve proveďte připravenou migraci časové platnosti HR.');
    }
    if ($db->query("SHOW COLUMNS FROM hr_person LIKE 'casova_aktivace_od'")->num_rows!==1) {
        throw new RuntimeException('Chybí migrace plánovaného nástupu HR.');
    }
    $ready[$db]=true;
}

/** Uzavre puvodni interval, nebo zrusi opravu ke stejnemu dni; historii nemaze. */
function hr_casovy_interval_uzavrit(mysqli $db, string $table, string $pk, array $plan, ?int $actor): void
{
    $allowed=['hr_funkce'=>'id_hr_funkce','hr_pracovni_vztah'=>'id_pracovni_vztah',
        'hr_pracovni_uvazek'=>'id_pracovni_uvazek','hr_mzda'=>'id_mzda','hr_benefit'=>'id_benefit'];
    if (($allowed[$table]??null)!==$pk) throw new InvalidArgumentException('Nepovolená časová tabulka.');
    if ($plan['id']===null) return;
    if ($plan['storno']) {
        $maAutora=in_array($table,['hr_pracovni_uvazek','hr_mzda','hr_benefit'],true);
        $sql="UPDATE $table SET platny=0".($table==='hr_funkce'?'':',zruseno=NOW()')
            .($maAutora?',id_user_zrusil=?':'')." WHERE $pk=?";
        $stmt=$db->prepare($sql);
        if ($maAutora) $stmt->bind_param('ii',$actor,$plan['id']); else $stmt->bind_param('i',$plan['id']);
    } else {
        $column=$table==='hr_pracovni_vztah'?'datum_ukonceni':'platnost_do';
        $stmt=$db->prepare("UPDATE $table SET $column=? WHERE $pk=?");
        $stmt->bind_param('si',$plan['pred'],$plan['id']);
    }
    $stmt->execute(); $stmt->close();
}

/** Nacte zamcene verze jedne vazby; samostatne benefity maji vlastni casovou osu. */
function hr_casove_verze(mysqli $db, string $table, int $id, ?int $benefit = null): array
{
    $keys=['hr_funkce'=>'id_person','hr_pracovni_vztah'=>'id_person','hr_pracovni_uvazek'=>'id_pracovni_vztah',
        'hr_mzda'=>'id_pracovni_vztah','hr_benefit'=>'id_pracovni_vztah'];
    if (!isset($keys[$table])) throw new InvalidArgumentException('Nepovolená časová tabulka.');
    $stmt=$db->prepare("SELECT * FROM $table WHERE {$keys[$table]}=?".($benefit!==null?' AND id_cis_benefit=?':'').' FOR UPDATE');
    if ($benefit===null) $stmt->bind_param('i',$id); else $stmt->bind_param('ii',$id,$benefit);
    $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); return $rows;
}

/** Zachova specialni profily a aktualizuje pouze skutecne zmenenou odvozenou roli. */
function hr_pristupovy_profil_synchronizovat(mysqli $db, int $idPerson, ?int $actor): int
{
    $stmt=$db->prepare('SELECT p.id_user,r.id_role FROM hr_person p LEFT JOIN hr_pristupovy_profil r ON r.id_person=p.id_person WHERE p.id_person=? FOR UPDATE');
    $stmt->bind_param('i',$idPerson); $stmt->execute(); $p=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$p) throw new RuntimeException('Zaměstnanec nebyl nalezen.');
    $fixed=match((int)$p['id_user']){1=>1,57=>2,default=>null};
    $role=$fixed ?? ((int)$p['id_role']===4 ? 4 : (int)(cb_hr_funkce_osoby($db,$idPerson)['id_role']??9));
    if ((int)$p['id_role']===$role) return $role;
    $stmt=$db->prepare('INSERT INTO hr_pristupovy_profil(id_person,id_role,sub_role,id_person_zadal) VALUES(?,?,NULL,?) ON DUPLICATE KEY UPDATE id_role=VALUES(id_role),sub_role=NULL,id_person_zadal=VALUES(id_person_zadal)');
    $stmt->bind_param('iii',$idPerson,$role,$actor); $stmt->execute(); $stmt->close(); return $role;
}

/** Naplanovany nastup nikdy neozivi jinou, rucne vypnutou osobu bez vlastniho priznaku. */
function hr_casova_aktivace_naplanovat(mysqli $db, int $idPerson, string $datum): void
{
    hr_casove_schema_vyzaduj($db); cb_hr_den($datum);
    // Dalsi pozdejsi nastup nesmi odsunout jiz naplanovany drivejsi nastup.
    $stmt=$db->prepare('UPDATE hr_person SET casova_aktivace_od=LEAST(COALESCE(casova_aktivace_od,?),?) WHERE id_person=?');
    $stmt->bind_param('ssi',$datum,$datum,$idPerson); $stmt->execute(); $stmt->close();
}

/** Prepocet je soucasti transakce volajiciho; neprepisuje historicke funkce ani mzdy. */
function hr_casovy_stav_obnovit(mysqli $db, int $idPerson, ?int $actor): void
{
    hr_casove_schema_vyzaduj($db);
    require_once __DIR__.'/../../common/lib/hr_user_sync.php';
    // Aktivujeme pouze vyslovne naplanovany nastup, jehoz vztah dnes opravdu trva.
    $stmt=$db->prepare('UPDATE hr_person p SET aktivni=1,casova_aktivace_od=NULL WHERE p.id_person=? AND p.casova_aktivace_od<=CURRENT_DATE()
        AND EXISTS(SELECT 1 FROM hr_pracovni_vztah v WHERE v.id_person=p.id_person AND v.platny=1 AND v.zruseno IS NULL
        AND v.datum_nastupu<=CURRENT_DATE() AND (v.datum_ukonceni IS NULL OR v.datum_ukonceni>=CURRENT_DATE()))');
    $stmt->bind_param('i',$idPerson); $stmt->execute(); $stmt->close();
    cb_hr_user_sync_ukoncene($db,$idPerson);
    hr_pristupovy_profil_synchronizovat($db,$idPerson,$actor);
    hr_update_employee_completeness($db,$idPerson);
}

/** Minimalni provozni snimek pro porovnani a audit; neobsahuje mzdy ani osobni udaje. */
function hr_casovy_stav_snimnout(mysqli $db, int $idPerson): array
{
    $stmt=$db->prepare('SELECT p.aktivni,p.kompletni,r.id_role FROM hr_person p LEFT JOIN hr_pristupovy_profil r ON r.id_person=p.id_person WHERE p.id_person=?');
    $stmt->bind_param('i',$idPerson); $stmt->execute(); $row=$stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) throw new RuntimeException('Osoba přepočtu nebyla nalezena.');
    return $row;
}

/** Systemovy autor 0 je odlisny od personalisty; audit se potvrdi s prepocitanou osobou. */
function hr_casovy_audit(mysqli $db, int $idPerson, array $before, array $after): void
{
    $old=json_encode($before,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $new=json_encode($after,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $detail=json_encode(['zdroj'=>'hr_casove_zmeny','puvod'=>'automaticky','id_person'=>$idPerson,
        'platnost_od'=>cb_hr_den(),'popis'=>'Uplatnění časových změn HR'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $stmt=$db->prepare("INSERT INTO user_akce_new(id_user,id_modul,id_user_akce_typ,objekt,id_objektu,pole,hodnota_old,hodnota_new,detail_json,vysledek) VALUES(0,3,14,'hr_person',?,'casovy_stav',?,?,?,1)");
    $stmt->bind_param('isss',$idPerson,$old,$new,$detail); $stmt->execute(); $stmt->close();
}
