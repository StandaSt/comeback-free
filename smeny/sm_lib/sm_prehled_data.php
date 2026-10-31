<?php
declare(strict_types=1);
// Zobrazovane jmeno vcetne titulu cteme pres spolecny zdroj osoby.
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';
/* Účel: Načte definitivní stavy rozpisů a existující přehled souhlasů či výhrad pouze za povolené pobočky. */

/** Pobočku bez publikace označíme jako externí zdroj, i když už má pracovní kopii. */
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
