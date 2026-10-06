<?php
declare(strict_types=1);

/*
 * Ucel: Sdili samostatnou casovou evidenci MAIN mezi HR, Provozem a Smenami.
 * Pred rucni SQL migraci umoznuje cteni stare evidence; novy zapis vyzaduje novou tabulku.
 */

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
    return cb_hr_main_pripraveno($db)
        ? '(SELECT id_main AS id_pracoviste, id_person, id_pob, 1 AS hlavni, platnost_od, platnost_do, id_user_zadal, vytvoreno, platny, zruseno FROM hr_pracoviste_main WHERE 1=1' . $filtr . ')'
        : '(SELECT * FROM hr_pracoviste WHERE hlavni = 1' . $filtr . ')';
}

// Seznam povolenych pracovist doplni MAIN k dnesku, nikoli zastaraly priznak na prirazeni.
function cb_hr_pracoviste_sql(mysqli $db): string
{
    $main = cb_hr_main_sql($db);
    return '(SELECT w.id_pracoviste, w.id_person, w.id_pob, w.platnost_od, w.platnost_do, w.id_user_zadal, w.vytvoreno, w.platny, w.zruseno,
        EXISTS (SELECT 1 FROM ' . $main . ' m WHERE m.id_person=w.id_person AND m.id_pob=w.id_pob
            AND m.platny=1 AND m.zruseno IS NULL AND (m.platnost_od IS NULL OR m.platnost_od<=CURDATE())
            AND (m.platnost_do IS NULL OR m.platnost_do>=CURDATE())) AS hlavni FROM hr_pracoviste w)';
}

// Nejednoznacna MAIN nerozsiri opravneni ani nevybere pobocku nahodne.
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
