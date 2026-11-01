<?php
/* Co-Link: overi povolenou reakci a dostupnost zpravy a v transakci prepne reakci osoby pridat/odebrat. */
declare(strict_types=1);
require_once __DIR__ . '/co_chyba.php';
require_once __DIR__ . '/co_konverzace.php';
require_once __DIR__ . '/co_transakce.php';
require_once __DIR__ . '/../co_db/co_reakce_zpravu_nacist.php';
require_once __DIR__ . '/../co_db/co_reakce_zpravu_zamknout.php';
require_once __DIR__ . '/../co_db/co_reakci_odstranit.php';
require_once __DIR__ . '/../co_db/co_reakci_vlozit.php';

/** Ucastnik muze reagovat i po precteni; reakce nemeni obsah autorovy zpravy. */
function co_reakce(int $user, int $id, string $emoji): void
{
    if (!in_array($emoji, ['👍','❤️','✅','😂','🙏','👀'], true)) co_chyba('Nepodporovaná reakce.');
    $message = co_reakce_zpravu_nacist($id)[0] ?? [];
    if (!$message) co_chyba('Zpráva není dostupná.', 404);
    $channel = co_konverzace($user, (int)$message['konverzace']);
    if ((int)$channel['archiv'] === 1) co_chyba('Archivovaný kanál je pouze pro čtení.', 409);
    co_transakce(static function() use ($user, $id, $emoji): void {
        co_reakce_zpravu_zamknout($id);
        // Puvodni DELETE soucasne zjisti, zda uz reakce existovala; jen chybejici reakci pridame.
        if (!co_reakci_odstranit($id, $user, $emoji)) co_reakci_vlozit($id, $user, $emoji);
    });
}
