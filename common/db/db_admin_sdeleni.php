<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../lib/osoba_jmeno.php';
// Rozsah mist vykonu prace sdileji vsechny cteci dotazy tohoto souboru.
require_once __DIR__ . '/../lib/hr_pracoviste_main.php';

/* Účel: Datové operace výhradně nad administrátorskými sděleními a jejich příjemci.
 * Seznam příjemců je neměnný od zveřejnění; výběr vrací také id_person pro rozlišení osob.
 * Jiné druhy oznámení se sem neukládají.
 */

/** Provede parametrizovaný dotaz a předá výsledek volajícímu. */
function cb_sdeleni_sql(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    return $stmt;
}

/** Čte řádky a vždy uvolní dotaz. */
function cb_sdeleni_rows(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = cb_sdeleni_sql($db, $sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Platné jméno se vybírá jedinou nejnovější verzí, aby se člověk nikdy nezdvojil. */
function cb_sdeleni_jmeno_sql(string $person): string
{
    return 'LEFT JOIN '.cb_osoby_zdroj_sql().' ou ON ou.id_person='.$person;
}

/** Společný osobní seznam; prošlá i přečtená sdělení zůstávají v archivu. */
function cb_sdeleni_osobni(mysqli $db, int $user, bool $archiv = false, int $offset = 0): array
{
    $where = $archiv ? '' : ' AND p.precteno IS NULL AND s.odeslano<=NOW() AND s.platnost_do>NOW()';
    return cb_sdeleni_rows($db, 'SELECT s.*,UNIX_TIMESTAMP(s.platnost_do) platnost_do_ts,p.zobrazeno,p.precteno,
        ' . cb_osoba_jmeno_sql('ou.id_person') . ' autor
        FROM admin_sdeleni_prijemce p JOIN admin_sdeleni s ON s.id_sdeleni=p.id_sdeleni '
        . cb_sdeleni_jmeno_sql('s.id_user_vytvoril') . ' WHERE p.id_user=?' . $where
        . ($archiv ? ' ORDER BY s.odeslano DESC,s.id_sdeleni DESC LIMIT 51 OFFSET '.max(0,$offset) : ' ORDER BY s.odeslano,s.id_sdeleni'), 'i', [$user]);
}

/** Čas se zapíše jen jednou vlastnímu příjemci; prošlou zprávu lze zobrazit v archivu, ne potvrzovat jako aktuální. */
function cb_sdeleni_zaznamenej(mysqli $db, int $user, int $id, bool $precteno): void
{
    $set = $precteno ? 'p.precteno=COALESCE(p.precteno,NOW()),p.zobrazeno=COALESCE(p.zobrazeno,NOW())' : 'p.zobrazeno=COALESCE(p.zobrazeno,NOW())';
    cb_sdeleni_sql($db, 'UPDATE admin_sdeleni_prijemce p JOIN admin_sdeleni s ON s.id_sdeleni=p.id_sdeleni
        SET '.$set.' WHERE p.id_user=? AND p.id_sdeleni=? AND s.odeslano<=NOW()'.($precteno?' AND s.platnost_do>NOW()':''), 'ii', [$user,$id])->close();
}

/** Aktivní účty ve firemním rozsahu odesílatele; skupina znamená (role OR slot) AND pobočka. */
function cb_sdeleni_prijemci(mysqli $db, array $firmy, array $vyber): array
{
    if ($firmy === [] || !in_array($vyber['rezim']??'', ['vsichni','skupina','jednotlivec'], true)) { return []; }
    $scope = implode(',', array_map('intval',$firmy));
    $where = 'p.aktivni=1 AND p.id_firma IN ('.$scope.')';
    $params = []; $types = '';
    if ($vyber['rezim'] === 'jednotlivec') {
        // Prázdný seznam ani starý koncept jednotlivce nesmí oslovit všechny účty.
        if (!is_array($vyber['id_users']??null) || $vyber['id_users']===[]) { return []; }
        $params=array_values(array_map('intval',$vyber['id_users']));
        $where .= ' AND u.id_user IN ('.implode(',',array_fill(0,count($params),'?')).')';
        $types=str_repeat('i',count($params));
    } elseif ($vyber['rezim'] === 'skupina') {
        $or=[];
        if ($vyber['role'] !== []) { $or[]='EXISTS(SELECT 1 FROM hr_pristupovy_profil r WHERE r.id_person=p.id_person AND r.id_role IN ('.implode(',',$vyber['role']).'))'; }
        if ($vyber['sloty'] !== []) { $or[]='EXISTS(SELECT 1 FROM hr_slot r WHERE r.id_person=p.id_person AND r.id_slot IN ('.implode(',',$vyber['sloty']).') AND r.platny=1 AND r.zruseno IS NULL AND (r.platnost_od IS NULL OR r.platnost_od<=CURDATE()) AND (r.platnost_do IS NULL OR r.platnost_do>=CURDATE()))'; }
        if ($or === [] || $vyber['pobocky'] === []) { return []; }
        $where .= ' AND ('.implode(' OR ',$or).') AND EXISTS(SELECT 1 FROM ' . cb_hr_pracoviste_sql($db) . ' w WHERE w.id_person=p.id_person AND w.id_pob IN ('.implode(',',$vyber['pobocky']).') AND w.platny=1 AND w.zruseno IS NULL AND (w.platnost_od IS NULL OR w.platnost_od<=CURDATE()) AND (w.platnost_do IS NULL OR w.platnost_do>=CURDATE()))';
    }
    return cb_sdeleni_rows($db, 'SELECT u.id_user,p.id_person,' . cb_osoba_jmeno_sql('ou.id_person',true) . ' jmeno
        FROM user u JOIN hr_person p ON p.id_person=u.id_user AND p.id_user=u.id_user '
        .cb_sdeleni_jmeno_sql('p.id_person').' WHERE '.$where.' ORDER BY ou.prijmeni,ou.jmeno,u.id_user', $types,$params);
}

/** Číselníky i výběr konkrétních uživatelů se omezí na firemní rozsah správce. */
function cb_sdeleni_ciselniky(mysqli $db, array $firmy): array
{
    $ids=implode(',',array_map('intval',$firmy)) ?: '0';
    return [
        'role'=>cb_sdeleni_rows($db,'SELECT id_role AS id,role AS nazev FROM cis_role WHERE aktivni=1 ORDER BY id_role'),
        'sloty'=>cb_sdeleni_rows($db,'SELECT id_slot AS id,slot AS nazev FROM cis_slot WHERE aktivni=1 ORDER BY id_slot'),
        'pobocky'=>cb_sdeleni_rows($db,"SELECT id_pob AS id,nazev FROM pobocka WHERE aktivni=1 AND id_firma IN ($ids) ORDER BY (kod='Vyroba'),id_pob"),
        'lide'=>cb_sdeleni_prijemci($db,$firmy,['rezim'=>'vsichni']),
    ];
}

/** Uloží zprávu a celý potvrzený seznam příjemců nedělitelně; neukládá výběrové filtry. */
function cb_sdeleni_zverejni(mysqli $db, int $autor, array $draft): int
{
    $db->begin_transaction();
    try {
        cb_sdeleni_sql($db,'INSERT INTO admin_sdeleni(nadpis,obsah,id_user_vytvoril,odeslano,platnost_dni,platnost_do)
            VALUES(?,?,?,NOW(),?,DATE_ADD(NOW(),INTERVAL ? DAY))','ssiii',[$draft['nadpis'],$draft['obsah'],$autor,$draft['dny'],$draft['dny']])->close();
        $id=(int)$db->insert_id;
        foreach ($draft['prijemci'] as $person) {
            cb_sdeleni_sql($db,'INSERT INTO admin_sdeleni_prijemce(id_sdeleni,id_user) VALUES(?,?)','ii',[$id,(int)$person['id_user']])->close();
        }
        $db->commit();
        return $id;
    } catch (Throwable $error) { $db->rollback(); throw $error; }
}

/** Správce vidí jen sdělení, jejichž všichni příjemci patří do jeho firemního rozsahu. */
function cb_sdeleni_admin_archiv(mysqli $db, array $firmy, int $id = 0, int $offset = 0): array
{
    $scope=implode(',',array_map('intval',$firmy)) ?: '0';
    return cb_sdeleni_rows($db,'SELECT s.*,' . cb_osoba_jmeno_sql('ou.id_person') . ' autor,
        (SELECT COUNT(*) FROM admin_sdeleni_prijemce r WHERE r.id_sdeleni=s.id_sdeleni) celkem,
        (SELECT COUNT(*) FROM admin_sdeleni_prijemce r WHERE r.id_sdeleni=s.id_sdeleni AND r.zobrazeno IS NOT NULL) zobrazeno_pocet,
        (SELECT COUNT(*) FROM admin_sdeleni_prijemce r WHERE r.id_sdeleni=s.id_sdeleni AND r.precteno IS NOT NULL) precteno_pocet,
        s.platnost_do>NOW() aktivni FROM admin_sdeleni s '.cb_sdeleni_jmeno_sql('s.id_user_vytvoril').'
        WHERE NOT EXISTS(SELECT 1 FROM admin_sdeleni_prijemce r LEFT JOIN hr_person p ON p.id_user=r.id_user
            WHERE r.id_sdeleni=s.id_sdeleni AND (p.id_firma IS NULL OR p.id_firma NOT IN ('.$scope.')))' . ($id>0?' AND s.id_sdeleni=?':'')
        .' ORDER BY s.odeslano DESC,s.id_sdeleni DESC LIMIT 51 OFFSET '.max(0,$offset),$id>0?'i':'',$id>0?[$id]:[]);
}

/** Detail se volá až po ověření přístupu ke konkrétnímu sdělení v archivu. */
function cb_sdeleni_admin_prijemci(mysqli $db, int $id): array
{
    return cb_sdeleni_rows($db,'SELECT r.id_user,r.zobrazeno,r.precteno,' . cb_osoba_jmeno_sql('ou.id_person',true) . ' jmeno
        FROM admin_sdeleni_prijemce r '.cb_sdeleni_jmeno_sql('r.id_user').' WHERE r.id_sdeleni=? ORDER BY ou.prijmeni,ou.jmeno,r.id_user','i',[$id]);
}
