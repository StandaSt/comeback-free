<?php
declare(strict_types=1);

/* Ucel: Zaklada aktivni oblasti, meni pouze jejich stav a spravuje casove prirazeni pobocek s ochranou existujicich vazeb. */
require_once __DIR__ . '/hr_mista_zapis.php';

// Nazev existujici oblasti je nemenny; novou zakladame aktivni a pouzivanou nelze deaktivovat.
function hr_oblast_ulozit(mysqli $db, int $id, string $nazev, bool $aktivni): void
{
    if (!cb_hr_nastaveni_ma_pravo()) throw new CbUserVisibleException('Nemáte právo spravovat oblasti.');
    $nazev=trim($nazev);
    if ($id<0 || ($id===0 && ($nazev==='' || mb_strlen($nazev)>100))) throw new CbUserVisibleException('Zadejte platný název oblasti.');
    $db->begin_transaction();
    try {
        // Stejny zamek serializuje deaktivaci oblasti s presunem pobocky a zapisovaci kontrolou osob.
        $db->query('SELECT id_oblast_prace FROM cis_oblast_prace ORDER BY id_oblast_prace FOR UPDATE')->free();
        if ($id>0) {
            $exists=$db->query('SELECT id_oblast_prace FROM cis_oblast_prace WHERE id_oblast_prace='.(int)$id)->num_rows===1;
            if (!$exists) throw new CbUserVisibleException('Oblast nebyla nalezena.');
            if (!$aktivni) {
                $used=$db->query("SELECT 1 FROM pobocka_oblast_prace a WHERE a.id_oblast_prace=$id AND a.platny=1 AND a.zruseno IS NULL AND (a.platnost_do IS NULL OR a.platnost_do>=CURRENT_DATE())
                    UNION ALL SELECT 1 FROM hr_misto_vykonu_prace_polozka i JOIN hr_misto_vykonu_prace h USING(id_hr_misto_vykonu_prace)
                    WHERE i.id_oblast_prace=$id AND h.platny=1 AND h.zruseno IS NULL AND (h.platnost_do IS NULL OR h.platnost_do>=CURRENT_DATE()) LIMIT 1")->num_rows>0;
                if ($used) throw new CbUserVisibleException('Používanou oblast nelze deaktivovat.');
            }
            $state=(int)$aktivni;
            $stmt=$db->prepare('UPDATE cis_oblast_prace SET aktivni=? WHERE id_oblast_prace=?');
            $stmt->bind_param('ii',$state,$id);
        } else {
            // Kod je trvaly technicky identifikator; nova oblast je aktivni bez ohledu na vstup formulare.
            $kod='oblast_'.bin2hex(random_bytes(8));
            $stmt=$db->prepare('INSERT INTO cis_oblast_prace(kod,nazev,aktivni) VALUES(?,?,1)'); $stmt->bind_param('ss',$kod,$nazev);
        }
        $stmt->execute(); $stmt->close(); $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}

// Presun pobocky je nova casova verze; nelze jim zneplatnit MAIN lidi s oblastnim mistem vykonu prace.
function hr_pobocka_oblast_ulozit(mysqli $db, int $branch, int $region, string $od, int $idUser): void
{
    if (!cb_hr_nastaveni_ma_pravo()) throw new CbUserVisibleException('Nemáte právo spravovat oblasti.');
    $firms=implode(',',array_map('intval',cb_firemni_pristup_firmy($db,$idUser)))?:'0';
    $db->begin_transaction();
    try {
        $db->query('SELECT id_oblast_prace FROM cis_oblast_prace ORDER BY id_oblast_prace FOR UPDATE')->free();
        $stmt=$db->prepare("SELECT id_pob FROM pobocka WHERE id_pob=? AND aktivni=1 AND id_firma IN ($firms) FOR UPDATE");
        $stmt->bind_param('i',$branch); $stmt->execute(); $ok=$stmt->get_result()->num_rows===1; $stmt->close();
        if (!$ok) throw new CbUserVisibleException('Pobočka není v povoleném firemním rozsahu.');
        if ($db->query('SELECT 1 FROM cis_oblast_prace WHERE aktivni=1 AND id_oblast_prace='.(int)$region)->num_rows!==1) throw new CbUserVisibleException('Vyberte aktivní oblast.');
        $stmt=$db->prepare('SELECT * FROM pobocka_oblast_prace WHERE id_pob=? AND platny=1 AND zruseno IS NULL ORDER BY platnost_od FOR UPDATE');
        $stmt->bind_param('i',$branch); $stmt->execute(); $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $current=null;
        foreach ($rows as $row) {
            if ($row['platnost_od']>$od || ($row['platnost_do']!==null && $row['platnost_do']>=$od)) throw new CbUserVisibleException('Pobočka má již naplánovanou změnu oblasti.');
            if ($row['platnost_od']<=$od && ($row['platnost_do']===null || $row['platnost_do']>=$od)) {
                if ($current!==null) throw new CbUserVisibleException('Oblasti pobočky mají překrývající se intervaly.');
                $current=$row;
            }
        }
        if ($current && (int)$current['id_oblast_prace']===$region) { $db->commit(); return; }
        if ($current) {
            $key=(int)$current['id_pobocka_oblast_prace'];
            if ($current['platnost_od']===$od) $db->query("UPDATE pobocka_oblast_prace SET platny=0,zruseno=NOW() WHERE id_pobocka_oblast_prace=$key");
            else {
                $pred=(new DateTimeImmutable($od))->modify('-1 day')->format('Y-m-d');
                $stmt=$db->prepare('UPDATE pobocka_oblast_prace SET platnost_do=? WHERE id_pobocka_oblast_prace=?'); $stmt->bind_param('si',$pred,$key); $stmt->execute(); $stmt->close();
            }
        }
        $stmt=$db->prepare('INSERT INTO pobocka_oblast_prace(id_pob,id_oblast_prace,platnost_od,id_user_zadal) VALUES(?,?,?,?)');
        $stmt->bind_param('iisi',$branch,$region,$od,$idUser); $stmt->execute(); $stmt->close();
        // Uzavrena historie a neaktivni osoby nesmeji blokovat dnesni zmenu oblasti pobocky.
        $stmt=$db->prepare('SELECT DISTINCT m.id_person FROM hr_pracoviste_main m JOIN hr_person p ON p.id_person=m.id_person
            WHERE m.id_pob=? AND m.platny=1 AND m.zruseno IS NULL AND p.aktivni=1 AND p.testovaci=0
            AND (m.platnost_do IS NULL OR m.platnost_do>=?)');
        $stmt->bind_param('is',$branch,$od); $stmt->execute(); $people=$stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        foreach ($people as $person) { cb_hr_osobu_zamkni($db,(int)$person['id_person']); hr_mista_over_casovou_osu($db,(int)$person['id_person'],$od); }
        $db->commit();
    } catch (Throwable $e) { $db->rollback(); throw $e; }
}
