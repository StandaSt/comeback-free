<?php
declare(strict_types=1);
/* Účel: Načte stavy rozpisů a přehled souhlasů vedoucímu pouze za povolené pobočky. */

/** Pobočku bez publikace označíme jako externí zdroj, i když už má pracovní kopii. */
function cb_smeny_prehled_rozpisy(mysqli $db, array $branches, string $week): array
{
    if(!cb_smeny_planovani_vidi() || $branches===[]) return [];
    $ids=implode(',',array_map('intval',array_keys($branches)));
    $stmt=cb_smeny_planovani_sql($db,'SELECT p.id_pob,p.nazev,r.id_smeny_rozpis,r.pracovni_verze,r.zverejnena_verze,r.pripraveno FROM pobocka p LEFT JOIN smeny_rozpis r ON r.id_pob=p.id_pob AND r.tyden_od=? WHERE p.id_pob IN ('.$ids.') ORDER BY p.nazev','s',[$week]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}

/** Souhlasy ukazujeme po jednotlivých zprávách; potvrzená první verze nezakrývá nepotvrzenou změnu. */
function cb_smeny_prehled_souhlasy(mysqli $db, array $branches, string $week): array
{
    if(!cb_smeny_planovani_vidi() || $branches===[]) return [];
    $ids=implode(',',array_map('intval',array_keys($branches)));
    $stmt=cb_smeny_planovani_sql($db,'SELECT o.*,p.nazev pobocka,TRIM(CONCAT_WS(" ",ou.prijmeni,ou.jmeno)) jmeno FROM smeny_oznameni o JOIN smeny_rozpis r ON r.id_smeny_rozpis=o.id_smeny_rozpis JOIN pobocka p ON p.id_pob=r.id_pob LEFT JOIN hr_osobni_udaje ou ON ou.id_osobni_udaje=(SELECT MAX(x.id_osobni_udaje) FROM hr_osobni_udaje x WHERE x.id_person=o.id_person AND x.platny=1) WHERE r.tyden_od=? AND r.id_pob IN ('.$ids.') ORDER BY (o.potvrzeno IS NULL) DESC,p.nazev,jmeno,o.verze,o.id_smeny_oznameni','s',[$week]);
    $rows=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);$stmt->close();return $rows;
}
