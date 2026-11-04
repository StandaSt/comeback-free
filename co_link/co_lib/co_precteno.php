<?php
/* Co-Link: ověří zobrazenou revizi a v jedné transakci uloží přečtení i jeho audit bez textu zprávy. */
declare(strict_types=1);
require_once __DIR__ . '/co_konverzace.php';
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_audit_kontext.php';
require_once __DIR__ . '/co_transakce.php';
require_once __DIR__ . '/../co_db/co_zpravu_pro_precteni_nacist.php';
require_once __DIR__ . '/../co_db/co_precteni_vlozit.php';
require_once __DIR__ . '/../co_db/co_prvni_precteni_nastavit.php';

/** Zobrazeni stare revize neuzamkne novou verzi, kterou prijemce jeste nevidel. */
function co_precteno(int $user, int $cid, array $items): void
{
    $channel=co_konverzace($user, $cid);
    if (count($items) > 80) co_chyba('Příliš mnoho potvrzení.');
    // Jednotne poradi ID zachovava puvodni poradi zamku pri soubeznych potvrzenich.
    usort($items, static fn($a, $b) => (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
    co_transakce(static function() use ($user, $cid, $items, $channel): void {
        $audit=null;
        foreach ($items as $item) {
            $message = co_zpravu_pro_precteni_nacist((int)($item['id'] ?? 0), $cid)[0] ?? [];
            // Vlastni, smazanou, chybejici nebo mezitim upravenou zpravu nepotvrzujeme.
            if (!$message || (int)$message['autor'] === $user || $message['smazano'] !== null || (int)$message['revize'] !== (int)($item['revize'] ?? 0)) continue;
            if (co_precteni_vlozit((int)$message['id'], $user)) {
                // Jde o potvrzené první přečtení, nikoli pravidelné načítání seznamu zpráv.
                $audit ??= co_audit_kontext($user,$channel);
                co_audit($user,'zprava_prectena',(int)$message['id'],array_replace($audit,['cil'=>'Od '.co_audit_autora_nacist((int)$message['autor'])])+['zprava'=>(int)$message['id'],'autor'=>(int)$message['autor'],'revize'=>(int)$message['revize']]);
            }
            co_prvni_precteni_nastavit((int)$message['id']);
        }
    });
}
