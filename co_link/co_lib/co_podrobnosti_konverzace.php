<?php
/* Co-Link: opravneny prehled clenu a precteni zpravy a ztiseni otevrene konverzace. */
declare(strict_types=1);
require_once __DIR__ . '/co_konverzace.php';
require_once __DIR__ . '/co_audit_kontext.php';
require_once __DIR__ . '/co_transakce.php';
require_once __DIR__ . '/co_lide.php';
require_once __DIR__ . '/../co_db/co_podrobnosti_konverzace_db.php';

/** Nejprve overi volajiciho a pote zachova puvodni vyber aktivnich osob s pristupem. */
function co_konverzace_clenove(int $user, int $id): array
{
    co_konverzace($user, $id);
    $members = [];
    foreach (co_lide() as $person) {
        if (co_podrobnosti_clen_pristup($id, (int)$person['id'])) $members[] = $person;
    }
    return ['lide' => $members];
}

/** Chybejici zprava a nepristupna konverzace zachovaji puvodni poradi chyb 404/403. */
function co_zprava_precteni(int $user, int $id): array
{
    $m = co_podrobnosti_zprava_konverzace($id)[0] ?? [];
    if (!$m) co_chyba('Zpráva není dostupná.', 404);
    co_konverzace($user, (int)$m['konverzace']);
    return ['lide' => co_podrobnosti_precteni_nacist($id)];
}

/** Samotnou zmenu ztiseni povoli az po overeni pristupu do konverzace. */
function co_konverzace_ztisit(int $user, array $d): void
{
    co_transakce(static function() use ($user,$d): void {
        $cid=(int)($d['konverzace']??0); $new=!empty($d['ztiseno'])?1:0;
        $channel=co_konverzace($user,$cid,true);
        $old=co_podrobnosti_ztiseni_nacist($user,$cid);
        if ($old===$new) return;
        co_podrobnosti_ztiseni_ulozit($user,$cid,$new);
        co_audit($user,'ztiseni_zmeneno',$cid,co_audit_kontext($user,$channel)+['pole'=>'Ztišení oznámení','puvodni'=>$old?'Zapnuto':'Vypnuto','nova'=>$new?'Zapnuto':'Vypnuto']);
    });
}
