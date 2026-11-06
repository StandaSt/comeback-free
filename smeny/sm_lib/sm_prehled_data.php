<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
/* Účel: Čte vlastní evidované hodiny z platných denních reportů IS a interní plán směn; vedení poskytuje povolené pobočky a přehled souhlasů. */

/** Čte skutečně evidované hodiny vlastní osoby ze všech platných reportů IS stejně jako Přehled hodin v Provozu.
 * Historický název ro.id_user v reportech již odkazuje na HR osobu, stejně jako Přehled hodin v Provozu.
 */
function cb_smeny_prehled_osobni_reporty(mysqli $db, int $person, string $from, string $to): array
{
    if ($person <= 0) return [];
    $stmt = cb_smeny_planovani_sql($db, 'SELECT r.id_reportu,r.datum_reportu datum,r.id_pob,
        ro.slot id_slot,COALESCE(cs.slot,"Neurčený slot") slot,p.nazev pobocka,
        ro.smena_od,ro.smena_do,ro.odpracovano
        FROM reporty_is r JOIN reporty_is_osoby ro ON ro.id_reportu=r.id_reportu
        JOIN pobocka p ON p.id_pob=r.id_pob LEFT JOIN cis_slot cs ON cs.id_slot=ro.slot
        WHERE r.platny=1 AND ro.id_user=? AND r.datum_reportu>=? AND r.datum_reportu<?
        ORDER BY r.datum_reportu,r.id_pob,ro.smena_od,ro.slot,ro.id_reporty_osoby', 'iss', [$person,$from,$to]);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Platný pracovní slot rozhoduje o osobním přehledu nezávisle na pojmenování přístupové role. */
function cb_smeny_prehled_ma_slot(mysqli $db, int $person): bool
{
    if ($person <= 0) return false;
    $stmt = cb_smeny_planovani_sql($db, 'SELECT 1 FROM hr_slot s WHERE s.id_person=? AND '.cb_hr_ucinne_sql('s').' LIMIT 1', 'i', [$person]);
    $found = $stmt->get_result()->fetch_row() !== null;
    $stmt->close();
    return $found;
}

/** Pouze vlastní aktuálně zveřejněné verze; rozsah zahrne i směny přes hranici měsíce. */
function cb_smeny_prehled_osobni_smeny(mysqli $db, int $person, string $from, string $to): array
{
    if ($person <= 0) return [];
    $stmt = cb_smeny_planovani_sql($db, 'SELECT s.id_slot,s.zacatek,s.konec,r.tyden_od,p.nazev pobocka,cs.slot,
        DATE(DATE_SUB(s.zacatek,INTERVAL 6 HOUR)) datum
        FROM smeny_rozpis r JOIN smeny_smena s ON s.id_smeny_rozpis=r.id_smeny_rozpis AND s.verze=r.zverejnena_verze
        JOIN pobocka p ON p.id_pob=r.id_pob JOIN cis_slot cs ON cs.id_slot=s.id_slot
        WHERE s.id_person=? AND s.konec>? AND s.zacatek<? ORDER BY s.zacatek,s.id_slot,s.id_smeny_smena', 'iss', [$person,$from,$to]);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Cíl odkazu z přehledu nepřimíchá externí směny; pobočky předává výhradně ověřený rozsah vedení. */
function cb_smeny_prehled_interni_pobocky(mysqli $db, string $week, array $branches): array
{
    if (!cb_smeny_planovani_vidi() || $branches === []) return [];
    $ids = implode(',', array_map('intval', array_keys($branches)));
    $stmt = cb_smeny_planovani_sql($db, 'SELECT s.*,r.id_pob,r.tyden_od start_day,2 zdroj,
        DATE(DATE_SUB(s.zacatek,INTERVAL 6 HOUR)) datum,TIME(s.zacatek) cas_od,TIME(s.konec) cas_do,
        p.nazev pobocka,cs.slot,'.cb_osoba_jmeno_sql('s.id_person',true).' pracovnik
        FROM smeny_rozpis r JOIN smeny_smena s ON s.id_smeny_rozpis=r.id_smeny_rozpis AND s.verze=r.zverejnena_verze
        JOIN pobocka p ON p.id_pob=r.id_pob JOIN cis_slot cs ON cs.id_slot=s.id_slot
        WHERE r.tyden_od=? AND r.id_pob IN ('.$ids.') ORDER BY s.zacatek,p.nazev,cs.slot,pracovnik', 's', [$week]);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** Pobočka bez interní publikace má prázdnou zveřejněnou verzi, i když už existuje pracovní kopie. */
function cb_smeny_prehled_rozpisy(mysqli $db, array $branches, string $week): array
{
    if(!cb_smeny_planovani_vidi() || $branches===[]) return [];
    $ids=implode(',',array_map('intval',array_keys($branches)));
    $definitive=cb_smeny_souhlasy_schema($db)?'r.definitivni_od':'NULL definitivni_od';
    $stmt=cb_smeny_planovani_sql($db,'SELECT p.id_pob,p.nazev,r.id_smeny_rozpis,r.pracovni_verze,r.zverejnena_verze,r.pripraveno,'.$definitive.' FROM pobocka p LEFT JOIN smeny_rozpis r ON r.id_pob=p.id_pob AND r.tyden_od=? WHERE p.id_pob IN ('.$ids.') ORDER BY p.nazev','s',[$week]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Souhlasy ukazujeme po jednotlivých zprávách; potvrzená první verze nezakrývá nepotvrzenou změnu. */
function cb_smeny_prehled_souhlasy(mysqli $db, array $branches, string $week): array
{
    if(!cb_smeny_planovani_vidi() || $branches===[]) return [];
    $ids=implode(',',array_map('intval',array_keys($branches)));
    $stmt=cb_smeny_planovani_sql($db,'SELECT o.*,p.nazev pobocka,' . cb_osoba_jmeno_sql('ou.id_person',true) . ' jmeno FROM smeny_oznameni o JOIN smeny_rozpis r ON r.id_smeny_rozpis=o.id_smeny_rozpis JOIN pobocka p ON p.id_pob=r.id_pob LEFT JOIN ' . cb_osoby_zdroj_sql() . ' ou ON ou.id_person=o.id_person WHERE r.tyden_od=? AND r.id_pob IN ('.$ids.') ORDER BY (o.potvrzeno IS NULL) DESC,p.nazev,jmeno,o.verze,o.id_smeny_oznameni','s',[$week]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
