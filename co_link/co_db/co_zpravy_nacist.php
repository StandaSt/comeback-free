<?php
/* Co-Link: jednim SELECT nacte nejvyse 81 odpovidajicich zprav pro presnou detekci dalsi stranky. */
declare(strict_types=1);
require_once __DIR__ . '/../co_lib/co_rows.php';
require_once __DIR__ . '/../../common/lib/osoba_jmeno.php';

/** Podminku a parametry pripravuje interni filtr; 81. radek je pouze dukaz existence starsi zpravy. */
function co_zpravy_nacist(int $user, string $where, array $params): array
{
    return co_rows('SELECT z.*,COALESCE(' . cb_osoba_jmeno_sql('z.autor') . ',"Uživatel") jmeno,
        (SELECT COUNT(*) FROM co_precteni p WHERE p.zprava=z.id) precteno_pocet,
        EXISTS(SELECT 1 FROM co_precteni p WHERE p.zprava=z.id AND p.uzivatel='.(int)$user.') precteno_mnou,
        (SELECT COUNT(*) FROM co_zprava odpoved WHERE odpoved.rodic=z.id AND odpoved.smazano IS NULL) odpovedi
        FROM co_zprava z WHERE '.$where.' ORDER BY z.id DESC LIMIT 81',$params);
}
