<?php
/* Co-Link: ve spolecne transakci overi moznost zmeny zpravy, provede upravu nebo smazani a zapise audit. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_transakce.php';
require_once __DIR__ . '/co_konverzace.php';
require_once __DIR__ . '/co_zprava_uprava.php';
require_once __DIR__ . '/co_zprava_smazani.php';
require_once __DIR__ . '/../co_db/co_zpravu_pro_zmenu_nacist.php';
require_once __DIR__ . '/../co_db/co_audit_vlozit.php';

/** Precteni, editace i smazani zamykaji stejnou zpravu a respektuji prvni cizi zobrazeni. */
function co_men_zpravu(int $user, array $d): array
{
    return co_transakce(static function() use ($user, $d): array {
        $id = (int)($d['zprava'] ?? 0);
        $message = co_zpravu_pro_zmenu_nacist($id)[0] ?? [];
        if (!$message) co_chyba('Zpráva neexistuje.', 404);
        $channel = co_konverzace($user, (int)$message['konverzace']);
        if ((int)$channel['archiv'] === 1) co_chyba('Archivovaný kanál je pouze pro čtení.', 409);
        if ((int)$message['autor'] !== $user || $message['prvni_precteni'] !== null || $message['smazano'] !== null) co_chyba('Přečtenou nebo cizí zprávu nelze změnit ani smazat.', 409);
        if ((int)($d['revize'] ?? 0) !== (int)$message['revize']) co_chyba('Zpráva se mezitím změnila. Obnovte ji.', 409);
        // Obe vetve i audit zustavaji pod stejnym zamkem a commitem jako puvodni zmena.
        if (($d['akce'] ?? '') === 'smazat') co_zprava_smazani($id);
        else co_zprava_uprava($id, (string)($d['text'] ?? ''));
        co_audit($user, ($d['akce'] ?? '') === 'smazat' ? 'zprava_smazana' : 'zprava_upravena', $id);
        return [];
    });
}
