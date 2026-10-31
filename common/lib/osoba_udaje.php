<?php
declare(strict_types=1);
/* Jediny cteci zdroj aktualnich osobnich udaju HR pro jednotlive osoby, seznamy i vlozene SQL dotazy. */

/** Vybere jeden nejnovější platný osobní záznam; nefiltruje aktivitu ani oprávnění osoby. */
function cb_osoby_zdroj_sql(): string
{
    return '(SELECT osobni.*
        FROM hr_osobni_udaje osobni
        WHERE osobni.platny=1 AND osobni.id_osobni_udaje=(
            SELECT MAX(aktualni.id_osobni_udaje) FROM hr_osobni_udaje aktualni
            WHERE aktualni.id_person=osobni.id_person AND aktualni.platny=1))';
}

/** Načte seznam jedním parametrizovaným dotazem; vrací nezformátované údaje pod ID osoby. */
function cb_osoby_nacist(mysqli $db, array $ids): array
{
    $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id): bool=>$id>0)));
    if($ids===[]) return [];
    $stmt=$db->prepare('SELECT osoby.id_person,osoby.id_osobni_udaje,osoby.titul_pred,osoby.jmeno,
        osoby.druhe_jmeno,osoby.prijmeni,osoby.titul_za FROM '.cb_osoby_zdroj_sql().' osoby
        WHERE osoby.id_person IN ('.implode(',',array_fill(0,count($ids),'?')).')');
    try {
        $stmt->bind_param(str_repeat('i',count($ids)),...$ids);
        $stmt->execute();
        $out=[];
        foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) $out[(int)$row['id_person']]=$row;
        return $out;
    } finally { $stmt->close(); }
}

/** Doplní aktuální surové údaje hromadně; neexistující osoba zachová původní historický údaj. */
function cb_osoby_doplnit(mysqli $db, array $rows, string $idKey='id_person'): array
{
    $people=cb_osoby_nacist($db,array_column($rows,$idKey));
    foreach($rows as &$row) {
        $person=$people[(int)($row[$idKey]??0)]??null;
        if($person!==null) foreach(['jmeno','druhe_jmeno','prijmeni','titul_pred','titul_za'] as $key) $row[$key]=$person[$key];
    }
    unset($row);
    return $rows;
}
