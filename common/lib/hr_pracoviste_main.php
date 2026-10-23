<?php
declare(strict_types=1);

/*
 * Ucel: Sdili samostatnou casovou evidenci MAIN mezi HR, Provozem a Smenami.
 * Nasazeni vyzaduje pripravenou migraci mist vykonu prace; stara povoleni nejsou autoritou.
 * MAIN je mnozina pobocek; manazerske pozice nemaji MAIN ani sloty. Rozsah urcuji mista vykonu prace.
 */
require_once __DIR__ . '/hr_mista_prace.php';

// Stav schematu pamatujeme jen pro dane spojeni a jeden PHP pozadavek.
function cb_hr_main_pripraveno(mysqli $db): bool
{
    static $stav;
    $stav ??= new WeakMap();
    if (!isset($stav[$db])) {
        $result = $db->query("SHOW TABLES LIKE 'hr\\_pracoviste\\_main'");
        $stav[$db] = $result->num_rows === 1;
        $result->free();
    }
    return $stav[$db];
}

// Stejne nazvy sloupcu umoznuji sdilet dotazy; data ani historicke datum se pri cteni nemeni.
function cb_hr_main_sql(mysqli $db, bool $vcetneZrusenych = false): string
{
    $filtr = $vcetneZrusenych ? '' : ' AND zruseno IS NULL';
    return '(SELECT id_main AS id_pracoviste, id_person, id_pob, 1 AS hlavni, platnost_od, platnost_do, id_user_zadal, vytvoreno, platny, zruseno FROM hr_pracoviste_main WHERE 1=1' . $filtr . ')';
}

// Sdileny rozsah mist doplni dnesni priznak MAIN pro existujici odberatele prehledu.
function cb_hr_pracoviste_sql(mysqli $db): string
{
    $main = cb_hr_main_sql($db);
    return '(SELECT w.id_pracoviste, w.id_person, w.id_pob, w.platnost_od, w.platnost_do, w.id_user_zadal, w.vytvoreno, w.platny, w.zruseno,
        EXISTS (SELECT 1 FROM ' . $main . ' m WHERE m.id_person=w.id_person AND m.id_pob=w.id_pob
            AND m.platny=1 AND m.zruseno IS NULL AND (m.platnost_od IS NULL OR m.platnost_od<=CURDATE())
            AND (m.platnost_do IS NULL OR m.platnost_do>=CURDATE())) AS hlavni FROM ' . cb_hr_mista_sql() . ' w WHERE ' . cb_hr_ucinne_sql('w') . ')';
}

// Pouze vychozi volba obrazovky: vice MAIN vyzaduje konkretni vyber; pro opravneni slouzi cela mnozina.
function cb_hr_main_id(mysqli $db, int $idPerson): int
{
    $stmt = $db->prepare('SELECT id_pob FROM ' . cb_hr_main_sql($db) . ' m WHERE id_person=? AND platny=1 AND zruseno IS NULL
        AND (platnost_od IS NULL OR platnost_od<=CURDATE()) AND (platnost_do IS NULL OR platnost_do>=CURDATE()) LIMIT 2');
    $stmt->bind_param('i', $idPerson);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return count($rows) === 1 ? (int)$rows[0]['id_pob'] : 0;
}

// Bez slotu MAIN neni povinna; manazerska pozice ji mit primo nesmi.
function cb_hr_main_nepovinne(mysqli $db, int $idPerson, ?string $datum = null): bool
{
    $datum ??= date('Y-m-d');
    $stmt = $db->prepare('SELECT 1 FROM hr_slot s WHERE id_person=? AND ' . cb_hr_ucinne_sql('s','?') . ' LIMIT 1');
    $stmt->bind_param('iss', $idPerson, $datum, $datum);
    $stmt->execute();
    $optional = $stmt->get_result()->fetch_row() === null;
    $stmt->close();
    return $optional;
}

// Vrati vsechny MAIN k datu, nikdy nahodne jednu; pouziva se pro opravneni a nabidku pobocky.
function cb_hr_main_ids(mysqli $db, int $idPerson, ?string $datum = null): array
{
    $datum ??= date('Y-m-d');
    $stmt=$db->prepare('SELECT DISTINCT m.id_pob FROM hr_pracoviste_main m JOIN hr_person p ON p.id_person=m.id_person
        JOIN pobocka b ON b.id_pob=m.id_pob AND b.id_firma=p.id_firma AND b.aktivni=1
        WHERE m.id_person=? AND '.cb_hr_ucinne_sql('m','?').' ORDER BY m.id_pob');
    $stmt->bind_param('iss',$idPerson,$datum,$datum); $stmt->execute();
    $ids=array_map('intval',array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC),'id_pob')); $stmt->close();
    return $ids;
}

// Zapis pred nasazenim schematu zastavime jeste pred prvni zmenou dat.
function cb_hr_main_vyzaduj(mysqli $db): void
{
    if (!cb_hr_main_pripraveno($db)) {
        throw new CbUserVisibleException('Nejprve spusťte připravenou SQL migraci samostatné historie MAIN poboček.');
    }
}

// Noveho zamestnance zalozime v obou evidencich v transakci volajiciho.
function cb_hr_main_vlozit(mysqli $db, int $idPerson, int $idPob, ?string $od, int $idUser): void
{
    cb_hr_main_vyzaduj($db);
    $stmt = $db->prepare('INSERT INTO hr_pracoviste_main (id_person,id_pob,platnost_od,id_user_zadal,vytvoreno,platny) VALUES (?,?,?,?,NOW(),1)');
    $stmt->bind_param('iisi', $idPerson, $idPob, $od, $idUser);
    $stmt->execute();
    $stmt->close();
}
