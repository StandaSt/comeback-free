<?php
declare(strict_types=1);
/* Účel: Načte dostupnost a ukládá i upravuje směny V2; změna času zachová klíč pro adresné oznámení. */

/** Kandidáti se řídí HR k datu směny; Kdykoliv vychází z provozní doby hlavní pobočky. */
function cb_smeny_planovani_kandidati(mysqli $db, array $plan, string $date, int $idSlot): array
{
    $slotTable = cb_hr_schema_table($db, 'slot');
    $day = (int)(new DateTimeImmutable($date))->format('N');
    $closingColumn = [1=>'end_po',2=>'end_ut',3=>'end_st',4=>'end_ct',5=>'end_pa',6=>'end_so',7=>'end_ne'][$day];
    $sql = 'SELECT hp.id_person,TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) jmeno,
        COALESCE(pr.id_pob,0) id_hlavni_pob,COALESCE(p.nazev,"Bez hlavní pobočky") hlavni_pobocka,
        EXISTS(SELECT 1 FROM hr_pracovni_vztah pv WHERE pv.id_person=hp.id_person AND pv.id_pracovni_vztah_typ=1 AND pv.platny=1 AND (pv.datum_nastupu IS NULL OR pv.datum_nastupu<=?) AND (pv.datum_ukonceni IS NULL OR pv.datum_ukonceni>=?)) je_hpp,
        sp.rezim,sp.volno_datum,TIME_FORMAT(pd.cas_od,"%H:%i") pozadavek_od,TIME_FORMAT(pd.cas_do,"%H:%i") pozadavek_do,
        TIME_FORMAT(p.' . $closingColumn . ',"%H:%i") zavira,
        COALESCE((SELECT ROUND(SUM(TIMESTAMPDIFF(MINUTE,s.zacatek,s.konec))/60,1) FROM smeny_smena s JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis WHERE s.id_person=hp.id_person AND r.tyden_od=? AND s.verze=COALESCE(r.pracovni_verze,r.zverejnena_verze)),0) hodin_tyden
        FROM hr_person hp
        LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(o.id_osobni_udaje) FROM hr_osobni_udaje o WHERE o.id_person=hp.id_person AND o.platny=1)
        LEFT JOIN hr_pracoviste pr ON pr.id_pracoviste=(SELECT MAX(x.id_pracoviste) FROM hr_pracoviste x WHERE x.id_person=hp.id_person AND x.hlavni=1 AND x.platny=1 AND (x.platnost_od IS NULL OR x.platnost_od<=?) AND (x.platnost_do IS NULL OR x.platnost_do>=?))
        LEFT JOIN pobocka p ON p.id_pob=pr.id_pob
        LEFT JOIN smeny_pozadavek sp ON sp.id_person=hp.id_person AND sp.tyden_od=?
        LEFT JOIN smeny_pozadavek_den pd ON pd.id_smeny_pozadavek=sp.id_smeny_pozadavek AND pd.den_tydne=?
        WHERE hp.aktivni=1 AND hp.id_firma=? AND EXISTS(SELECT 1 FROM ' . $slotTable . ' z WHERE z.id_person=hp.id_person AND z.id_slot=? AND z.platny=1 AND (z.platnost_od IS NULL OR z.platnost_od<=?) AND (z.platnost_do IS NULL OR z.platnost_do>=?))';
    $stmt = cb_smeny_planovani_sql($db,$sql,'ssssssiiiss',[$date,$date,$plan['tyden_od'],$date,$date,$plan['tyden_od'],$day,(int)$plan['id_firma'],$idSlot,$date,$date]);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
    foreach ($rows as &$row) {
        $row['id_person'] = (int)$row['id_person'];
        $row['je_hpp'] = (int)$row['je_hpp'];
        $row['je_hlavni_pobocka'] = (int)$row['id_hlavni_pob'] === (int)$plan['id_pob'] ? 1 : 0;
        $row['ma_volno'] = (string)$row['volno_datum'] === $date ? 1 : 0;
        if ($row['rezim'] === 'kdykoliv') {
            $row['pozadavek_od'] = $row['zavira'] ? '10:00' : null;
            $row['pozadavek_do'] = $row['zavira'];
        }
        $row['podle_pozadavku'] = $row['je_hpp'] === 1 ? $row['ma_volno'] === 0 : !empty($row['pozadavek_od']);
        $row['hodin_tyden'] = (float)$row['hodin_tyden'];
    }
    unset($row);
    usort($rows,static fn(array $a,array $b):int => ($b['je_hlavni_pobocka'] <=> $a['je_hlavni_pobocka']) ?: ((int)$b['podle_pozadavku'] <=> (int)$a['podle_pozadavku']) ?: strcoll($a['jmeno'],$b['jmeno']));
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

/** Volba bez požadavků smí obejít dostupnost, nikdy kvalifikaci či překryv směn. */
function cb_smeny_planovani_dostupnost(array $candidate, array $interval, bool $ignore): void
{
    if ($ignore) return;
    if (empty($candidate['podle_pozadavku'])) throw new CbUserVisibleException('Pracovník má volno nebo nemá požadavek. Použijte volbu Bez ohledu na požadavky.');
    if ((int)$candidate['je_hpp'] !== 1) {
        $from = cb_smeny_sablony_cas_minuty((string)$candidate['pozadavek_od'],false);
        $to = cb_smeny_sablony_cas_minuty((string)$candidate['pozadavek_do'],true);
        if (max($from,$interval['od']) >= min($to,$interval['do'])) throw new CbUserVisibleException('Směna se ani částečně nepřekrývá s požadavkem pracovníka.');
    }
}

/** Po zamčení osoby zkontroluje skutečné intervaly i přes půlnoc a hranici týdne. */
function cb_smeny_planovani_kolize(mysqli $db, array $plan, int $idPerson, string $start, string $end, string $exceptKey = ''): void
{
    $idPlan = (int)$plan['id_smeny_rozpis'];
    // Na jiných pobočkách chráníme také dosud platnou publikaci, i když se tam už upravuje kopie.
    $stmt = cb_smeny_planovani_sql($db, 'SELECT s.id_smeny_smena FROM smeny_smena s JOIN smeny_rozpis r ON r.id_smeny_rozpis=s.id_smeny_rozpis WHERE s.id_person=? AND s.zacatek<? AND s.konec>? AND ((r.id_smeny_rozpis=? AND s.verze=r.pracovni_verze AND s.klic_smeny<>?) OR (r.id_smeny_rozpis<>? AND (s.verze=r.pracovni_verze OR s.verze=r.zverejnena_verze))) LIMIT 1 FOR UPDATE', 'issisi',[$idPerson,$end,$start,$idPlan,$exceptKey,$idPlan]);
    $collision = $stmt->get_result()->fetch_assoc() !== null; $stmt->close();
    if ($collision) throw new CbUserVisibleException('Pracovník už má v tomto čase jinou směnu v IS.');
    // Externí zdroj se bere pouze bez publikovaného interního týdne; právě nahrazovaný týden této pobočky vynecháme.
    $stmt = cb_smeny_planovani_sql($db, 'SELECT e.id_user FROM smeny_plan e JOIN hr_person hp ON hp.id_user=e.id_user WHERE hp.id_person=? AND e.zdroj=1 AND e.datum BETWEEN DATE_SUB(DATE(?),INTERVAL 1 DAY) AND DATE(?) AND TIMESTAMP(e.datum,e.cas_od)<? AND TIMESTAMP(DATE_ADD(e.datum,INTERVAL (e.cas_do<=e.cas_od) DAY),e.cas_do)>? AND NOT(e.id_pob=? AND e.start_day=?) AND NOT EXISTS(SELECT 1 FROM smeny_rozpis r WHERE r.id_pob=e.id_pob AND r.tyden_od=e.start_day AND r.zverejnena_verze IS NOT NULL) LIMIT 1', 'issssis',[$idPerson,$start,$end,$end,$start,(int)$plan['id_pob'],$plan['tyden_od']]);
    $collision = $stmt->get_result()->fetch_assoc() !== null; $stmt->close();
    if ($collision) throw new CbUserVisibleException('Pracovník má v tomto čase externě naplánovanou směnu na jiné pobočce nebo v sousedním týdnu.');
}

/** Zápis jedné směny patří do transakce dispatcheru a mění výhradně pracovní verzi. */
function cb_smeny_planovani_priradit(mysqli $db, array $plan, array $branch, array $week, array $post, int $plannerId): void
{
    if ($plan['stav'] !== 'rozpracovany') throw new CbUserVisibleException('Nejprve otevřete týden k úpravám.');
    $interval = cb_smeny_planovani_interval($branch,$week,$post);
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
    if ($candidate === null) throw new CbUserVisibleException('Pracovník není pro tuto pozici dostupný v HR.');
    $ignore = !empty($post['bez_ohledu']);
    cb_smeny_planovani_dostupnost($candidate,$interval,$ignore);
    $key = $oldShift['klic_smeny'] ?? bin2hex(random_bytes(16));
    cb_smeny_planovani_kolize($db,$plan,$idPerson,$interval['zacatek'],$interval['konec'],$editing?$key:'');
    if ($editing) {
        cb_smeny_planovani_sql($db,'UPDATE smeny_smena SET id_slot=?,zacatek=?,konec=?,bez_ohledu_na_pozadavky=?,ulozil_id_person=?,ulozeno=NOW() WHERE id_smeny_smena=? AND id_smeny_rozpis=? AND verze=?','issiiiii',[$interval['id_slot'],$interval['zacatek'],$interval['konec'],(int)$ignore,$plannerId,(int)$oldShift['id_smeny_smena'],(int)$plan['id_smeny_rozpis'],(int)$plan['pracovni_verze']])->close();
    } else {
        cb_smeny_planovani_sql($db, 'INSERT INTO smeny_smena (id_smeny_rozpis,verze,klic_smeny,id_person,id_slot,zacatek,konec,bez_ohledu_na_pozadavky,ulozil_id_person) VALUES (?,?,?,?,?,?,?,?,?)', 'iisiissii',[(int)$plan['id_smeny_rozpis'],(int)$plan['pracovni_verze'],$key,$idPerson,$interval['id_slot'],$interval['zacatek'],$interval['konec'],(int)$ignore,$plannerId])->close();
    }
    cb_smeny_audit_zapis($db,$plannerId,$editing?'zmenena_smena':'pridana_smena','rozpis',(int)$plan['id_smeny_rozpis'],$oldShift,['klic'=>$key,'id_person'=>$idPerson]+$interval);
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
