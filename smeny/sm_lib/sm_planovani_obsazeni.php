<?php
declare(strict_types=1);

// Ucel rozsahu: Kandidaty urcuji mista vykonu prace; vypomoc rozsiri oblasti, nikoli prava planovace.
require_once __DIR__ . '/../../common/lib/hr_pracoviste_main.php';
/* Účel: Nabízí kvalifikované netestovací pracovníky ve skupinách podle MAIN a požadavku; chrání zápis do sloupců i kolize napříč pobočkami. */

/** Kandidati maji slot a alespon jednu MAIN; bezna nabidka vychazi z pokryti planovane pobocky mistem prace. */
function cb_smeny_planovani_kandidati(mysqli $db, array $plan, string $date, int $idSlot): array
{
    $slotTable = cb_hr_schema_table($db, 'slot');
    $day = (int)(new DateTimeImmutable($date))->format('N');
    $closingColumn = [1=>'end_po',2=>'end_ut',3=>'end_st',4=>'end_ct',5=>'end_pa',6=>'end_so',7=>'end_ne'][$day];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)!==1) throw new InvalidArgumentException('Neplatné datum směny.');
    $branch=(int)$plan['id_pob'];
    $places=cb_hr_mista_sql();
    $sql = 'SELECT hp.id_person,TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) jmeno,
        pr.pocet ma_main,pr.nazvy hlavni_pobocka,
        EXISTS(SELECT 1 FROM ' . $places . ' misto WHERE misto.id_person=hp.id_person AND misto.id_pob=' . $branch . '
            AND ' . cb_hr_ucinne_sql('misto',"'".$date."'") . ') je_v_oblasti,
        EXISTS(SELECT 1 FROM ' . cb_hr_main_sql($db) . ' main WHERE main.id_person=hp.id_person AND main.id_pob=' . $branch . '
            AND ' . cb_hr_ucinne_sql('main',"'".$date."'") . ') je_hlavni_pobocka,
        EXISTS(SELECT 1 FROM hr_pracovni_vztah pv WHERE pv.id_person=hp.id_person AND pv.id_pracovni_vztah_typ=1 AND pv.platny=1 AND (pv.datum_nastupu IS NULL OR pv.datum_nastupu<=?) AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni>=?)) je_hpp,
        sp.rezim,sp.volno_datum,TIME_FORMAT(pd.cas_od,"%H:%i") pozadavek_od,TIME_FORMAT(pd.cas_do,"%H:%i") pozadavek_do,
        TIME_FORMAT(p.' . $closingColumn . ',"%H:%i") zavira,
        COALESCE((SELECT ROUND(SUM(TIMESTAMPDIFF(MINUTE,s.zacatek,s.konec))/60,1) FROM smeny_smena s JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis WHERE s.id_person=hp.id_person AND r.tyden_od=? AND s.verze=COALESCE(r.pracovni_verze,r.zverejnena_verze)),0) hodin_tyden
        FROM hr_person hp
        LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(o.id_osobni_udaje) FROM hr_osobni_udaje o WHERE o.id_person=hp.id_person AND o.platny=1)
        JOIN (SELECT m.id_person,COUNT(DISTINCT m.id_pob) pocet,GROUP_CONCAT(DISTINCT b.nazev ORDER BY b.nazev SEPARATOR ", ") nazvy
            FROM hr_pracoviste_main m JOIN hr_person osoba ON osoba.id_person=m.id_person
            JOIN pobocka b ON b.id_pob=m.id_pob AND b.id_firma=osoba.id_firma AND b.aktivni=1
            WHERE ' . cb_hr_ucinne_sql('m','?') . ' GROUP BY m.id_person) pr ON pr.id_person=hp.id_person
        JOIN pobocka p ON p.id_pob=' . $branch . '
        LEFT JOIN smeny_pozadavek sp ON sp.id_person=hp.id_person AND sp.tyden_od=?
        LEFT JOIN smeny_pozadavek_den pd ON pd.id_smeny_pozadavek=sp.id_smeny_pozadavek AND pd.den_tydne=?
        WHERE pr.pocet>0 AND hp.aktivni=1 AND hp.testovaci=0 AND hp.id_firma=? AND EXISTS(SELECT 1 FROM ' . $slotTable . ' z WHERE z.id_person=hp.id_person AND z.id_slot=? AND z.platny=1 AND z.zruseno IS NULL AND (z.platnost_od IS NULL OR z.platnost_od<=?) AND (z.platnost_do IS NULL OR z.platnost_do>=?))';
    $stmt = cb_smeny_planovani_sql($db,$sql,'ssssssiiiss',[$date,$date,$plan['tyden_od'],$date,$date,$plan['tyden_od'],$day,(int)$plan['id_firma'],$idSlot,$date,$date]);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    foreach ($rows as &$row) {
        $row['id_person'] = (int)$row['id_person'];
        $row['je_hpp'] = (int)$row['je_hpp'];
        $row['je_v_oblasti']=(int)$row['je_v_oblasti'];
        $row['je_hlavni_pobocka']=(int)$row['je_hlavni_pobocka'];
        if (cb_hr_zarazeni_chyby($db,$row['id_person'],$date)!==[]) { $row['ma_main']=0; continue; }
        $row['oblast']=cb_hr_mista_popis($db,$row['id_person'],$date);
        if (!$row['je_v_oblasti']) $row['jmeno'].=' — '.$row['oblast'];
        $row['ma_volno'] = (string)$row['volno_datum'] === $date ? 1 : 0;
        if ($row['rezim'] === 'kdykoliv') {
            $row['pozadavek_od'] = $row['zavira'] ? '10:00' : null;
            $row['pozadavek_do'] = $row['zavira'];
        }
        $row['podle_pozadavku'] = $row['je_hpp'] === 1 ? $row['ma_volno'] === 0 : !empty($row['pozadavek_od']);
        $row['hodin_tyden'] = (float)$row['hodin_tyden'];
    }
    unset($row);
    $rows=array_values(array_filter($rows,static fn($r)=>(int)$r['ma_main']>0));
    usort($rows,static fn(array $a,array $b):int => ($b['je_v_oblasti'] <=> $a['je_v_oblasti']) ?: ((int)$b['podle_pozadavku'] <=> (int)$a['podle_pozadavku']) ?: strcoll($a['jmeno'],$b['jmeno']));
    return $rows;
}

/** Časovou osu provozního dne převede na skutečná data; noční konec má následující datum. */
function cb_smeny_planovani_interval(array $branch, array $week, array $post): array
{
    $date = (string)($post['datum'] ?? '');
    $idSlot = (int)($post['id_slot'] ?? 0);
    $dates = array_column($week['days'],'date');
    $day = array_search($date,$dates,true);
    if ($day === false || !isset(cb_smeny_sablony_pozice($branch)[$idSlot])) throw new CbUserVisibleException('Den nebo pracovní pozice nepatří k tomuto rozpisu.');
    $closing = cb_smeny_sablony_zaviraci_cas($branch,$day+1);
    if ($closing === '') throw new CbUserVisibleException('Pobočka nemá pro tento den nastavený zavírací čas.');
    $from = cb_smeny_sablony_cas_overit((string)($post['cas_od'] ?? ''),false,$closing);
    $to = cb_smeny_sablony_cas_overit((string)($post['cas_do'] ?? ''),true,$closing);
    if ($from < 600 || $to <= $from || $to > cb_smeny_sablony_cas_minuty($closing,true)) throw new CbUserVisibleException('Směna musí začínat nejdříve v 10:00, končit později než začíná a nepřekročit zavírací dobu.');
    $base = new DateTimeImmutable($date,new DateTimeZone('Europe/Prague'));
    $stamp = static fn(int $minute):string => $base->modify('+' . intdiv($minute,1440) . ' days')->format('Y-m-d') . sprintf(' %02d:%02d:00',intdiv($minute%1440,60),$minute%60);
    return ['datum'=>$date,'id_slot'=>$idSlot,'od'=>$from,'do'=>$to,'zacatek'=>$stamp($from),'konec'=>$stamp($to)];
}

/** Nabídku jiné MAIN rozšiřuje jen výslovná volba; kontrola platí i pro přímý POST. */
function cb_smeny_planovani_main_overit(array $candidate, bool $ignoreBranch): void
{
    if ((int)$candidate['ma_main'] <= 0) throw new CbUserVisibleException('Pracovník nemá platnou hlavní pobočku. Nejprve ji nastavte v HR.');
    if (empty($candidate['je_v_oblasti']) && !$ignoreBranch) {
        throw new CbUserVisibleException('Místo výkonu práce pracovníka nepokrývá tuto pobočku. Použijte volbu Bez ohledu na oblast.');
    }
}

/** Požadavek je pouze řadicí údaj; odpovídá, pokud pokrývá celý vybraný interval. */
function cb_smeny_planovani_pozadavek_odpovida(array $candidate, array $interval): bool
{
    if((int)$candidate['je_hpp']===1) return empty($candidate['ma_volno']);
    if(empty($candidate['pozadavek_od']) || empty($candidate['pozadavek_do'])) return false;
    return cb_smeny_sablony_cas_minuty((string)$candidate['pozadavek_od'],false)<=$interval['od']
        && cb_smeny_sablony_cas_minuty((string)$candidate['pozadavek_do'],true)>=$interval['do'];
}

/** Stejná volba interních verzí chrání nabídku i zápis; vlastní publikaci nahrazuje pracovní kopie. */
function cb_smeny_planovani_interni_verze_sql(): string
{
    return '((r.id_smeny_rozpis=? AND s.verze=COALESCE(r.pracovni_verze,r.zverejnena_verze) AND s.klic_smeny<>?) OR (r.id_smeny_rozpis<>? AND (s.verze=r.pracovni_verze OR s.verze=r.zverejnena_verze)))';
}

/** Externí směny chrání jen týdny bez interní publikace; nahrazovaný týden plánované pobočky se vynechá. */
function cb_smeny_planovani_externi_verze_sql(): string
{
    return 'NOT(e.id_pob=? AND e.start_day=?) AND NOT EXISTS(SELECT 1 FROM smeny_rozpis r WHERE r.id_pob=e.id_pob AND r.tyden_od=e.start_day AND r.zverejnena_verze IS NOT NULL)';
}

/** Dva hromadné čtecí dotazy vrátí obsazení jen nabízených osob; slot ani pobočka kolizi neomezují. */
function cb_smeny_planovani_obsazene_intervaly(mysqli $db, array $plan, array $personIds): array
{
    $personIds = array_values(array_unique(array_filter(array_map('intval',$personIds),static fn(int $id):bool=>$id>0)));
    if ($personIds === []) return [];
    $ids = implode(',',$personIds);
    $start = $plan['tyden_od'].' 00:00:00';
    $end = (new DateTimeImmutable($plan['tyden_od']))->modify('+7 days')->format('Y-m-d').' 06:00:00';
    $idPlan = (int)$plan['id_smeny_rozpis'];
    $stmt = cb_smeny_planovani_sql($db,'SELECT s.id_person,s.zacatek,s.konec FROM smeny_smena s JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis WHERE s.id_person IN ('.$ids.') AND s.zacatek<? AND s.konec>? AND '.cb_smeny_planovani_interni_verze_sql(),'ssisi',[$end,$start,$idPlan,'',$idPlan]);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    $stmt = cb_smeny_planovani_sql($db,'SELECT hp.id_person,TIMESTAMP(e.datum,e.cas_od) zacatek,TIMESTAMP(DATE_ADD(e.datum,INTERVAL (e.cas_do<=e.cas_od) DAY),e.cas_do) konec FROM smeny_plan e JOIN hr_person hp ON hp.id_user=e.id_user WHERE hp.id_person IN ('.$ids.') AND e.zdroj=1 AND e.datum BETWEEN DATE_SUB(DATE(?),INTERVAL 1 DAY) AND DATE(?) AND TIMESTAMP(e.datum,e.cas_od)<? AND TIMESTAMP(DATE_ADD(e.datum,INTERVAL (e.cas_do<=e.cas_od) DAY),e.cas_do)>? AND '.cb_smeny_planovani_externi_verze_sql(),'ssssis',[$start,$end,$end,$start,(int)$plan['id_pob'],$plan['tyden_od']]);
    $rows = array_merge($rows,$stmt->get_result()->fetch_all(MYSQLI_ASSOC)); $stmt->close();
    $busy = [];
    foreach ($rows as $row) $busy[(int)$row['id_person']][$row['zacatek'].'|'.$row['konec']] = ['od'=>$row['zacatek'],'do'=>$row['konec']];
    foreach ($busy as &$intervals) $intervals = array_values($intervals);
    unset($intervals);
    return $busy;
}

/** Po zamčení osoby zkontroluje skutečné intervaly i přes půlnoc a hranici týdne. */
function cb_smeny_planovani_kolize(mysqli $db, array $plan, int $idPerson, string $start, string $end, string $exceptKey = ''): void
{
    $idPlan = (int)$plan['id_smeny_rozpis'];
    // Na jiných pobočkách chráníme také dosud platnou publikaci, i když se tam už upravuje kopie.
    $stmt = cb_smeny_planovani_sql($db, 'SELECT s.id_smeny_smena FROM smeny_smena s JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis WHERE s.id_person=? AND s.zacatek<? AND s.konec>? AND '.cb_smeny_planovani_interni_verze_sql().' LIMIT 1 FOR UPDATE', 'issisi',[$idPerson,$end,$start,$idPlan,$exceptKey,$idPlan]);
    $collision = $stmt->get_result()->fetch_assoc() !== null; $stmt->close();
    if ($collision) throw new CbUserVisibleException('Pracovník už má v tomto čase jinou směnu v IS.');
    // Externí zdroj se bere pouze bez publikovaného interního týdne; právě nahrazovaný týden této pobočky vynecháme.
    $stmt = cb_smeny_planovani_sql($db, 'SELECT e.id_user FROM smeny_plan e JOIN hr_person hp ON hp.id_user=e.id_user WHERE hp.id_person=? AND e.zdroj=1 AND e.datum BETWEEN DATE_SUB(DATE(?),INTERVAL 1 DAY) AND DATE(?) AND TIMESTAMP(e.datum,e.cas_od)<? AND TIMESTAMP(DATE_ADD(e.datum,INTERVAL (e.cas_do<=e.cas_od) DAY),e.cas_do)>? AND '.cb_smeny_planovani_externi_verze_sql().' LIMIT 1', 'issssis',[$idPerson,$start,$end,$end,$start,(int)$plan['id_pob'],$plan['tyden_od']]);
    $collision = $stmt->get_result()->fetch_assoc() !== null; $stmt->close();
    if ($collision) throw new CbUserVisibleException('Pracovník má v tomto čase externě naplánovanou směnu na jiné pobočce nebo v sousedním týdnu.');
}

/** Zápis jedné směny patří do transakce dispatcheru a mění výhradně pracovní verzi. */
function cb_smeny_planovani_priradit(mysqli $db, array $plan, array $branch, array $week, array $post, int $plannerId): void
{
    if ($plan['stav'] !== 'rozpracovany') throw new CbUserVisibleException('Nejprve otevřete týden k úpravám.');
    $interval = cb_smeny_planovani_interval($branch,$week,$post);
    $columnKey=(string)($post['klic_slotu'] ?? '');
    cb_smeny_planovani_slot_overit($plan,$columnKey,$interval['datum'],$interval['id_slot']);
    $idPerson = (int)($post['id_person'] ?? 0);
    $editing = ($post['action'] ?? '') === 'smeny_plan_zmenit';
    $oldShift = null;
    if ($editing) {
        foreach ($plan['blocks'] as $row) if ((int)$row['id_smeny_smena'] === (int)($post['id_smeny_smena'] ?? 0)) $oldShift = $row;
        if ($oldShift === null || (int)$oldShift['id_person'] !== $idPerson) throw new CbUserVisibleException('Upravovaná směna není dostupná. Pro změnu pracovníka směnu odeberte a přidělte znovu.');
    }
    // Rodičovská osoba serializuje plánování stejného člověka ze dvou různých poboček.
    $stmt = cb_smeny_planovani_sql($db, 'SELECT id_person FROM hr_person WHERE id_person=? AND aktivni=1 FOR UPDATE', 'i',[$idPerson]);
    $active = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($active === null) throw new CbUserVisibleException('Pracovník již není aktivní v HR.');
    $candidate = null;
    foreach (cb_smeny_planovani_kandidati($db,$plan,$interval['datum'],$interval['id_slot']) as $row) if ($row['id_person'] === $idPerson) $candidate = $row;
    if ($candidate === null) throw new CbUserVisibleException('Pracovník nemá pro tento den platný slot, MAIN nebo bezchybné zařazení v HR.');
    // Rozsireni nabidky mimo misto vykonu prace vyzaduje vyslovnou volbu i pri primem POST.
    cb_smeny_planovani_main_overit($candidate, !empty($plan['bez_ohledu_na_pobocku']) || $editing);
    // Dostupnost podle požadavku řadí nabídku, ale neomezuje zápis. Auditní příznak popisuje skutečný interval.
    $ignore = !cb_smeny_planovani_pozadavek_odpovida($candidate,$interval);
    foreach($plan['blocks'] as $row) if(($row['klic_slotu'] ?? '')===$columnKey && (int)$row['id_smeny_smena']!==(int)($oldShift['id_smeny_smena'] ?? 0) && $row['zacatek']<$interval['konec'] && $interval['zacatek']<$row['konec']) throw new CbUserVisibleException('V tomto sloupci už je pro zvolený čas naplánovaná směna. Přidejte další sloupec.');
    $key = $oldShift['klic_smeny'] ?? bin2hex(random_bytes(16));
    cb_smeny_planovani_kolize($db,$plan,$idPerson,$interval['zacatek'],$interval['konec'],$editing?$key:'');
    if ($editing) {
        cb_smeny_planovani_sql($db,'UPDATE smeny_smena SET id_slot=?,klic_slotu=?,zacatek=?,konec=?,bez_ohledu_na_pozadavky=?,ulozil_id_person=?,ulozeno=NOW() WHERE id_smeny_smena=? AND id_smeny_rozpis=? AND verze=?','isssiiiii',[$interval['id_slot'],$columnKey,$interval['zacatek'],$interval['konec'],(int)$ignore,$plannerId,(int)$oldShift['id_smeny_smena'],(int)$plan['id_smeny_rozpis'],(int)$plan['pracovni_verze']])->close();
    } else {
        cb_smeny_planovani_sql($db, 'INSERT INTO smeny_smena (id_smeny_rozpis,verze,klic_smeny,klic_slotu,id_person,id_slot,zacatek,konec,bez_ohledu_na_pozadavky,ulozil_id_person) VALUES (?,?,?,?,?,?,?,?,?,?)', 'iissiissii',[(int)$plan['id_smeny_rozpis'],(int)$plan['pracovni_verze'],$key,$columnKey,$idPerson,$interval['id_slot'],$interval['zacatek'],$interval['konec'],(int)$ignore,$plannerId])->close();
    }
    // ID nově vložené směny zachytíme před insertem auditního záznamu.
    $savedShiftId=$editing?(int)$oldShift['id_smeny_smena']:(int)$db->insert_id;
    cb_smeny_audit_zapis($db,$plannerId,$editing?'zmenena_smena':'pridana_smena','rozpis',(int)$plan['id_smeny_rozpis'],$oldShift,['id_smeny_smena'=>$savedShiftId,'klic'=>$key,'id_person'=>$idPerson]+$interval);
}

/** Odebere pouze řádek pracovní kopie, nikdy původní publikovanou směnu. */
function cb_smeny_planovani_odebrat(mysqli $db, array $plan, int $idShift, int $plannerId): void
{
    if ($plan['stav'] !== 'rozpracovany') throw new CbUserVisibleException('Nejprve otevřete týden k úpravám.');
    $shift = null;
    foreach ($plan['blocks'] as $row) if ((int)$row['id_smeny_smena'] === $idShift) $shift = $row;
    if ($shift === null) throw new CbUserVisibleException('Směna už není v této pracovní verzi. Obnovte stránku.');
    cb_smeny_planovani_sql($db, 'SELECT id_person FROM hr_person WHERE id_person=? FOR UPDATE', 'i',[(int)$shift['id_person']])->close();
    cb_smeny_planovani_sql($db, 'DELETE FROM smeny_smena WHERE id_smeny_smena=? AND id_smeny_rozpis=? AND verze=?', 'iii',[$idShift,(int)$plan['id_smeny_rozpis'],(int)$plan['pracovni_verze']])->close();
    cb_smeny_audit_zapis($db,$plannerId,'odebrana_smena','rozpis',(int)$plan['id_smeny_rozpis'],$shift,null);
}
