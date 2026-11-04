<?php
/* Co-Link: overi ucastniky a nazev a v transakci zalozi soukromou konverzaci nebo skupinu s pevnymi cleny. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_audit_kontext.php';
require_once __DIR__ . '/co_lide.php';
require_once __DIR__ . '/co_transakce.php';
require_once __DIR__ . '/../co_db/co_soukromou_vlozit.php';
require_once __DIR__ . '/../co_db/co_soukromeho_clena_vlozit.php';

/** Soukromy chat jedne dvojice ma stabilni klic; skupina ma pevne vybrane cleny. */
function co_soukroma(int $user, array $d): array
{
    $members = array_values(array_unique(array_merge([$user], array_map('intval', (array)($d['lide'] ?? [])))));
    // Stejne poradi clenu vytvori stejny klic dvojice bez ohledu na poradi vyberu nebo zakladatele.
    sort($members);
    if (count($members) < 2 || count($members) > 100 || array_diff($members, array_column(co_lide(), 'id'))) co_chyba('Vyberte 1 až 99 dalších aktivních uživatelů linku.');
    $pair = count($members) === 2 ? implode(':', $members) : null;
    $name = trim((string)($d['nazev'] ?? ''));
    if (!$pair && ($name === '' || mb_strlen($name) > 100)) co_chyba('Pojmenujte skupinu (nejvýše 100 znaků).');
    return co_transakce(static function() use ($user, $members, $pair, $name): array {
        $id = co_soukromou_vlozit($pair ? 'soukroma' : 'skupina', $name, $user, $pair, $created);
        foreach ($members as $member) co_soukromeho_clena_vlozit($id, $member);
        if ($created) co_audit($user,'konverzace_vytvorena',$id,co_audit_kontext($user,['id'=>$id,'typ'=>$pair?'soukroma':'skupina','nazev'=>$name]));
        return ['id' => $id];
    });
}
