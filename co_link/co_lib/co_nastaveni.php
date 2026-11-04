<?php
/* Co-Link: nacteni limitu priloh a jeho validovana zmena spravcem vcetne transakce a auditu. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_prava.php';
require_once __DIR__ . '/co_transakce.php';
require_once __DIR__ . '/../co_db/co_audit_vlozit.php';
require_once __DIR__ . '/../co_db/co_nastaveni_db.php';

/** Obal odpovedi i pristup k prvnimu radku zustava shodny s dosavadnim API. */
function co_nastaveni_nacist(): array
{
    return ['nastaveni' => co_nastaveni_db_nacist()[0]];
}

/** Pravo 703 se overi pred validaci a oba zapisy zustavaji v jedne transakci. */
function co_nastaveni_ulozit(int $user, array $d): void
{
    if (!co_pravo($user, 703)) co_chyba('Nemáte právo spravovat Co-Link.', 403);
    $mb = filter_var($d['priloha_mb'] ?? null, FILTER_VALIDATE_INT);
    if ($mb === false || $mb < 1 || $mb > 2048) co_chyba('Příloha může mít 1–2048 MB.');
    co_transakce(static function() use ($user, $mb): void {
        $old=(int)(co_nastaveni_db_nacist(true)[0]['priloha_mb']??0);
        if ($old===$mb) return;
        co_nastaveni_db_ulozit($mb);
        co_audit($user, 'nastaveni_zmeneno', null, ['pole'=>'Limit přílohy (MB)','puvodni'=>$old,'nova'=>$mb]);
    });
}
