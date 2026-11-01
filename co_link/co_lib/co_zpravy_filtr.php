<?php
/* Co-Link: sestavi puvodni podminky strankovani, hledani a vlakna s parametry. */
declare(strict_types=1);

/** Hledani ma prednost pred filtrem vlakna; text je vzdy vazany parametr a nejvyse 120 znaku. */
function co_zpravy_filtr(int $conversation, int $before = 0, string $search = '', int $around = 0, int $thread = 0): array
{
    $where='z.konverzace=?'; $params=[$conversation];
    if ($before>0) { $where.=' AND z.id<?'; $params[]=$before; }
    if ($around>0) {
        $where.=' AND z.id<=?'; $params[]=$around;
    }
    if ($search!=='') { $where.=" AND z.smazano IS NULL AND SUBSTRING_INDEX(z.text,CONCAT(CHAR(10),CHAR(30),'CO1:'),1) LIKE ?"; $params[]='%'.mb_substr($search,0,120).'%'; }
    elseif($thread>0) { $where.=' AND (z.rodic=? OR z.id=?)'; $params[]=$thread; $params[]=$thread; }
    else $where.=' AND z.rodic IS NULL';
    return ['where' => $where, 'params' => $params];
}
