<?php
/* Co-Link: v jedné transakci uloží zprávu, připojí přílohy, připraví push a bezpečný audit bez duplicit. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_audit_kontext.php';
require_once __DIR__ . '/co_transakce.php';
require_once __DIR__ . '/co_konverzace.php';
require_once __DIR__ . '/co_zprava_vstup.php';
require_once __DIR__ . '/co_zprava_rodic_overit.php';
require_once __DIR__ . '/co_zprava_prilohy_pripojit.php';
require_once __DIR__ . '/co_zprava_push_pripravit.php';
require_once __DIR__ . '/../co_db/co_zpravu_podle_klienta_nacist.php';
require_once __DIR__ . '/../co_db/co_zpravu_vlozit.php';
require_once __DIR__ . '/../co_db/co_konverzace_cas_obnovit.php';

/** Identifikator vygenerovany klientem prezije vypadek spojeni; stejna zprava se ulozi pouze jednou. */
function co_odeslat(int $user, array $d): array
{
    ['client' => $client, 'text' => $text, 'files' => $files, 'cid' => $cid] = co_zprava_vstup($d);
    return co_transakce(static function() use ($user, $d, $client, $text, $files, $cid): array {
        $channel = co_konverzace($user, $cid, true);
        if ((int)$channel['archiv'] === 1) co_chyba('Kanál je archivovaný.');
        $existing = co_zpravu_podle_klienta_nacist($user, $client)[0] ?? [];
        if ($existing) {
            if ((int)$existing['konverzace'] !== $cid) co_chyba('Identifikátor zprávy už byl použit.', 409);
            return ['id' => (int)$existing['id']];
        }
        $parent = (int)($d['rodic'] ?? 0);
        co_zprava_rodic_overit($parent, $cid);
        $id = co_zpravu_vlozit($cid, $user, $client, $parent ?: null, $text);
        co_zprava_prilohy_pripojit($id, $files, $user);
        co_konverzace_cas_obnovit($cid);
        co_zprava_push_pripravit($user, $cid, $id);
        // Audit je součást stejné transakce; opakovaný klient_id se vrací ještě před zápisem.
        $audit=co_audit_kontext($user,$channel)+['zprava'=>$id];
        co_audit($user,'zprava_odeslana',$id,$audit);
        foreach ($files as $file) co_audit($user,'priloha_pripojena',$id,$audit+['priloha'=>(int)$file]);
        return ['id' => $id];
    });
}
